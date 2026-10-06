<script setup>
import { reactive, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import TabNav from '../../components/TabNav.vue';
import FieldError from '../../components/FieldError.vue';
import { useT } from '../../lib/i18n';
import { staffTabs } from '../../lib/tabs';

const props = defineProps({ staff: Array, users: Array, campuses: Array, filters: Object });
const t = useT();

const filters = reactive({ search: props.filters.search ?? '' });
let timer;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get('/staff', filters.search ? { search: filters.search } : {}, { preserveState: true, replace: true }), 300);
});

const blank = { employee_number: '', name_ar: '', name_en: '', national_id: '', job_title: '', phone: '', email: '', campus_id: '', user_id: '', status: 'active' };
const editing = ref(null);
const form = useForm({ ...blank });
const start = (s = null) => {
    editing.value = s ? s.id : 'new';
    form.clearErrors();
    Object.assign(form, s ? Object.fromEntries(Object.keys(blank).map((k) => [k, s[k] ?? ''])) : blank);
};
const save = () => {
    form.transform((d) => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v])));
    const options = { preserveScroll: true, onSuccess: () => { editing.value = null; } };
    editing.value === 'new' ? form.post('/staff', options) : form.put(`/staff/${editing.value}`, options);
};
const linkable = (s) => (s && s.user_id ? [{ id: s.user_id, label: s.login }, ...props.users] : props.users);
</script>

<template>
    <AppLayout :title="t('Staff')">
        <TabNav :tabs="staffTabs" />

        <div class="mb-4 flex flex-wrap gap-3">
            <button type="button" class="btn-primary" @click="start()">{{ t('Add a staff member') }}</button>
            <input v-model="filters.search" type="search" class="input max-w-xs" :placeholder="t('Search by name or number')">
        </div>

        <section v-if="editing" class="card mb-4">
            <h2 class="mb-4 font-semibold">{{ editing === 'new' ? t('Add a staff member') : t('Edit') }}</h2>
            <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="save">
                <div><label class="label" for="emp">{{ t('Employee number') }} *</label><input id="emp" v-model="form.employee_number" class="input" dir="ltr" required><FieldError :message="form.errors.employee_number" /></div>
                <div><label class="label" for="nar">{{ t('Name (Arabic)') }} *</label><input id="nar" v-model="form.name_ar" class="input" required><FieldError :message="form.errors.name_ar" /></div>
                <div><label class="label" for="nen">{{ t('Name (English)') }}</label><input id="nen" v-model="form.name_en" class="input" dir="ltr"></div>
                <div><label class="label" for="job">{{ t('Job title') }}</label><input id="job" v-model="form.job_title" class="input"></div>
                <div><label class="label" for="nid">{{ t('National ID / Iqama') }}</label><input id="nid" v-model="form.national_id" class="input" dir="ltr" maxlength="10"><FieldError :message="form.errors.national_id" /></div>
                <div><label class="label" for="phone">{{ t('Mobile') }}</label><input id="phone" v-model="form.phone" class="input" dir="ltr"><FieldError :message="form.errors.phone" /></div>
                <div><label class="label" for="email">{{ t('Email') }}</label><input id="email" v-model="form.email" type="email" class="input" dir="ltr"><FieldError :message="form.errors.email" /></div>
                <div v-if="campuses.length > 1">
                    <label class="label" for="campus">{{ t('Campus') }}</label>
                    <select id="campus" v-model="form.campus_id" class="input"><option value="">—</option><option v-for="c in campuses" :key="c.id" :value="c.id">{{ c.name }}</option></select>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="user">{{ t('Login account') }}</label>
                    <select id="user" v-model="form.user_id" class="input">
                        <option value="">{{ t('No login') }}</option>
                        <option v-for="u in linkable(staff.find((s) => s.id === editing))" :key="u.id" :value="u.id">{{ u.label }}</option>
                    </select>
                    <p class="mt-1 text-xs text-muted">{{ t('Invite the person from Users first; then link the account here so they see their classes.') }}</p>
                    <FieldError :message="form.errors.user_id" />
                </div>
                <div>
                    <label class="label" for="status">{{ t('Status') }}</label>
                    <select id="status" v-model="form.status" class="input"><option value="active">{{ t('Active') }}</option><option value="inactive">{{ t('Inactive') }}</option></select>
                </div>
                <div class="flex items-end gap-3 sm:col-span-2 lg:col-span-4">
                    <button class="btn-primary" :disabled="form.processing">{{ t('Save') }}</button>
                    <button type="button" class="btn-ghost" @click="editing = null">{{ t('Cancel') }}</button>
                </div>
            </form>
        </section>

        <div class="card overflow-x-auto p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-line text-muted">
                    <tr>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Employee number') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Name') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Job title') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Login account') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Classes') }}</th>
                        <th class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in staff" :key="s.id" class="border-b border-line last:border-0" :class="s.status === 'inactive' ? 'text-muted' : ''">
                        <td class="px-4 py-3" dir="ltr">{{ s.employee_number }}</td>
                        <td class="px-4 py-3 font-medium">{{ s.name_ar }}<span v-if="s.status === 'inactive'" class="ms-2 text-xs">({{ t('Inactive') }})</span></td>
                        <td class="px-4 py-3">{{ s.job_title ?? '—' }}</td>
                        <td class="px-4 py-3" dir="ltr">{{ s.login ?? '—' }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ s.teaching_assignments_count }}</td>
                        <td class="px-4 py-3 text-end"><button type="button" class="text-accent hover:underline" @click="start(s)">{{ t('Edit') }}</button></td>
                    </tr>
                    <tr v-if="!staff.length"><td colspan="6" class="px-4 py-8 text-center text-muted">{{ t('No staff yet.') }}</td></tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
