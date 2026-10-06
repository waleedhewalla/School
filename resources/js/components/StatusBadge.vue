<script setup>
import { computed } from 'vue';

const props = defineProps({ kind: { type: String, required: true }, label: { type: String, required: true } });

const classes = computed(() => ({
    present: 'bg-accent-soft text-accent',
    absent: 'bg-danger-soft text-danger',
    late: 'bg-warn-soft text-warn',
    excused: 'bg-surface text-muted',
    // Admission applications and documents
    under_review: 'bg-surface text-ink',
    assessment_scheduled: 'bg-warn-soft text-warn',
    assessed: 'bg-warn-soft text-warn',
    waitlisted: 'bg-warn-soft text-warn',
    pending: 'bg-warn-soft text-warn',
    offered: 'bg-accent-soft text-accent',
    accepted: 'bg-accent-soft text-accent',
    enrolled: 'bg-accent-soft text-accent',
    rejected: 'bg-danger-soft text-danger',
}[props.kind] ?? 'bg-surface text-muted'));
</script>

<template>
    <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="classes">{{ label }}</span>
</template>
