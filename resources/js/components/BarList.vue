<script setup>
import { computed, ref } from 'vue';

/**
 * Horizontal bars for one measure (magnitude): one hue, thin marks with a
 * rounded data end, labels and values in text colour, a tooltip on hover
 * or focus. The label + value list doubles as the table view.
 *
 * rows: [{ label, value, display?, hint? }]  max: optional scale maximum
 */
const props = defineProps({
    rows: { type: Array, required: true },
    max: { type: Number, default: null },
    label: { type: String, required: true },
});
const top = computed(() => props.max ?? Math.max(1, ...props.rows.map((r) => Number(r.value) || 0)));
const width = (v) => `${Math.max(0, Math.min(100, (Number(v) || 0) / top.value * 100))}%`;
const active = ref(null);
</script>

<template>
    <ul class="space-y-0.5" :aria-label="label">
        <li
            v-for="(r, i) in rows" :key="i"
            class="group relative grid grid-cols-[minmax(6rem,10rem)_1fr_auto] items-center gap-3 rounded px-1 py-1 text-sm hover:bg-surface focus:bg-surface focus:outline-none"
            tabindex="0" @mouseenter="active = i" @mouseleave="active = null" @focus="active = i" @blur="active = null"
        >
            <span class="truncate text-muted" :title="r.label">{{ r.label }}</span>
            <span class="h-2.5 rounded-e bg-surface" aria-hidden="true">
                <span class="block h-2.5 rounded-e bg-chart" :style="{ width: width(r.value) }" />
            </span>
            <span class="min-w-12 text-end font-semibold tabular-nums text-ink">{{ r.display ?? r.value }}</span>
            <span
                v-if="active === i && r.hint" role="tooltip"
                class="pointer-events-none absolute -top-8 start-40 z-10 whitespace-nowrap rounded-md border border-line bg-card px-2 py-1 text-xs text-ink shadow-sm"
            >{{ r.hint }}</span>
        </li>
    </ul>
</template>
