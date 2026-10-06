<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import TabNav from '../../components/TabNav.vue';
import FieldError from '../../components/FieldError.vue';
import { formatDate, useT } from '../../lib/i18n';
import { setupTabs } from '../../lib/tabs';

defineProps({ years: Array });
const t = useT();

const yearForm = useForm({
    name: '', starts_on: '', ends_on: '', is_current: false,
    terms: [{ name_ar: 'الفصل الدراسي الأول', name_en: 'Semester 1', starts_on: '', ends_on: '' }, { name_ar: 'الفصل الدراسي الثاني', name_en: 'Semester 2', starts_on: '', ends_on: '' }],
});
const addYear = () => yearForm.post('/setup/years', { preserveScroll: true, onSuccess: () => yearForm.reset() });

const editingYear = ref(null);
const editYear = useForm({ name: '', starts_on: '', ends_on: '' });
const startEditYear = (y) => { editingYear.value = y.id; Object.assign(editYear, { name: y.name, starts_on: y.starts_on, ends_on: y.ends_on }); };
const saveYear = (y) => editYear.put(`/setup/years/${y.id}`, { preserveScroll: true, onSuccess: () => { editingYear.value = null; } });
const makeCurrent = (y) => router.put(`/setup/years/${y.id}`, { is_current: true }, { preserveScroll: true });
const deleteYear = (y) => confirm(t('Delete this academic year?')) && router.delete(`/setup/years/${y.id}`, { preserveScroll: true });

const editingTerm = ref(null);
const termForm = useForm({ name_ar: '', name_en: '', starts_on: '', ends_on: '' });
const startTerm = (term, yearId) => {
    editingTerm.value = term ? term.id : `new-${yearId}`;
    termForm.clearErrors();
    Object.assign(termForm, term ? { name_ar: term.name_ar, name_en: term.name_en ?? '', starts_on: term.starts_on, ends_on: term.ends_on } : { name_ar: '', name_en: '', starts_on: '', ends_on: '' });
};
const saveTerm = (yearId) => {
    const options = { preserveScroll: true, onSuccess: () => { editingTerm.value = null; } };
    String(editingTerm.value).startsWith('new-') ? termForm.post(`/setup/years/${yearId}/terms`, options) : termForm.put(`/setup/terms/${editingTerm.value}`, options);
};
const deleteTerm = (term) => confirm(t('Delete this term?')) && router.delete(`/setup/terms/${term.id}`, { preserveScroll: true });
</script>

