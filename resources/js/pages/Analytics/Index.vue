<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import BarList from '../../components/BarList.vue';
import { formatDate, useT } from '../../lib/i18n';

const props = defineProps({
    empty: Boolean, year: String, tiles: Object, byGrade: Array, weekly: Array, bySection: Array,
    terms: Array, termId: Number, subjects: Array, atRisk: Array, admissions: [Object, Array], services: Object,
});
const t = useT();
const page = usePage();

const pct = (v) => (v === null || v === undefined ? '—' : `${v}%`);
const weekRows = computed(() => (props.weekly ?? []).map((w) => ({
    label: t('Week of :date', { date: formatDate(w.label, page.props.locale) }), value: w.value ?? 0, display: pct(w.value),
    hint: t(':count school days recorded', { count: w.days }),
})));
const sectionRows = computed(() => (props.bySection ?? []).map((s) => ({ label: s.label, value: s.value, display: pct(s.value) })));
const subjectRows = computed(() => (props.subjects ?? []).map((s) => ({ label: s.label, value: s.value, display: pct(s.value), hint: t(':count students', { count: s.count }) })));
const funnel = ['submitted', 'under_review', 'assessment_scheduled', 'offered', 'accepted', 'enrolled', 'waitlisted'];
const admissionRows = computed(() => funnel.map((s) => ({ label: t(`admission.status.${s}`), value: Number(props.admissions?.[s] ?? 0) })));
const reasonText = (r) => ({
    attendance: t('Attendance :rate', { rate: pct(r.value) }),
    behaviour: t('Behaviour :score', { score: r.value }),
    failing: t(':count subjects below pass', { count: r.value }),
}[r.kind]);
</script>

<template>
    <AppLayout :title="t('Analytics')">
        <p v-if="empty" class="card text-muted">{{ t('Set a current academic year first.') }}</p>
        <template v-else>
            <div class="mb-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="card">
                    <div class="text-sm text-muted">{{ t('Students :year', { year }) }}</div>
                    <div class="text-3xl font-semibold tabular-nums">{{ tiles.students }}</div>
                    <div class="text-sm text-muted">{{ t(':boys boys · :girls girls', { boys: tiles.boys, girls: tiles.students - tiles.boys }) }}</div>
                </div>
                <div class="card">
                    <div class="text-sm text-muted">{{ t('Attendance, last 30 days') }}</div>
                    <div class="text-3xl font-semibold tabular-nums">{{ pct(tiles.attendance) }}</div>
                </div>
                <div class="card">
                    <div class="text-sm text-muted">{{ t('Average behaviour score') }}</div>
                    <div class="text-3xl font-semibold tabular-nums">{{ tiles.behaviour ?? '—' }}</div>
                </div>
                <div class="card">
                    <div class="text-sm text-muted">{{ t('Students to follow up') }}</div>
                    <div class="text-3xl font-semibold tabular-nums" :class="tiles.at_risk && 'text-warn'">{{ tiles.at_risk }}</div>
                </div>
            </div>

            <div class="mb-4 grid gap-4 lg:grid-cols-2">
                <section class="card">
                    <h2 class="mb-3 font-semibold">{{ t('Attendance by week') }}</h2>
                    <BarList v-if="weekRows.length" :rows="weekRows" :max="100" :label="t('Attendance by week')" />
                    <p v-else class="text-sm text-muted">{{ t('No registers yet.') }}</p>
                </section>
                <section class="card">
                    <h2 class="mb-1 font-semibold">{{ t('Lowest attendance by section') }}</h2>
                    <p class="mb-3 text-xs text-muted">{{ t('Last 30 days') }}</p>
                    <BarList v-if="sectionRows.length" :rows="sectionRows" :max="100" :label="t('Lowest attendance by section')" />
                    <p v-else class="text-sm text-muted">{{ t('No registers yet.') }}</p>
                </section>
                <section class="card">
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <h2 class="font-semibold">{{ t('Average by subject') }}</h2>
                        <select v-if="terms.length > 1" class="input ms-auto w-auto py-1" :value="termId" :aria-label="t('Term')" @change="router.get('/analytics', { term: $event.target.value }, { preserveScroll: true })">
                            <option v-for="term in terms" :key="term.id" :value="term.id">{{ term.name }}</option>
                        </select>
                    </div>
                    <BarList v-if="subjectRows.length" :rows="subjectRows" :max="100" :label="t('Average by subject')" />
                    <p v-else class="text-sm text-muted">{{ t('No complete marks for this term yet.') }}</p>
                </section>
                <section class="card">
                    <h2 class="mb-3 font-semibold">{{ t('Students by grade') }}</h2>
                    <BarList :rows="byGrade" :label="t('Students by grade')" />
                </section>
                <section class="card">
                    <h2 class="mb-3 font-semibold">{{ t('Admissions') }}</h2>
                    <BarList :rows="admissionRows" :label="t('Admissions')" />
                </section>
                <section class="card">
                    <h2 class="mb-3 font-semibold">{{ t('Services') }}</h2>
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div><dt class="text-muted">{{ t('Clinic visits, 30 days') }}</dt><dd class="text-2xl font-semibold tabular-nums">{{ services.clinic_visits }}</dd></div>
                        <div><dt class="text-muted">{{ t('Overdue library books') }}</dt><dd class="text-2xl font-semibold tabular-nums">{{ services.overdue_books }}</dd></div>
                    </dl>
                </section>
            </div>

            <section class="card">
                <h2 class="mb-1 font-semibold">{{ t('Students to follow up') }}</h2>
                <p class="mb-3 text-xs text-muted">{{ t('Attendance under 90% (30 days), behaviour under 80, or a subject below the pass mark this term.') }}</p>
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-for="s in atRisk" :key="s.id" class="border-b border-line last:border-0">
                            <td class="py-2"><Link :href="`/students/${s.id}`" class="font-medium text-accent hover:underline">{{ s.name }}</Link></td>
                            <td class="py-2 text-muted">{{ s.class }}</td>
                            <td class="py-2">
                                <span v-for="(r, i) in s.reasons" :key="i" class="me-2 inline-block rounded bg-warn-soft px-2 py-0.5 text-xs text-warn">{{ reasonText(r) }}</span>
                            </td>
                        </tr>
                        <tr v-if="!atRisk.length"><td class="py-6 text-center text-muted">{{ t('No students flagged.') }}</td></tr>
                    </tbody>
                </table>
            </section>
        </template>
    </AppLayout>
</template>
