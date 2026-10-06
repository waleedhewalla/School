<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { useT } from '../../lib/i18n';

defineProps({ payslips: Array, name: String });
const t = useT();
const money = (v) => Number(v ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const print = () => window.print();
</script>

<template>
    <AppLayout :title="t('My payslips')">
        <p v-if="!payslips.length" class="card text-muted">{{ t('No payslips yet.') }}</p>
        <button v-else type="button" class="btn-ghost mb-4 print:hidden" @click="print">{{ t('Print') }}</button>
        <article v-for="p in payslips" :key="p.id" class="card mb-4 break-inside-avoid">
            <div class="mb-3 flex items-center"><h2 class="font-semibold">{{ name }}</h2><span class="ms-auto font-semibold" dir="ltr">{{ p.month }}</span></div>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm sm:grid-cols-4">
                <dt class="text-muted">{{ t('Basic') }}</dt><dd class="tabular-nums">{{ money(p.basic) }}</dd>
                <dt class="text-muted">{{ t('Housing allowance') }}</dt><dd class="tabular-nums">{{ money(p.housing) }}</dd>
                <dt class="text-muted">{{ t('Transport allowance') }}</dt><dd class="tabular-nums">{{ money(p.transport) }}</dd>
                <dt class="text-muted">{{ t('Other allowances') }}</dt><dd class="tabular-nums">{{ money(p.other) }}</dd>
                <dt class="text-muted">{{ t('Additions') }}</dt><dd class="tabular-nums">{{ money(p.additions) }}</dd>
                <dt class="text-muted">{{ t('Deductions') }}</dt><dd class="tabular-nums">{{ money(p.deductions) }}<span v-if="p.note" class="text-xs text-muted"> · {{ p.note }}</span></dd>
                <dt class="text-muted">{{ t('GOSI') }}</dt><dd class="tabular-nums">{{ money(p.gosi_employee) }}</dd>
                <dt class="font-semibold">{{ t('Net') }}</dt><dd class="font-semibold tabular-nums">{{ money(p.net) }} {{ t('SAR') }}</dd>
            </dl>
        </article>
    </AppLayout>
</template>
