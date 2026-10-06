<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import FieldError from '../../components/FieldError.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ runs: Array, staff: Array, suggestedMonth: String });
const t = useT();
const money = (v) => Number(v ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const run = useForm({ month: props.suggestedMonth });
const createRun = () => run.post('/payroll/runs');
const missing = computed(() => props.staff.filter((s) => !s.contract).length);

const editing = ref(null);
const contract = useForm({ is_saudi: true, hired_on: '', basic_salary: '', housing_allowance: '', transport_allowance: '', other_allowances: '', gosi_registered: true, bank: '', iban: '' });
const start = (s) => {
    editing.value = s.id;
    contract.clearErrors();
    const c = s.contract ?? {};
    Object.assign(contract, {
        is_saudi: c.is_saudi ?? true, hired_on: c.hired_on ?? '', basic_salary: c.basic_salary ?? '', housing_allowance: c.housing_allowance ?? '',
        transport_allowance: c.transport_allowance ?? '', other_allowances: c.other_allowances ?? '', gosi_registered: c.gosi_registered ?? true,
        bank: c.bank ?? '', iban: c.iban ?? '',
    });
};
const save = () => contract.transform((d) => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v])))
    .put(`/payroll/contracts/${editing.value}`, { preserveScroll: true, onSuccess: () => { editing.value = null; } });
const gross = (c) => Number(c.basic_salary) + Number(c.housing_allowance) + Number(c.transport_allowance) + Number(c.other_allowances);
</script>

<template>
    <AppLayout :title="t('Payroll')">
        <section class="card mb-4">
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="label" for="month">{{ t('Month') }}</label>
                    <input id="month" v-model="run.month" type="month" class="input" dir="ltr">
                </div>
                <button type="button" class="btn-primary" :disabled="run.processing" @click="createRun">{{ t('Prepare payroll') }}</button>
                <FieldError :message="run.errors.month" />
                <p v-if="missing" class="text-sm text-warn">{{ t(':count active staff have no pay details and will be left out.', { count: missing }) }}</p>
            </div>
            <ul class="mt-4 divide-y divide-line text-sm">
                <li v-for="r in runs" :key="r.id" class="flex items-center gap-3 py-2">
                    <Link :href="`/payroll/runs/${r.id}`" class="font-medium text-accent hover:underline" dir="ltr">{{ r.month }}</Link>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="r.status === 'approved' ? 'bg-accent-soft text-accent' : 'bg-warn-soft text-warn'">{{ t(`payroll_status.${r.status}`) }}</span>
                    <span class="text-muted">{{ t(':count staff', { count: r.lines }) }}</span>
                    <span class="ms-auto tabular-nums">{{ money(r.net) }} {{ t('SAR') }}</span>
                </li>
            </ul>
        </section>

        <h2 class="mb-3 font-semibold">{{ t('Pay details') }}</h2>
        <div class="card overflow-x-auto p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-line text-muted">
                    <tr>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Staff') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Basic') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Total salary') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('GOSI') }}</th>
                        <th class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody>
                    <template v-for="s in staff" :key="s.id">
                        <tr class="border-b border-line">
                            <td class="px-4 py-3"><div class="font-medium">{{ s.name }}</div><div class="text-xs text-muted">{{ [s.employee_number, s.job_title].filter(Boolean).join(' · ') }}</div></td>
                            <td class="px-4 py-3 tabular-nums">{{ s.contract ? money(s.contract.basic_salary) : '—' }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ s.contract ? money(gross(s.contract)) : '—' }}</td>
                            <td class="px-4 py-3">{{ s.contract ? (s.contract.gosi_registered ? (s.contract.is_saudi ? t('Saudi') : t('Non-Saudi')) : t('Not registered')) : '' }}</td>
                            <td class="px-4 py-3 text-end"><button type="button" class="text-accent hover:underline" @click="start(s)">{{ s.contract ? t('Edit') : t('Add pay details') }}</button></td>
                        </tr>
                        <tr v-if="editing === s.id" class="border-b border-line bg-surface">
                            <td colspan="5" class="px-4 py-4">
                                <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="save">
                                    <div><label class="label" :for="`b${s.id}`">{{ t('Basic') }}</label><input :id="`b${s.id}`" v-model="contract.basic_salary" type="number" step="0.01" min="0" class="input" dir="ltr" required><FieldError :message="contract.errors.basic_salary" /></div>
                                    <div><label class="label" :for="`h${s.id}`">{{ t('Housing allowance') }}</label><input :id="`h${s.id}`" v-model="contract.housing_allowance" type="number" step="0.01" min="0" class="input" dir="ltr"></div>
                                    <div><label class="label" :for="`t${s.id}`">{{ t('Transport allowance') }}</label><input :id="`t${s.id}`" v-model="contract.transport_allowance" type="number" step="0.01" min="0" class="input" dir="ltr"></div>
                                    <div><label class="label" :for="`o${s.id}`">{{ t('Other allowances') }}</label><input :id="`o${s.id}`" v-model="contract.other_allowances" type="number" step="0.01" min="0" class="input" dir="ltr"></div>
                                    <div><label class="label" :for="`bank${s.id}`">{{ t('Bank') }}</label><input :id="`bank${s.id}`" v-model="contract.bank" class="input"></div>
                                    <div class="sm:col-span-2"><label class="label" :for="`iban${s.id}`">{{ t('IBAN') }}</label><input :id="`iban${s.id}`" v-model="contract.iban" class="input" dir="ltr" placeholder="SA0000000000000000000000"><FieldError :message="contract.errors.iban" /></div>
                                    <div><label class="label" :for="`hd${s.id}`">{{ t('Hired on') }}</label><input :id="`hd${s.id}`" v-model="contract.hired_on" type="date" class="input" dir="ltr"></div>
                                    <div class="flex flex-col justify-center gap-1 sm:col-span-2">
                                        <label class="flex items-center gap-2"><input v-model="contract.is_saudi" type="checkbox"> {{ t('Saudi national') }}</label>
                                        <label class="flex items-center gap-2"><input v-model="contract.gosi_registered" type="checkbox"> {{ t('Registered with GOSI') }}</label>
                                    </div>
                                    <div class="flex items-end gap-2 sm:col-span-2"><button class="btn-primary">{{ t('Save') }}</button><button type="button" class="btn-ghost" @click="editing = null">{{ t('Cancel') }}</button></div>
                                </form>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
