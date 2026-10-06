<script setup>
import { computed, ref } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import { formatDate, useT } from '../../lib/i18n';

const props = defineProps({ student: Object, years: Object, attendance: Array, canManage: Boolean, otherSections: Array });
const t = useT();
const page = usePage();
const s = computed(() => props.student.data ?? props.student);
const active = computed(() => s.value.enrollments?.find((e) => e.status === 'active'));

const panel = ref(null);
const move = useForm({ section_id: '' });
const withdraw = useForm({ status: 'withdrawn', left_on: new Date().toISOString().slice(0, 10) });
const submitMove = () => move.patch(`/enrollments/${active.value.id}`, { preserveScroll: true, onSuccess: () => { panel.value = null; } });
const submitWithdraw = () => withdraw.patch(`/enrollments/${active.value.id}`, { preserveScroll: true, onSuccess: () => { panel.value = null; } });
</script>

<template>
    <AppLayout :title="s.name">
        <div v-if="canManage" class="mb-4 flex flex-wrap gap-2">
            <Link :href="`/students/${s.id}/edit`" class="btn-ghost">{{ t('Edit') }}</Link>
            <template v-if="active">
                <button v-if="otherSections.length" type="button" class="btn-ghost" @click="panel = panel === 'move' ? null : 'move'">{{ t('Move to another section') }}</button>
                <button type="button" class="btn-ghost" @click="panel = panel === 'withdraw' ? null : 'withdraw'">{{ t('Withdraw / transfer') }}</button>
            </template>
        </div>

        <form v-if="panel === 'move'" class="card mb-4 flex flex-wrap items-end gap-3" @submit.prevent="submitMove">
            <div>
                <label class="label" for="move-section">{{ t('New section') }}</label>
                <select id="move-section" v-model="move.section_id" class="input" required>
                    <option value="" disabled>{{ t('Choose a section') }}</option>
                    <option v-for="o in otherSections" :key="o.id" :value="o.id">{{ o.name }}</option>
                </select>
            </div>
            <button type="submit" class="btn-primary" :disabled="move.processing">{{ t('Move') }}</button>
            <p v-if="move.errors.section_id" class="w-full text-sm text-danger">{{ move.errors.section_id }}</p>
        </form>

        <form v-if="panel === 'withdraw'" class="card mb-4 flex flex-wrap items-end gap-3" @submit.prevent="submitWithdraw">
            <div>
                <label class="label" for="w-status">{{ t('Reason') }}</label>
                <select id="w-status" v-model="withdraw.status" class="input">
                    <option value="withdrawn">{{ t('enrollment.withdrawn') }}</option>
                    <option value="transferred">{{ t('enrollment.transferred') }}</option>
                </select>
            </div>
            <div>
                <label class="label" for="w-date">{{ t('Last day') }}</label>
                <input id="w-date" v-model="withdraw.left_on" type="date" class="input" dir="ltr" required>
            </div>
            <button type="submit" class="btn-ghost text-danger" :disabled="withdraw.processing">{{ t('Confirm') }}</button>
        </form>

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
