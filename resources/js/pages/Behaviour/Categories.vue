<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import FieldError from '../../components/FieldError.vue';
import { useT } from '../../lib/i18n';

defineProps({ categories: Array, startingScore: Number });
const t = useT();

const editing = ref(null);
const blank = { name_ar: '', name_en: '', kind: 'negative', degree: 1, points: 1, notify_guardian: false, active: true };
const form = useForm({ ...blank });
const start = (c = null) => {
    editing.value = c ? c.id : 'new';
    form.clearErrors();
    Object.assign(form, c ? { ...c, name_en: c.name_en ?? '', degree: c.degree ?? '' } : blank);
};
const save = () => {
    form.transform((d) => ({ ...d, name_en: d.name_en || null, degree: d.kind === 'positive' || d.degree === '' ? null : d.degree }));
    const options = { preserveScroll: true, onSuccess: () => { editing.value = null; } };
    editing.value === 'new' ? form.post('/behaviour/categories', options) : form.put(`/behaviour/categories/${editing.value}`, options);
};
const remove = (c) => confirm(t('Delete this category?')) && router.delete(`/behaviour/categories/${c.id}`, { preserveScroll: true });
</script>

<template>
    <AppLayout :title="t('Behaviour categories')">
        <Link href="/behaviour" class="mb-4 inline-block text-sm text-muted hover:text-ink"><span class="rtl:hidden">←</span><span class="ltr:hidden">→</span> {{ t('Behaviour') }}</Link>
        <p class="mb-4 text-sm text-muted">{{ t('Every student starts the year with :score behaviour points. Violations deduct points; distinguished behaviour adds them back. Check the starting list against the Ministry rules in force.', { score: startingScore }) }}</p>
        <FieldError :message="$page.props.errors?.category" />

        <section v-if="editing" class="card mb-4">
            <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="save">
                <div class="sm:col-span-2"><label class="label" for="nar">{{ t('Name (Arabic)') }}</label><input id="nar" v-model="form.name_ar" class="input" required><FieldError :message="form.errors.name_ar" /></div>
                <div class="sm:col-span-2"><label class="label" for="nen">{{ t('Name (English)') }}</label><input id="nen" v-model="form.name_en" class="input" dir="ltr"></div>
                <div>
                    <label class="label" for="kind">{{ t('Type') }}</label>
                    <select id="kind" v-model="form.kind" class="input"><option value="negative">{{ t('Violation') }}</option><option value="positive">{{ t('Distinguished behaviour') }}</option></select>
                </div>
                <div v-if="form.kind === 'negative'">
                    <label class="label" for="degree">{{ t('Degree') }}</label>
                    <select id="degree" v-model.number="form.degree" class="input"><option v-for="n in 6" :key="n" :value="n">{{ n }}</option></select>
                </div>
                <div><label class="label" for="points">{{ t('Points') }}</label><input id="points" v-model.number="form.points" type="number" min="0" max="100" class="input" required></div>
                <div class="flex flex-col justify-end gap-2 text-sm">
                    <label class="flex items-center gap-2"><input v-model="form.notify_guardian" type="checkbox"> {{ t('Tell the guardian') }}</label>
                    <label class="flex items-center gap-2"><input v-model="form.active" type="checkbox"> {{ t('Active') }}</label>
                </div>
                <div class="flex gap-3 sm:col-span-2 lg:col-span-4">
                    <button class="btn-primary" :disabled="form.processing">{{ t('Save') }}</button>
                    <button type="button" class="btn-ghost" @click="editing = null">{{ t('Cancel') }}</button>
                </div>
            </form>
        </section>
        <button v-else type="button" class="btn-primary mb-4" @click="start()">+ {{ t('Add a category') }}</button>

        <div class="card overflow-x-auto p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-line text-muted">
                    <tr>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Category') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Degree') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Points') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Tell the guardian') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Records') }}</th>
                        <th class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in categories" :key="c.id" class="border-b border-line last:border-0" :class="!c.active && 'text-muted'">
                        <td class="px-4 py-3">{{ c.name_ar }}</td>
                        <td class="px-4 py-3">{{ c.degree ?? '—' }}</td>
                        <td class="px-4 py-3 font-semibold tabular-nums" :class="c.kind === 'positive' ? 'text-accent' : 'text-danger'" dir="ltr">{{ c.kind === 'positive' ? '+' : '−' }}{{ c.points }}</td>
                        <td class="px-4 py-3">{{ c.notify_guardian ? '✓' : '' }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ c.incidents_count }}</td>
                        <td class="px-4 py-3 text-end whitespace-nowrap">
                            <button type="button" class="text-accent hover:underline" @click="start(c)">{{ t('Edit') }}</button>
                            <button v-if="!c.incidents_count" type="button" class="ms-3 text-danger hover:underline" @click="remove(c)">{{ t('Delete') }}</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
