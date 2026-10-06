<script setup>
import { usePage } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import { formatDate, useT } from '../../lib/i18n';

const props = defineProps({ student: Object, years: Object, attendance: Array });
const t = useT();
const page = usePage();
const s = props.student.data ?? props.student;
</script>

<template>
    <AppLayout :title="s.name">
        <div class="grid gap-4 lg:grid-cols-3">
            <section class="card lg:col-span-2">
                <h2 class="mb-4 font-semibold">{{ t('Profile') }}</h2>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-muted">{{ t('Full name (Arabic)') }}</dt><dd>{{ s.name_ar }}</dd></div>
                    <div><dt class="text-muted">{{ t('Name (English)') }}</dt><dd dir="ltr" class="text-start">{{ s.name_en || '—' }}</dd></div>
                    <div><dt class="text-muted">{{ t('Student number') }}</dt><dd dir="ltr" class="text-start tabular-nums">{{ s.student_number }}</dd></div>
                    <div><dt class="text-muted">{{ t('National ID') }}</dt><dd dir="ltr" class="text-start tabular-nums">{{ s.national_id || '—' }}</dd></div>
                    <div><dt class="text-muted">{{ t('Gender') }}</dt><dd>{{ t(`gender.${s.gender}`) }}</dd></div>
                    <div><dt class="text-muted">{{ t('Date of birth') }}</dt><dd>{{ formatDate(s.date_of_birth, page.props.locale) || '—' }}</dd></div>
                </dl>
            </section>

            <section class="card">
                <h2 class="mb-4 font-semibold">{{ t('Guardians') }}</h2>
                <ul class="space-y-3 text-sm">
                    <li v-for="g in s.guardians" :key="g.id">
                        <div class="font-medium">{{ g.name }} <span v-if="g.is_primary" class="text-xs text-accent">· {{ t('Primary') }}</span></div>
                        <div class="text-muted">{{ t(`relationship.${g.relationship}`) }}<template v-if="g.phone"> · <span dir="ltr">{{ g.phone }}</span></template></div>
                    </li>
                    <li v-if="!s.guardians?.length" class="text-muted">—</li>
                </ul>
            </section>

            <section class="card lg:col-span-2">
                <h2 class="mb-4 font-semibold">{{ t('Recent attendance') }}</h2>
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-for="(r, i) in attendance" :key="i" class="border-b border-line last:border-0">
                            <td class="py-2">{{ formatDate(r.date, page.props.locale) }}</td>
                            <td class="py-2 text-muted">{{ r.period === 0 ? t('Daily') : t('Period :n', { n: r.period }) }}</td>
                            <td class="py-2"><StatusBadge :kind="r.kind" :label="r.name" /></td>
                            <td class="py-2 text-muted">{{ r.note }}</td>
                        </tr>
                        <tr v-if="!attendance.length"><td class="py-2 text-muted">{{ t('No attendance recorded yet.') }}</td></tr>
                    </tbody>
                </table>
            </section>

            <section class="card">
                <h2 class="mb-4 font-semibold">{{ t('Enrollment history') }}</h2>
                <ul class="space-y-2 text-sm">
                    <li v-for="e in s.enrollments" :key="e.id">
                        <span class="font-medium">{{ years[e.academic_year_id] }}</span>
                        — {{ e.grade_level?.name }}<template v-if="e.section"> / {{ e.section.name }}</template>
                        <span class="text-muted"> · {{ t(`enrollment.${e.status}`) }}</span>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
