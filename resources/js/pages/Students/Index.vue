<script setup>
import { reactive, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ students: Object, sections: Array, filters: Object });
const t = useT();

const filters = reactive({ search: props.filters.search ?? '', section_id: props.filters.section_id ?? '' });

let timer;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/students', Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '')), {
            preserveState: true, replace: true,
        });
    }, 300);
});

const classLabel = (student) => {
    const e = student.current_enrollment;
    if (!e) return '—';
    return [e.grade_level?.name, e.section?.name].filter(Boolean).join(' / ');
};
</script>

<template>
    <AppLayout :title="t('Students')">
        <div class="mb-4 flex flex-wrap gap-3">
            <template v-if="$page.props.can['students.manage']">
                <Link href="/students/create" class="btn-primary">{{ t('Admit a student') }}</Link>
                <Link href="/students/import" class="btn-ghost">{{ t('Import from Noor') }}</Link>
            </template>
            <input v-model="filters.search" type="search" class="input max-w-xs" :placeholder="t('Search by name, number or ID')">
            <select v-model="filters.section_id" class="input max-w-xs">
                <option value="">{{ t('All sections') }}</option>
                <option v-for="section in sections" :key="section.id" :value="section.id">{{ section.label }}</option>
            </select>
        </div>

        <div class="card overflow-x-auto p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-line text-muted">
                    <tr>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Student number') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Name') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Class') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="student in students.data" :key="student.id" class="border-b border-line last:border-0 hover:bg-surface">
                        <td class="px-4 py-3 tabular-nums" dir="ltr">{{ student.student_number }}</td>
                        <td class="px-4 py-3">
                            <Link :href="`/students/${student.id}`" class="font-medium text-accent hover:underline">{{ student.name }}</Link>
                        </td>
                        <td class="px-4 py-3">{{ classLabel(student) }}</td>
                        <td class="px-4 py-3 text-muted">{{ t(`status.${student.status}`) }}</td>
                    </tr>
                    <tr v-if="!students.data.length">
                        <td colspan="4" class="px-4 py-8 text-center text-muted">{{ t('No students found.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="students.meta.last_page > 1" class="mt-4 flex flex-wrap gap-1">
            <template v-for="link in students.meta.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded-lg px-3 py-1.5 text-sm"
                    :class="link.active ? 'bg-accent text-accent-ink' : 'border border-line'"
                    preserve-scroll
                ><span v-html="link.label" /></Link>
            </template>
        </div>
    </AppLayout>
</template>
