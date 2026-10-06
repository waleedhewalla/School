<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import TabNav from '../../components/TabNav.vue';
import FieldError from '../../components/FieldError.vue';
import { useT } from '../../lib/i18n';
import { setupTabs } from '../../lib/tabs';

defineProps({ subjects: Array });
const t = useT();

const editing = ref(null);
const form = useForm({ code: '', name_ar: '', name_en: '', sequence: 0 });
const start = (s = null) => {
    editing.value = s ? s.id : 'new';
    form.clearErrors();
    Object.assign(form, s ? { code: s.code, name_ar: s.name_ar, name_en: s.name_en ?? '', sequence: s.sequence } : { code: '', name_ar: '', name_en: '', sequence: 0 });
};
const save = () => {
    const options = { preserveScroll: true, onSuccess: () => { editing.value = null; } };
    form.transform((d) => (editing.value === 'new' ? { code: d.code, name_ar: d.name_ar, name_en: d.name_en || null } : { ...d, name_en: d.name_en || null }));
    editing.value === 'new' ? form.post('/setup/subjects', options) : form.put(`/setup/subjects/${editing.value}`, options);
};
const remove = (s) => confirm(t('Delete this subject?')) && router.delete(`/setup/subjects/${s.id}`, { preserveScroll: true });
</script>

<template>
    <AppLayout :title="t('School setup')">
        <TabNav :tabs="setupTabs" />
        <FieldError :message="$page.props.errors?.subject" />

        <div class="card overflow-x-auto p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-line text-muted">
                    <tr>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Order') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Code') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Name (Arabic)') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Name (English)') }}</th>
                        <th class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody>
                    <template v-for="s in subjects" :key="s.id">
                        <tr v-if="editing !== s.id" class="border-b border-line last:border-0">
                            <td class="px-4 py-3 tabular-nums">{{ s.sequence }}</td>
                            <td class="px-4 py-3" dir="ltr">{{ s.code }}</td>
                            <td class="px-4 py-3">{{ s.name_ar }}</td>
                            <td class="px-4 py-3" dir="ltr">{{ s.name_en }}</td>
                            <td class="px-4 py-3 text-end">
                                <button type="button" class="text-accent hover:underline" @click="start(s)">{{ t('Edit') }}</button>
                                <button v-if="!s.teaching_assignments_count" type="button" class="ms-3 text-danger hover:underline" @click="remove(s)">{{ t('Delete') }}</button>
                            </td>
                        </tr>
                        <tr v-else class="border-b border-line bg-surface">
                            <td class="px-4 py-2"><input v-model.number="form.sequence" type="number" min="0" class="input w-20" :aria-label="t('Order')"></td>
                            <td class="px-4 py-2"><input v-model="form.code" class="input w-28" dir="ltr" required :aria-label="t('Code')"></td>
                            <td class="px-4 py-2"><input v-model="form.name_ar" class="input" required :aria-label="t('Name (Arabic)')"></td>
                            <td class="px-4 py-2"><input v-model="form.name_en" class="input" dir="ltr" :aria-label="t('Name (English)')"></td>
                            <td class="px-4 py-2 text-end whitespace-nowrap">
                                <button type="button" class="btn-primary" @click="save">{{ t('Save') }}</button>
                                <button type="button" class="btn-ghost ms-2" @click="editing = null">{{ t('Cancel') }}</button>
                                <FieldError :message="form.errors.code || form.errors.name_ar" />
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <section class="card mt-4">
            <form v-if="editing === 'new'" class="flex flex-wrap items-end gap-3" @submit.prevent="save">
                <div><label class="label" for="code">{{ t('Code') }}</label><input id="code" v-model="form.code" class="input w-32" dir="ltr" required placeholder="MATH"></div>
                <div><label class="label" for="ar">{{ t('Name (Arabic)') }}</label><input id="ar" v-model="form.name_ar" class="input" required></div>
                <div><label class="label" for="en">{{ t('Name (English)') }}</label><input id="en" v-model="form.name_en" class="input" dir="ltr"></div>
                <button class="btn-primary" :disabled="form.processing">{{ t('Save') }}</button>
                <button type="button" class="btn-ghost" @click="editing = null">{{ t('Cancel') }}</button>
                <FieldError class="w-full" :message="form.errors.code || form.errors.name_ar" />
            </form>
            <button v-else type="button" class="btn-ghost" @click="start()">+ {{ t('Add a subject') }}</button>
        </section>
    </AppLayout>
</template>
