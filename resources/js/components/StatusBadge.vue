<script setup>
import { computed } from 'vue';

const props = defineProps({ kind: { type: String, required: true }, label: { type: String, required: true } });

const classes = computed(() => ({
    present: 'bg-accent-soft text-accent',
    absent: 'bg-danger-soft text-danger',
    late: 'bg-warn-soft text-warn',
    excused: 'bg-surface text-muted',
}[props.kind] ?? 'bg-surface text-muted'));
</script>

<template>
    <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="classes">{{ label }}</span>
</template>
