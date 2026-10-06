<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ sections: Array, codes: Object, filters: Object, register: Object, canEdit: Boolean });
const t = useT();

const codes = computed(() => props.codes.data ?? props.codes);
const defaultCode = computed(() => codes.value.find((c) => c.is_default)?.code ?? codes.value[0]?.code);
const filters = reactive({ ...props.filters, section_id: props.filters.section_id ?? '' });

watch(filters, () => {
    router.get('/attendance', filters.section_id ? filters : { date: filters.date }, { preserveState: false, replace: true });
});

const form = useForm({ date: props.filters.date, period: props.filters.period, records: [] });
const rows = ref((props.register?.students ?? []).map((s) => ({ ...s, code: s.code ?? defaultCode.value, note: s.note ?? '' })));

const markAll = (code) => rows.value.forEach((row) => { row.code = code; });

const tally = computed(() => Object.fromEntries(codes.value.map((c) => [c.code, rows.value.filter((r) => r.code === c.code).length])));

const tone = {
    present: 'border-accent bg-accent-soft text-accent',
    absent: 'border-danger bg-danger-soft text-danger',
    late: 'border-warn bg-warn-soft text-warn',
    excused: 'border-line bg-surface text-ink',
};

const save = () => {
    form.records = rows.value.map(({ student_id, code, note }) => ({ student_id, code, note: note || null }));
    form.post(`/attendance/${filters.section_id}`, { preserveScroll: true });
};
</script>

<template>
    <AppLayout :title="t('Attendance')">
        <div class="mb-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="label" for="section">{{ t('Section') }}</label>
                <select id="section" v-model="filters.section_id" class="input min-w-56">
                    <option value="">{{ t('Choose a section') }}</option>
                    <option v-for="section in sections" :key="section.id" :value="section.id">{{ section.label }}</option>
                </select>
            </div>
            <div>
                <label class="label" for="date">{{ t('Date') }}</label>
                <input id="date" v-model="filters.date" type="date" class="input" dir="ltr">
            </div>
            <div>
                <label class="label" for="period">{{ t('Register') }}</label>
                <select id="period" v-model.number="filters.period" class="input">
                    <option :value="0">{{ t('Daily') }}</option>
                    <option v-for="n in 8" :key="n" :value="n">{{ t('Period :n', { n }) }}</option>
                </select>
            </div>
        </div>

        <p v-if="!sections.length" class="card text-muted">{{ t('You are not assigned to any section.') }}</p>
        <p v-else-if="!register" class="card text-muted">{{ t('Choose a section to take attendance.') }}</p>

        <template v-else>
            <div class="mb-3 flex flex-wrap items-center gap-2 text-sm">
                <span v-if="register.taken" class="rounded-full bg-accent-soft px-3 py-1 text-accent">{{ t('Already taken — saving will correct it') }}</span>
                <span class="text-muted">{{ codes.map((c) => `${c.name}: ${tally[c.code]}`).join(' · ') }}</span>
                <div v-if="canEdit" class="ms-auto flex gap-2">
                    <button type="button" class="btn-ghost" @click="markAll(defaultCode)">{{ t('Mark all present') }}</button>
                </div>
            </div>

            <div class="card overflow-x-auto p-0">
                <table class="w-full text-sm">
                    <thead class="border-b border-line text-muted">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium">#</th>
                            <th class="px-4 py-3 text-start font-medium">{{ t('Name') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ t('Status') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ t('Note') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in rows" :key="row.student_id" class="border-b border-line last:border-0">
                            <td class="px-4 py-2 text-muted tabular-nums">{{ index + 1 }}</td>
                            <td class="px-4 py-2 font-medium">{{ row.name }}</td>
                            <td class="px-4 py-2">
                                <div class="flex flex-wrap gap-1" role="radiogroup" :aria-label="row.name">
                                    <button
                                        v-for="c in codes"
                                        :key="c.code"
                                        type="button"
                                        role="radio"
                                        :aria-checked="row.code === c.code"
                                        :disabled="!canEdit"
                                        class="rounded-lg border px-2.5 py-1 text-xs font-semibold"
                                        :class="row.code === c.code ? tone[c.kind] : 'border-line text-muted'"
                                        @click="row.code = c.code"
                                    >{{ c.name }}</button>
                                </div>
                            </td>
                            <td class="px-4 py-2">
                                <input v-model="row.note" type="text" class="input" :disabled="!canEdit" maxlength="255">
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td colspan="4" class="px-4 py-8 text-center text-muted">{{ t('No students in this section.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-if="Object.keys(form.errors).length" class="mt-3 text-sm text-danger">{{ Object.values(form.errors)[0] }}</p>

            <div v-if="canEdit && rows.length" class="mt-4">
                <button type="button" class="btn-primary" :disabled="form.processing" @click="save">{{ t('Save attendance') }}</button>
            </div>
        </template>
    </AppLayout>
</template>
