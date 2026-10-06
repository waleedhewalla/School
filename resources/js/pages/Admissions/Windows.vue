<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import FieldError from '../../components/FieldError.vue';
import { formatDate, useT } from '../../lib/i18n';

const props = defineProps({ windows: Array, years: Array, grades: Array, publicUrl: String });
const t = useT();

const blank = () => ({ academic_year_id: props.years[0]?.id ?? '', grade_level_id: '', seats: 30, opens_on: '', closes_on: '', born_from: '', born_to: '', exception_days: 0 });
const editing = ref(null);
const form = useForm(blank());

const edit = (w) => {
    editing.value = w.id;
    form.defaults({ ...w, born_from: w.born_from ?? '', born_to: w.born_to ?? '' });
    form.reset();
    form.clearErrors();
};
const cancel = () => { editing.value = null; form.defaults(blank()); form.reset(); form.clearErrors(); };

const save = () => {
    const options = { preserveScroll: true, onSuccess: cancel };
    form.transform((d) => ({ ...d, born_from: d.born_from || null, born_to: d.born_to || null }));
    editing.value ? form.put(`/admissions/windows/${editing.value}`, options) : form.post('/admissions/windows', options);
};
const remove = (w) => {
    if (confirm(t('Delete this admission window?'))) router.delete(`/admissions/windows/${w.id}`, { preserveScroll: true });
};
const copied = ref(false);
const copy = async () => { await navigator.clipboard.writeText(props.publicUrl); copied.value = true; };
</script>

<template>
    <AppLayout :title="t('Admission windows')">
        <Link href="/admissions" class="mb-4 inline-block text-sm text-muted hover:text-ink"><span class="rtl:hidden">←</span><span class="ltr:hidden">→</span> {{ t('Admissions') }}</Link>

        <section class="card mb-4">
            <h2 class="mb-2 font-semibold">{{ t('Public application form') }}</h2>
            <p class="mb-3 text-sm text-muted">{{ t('Share this link on your website and social media. Only grades with an open window appear on the form.') }}</p>
            <div class="flex flex-wrap gap-2">
                <input :value="publicUrl" class="input max-w-lg" dir="ltr" readonly :aria-label="t('Public application form')">
                <button type="button" class="btn-ghost" @click="copy">{{ copied ? t('Copied') : t('Copy') }}</button>
                <a :href="publicUrl" target="_blank" rel="noopener" class="btn-ghost">{{ t('Open') }}</a>
            </div>
        </section>

        <div class="card mb-4 overflow-x-auto p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-line text-muted">
                    <tr>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Grade level') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Application period') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Birth dates') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Seats') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Applications') }}</th>
                        <th class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="w in windows" :key="w.id" class="border-b border-line last:border-0">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ w.grade }}</div>
                            <div class="text-xs text-muted">{{ w.year }}</div>
                        </td>
                        <td class="px-4 py-3">
                            {{ formatDate(w.opens_on, $page.props.locale) }} – {{ formatDate(w.closes_on, $page.props.locale) }}
                            <span v-if="w.open" class="ms-1 rounded bg-accent-soft px-1.5 text-xs text-accent">{{ t('Open now') }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <template v-if="w.born_from || w.born_to">{{ formatDate(w.born_from, $page.props.locale) || '…' }} – {{ formatDate(w.born_to, $page.props.locale) || '…' }}</template>
                            <template v-else>—</template>
                            <div v-if="w.exception_days" class="text-xs text-muted">{{ t('+:days days by exception', { days: w.exception_days }) }}</div>
                        </td>
                        <td class="px-4 py-3 tabular-nums">{{ t(':left of :seats seats left', { left: w.seats_left, seats: w.seats }) }}</td>
                        <td class="px-4 py-3 tabular-nums">
                            <Link :href="`/admissions?window=${w.id}`" class="text-accent hover:underline">{{ w.applications_count }}</Link>
                        </td>
                        <td class="px-4 py-3 text-end">
                            <button type="button" class="text-accent hover:underline" @click="edit(w)">{{ t('Edit') }}</button>
                            <button v-if="!w.applications_count" type="button" class="ms-3 text-danger hover:underline" @click="remove(w)">{{ t('Delete') }}</button>
                        </td>
                    </tr>
                    <tr v-if="!windows.length">
                        <td colspan="6" class="px-4 py-8 text-center text-muted">{{ t('No admission windows yet. Add one below to open applications.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <FieldError :message="$page.props.errors?.window" />

        <section class="card">
            <h2 class="mb-4 font-semibold">{{ editing ? t('Edit window') : t('Add a window') }}</h2>
            <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="save">
                <div>
                    <label class="label" for="year">{{ t('Academic year') }}</label>
                    <select id="year" v-model="form.academic_year_id" class="input" required>
                        <option v-for="y in years" :key="y.id" :value="y.id">{{ y.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="grade">{{ t('Grade level') }}</label>
                    <select id="grade" v-model="form.grade_level_id" class="input" required>
                        <option value="" disabled>{{ t('Choose a grade') }}</option>
                        <option v-for="g in grades" :key="g.id" :value="g.id">{{ g.name }}</option>
                    </select>
                    <FieldError :message="form.errors.grade_level_id" />
                </div>
                <div>
                    <label class="label" for="seats">{{ t('Seats') }}</label>
                    <input id="seats" v-model.number="form.seats" type="number" min="1" class="input" required>
                    <FieldError :message="form.errors.seats" />
                </div>
                <div>
                    <label class="label" for="exception">{{ t('Exception margin (days)') }}</label>
                    <input id="exception" v-model.number="form.exception_days" type="number" min="0" max="365" class="input">
                </div>
                <div>
                    <label class="label" for="opens">{{ t('Opens on') }}</label>
                    <input id="opens" v-model="form.opens_on" type="date" class="input" dir="ltr" required>
                </div>
                <div>
                    <label class="label" for="closes">{{ t('Closes on') }}</label>
                    <input id="closes" v-model="form.closes_on" type="date" class="input" dir="ltr" required>
                    <FieldError :message="form.errors.closes_on" />
                </div>
                <div>
                    <label class="label" for="born_from">{{ t('Born from') }}</label>
                    <input id="born_from" v-model="form.born_from" type="date" class="input" dir="ltr">
                </div>
                <div>
                    <label class="label" for="born_to">{{ t('Born up to') }}</label>
                    <input id="born_to" v-model="form.born_to" type="date" class="input" dir="ltr">
                    <FieldError :message="form.errors.born_to" />
                </div>
                <p class="text-sm text-muted sm:col-span-2 lg:col-span-4">{{ t('Children born up to the margin after the range can apply and are flagged for your decision (e.g. the Ministry\'s 90-day exception for grade 1).') }}</p>
                <div class="flex gap-3 sm:col-span-2 lg:col-span-4">
                    <button type="submit" class="btn-primary" :disabled="form.processing">{{ t('Save') }}</button>
                    <button v-if="editing" type="button" class="btn-ghost" @click="cancel">{{ t('Cancel') }}</button>
                </div>
            </form>
        </section>
    </AppLayout>
</template>
