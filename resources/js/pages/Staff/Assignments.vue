<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import TabNav from '../../components/TabNav.vue';
import FieldError from '../../components/FieldError.vue';
import { useT } from '../../lib/i18n';
import { staffTabs } from '../../lib/tabs';

const props = defineProps({ sections: Array, sectionId: Number, subjects: Array, teachers: Array, assignments: Array, load: Object });
const t = useT();

const assignment = (subjectId) => props.assignments.find((a) => a.subject_id === subjectId);
const save = (subjectId, staffId, isHomeroom) => router.post(`/staff/assignments/${props.sectionId}`, {
    subject_id: subjectId, staff_member_id: staffId || null, is_homeroom: !!isHomeroom,
}, { preserveScroll: true });
const changeTeacher = (subjectId, event) => save(subjectId, event.target.value, assignment(subjectId)?.is_homeroom);
const makeHomeroom = (subjectId) => save(subjectId, assignment(subjectId).staff_member_id, true);
const changeSection = (event) => router.get('/staff/assignments', { section: event.target.value });
</script>

<template>
    <AppLayout :title="t('Staff')">
        <TabNav :tabs="staffTabs" />

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <label class="text-sm text-muted" for="section">{{ t('Section') }}</label>
            <select id="section" class="input max-w-xs" :value="sectionId" @change="changeSection">
                <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.label }}</option>
            </select>
        </div>
        <p v-if="!sections.length" class="card text-muted">{{ t('Add sections for the current year first.') }}</p>
        <FieldError :message="$page.props.errors?.staff_member_id" />

        <div v-if="sectionId" class="card overflow-x-auto p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-line text-muted">
                    <tr>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Subject') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Teacher') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Homeroom teacher') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in subjects" :key="s.id" class="border-b border-line last:border-0">
                        <td class="px-4 py-2 font-medium">{{ s.name }}</td>
                        <td class="px-4 py-2">
                            <select class="input max-w-xs" :value="assignment(s.id)?.staff_member_id ?? ''" :aria-label="t('Teacher')" @change="changeTeacher(s.id, $event)">
                                <option value="">{{ t('Not assigned') }}</option>
                                <option v-for="tch in teachers" :key="tch.id" :value="tch.id">{{ tch.name }} ({{ t(':count classes', { count: load[tch.id] ?? 0 }) }})</option>
                            </select>
                        </td>
                        <td class="px-4 py-2">
                            <input
                                type="radio" name="homeroom" :checked="assignment(s.id)?.is_homeroom" :disabled="!assignment(s.id)"
                                :aria-label="t('Homeroom teacher')" @change="makeHomeroom(s.id)"
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
