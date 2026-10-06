<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import { useT } from '../../lib/i18n';
import { parseMark, subjectResult } from '../../lib/grades';

const props = defineProps({ terms: Array, classes: Array, filters: Object, sheet: Object, scale: Object });
const t = useT();
const page = usePage();

const term = ref(props.filters.term_id);
const chosen = ref(props.filters.section_id ? `${props.filters.section_id}-${props.filters.subject_id}` : '');
watch([term, chosen], () => {
    const [section_id, subject_id] = chosen.value ? chosen.value.split('-') : [];
    router.get('/marks', { term_id: term.value, section_id, subject_id }, { replace: true });
});

const display = (v) => (v === 'absent' ? 'غ' : v ?? '');
const rows = reactive((props.sheet?.students ?? []).map((s) => ({
    student_id: s.student_id,
    name: s.name,
    inputs: Object.fromEntries(Object.entries(s.scores).map(([id, v]) => [id, display(v)])),
})));

const parsed = (row) => Object.fromEntries(Object.entries(row.inputs).map(([id, v]) => [id, parseMark(v)]));
const result = (row) => subjectResult(props.sheet.components, parsed(row), props.scale);
const invalid = (component, text) => {
    const v = parseMark(text);
    return v !== null && v !== 'absent' && (Number.isNaN(Number(v)) || Number(v) < 0 || Number(v) > Number(component.max_score));
};
const hasInvalid = computed(() => rows.some((r) => props.sheet.components.some((c) => invalid(c, r.inputs[c.id]))));

const form = useForm({});
const save = () => {
    const [section_id, subject_id] = chosen.value.split('-');
    form.transform(() => ({
        term_id: term.value, section_id, subject_id,
        rows: rows.map((r) => ({ student_id: r.student_id, scores: parsed(r) })),
    })).post('/marks', { preserveScroll: true });
};
const currentTerm = computed(() => props.terms.find((x) => x.id === term.value));
</script>

<template>
    <AppLayout :title="t('Marks entry')">
        <div class="mb-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="label" for="term">{{ t('Term') }}</label>
                <select id="term" v-model="term" class="input">
                    <option v-for="x in terms" :key="x.id" :value="x.id">{{ x.name }}<template v-if="!x.marks_open"> ({{ t('closed') }})</template></option>
                </select>
            </div>
            <div>
                <label class="label" for="class">{{ t('Section and subject') }}</label>
                <select id="class" v-model="chosen" class="input min-w-72">
                    <option value="">{{ t('Choose') }}</option>
                    <option v-for="c in classes" :key="`${c.section_id}-${c.subject_id}`" :value="`${c.section_id}-${c.subject_id}`">{{ c.label }}</option>
                </select>
            </div>
        </div>

        <p v-if="!classes.length" class="card text-muted">{{ t('You have no subjects assigned this year.') }}</p>
        <p v-else-if="!sheet" class="card text-muted">{{ t('Choose a section and subject to enter marks.') }}</p>
        <p v-else-if="!sheet.components.length" class="card text-muted">{{ t('This subject has no assessment components for this term yet. Ask the school administration to set them up.') }}</p>

        <template v-else>
            <p v-if="!sheet.canEdit" class="mb-3 rounded-lg bg-warn-soft px-4 py-3 text-sm text-warn">
                {{ currentTerm?.marks_open ? t('You can view these marks but not change them.') : t('Mark entry is closed for this term.') }}
            </p>
            <p v-else class="mb-3 text-sm text-muted">{{ t('Type a mark, leave empty if not marked yet, or type غ for absent.') }}</p>

            <div class="card overflow-x-auto p-0">
                <table class="w-full text-sm">
                    <thead class="border-b border-line text-muted">
                        <tr>
                            <th class="px-3 py-3 text-start font-medium">{{ t('Name') }}</th>
                            <th v-for="c in sheet.components" :key="c.id" class="px-2 py-3 text-start font-medium">
                                {{ c.name }}<div class="text-xs font-normal">/{{ c.max_score }} · {{ c.weight }}٪</div>
                            </th>
                            <th class="px-3 py-3 text-start font-medium">{{ t('Total') }}</th>
                            <th class="px-3 py-3 text-start font-medium">{{ t('Grade') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.student_id" class="border-b border-line last:border-0">
                            <td class="px-3 py-2 font-medium">{{ row.name }}</td>
                            <td v-for="c in sheet.components" :key="c.id" class="px-2 py-2">
                                <input
                                    v-model="row.inputs[c.id]"
                                    class="input w-20 text-center"
                                    :class="invalid(c, row.inputs[c.id]) ? 'border-danger' : ''"
                                    :disabled="!sheet.canEdit"
                                    inputmode="decimal"
                                    :aria-label="`${row.name} — ${c.name}`"
                                >
                            </td>
                            <td class="px-3 py-2 tabular-nums">{{ result(row) ? `${result(row).percent}٪` : '—' }}</td>
                            <td class="px-3 py-2" :class="result(row)?.passed === false ? 'text-danger' : ''">{{ result(row)?.grade ?? '' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-if="Object.keys(page.props.errors ?? {}).length" class="mt-3 text-sm text-danger" role="alert">{{ Object.values(page.props.errors)[0] }}</p>
            <button v-if="sheet.canEdit" type="button" class="btn-primary mt-4" :disabled="form.processing || hasInvalid" @click="save">{{ t('Save marks') }}</button>
        </template>
    </AppLayout>
</template>
