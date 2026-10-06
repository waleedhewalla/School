<script setup>
import { useT } from '../lib/i18n';

const props = defineProps({
    grid: { type: Object, required: true },
    editable: { type: Boolean, default: false },
    assignments: { type: Array, default: () => [] },
    showSection: { type: Boolean, default: false },
});
const emit = defineEmits(['place']);
const t = useT();

const cell = (day, period) => props.grid.cells[`${day}-${period.id}`] ?? null;
const onChange = (day, period, event) => emit('place', { day, period_id: period.id, teaching_assignment_id: event.target.value || null, room: cell(day, period)?.room ?? null });
const onRoom = (day, period, event) => {
    const current = cell(day, period);
    if (current && (current.room ?? '') !== event.target.value) {
        emit('place', { day, period_id: period.id, teaching_assignment_id: current.teaching_assignment_id, room: event.target.value || null });
    }
};
</script>

<template>
    <div class="card overflow-x-auto p-0">
        <table class="w-full min-w-[720px] border-collapse text-sm">
            <thead>
                <tr class="border-b border-line text-muted">
                    <th class="w-36 px-3 py-3 text-start font-medium">{{ t('Period') }}</th>
                    <th v-for="d in grid.days" :key="d.day" class="px-3 py-3 text-start font-medium">{{ d.name }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="p in grid.periods" :key="p.id" class="border-b border-line last:border-0" :class="p.is_break ? 'bg-surface' : ''">
                    <td class="px-3 py-2 align-top">
                        <div class="font-medium">{{ p.name }}</div>
                        <div class="text-xs text-muted tabular-nums" dir="ltr">{{ p.starts_at }}–{{ p.ends_at }}</div>
                    </td>
                    <template v-if="p.is_break">
                        <td :colspan="grid.days.length" class="px-3 py-2 text-center text-muted">{{ p.name }}</td>
                    </template>
                    <template v-else>
                        <td v-for="d in grid.days" :key="d.day" class="px-2 py-2 align-top">
                            <template v-if="editable">
                                <select class="input py-1.5 text-xs" :value="cell(d.day, p)?.teaching_assignment_id ?? ''" @change="onChange(d.day, p, $event)">
                                    <option value="">—</option>
                                    <option v-for="a in assignments" :key="a.id" :value="a.id">{{ a.label }}</option>
                                </select>
                                <input
                                    v-if="cell(d.day, p)"
                                    class="input mt-1 py-1 text-xs"
                                    :placeholder="t('Room')"
                                    :value="cell(d.day, p).room ?? ''"
                                    maxlength="30"
                                    @change="onRoom(d.day, p, $event)"
                                >
                            </template>
                            <div v-else-if="cell(d.day, p)" class="rounded-lg bg-accent-soft px-2 py-1.5">
                                <div class="font-semibold text-accent">{{ cell(d.day, p).subject }}</div>
                                <div class="text-xs text-muted">{{ showSection ? cell(d.day, p).section : cell(d.day, p).teacher }}<template v-if="cell(d.day, p).room"> · {{ cell(d.day, p).room }}</template></div>
                            </div>
                        </td>
                    </template>
                </tr>
            </tbody>
        </table>
    </div>
</template>
