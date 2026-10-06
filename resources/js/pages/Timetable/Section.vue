<script setup>
import { ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import TimetableGrid from '../../components/TimetableGrid.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ sections: Array, sectionId: Number, grid: Object, assignments: Array, canEdit: Boolean });
const t = useT();
const page = usePage();

const sectionId = ref(props.sectionId ?? '');
watch(sectionId, (id) => router.get('/timetable', id ? { section_id: id } : {}, { replace: true }));

const place = (payload) => router.post(`/timetable/${props.sectionId}`, payload, { preserveScroll: true, preserveState: true });
</script>

<template>
    <AppLayout :title="t('Timetable')">
        <div class="mb-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="label" for="section">{{ t('Section') }}</label>
                <select id="section" v-model="sectionId" class="input min-w-56">
                    <option value="">{{ t('Choose a section') }}</option>
                    <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.label }}</option>
                </select>
            </div>
        </div>

        <div v-if="Object.keys(page.props.errors ?? {}).length" class="mb-4 rounded-lg bg-danger-soft px-4 py-3 text-sm text-danger" role="alert">
            {{ Object.values(page.props.errors)[0] }}
        </div>

        <p v-if="!grid" class="card text-muted">{{ t('Choose a section to see its timetable.') }}</p>
        <template v-else>
            <p v-if="canEdit && !assignments.length" class="card mb-4 text-muted">{{ t('Assign teachers to this section’s subjects first; then you can place their lessons.') }}</p>
            <TimetableGrid :grid="grid" :editable="canEdit" :assignments="assignments" @place="place" />
        </template>
    </AppLayout>
</template>
