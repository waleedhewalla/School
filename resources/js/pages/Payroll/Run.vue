<script setup>
import { computed, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import FieldError from '../../components/FieldError.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ run: Object, lines: Array });
const t = useT();
const money = (v) => Number(v ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const draft = computed(() => props.run.status === 'draft');
const edits = reactive(Object.fromEntries(props.lines.map((l) => [l.id, { additions: l.additions, deductions: l.deductions, note: l.note ?? '' }])));
const saveLine = (l) => router.patch(`/payroll/lines/${l.id}`, edits[l.id], { preserveScroll: true });
const sum = (key) => props.lines.reduce((a, l) => a + Number(l[key]), 0);
const approve = () => confirm(t('Approve this payroll? It can no longer be changed and staff will see their payslips.')) && router.post(`/payroll/runs/${props.run.id}/approve`, {}, { preserveScroll: true });
const discard = () => confirm(t('Delete this draft payroll?')) && router.delete(`/payroll/runs/${props.run.id}`);
</script>

<template>
    <AppLayout :title="t('Payroll :month', { month: run.month })">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <Link href="/payroll" class="text-sm text-muted hover:text-ink"><span class="rtl:hidden">←</span><span class="ltr:hidden">→</span> {{ t('Payroll') }}</Link>
            <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="draft ? 'bg-warn-soft text-warn' : 'bg-accent-soft text-accent'">{{ t(`payroll_status.${run.status}`) }}</span>
            <div class="ms-auto flex gap-2">
                <a :href="`/payroll/runs/${run.id}/export`" class="btn-ghost">{{ t('Download Excel') }}</a>
                <button v-if="draft" type="button" class="btn-ghost text-danger" @click="discard">{{ t('Delete') }}</button>
                <button v-if="draft" type="button" class="btn-primary" @click="approve">{{ t('Approve') }}</button>
            </div>
        </div>
        <FieldError :message="$page.props.errors?.run || $page.props.errors?.additions" />

        <div class="mb-4 grid gap-4 sm:grid-cols-3">
            <div class="card"><div class="text-sm text-muted">{{ t('Total net pay') }}</div><div class="text-2xl font-semibold tabular-nums">{{ money(sum('net')) }}</div></div>
            <div class="card"><div class="text-sm text-muted">{{ t('GOSI (staff share)') }}</div><div class="text-2xl font-semibold tabular-nums">{{ money(sum('gosi_employee')) }}</div></div>
            <div class="card"><div class="text-sm text-muted">{{ t('GOSI (school share)') }}</div><div class="text-2xl font-semibold tabular-nums">{{ money(sum('gosi_employer')) }}</div></div>
        </div>

        <div class="card overflow-x-auto p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-line text-muted">
                    <tr>
                        <th class="px-3 py-3 text-start font-medium">{{ t('Staff') }}</th>
                        <th class="px-3 py-3 text-start font-medium">{{ t('Salary and allowances') }}</th>
                        <th class="px-3 py-3 text-start font-medium">{{ t('Additions') }}</th>
                        <th class="px-3 py-3 text-start font-medium">{{ t('Deductions') }}</th>
                        <th class="px-3 py-3 text-start font-medium">{{ t('GOSI') }}</th>
                        <th class="px-3 py-3 text-start font-medium">{{ t('Net') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="l in lines" :key="l.id" class="border-b border-line last:border-0 align-top">
                        <td class="px-3 py-3"><div class="font-medium">{{ l.name }}</div><div class="text-xs text-muted" dir="ltr">{{ l.employee_number }}</div></td>
                        <td class="px-3 py-3 tabular-nums">{{ money(Number(l.basic) + Number(l.housing) + Number(l.transport) + Number(l.other)) }}</td>
                        <template v-if="draft">
                            <td class="px-3 py-2"><input v-model="edits[l.id].additions" type="number" step="0.01" min="0" class="input w-28" dir="ltr" :aria-label="t('Additions')" @change="saveLine(l)"></td>
                            <td class="px-3 py-2">
                                <input v-model="edits[l.id].deductions" type="number" step="0.01" min="0" class="input w-28" dir="ltr" :aria-label="t('Deductions')" @change="saveLine(l)">
                                <input v-model="edits[l.id].note" class="input mt-1 w-40 py-1 text-xs" :placeholder="t('Reason')" @change="saveLine(l)">
                            </td>
                        </template>
                        <template v-else>
                            <td class="px-3 py-3 tabular-nums">{{ money(l.additions) }}</td>
                            <td class="px-3 py-3 tabular-nums">{{ money(l.deductions) }}<div v-if="l.note" class="text-xs text-muted">{{ l.note }}</div></td>
                        </template>
                        <td class="px-3 py-3 tabular-nums">{{ money(l.gosi_employee) }}</td>
                        <td class="px-3 py-3 font-semibold tabular-nums" :class="l.net < 0 && 'text-danger'">{{ money(l.net) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-xs text-muted">{{ t('The Excel register lists IBANs and net pay for the bank transfer or the Mudad (WPS) upload; match it to your bank\'s template.') }}</p>
    </AppLayout>
</template>
