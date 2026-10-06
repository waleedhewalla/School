<script setup>
import { computed, reactive } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import BarList from '../../components/BarList.vue';
import FieldError from '../../components/FieldError.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ exam: Object, students: Array, bySection: Array, bands: Array, canManage: Boolean });
const t = useT();
const scores = reactive(Object.fromEntries(props.students.map((s) => [s.id, s.score ?? ''])));
const save = () => router.put(`/external-exams/${props.exam.id}/results`, { scores: Object.fromEntries(Object.entries(scores).map(([k, v]) => [k, v === '' ? null : v])) }, { preserveScroll: true });
const upload = useForm({ file: null });
const send = () => upload.post(`/external-exams/${props.exam.id}/import`, { forceFormData: true, preserveScroll: true });
const remove = () => confirm(t('Delete this test and its results?')) && router.delete(`/external-exams/${props.exam.id}`);

const done = computed(() => props.students.filter((s) => s.score !== null));
const average = computed(() => (done.value.length ? (done.value.reduce((a, s) => a + s.score, 0) / done.value.length).toFixed(1) : '—'));
const sectionRows = computed(() => props.bySection.filter((s) => s.average !== null).map((s) => ({
    label: s.section, value: s.average, display: s.average, hint: t(':count students', { count: s.count }),
})));
const bandRows = computed(() => props.bands.map((b) => ({
    label: `${b.from}–${b.to}%`, value: b.count, hint: t(':count students', { count: b.count }),
})));
</script>

<template>
    <AppLayout :title="exam.name">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <Link href="/external-exams" class="text-sm text-muted hover:text-ink"><span class="rtl:hidden">←</span><span class="ltr:hidden">→</span> {{ t('National tests') }}</Link>
            <span class="text-sm text-muted">{{ t(`exam_type.${exam.type}`) }}<template v-if="exam.grade"> · {{ exam.grade }}</template><template v-if="exam.subject"> · {{ exam.subject }}</template></span>
            <button v-if="canManage" type="button" class="ms-auto text-sm text-danger hover:underline" @click="remove">{{ t('Delete') }}</button>
        </div>

        <div class="mb-4 grid gap-4 md:grid-cols-3">
            <div class="card">
                <div class="text-sm text-muted">{{ t('School average') }}</div>
                <div class="text-3xl font-semibold tabular-nums">{{ average }} <span class="text-base font-normal text-muted">/ {{ exam.max }}</span></div>
                <div class="text-sm text-muted">{{ t(':count of :total students have a result', { count: done.length, total: students.length }) }}</div>
            </div>
            <section class="card">
                <h2 class="mb-2 text-sm font-semibold">{{ t('Average by section') }}</h2>
                <BarList v-if="sectionRows.length" :rows="sectionRows" :max="exam.max" :label="t('Average by section')" />
                <p v-else class="text-sm text-muted">—</p>
            </section>
            <section class="card">
                <h2 class="mb-2 text-sm font-semibold">{{ t('Students by result') }}</h2>
                <BarList :rows="bandRows" :label="t('Students by result')" />
            </section>
        </div>

        <section v-if="canManage" class="card mb-4">
            <form class="flex flex-wrap items-end gap-3" @submit.prevent="send">
                <div>
                    <label class="label" for="file">{{ t('Import results (Excel)') }}</label>
                    <input id="file" type="file" accept=".xlsx" class="input" @input="upload.file = $event.target.files[0]">
                </div>
                <button class="btn-primary" :disabled="upload.processing || !upload.file">{{ t('Import') }}</button>
                <p class="text-xs text-muted">{{ t('First column: national ID or student number; second column: score.') }}</p>
                <FieldError class="w-full" :message="upload.errors.file" />
            </form>
        </section>

        <div class="card overflow-x-auto p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-line text-muted">
                    <tr><th class="px-4 py-3 text-start font-medium">{{ t('Student') }}</th><th class="px-4 py-3 text-start font-medium">{{ t('Section') }}</th><th class="px-4 py-3 text-start font-medium">{{ t('Score') }}</th></tr>
                </thead>
                <tbody>
                    <tr v-for="s in students" :key="s.id" class="border-b border-line last:border-0">
                        <td class="px-4 py-2">{{ s.name }}</td>
                        <td class="px-4 py-2">{{ s.section ?? '—' }}</td>
                        <td class="px-4 py-2">
                            <input v-if="canManage" v-model="scores[s.id]" type="number" step="0.01" min="0" :max="exam.max" class="input w-28 py-1" dir="ltr" :aria-label="t('Score')">
                            <span v-else class="tabular-nums">{{ s.score ?? '—' }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <button v-if="canManage && students.length" type="button" class="btn-primary mt-4" @click="save">{{ t('Save') }}</button>
    </AppLayout>
</template>