<template>
    <AppLayout :title="t('School setup')">
        <TabNav :tabs="setupTabs" />
        <FieldError :message="$page.props.errors?.year || $page.props.errors?.term" />

        <section v-for="y in years" :key="y.id" class="card mb-4">
            <div v-if="editingYear !== y.id" class="flex flex-wrap items-center gap-3">
                <h2 class="text-lg font-semibold">{{ y.name }}</h2>
                <span v-if="y.is_current" class="rounded bg-accent-soft px-2 text-xs font-semibold text-accent">{{ t('Current year') }}</span>
                <span class="text-sm text-muted">{{ formatDate(y.starts_on, $page.props.locale) }} – {{ formatDate(y.ends_on, $page.props.locale) }} · {{ t(':count sections', { count: y.sections_count }) }}</span>
                <div class="ms-auto flex gap-3 text-sm">
                    <button v-if="!y.is_current" type="button" class="text-accent hover:underline" @click="makeCurrent(y)">{{ t('Make current') }}</button>
                    <button type="button" class="text-accent hover:underline" @click="startEditYear(y)">{{ t('Edit') }}</button>
                    <button v-if="!y.is_current && !y.sections_count" type="button" class="text-danger hover:underline" @click="deleteYear(y)">{{ t('Delete') }}</button>
                </div>
            </div>
            <form v-else class="grid gap-3 sm:grid-cols-4" @submit.prevent="saveYear(y)">
                <input v-model="editYear.name" class="input" :aria-label="t('Name')" required>
                <input v-model="editYear.starts_on" type="date" class="input" dir="ltr" required :aria-label="t('Starts on')">
                <input v-model="editYear.ends_on" type="date" class="input" dir="ltr" required :aria-label="t('Ends on')">
                <div class="flex gap-2"><button class="btn-primary" :disabled="editYear.processing">{{ t('Save') }}</button><button type="button" class="btn-ghost" @click="editingYear = null">{{ t('Cancel') }}</button></div>
                <FieldError class="sm:col-span-4" :message="editYear.errors.name || editYear.errors.ends_on" />
            </form>

            <ul class="mt-4 divide-y divide-line text-sm">
                <li v-for="term in y.terms" :key="term.id" class="py-2">
                    <div v-if="editingTerm !== term.id" class="flex flex-wrap items-center gap-3">
                        <span class="font-medium">{{ term.name_ar }}</span>
                        <span class="text-muted">{{ formatDate(term.starts_on, $page.props.locale) }} – {{ formatDate(term.ends_on, $page.props.locale) }}</span>
                        <div class="ms-auto flex gap-3">
                            <button type="button" class="text-accent hover:underline" @click="startTerm(term, y.id)">{{ t('Edit') }}</button>
                            <button type="button" class="text-danger hover:underline" @click="deleteTerm(term)">{{ t('Delete') }}</button>
                        </div>
                    </div>
                    <form v-else class="grid gap-2 sm:grid-cols-5" @submit.prevent="saveTerm(y.id)">
                        <input v-model="termForm.name_ar" class="input" required :aria-label="t('Name (Arabic)')">
                        <input v-model="termForm.name_en" class="input" dir="ltr" :aria-label="t('Name (English)')">
                        <input v-model="termForm.starts_on" type="date" class="input" dir="ltr" required :aria-label="t('Starts on')">
                        <input v-model="termForm.ends_on" type="date" class="input" dir="ltr" required :aria-label="t('Ends on')">
                        <div class="flex gap-2"><button class="btn-primary">{{ t('Save') }}</button><button type="button" class="btn-ghost" @click="editingTerm = null">{{ t('Cancel') }}</button></div>
                        <FieldError class="sm:col-span-5" :message="termForm.errors.starts_on || termForm.errors.ends_on || termForm.errors.name_ar" />
                    </form>
                </li>
                <li class="pt-2">
                    <form v-if="editingTerm === `new-${y.id}`" class="grid gap-2 sm:grid-cols-5" @submit.prevent="saveTerm(y.id)">
                        <input v-model="termForm.name_ar" class="input" required :placeholder="t('Name (Arabic)')">
                        <input v-model="termForm.name_en" class="input" dir="ltr" :placeholder="t('Name (English)')">
                        <input v-model="termForm.starts_on" type="date" class="input" dir="ltr" required :aria-label="t('Starts on')">
                        <input v-model="termForm.ends_on" type="date" class="input" dir="ltr" required :aria-label="t('Ends on')">
                        <div class="flex gap-2"><button class="btn-primary">{{ t('Save') }}</button><button type="button" class="btn-ghost" @click="editingTerm = null">{{ t('Cancel') }}</button></div>
                        <FieldError class="sm:col-span-5" :message="termForm.errors.starts_on || termForm.errors.ends_on || termForm.errors.name_ar" />
                    </form>
                    <button v-else type="button" class="text-accent hover:underline" @click="startTerm(null, y.id)">+ {{ t('Add a term') }}</button>
                </li>
            </ul>
        </section>

        <section class="card">
            <h2 class="mb-4 font-semibold">{{ t('Add an academic year') }}</h2>
            <form class="space-y-4" @submit.prevent="addYear">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="label" for="y-name">{{ t('Name') }}</label>
                        <input id="y-name" v-model="yearForm.name" class="input" placeholder="1449" required>
                        <FieldError :message="yearForm.errors.name" />
                    </div>
                    <div>
                        <label class="label" for="y-start">{{ t('Starts on') }}</label>
                        <input id="y-start" v-model="yearForm.starts_on" type="date" class="input" dir="ltr" required>
                    </div>
                    <div>
                        <label class="label" for="y-end">{{ t('Ends on') }}</label>
                        <input id="y-end" v-model="yearForm.ends_on" type="date" class="input" dir="ltr" required>
                        <FieldError :message="yearForm.errors.ends_on" />
                    </div>
                </div>
                <div v-for="(term, i) in yearForm.terms" :key="i" class="grid gap-2 sm:grid-cols-5">
                    <input v-model="term.name_ar" class="input" required :aria-label="t('Name (Arabic)')">
                    <input v-model="term.name_en" class="input" dir="ltr" :aria-label="t('Name (English)')">
                    <input v-model="term.starts_on" type="date" class="input" dir="ltr" required :aria-label="t('Starts on')">
                    <input v-model="term.ends_on" type="date" class="input" dir="ltr" required :aria-label="t('Ends on')">
                    <button type="button" class="btn-ghost" @click="yearForm.terms.splice(i, 1)">{{ t('Remove') }}</button>
                    <FieldError class="sm:col-span-5" :message="yearForm.errors[`terms.${i}.starts_on`] || yearForm.errors[`terms.${i}.ends_on`]" />
                </div>
                <div class="flex flex-wrap items-center gap-4">
                    <button v-if="yearForm.terms.length < 4" type="button" class="btn-ghost" @click="yearForm.terms.push({ name_ar: '', name_en: '', starts_on: '', ends_on: '' })">+ {{ t('Add a term') }}</button>
                    <label class="flex items-center gap-2 text-sm"><input v-model="yearForm.is_current" type="checkbox"> {{ t('Make it the current year') }}</label>
                    <button type="submit" class="btn-primary ms-auto" :disabled="yearForm.processing">{{ t('Save') }}</button>
                </div>
            </form>
        </section>
    </AppLayout>
</template>
