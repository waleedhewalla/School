<script setup>
import { ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import FieldError from '../../components/FieldError.vue';
import { useT, formatDateTime } from '../../lib/i18n';

const props = defineProps({ visits: Array, alerts: Array, results: Array, search: String, outcomes: Array, today: Number });
const t = useT();

const query = ref(props.search ?? '');
let timer;
watch(query, (q) => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get('/clinic', q ? { search: q } : {}, { preserveState: true, preserveScroll: true, replace: true, only: ['results', 'search'] }), 300);
});

const picked = ref(null);
const form = useForm({ student_id: null, complaint: '', temperature: '', outcome: 'returned_to_class', treatment: '' });
const pick = (s) => { picked.value = s; form.student_id = s.id; };
const save = () => form.transform((d) => ({ ...d, temperature: d.temperature || null })).post('/clinic/visits', {
    preserveScroll: true, onSuccess: () => { form.reset(); picked.value = null; query.value = ''; },
});
const time = (iso) => formatDateTime(iso, usePage().props.locale);
</script>

<template>
    <AppLayout :title="t('Clinic')">
        <div class="grid gap-4 lg:grid-cols-3">
            <section class="card lg:col-span-2">
                <h2 class="mb-3 font-semibold">{{ t('Record a visit') }} <span class="text-sm font-normal text-muted">· {{ t(':count today', { count: today }) }}</span></h2>
                <div v-if="!picked">
                    <input v-model="query" type="search" class="input" :placeholder="t('Search by name, number or ID')" autofocus>
                    <ul class="mt-2 divide-y divide-line">
                        <li v-for="s in results" :key="s.id"><button type="button" class="w-full py-2 text-start hover:text-accent" @click="pick(s)">{{ s.name }} <span class="text-xs text-muted" dir="ltr">{{ s.number }}</span></button></li>
                    </ul>
                </div>
                <form v-else class="grid gap-4 sm:grid-cols-2" @submit.prevent="save">
                    <div class="flex items-center gap-3 sm:col-span-2">
                        <span class="font-semibold">{{ picked.name }}</span>
                        <Link :href="`/clinic/students/${picked.id}`" class="text-sm text-accent hover:underline">{{ t('Health card') }}</Link>
                        <button type="button" class="ms-auto text-sm text-muted" @click="picked = null">{{ t('Change') }}</button>
                    </div>
                    <div class="sm:col-span-2"><label class="label" for="complaint">{{ t('Complaint') }}</label><input id="complaint" v-model="form.complaint" class="input" required><FieldError :message="form.errors.complaint" /></div>
                    <div><label class="label" for="temp">{{ t('Temperature (°C)') }}</label><input id="temp" v-model="form.temperature" type="number" step="0.1" min="34" max="43" class="input" dir="ltr"><FieldError :message="form.errors.temperature" /></div>
                    <div>
                        <label class="label" for="outcome">{{ t('Outcome') }}</label>
                        <select id="outcome" v-model="form.outcome" class="input"><option v-for="o in outcomes" :key="o" :value="o">{{ t(`clinic.${o}`) }}</option></select>
                    </div>
                    <div class="sm:col-span-2"><label class="label" for="treatment">{{ t('Treatment / notes') }}</label><textarea id="treatment" v-model="form.treatment" class="input" rows="2" /></div>
                    <p v-if="['sent_home', 'referred'].includes(form.outcome)" class="text-sm text-warn sm:col-span-2">{{ t('The guardian will be notified.') }}</p>
                    <div class="sm:col-span-2"><button class="btn-primary" :disabled="form.processing">{{ t('Save') }}</button></div>
                </form>
            </section>

            <section class="card">
                <h2 class="mb-3 font-semibold">{{ t('Health alerts') }}</h2>
                <ul class="space-y-2 text-sm">
                    <li v-for="a in alerts" :key="a.student_id">
                        <Link :href="`/clinic/students/${a.student_id}`" class="font-medium text-accent hover:underline">{{ a.student }}</Link>
                        <div class="text-muted">{{ a.summary }}</div>
                    </li>
                    <li v-if="!alerts.length" class="text-muted">{{ t('None recorded.') }}</li>
                </ul>
            </section>
        </div>

        <h2 class="mb-3 mt-6 font-semibold">{{ t('Last 14 days') }}</h2>
        <div class="card overflow-x-auto p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-line text-muted">
                    <tr>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Time') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Student') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Complaint') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Outcome') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="v in visits" :key="v.id" class="border-b border-line last:border-0">
                        <td class="px-4 py-3 whitespace-nowrap">{{ time(v.visited_at) }}</td>
                        <td class="px-4 py-3"><Link :href="`/clinic/students/${v.student_id}`" class="text-accent hover:underline">{{ v.student }}</Link><div class="text-xs text-muted">{{ v.class }}</div></td>
                        <td class="px-4 py-3">{{ v.complaint }}<span v-if="v.temperature" class="text-muted" dir="ltr"> · {{ v.temperature }}°</span></td>
                        <td class="px-4 py-3">{{ t(`clinic.${v.outcome}`) }}<span v-if="v.notified" class="ms-1 text-xs text-muted">({{ t('guardian told') }})</span></td>
                    </tr>
                    <tr v-if="!visits.length"><td colspan="4" class="px-4 py-8 text-center text-muted">{{ t('No visits.') }}</td></tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
