<script setup>
import { computed, reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ terms: Array, sections: Array, filters: Object, results: Object, canManage: Boolean });
const t = useT();

const filters = reactive({ term_id: props.filters.term_id, section_id: props.filters.section_id ?? '' });
watch(filters, () => router.get('/results', filters, { replace: true }));

const term = computed(() => props.terms.find((x) => x.id === filters.term_id));
const update = (data) => router.patch(`/terms/${filters.term_id}`, data, { preserveScroll: true });
const fmt = (n) => (n === null || n === undefined ? '—' : `${Number(n).toFixed(2)}٪`);
</script>

<template>
    <AppLayout :title="t('Results')">
        <div class="mb-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="label" for="term">{{ t('Term') }}</label>
                <select id="term" v-model="filters.term_id" class="input">
                    <option v-for="x in terms" :key="x.id" :value="x.id">{{ x.name }}</option>
                </select>
            </div>
            <div>
                <label class="label" for="section">{{ t('Section') }}</label>
                <select id="section" v-model="filters.section_id" class="input min-w-56">
                    <option value="">{{ t('Choose a section') }}</option>
                    <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.label }}</option>
                </select>
            </div>
            <div v-if="canManage && term" class="ms-auto flex flex-wrap gap-2">
                <button type="button" class="btn-ghost" @click="update({ marks_open: !term.marks_open })">
                    {{ term.marks_open ? t('Close mark entry') : t('Reopen mark entry') }}
                </button>
                <button type="button" :class="term.published ? 'btn-ghost' : 'btn-primary'" @click="update({ published: !term.published })">
                    {{ term.published ? t('Unpublish results') : t('Publish results to guardians') }}
                </button>
            </div>
        </div>

        <p v-if="term" class="mb-3 text-sm text-muted">
            {{ term.marks_open ? t('Mark entry is open.') : t('Mark entry is closed.') }}
            {{ term.published ? t('Results are visible to guardians.') : t('Results are not visible to guardians yet.') }}
        </p>

        <p v-if="!results" class="card text-muted">{{ t('Choose a section to see its results.') }}</p>
        <template v-else>
            <a :href="`/report-cards/${filters.section_id}/${filters.term_id}`" target="_blank" class="btn-ghost mb-3">{{ t('Print report cards for the section') }}</a>

            <div class="card overflow-x-auto p-0">
                <table class="w-full text-sm">
                    <thead class="border-b border-line text-muted">
                        <tr>
                            <th class="px-3 py-3 text-start font-medium">{{ t('Name') }}</th>
                            <th v-for="s in results.subjects" :key="s.id" class="px-3 py-3 text-start font-medium">{{ s.name }}</th>
                            <th class="px-3 py-3 text-start font-medium">{{ t('Average') }}</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="st in results.students" :key="st.student_id" class="border-b border-line last:border-0">
                            <td class="px-3 py-2 font-medium">{{ st.name }}</td>
                            <td v-for="s in results.subjects" :key="s.id" class="px-3 py-2 tabular-nums" :class="st.subjects[s.id].passed === false ? 'text-danger' : ''">
                                <template v-if="st.subjects[s.id].complete">{{ fmt(st.subjects[s.id].percent) }} <span class="text-xs text-muted">{{ st.subjects[s.id].grade }}</span></template>
                                <span v-else class="text-muted">{{ t('Incomplete') }}</span>
                            </td>
                            <td class="px-3 py-2 font-semibold tabular-nums">{{ fmt(st.average) }} <span class="text-xs font-normal text-muted">{{ st.average_grade }}</span></td>
                            <td class="px-3 py-2"><a :href="`/report-cards/${filters.section_id}/${filters.term_id}?student_id=${st.student_id}`" target="_blank" class="text-accent">{{ t('Report card') }}</a></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </AppLayout>
</template>
