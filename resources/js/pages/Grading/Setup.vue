<script setup>
import { reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ terms: Array, grades: Array, filters: Object, subjects: Array });
const t = useT();

const filters = reactive({ term_id: props.filters.term_id, grade_level_id: props.filters.grade_level_id ?? '' });
watch(filters, () => router.get('/grading', filters, { replace: true }));

// One editable copy per subject.
const drafts = reactive(Object.fromEntries(props.subjects.map((s) => [s.id, s.components.map((c) => ({ ...c }))])));
const total = (id) => drafts[id].reduce((sum, c) => sum + Number(c.weight || 0), 0);
const add = (id) => drafts[id].push({ id: null, name_ar: '', name_en: '', max_score: 10, weight: 0 });
const starter = (id) => {
    drafts[id].splice(0, drafts[id].length,
        { id: null, name_ar: 'المشاركة والواجبات', name_en: 'Participation & homework', max_score: 10, weight: 20 },
        { id: null, name_ar: 'الاختبارات القصيرة', name_en: 'Quizzes', max_score: 20, weight: 20 },
        { id: null, name_ar: 'المشروع', name_en: 'Project', max_score: 10, weight: 10 },
        { id: null, name_ar: 'الاختبار النهائي', name_en: 'Final exam', max_score: 50, weight: 50 });
};
const save = (id) => router.post('/grading', { ...filters, subject_id: id, components: drafts[id] }, { preserveScroll: true });
const copy = (id) => router.post('/grading/copy', { ...filters, subject_id: id }, { preserveScroll: true });
</script>

<template>
    <AppLayout :title="t('Assessment setup')">
        <div class="mb-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="label" for="term">{{ t('Term') }}</label>
                <select id="term" v-model="filters.term_id" class="input">
                    <option v-for="term in terms" :key="term.id" :value="term.id">{{ term.name }}</option>
                </select>
            </div>
            <div>
                <label class="label" for="grade">{{ t('Grade') }}</label>
                <select id="grade" v-model="filters.grade_level_id" class="input min-w-56">
                    <option value="">{{ t('Choose a grade') }}</option>
                    <option v-for="g in grades" :key="g.id" :value="g.id">{{ g.name }}</option>
                </select>
            </div>
        </div>

        <p v-if="!terms.length" class="card text-muted">{{ t('Add terms to the current academic year first.') }}</p>
        <p v-else-if="!filters.grade_level_id" class="card text-muted">{{ t('Choose a grade to set how each subject is assessed.') }}</p>

        <div v-else class="space-y-4">
            <section v-for="s in subjects" :key="s.id" class="card">
                <div class="mb-3 flex flex-wrap items-center gap-3">
                    <h2 class="font-semibold">{{ s.name }}</h2>
                    <span class="text-sm" :class="total(s.id) === 100 ? 'text-accent' : 'text-danger'">{{ t('Total weight') }}: {{ total(s.id) }}٪</span>
                    <div class="ms-auto flex flex-wrap gap-2">
                        <button v-if="!drafts[s.id].length" type="button" class="btn-ghost" @click="starter(s.id)">{{ t('Use a typical structure') }}</button>
                        <button v-if="s.components.length" type="button" class="btn-ghost" @click="copy(s.id)">{{ t('Copy to subjects not set up yet') }}</button>
                    </div>
                </div>

                <table v-if="drafts[s.id].length" class="mb-3 w-full text-sm">
                    <thead class="text-muted">
                        <tr>
                            <th class="pb-1 text-start font-medium">{{ t('Component') }}</th>
                            <th class="pb-1 text-start font-medium">{{ t('Name (English)') }}</th>
                            <th class="pb-1 text-start font-medium">{{ t('Out of') }}</th>
                            <th class="pb-1 text-start font-medium">{{ t('Weight %') }}</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(c, i) in drafts[s.id]" :key="i">
                            <td class="py-1 pe-2"><input v-model="c.name_ar" class="input" required></td>
                            <td class="py-1 pe-2"><input v-model="c.name_en" class="input" dir="ltr"></td>
                            <td class="py-1 pe-2"><input v-model.number="c.max_score" type="number" min="1" class="input w-24" dir="ltr"></td>
                            <td class="py-1 pe-2"><input v-model.number="c.weight" type="number" min="0" max="100" class="input w-24" dir="ltr"></td>
                            <td class="py-1"><button type="button" class="text-sm text-danger" @click="drafts[s.id].splice(i, 1)">{{ t('Remove') }}</button></td>
                        </tr>
                    </tbody>
                </table>

                <div class="flex gap-2">
                    <button type="button" class="btn-ghost" @click="add(s.id)">{{ t('Add component') }}</button>
                    <button v-if="drafts[s.id].length" type="button" class="btn-primary" :disabled="total(s.id) !== 100" @click="save(s.id)">{{ t('Save') }}</button>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
