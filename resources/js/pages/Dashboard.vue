<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../layouts/AppLayout.vue';
import { useT } from '../lib/i18n';

defineProps({ today: String, year: Object, stats: Object, lessonsToday: Array });
const t = useT();
</script>

<template>
    <AppLayout :title="t('Dashboard')">
        <p class="mb-6 text-muted">
            {{ today }}<template v-if="year"> · {{ t('Academic year') }} {{ year.name }}</template>
        </p>

        <section v-if="lessonsToday.length" class="card mb-6">
            <h2 class="mb-3 font-semibold">{{ t('My lessons today') }}</h2>
            <ul class="divide-y divide-line text-sm">
                <li v-for="l in lessonsToday" :key="l.period_sequence" class="flex flex-wrap items-center gap-3 py-2">
                    <span class="w-28 font-medium">{{ l.period }}</span>
                    <span class="w-24 text-muted tabular-nums" dir="ltr">{{ l.time }}</span>
                    <span class="flex-1">{{ l.subject }} — {{ l.section }}<span v-if="l.room" class="text-muted"> · {{ l.room }}</span></span>
                    <Link :href="`/attendance?section_id=${l.section_id}&period=${l.period_sequence}`" class="btn-ghost py-1">{{ t('Take attendance') }}</Link>
                </li>
            </ul>
        </section>

        <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
            <div class="card">
                <div class="text-sm text-muted">{{ t('Enrolled students') }}</div>
                <div class="mt-1 text-3xl font-semibold">{{ stats.students }}</div>
            </div>
            <div class="card">
                <div class="text-sm text-muted">{{ t('Sections') }}</div>
                <div class="mt-1 text-3xl font-semibold">{{ stats.sections }}</div>
            </div>
            <div class="card">
                <div class="text-sm text-muted">{{ t('Registers taken today') }}</div>
                <div class="mt-1 text-3xl font-semibold">{{ stats.registers_taken }} <span class="text-base text-muted">/ {{ stats.sections }}</span></div>
            </div>
            <div class="card">
                <div class="text-sm text-muted">{{ t('Absent today') }}</div>
                <div class="mt-1 text-3xl font-semibold text-danger">{{ stats.absent_today }}</div>
            </div>
            <div class="card">
                <div class="text-sm text-muted">{{ t('Late today') }}</div>
                <div class="mt-1 text-3xl font-semibold text-warn">{{ stats.late_today }}</div>
            </div>
        </div>

        <div class="mt-6 flex gap-3">
            <Link href="/attendance" class="btn-primary">{{ t('Take attendance') }}</Link>
            <Link href="/students" class="btn-ghost">{{ t('Students') }}</Link>
        </div>
    </AppLayout>
</template>
