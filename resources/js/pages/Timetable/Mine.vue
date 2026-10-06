<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import TimetableGrid from '../../components/TimetableGrid.vue';
import { useT } from '../../lib/i18n';

defineProps({ grid: Object });
const t = useT();
</script>

<template>
    <AppLayout :title="t('My timetable')">
        <p v-if="!Object.keys(grid.cells).length" class="card mb-4 text-muted">{{ t('No lessons are scheduled for you yet.') }}</p>
        <TimetableGrid :grid="grid" show-section />
    </AppLayout>
</template>
