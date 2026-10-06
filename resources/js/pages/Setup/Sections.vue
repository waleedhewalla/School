<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import TabNav from '../../components/TabNav.vue';
import FieldError from '../../components/FieldError.vue';
import { useT } from '../../lib/i18n';
import { setupTabs } from '../../lib/tabs';

const props = defineProps({ years: Array, yearId: Number, grades: Array, sections: Array, campuses: Array });
const t = useT();

const stages = computed(() => {
    const groups = [];
    for (const grade of props.grades) {
        let group = groups.find((g) => g.name === grade.stage);
        if (!group) groups.push(group = { name: grade.stage, grades: [] });
        group.grades.push({ ...grade, sections: props.sections.filter((s) => s.grade_level_id === grade.id) });
    }
    return groups;
});

const changeYear = (event) => router.get('/setup/sections', { year: event.target.value }, { preserveState: false });

const editing = ref(null); // section id, or `new-<gradeId>`
const form = useForm({ academic_year_id: props.yearId, grade_level_id: null, name: '', capacity: 30, campus_id: '' });
const start = (grade, section = null) => {
    editing.value = section ? section.id : `new-${grade.id}`;
    form.clearErrors();
    Object.assign(form, {
        academic_year_id: props.yearId, grade_level_id: grade.id,
        name: section?.name ?? '', capacity: section?.capacity ?? 30, campus_id: section?.campus_id ?? (props.campuses.length === 1 ? props.campuses[0].id : ''),
    });
};
const save = () => {
    form.transform((d) => ({ ...d, campus_id: d.campus_id || null, capacity: d.capacity || null }));
    const options = { preserveScroll: true, onSuccess: () => { editing.value = null; } };
    typeof editing.value === 'number' ? form.put(`/setup/sections/${editing.value}`, options) : form.post('/setup/sections', options);
};
const remove = (section) => confirm(t('Delete this section?')) && router.delete(`/setup/sections/${section.id}`, { preserveScroll: true });
</script>

<template>
    <AppLayout :title="t('School setup')">
        <TabNav :tabs="setupTabs" />

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <label class="text-sm text-muted" for="year">{{ t('Academic year') }}</label>
            <select id="year" class="input max-w-xs" :value="yearId" @change="changeYear">
                <option v-for="y in years" :key="y.id" :value="y.id">{{ y.name }}{{ y.is_current ? ` (${t('current')})` : '' }}</option>
            </select>
        </div>
        <p v-if="!years.length" class="card text-muted">{{ t('Add an academic year first.') }}</p>
        <FieldError :message="$page.props.errors?.section" />

        <section v-for="stage in stages" :key="stage.name" class="card mb-4">
            <h2 class="mb-3 font-semibold">{{ stage.name }}</h2>
            <div v-for="grade in stage.grades" :key="grade.id" class="border-b border-line py-3 last:border-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="w-48 text-sm font-medium">{{ grade.name }}</span>
                    <template v-for="s in grade.sections" :key="s.id">
                        <button
                            v-if="editing !== s.id" type="button"
                            class="rounded-lg border border-line px-3 py-1 text-sm hover:bg-surface"
                            :title="t('Edit')" @click="start(grade, s)"
                        >{{ s.name }} <span class="text-xs text-muted tabular-nums">{{ s.enrollments_count }}/{{ s.capacity ?? '∞' }}</span></button>
                    </template>
                    <button v-if="editing !== `new-${grade.id}`" type="button" class="rounded-lg px-2 py-1 text-sm text-accent hover:underline" @click="start(grade)">+ {{ t('Section') }}</button>
                </div>
                <form v-if="editing === `new-${grade.id}` || grade.sections.some((s) => s.id === editing)" class="mt-3 flex flex-wrap items-end gap-2" @submit.prevent="save">
                    <div>
                        <label class="label" :for="`n-${grade.id}`">{{ t('Name') }}</label>
                        <input :id="`n-${grade.id}`" v-model="form.name" class="input w-28" required maxlength="30">
                    </div>
                    <div>
                        <label class="label" :for="`c-${grade.id}`">{{ t('Capacity') }}</label>
                        <input :id="`c-${grade.id}`" v-model.number="form.capacity" type="number" min="1" max="200" class="input w-24">
                    </div>
                    <div v-if="campuses.length > 1">
                        <label class="label" :for="`k-${grade.id}`">{{ t('Campus') }}</label>
                        <select :id="`k-${grade.id}`" v-model="form.campus_id" class="input">
                            <option value="">—</option>
                            <option v-for="c in campuses" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <button class="btn-primary" :disabled="form.processing">{{ t('Save') }}</button>
                    <button type="button" class="btn-ghost" @click="editing = null">{{ t('Cancel') }}</button>
                    <button
                        v-if="typeof editing === 'number' && !grade.sections.find((s) => s.id === editing)?.enrollments_count"
                        type="button" class="ms-auto text-sm text-danger hover:underline"
                        @click="remove(grade.sections.find((s) => s.id === editing))"
                    >{{ t('Delete') }}</button>
                    <FieldError class="w-full" :message="form.errors.name || form.errors.capacity" />
                </form>
            </div>
        </section>
    </AppLayout>
</template>
