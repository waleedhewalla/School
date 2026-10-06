<script setup>
import { computed, reactive, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ years: Array, filters: Object, sections: Array, students: Array, targetSections: [Array, Object] });
const t = useT();

const filters = reactive({ ...props.filters, section_id: props.filters.section_id ?? '' });
watch(filters, () => router.get('/promotions', filters, { replace: true }));

const targets = computed(() => (Array.isArray(props.targetSections) ? { promoted: [], repeated: [], has_next_grade: false } : props.targetSections));
const defaultOutcome = computed(() => (targets.value.has_next_grade ? 'promoted' : 'graduated'));

const form = useForm({
    from: props.filters.from,
    to: props.filters.to,
    decisions: props.students.map((s) => ({ student_id: s.student_id, name: s.name, outcome: defaultOutcome.value, section_id: '' })),
});

const optionsFor = (outcome) => (outcome === 'graduated' ? [] : targets.value[outcome] ?? []);
const applyToAll = (sectionId) => form.decisions.forEach((d) => { if (d.outcome === 'promoted') d.section_id = sectionId; });

const submit = () => {
    form.transform((data) => ({
        ...data,
        decisions: data.decisions.map(({ student_id, outcome, section_id }) => ({ student_id, outcome, section_id: outcome === 'graduated' ? null : section_id || null })),
    })).post('/promotions', { preserveScroll: true });
};
</script>

<template>
    <AppLayout :title="t('Year-end promotion')">
        <div class="mb-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="label" for="from">{{ t('From year') }}</label>
                <select id="from" v-model="filters.from" class="input">
                    <option v-for="y in years" :key="y.id" :value="y.id">{{ y.name }}</option>
                </select>
            </div>
            <div>
                <label class="label" for="to">{{ t('To year') }}</label>
                <select id="to" v-model="filters.to" class="input">
                    <option :value="null">—</option>
                    <option v-for="y in years" :key="y.id" :value="y.id">{{ y.name }}</option>
                </select>
            </div>
            <div>
                <label class="label" for="section">{{ t('Section') }}</label>
                <select id="section" v-model="filters.section_id" class="input min-w-56">
                    <option value="">{{ t('Choose a section') }}</option>
                    <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.label }}</option>
                </select>
            </div>
        </div>

        <p v-if="!filters.to" class="card text-muted">{{ t('Create next year’s academic year first, then choose it as the target.') }}</p>
        <p v-else-if="!filters.section_id" class="card text-muted">{{ t('Choose a section to promote its students.') }}</p>
        <p v-else-if="!students.length" class="card text-muted">{{ t('No active students in this section.') }}</p>

        <template v-else>
            <div v-if="targets.promoted.length" class="mb-3 flex flex-wrap items-center gap-2 text-sm">
                <span class="text-muted">{{ t('Put all promoted students in') }}:</span>
                <button v-for="s in targets.promoted" :key="s.id" type="button" class="btn-ghost" @click="applyToAll(s.id)">{{ s.name }}</button>
            </div>

            <div class="card overflow-x-auto p-0">
                <table class="w-full text-sm">
                    <thead class="border-b border-line text-muted">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium">{{ t('Name') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ t('Decision') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ t('Section next year') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(d, i) in form.decisions" :key="d.student_id" class="border-b border-line last:border-0">
                            <td class="px-4 py-2 font-medium">{{ d.name }}</td>
                            <td class="px-4 py-2">
                                <select v-model="d.outcome" class="input" @change="d.section_id = ''">
                                    <option v-if="targets.has_next_grade" value="promoted">{{ t('enrollment.promoted') }}</option>
                                    <option value="repeated">{{ t('enrollment.repeated') }}</option>
                                    <option value="graduated">{{ t('enrollment.graduated') }}</option>
                                </select>
                                <p v-if="form.errors[`decisions.${i}.outcome`]" class="mt-1 text-danger">{{ form.errors[`decisions.${i}.outcome`] }}</p>
                            </td>
                            <td class="px-4 py-2">
                                <select v-if="d.outcome !== 'graduated'" v-model="d.section_id" class="input">
                                    <option value="">{{ t('Assign later') }}</option>
                                    <option v-for="s in optionsFor(d.outcome)" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                                <span v-else class="text-muted">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-if="form.errors.to || form.errors.section_id" class="mt-3 text-sm text-danger">{{ form.errors.to || form.errors.section_id }}</p>
            <button type="button" class="btn-primary mt-4" :disabled="form.processing" @click="submit">{{ t('Save promotion') }}</button>
        </template>
    </AppLayout>
</template>
