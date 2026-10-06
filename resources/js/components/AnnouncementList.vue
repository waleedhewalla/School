<script setup>
import { usePage } from '@inertiajs/vue3';
import { formatDate, useT } from '../lib/i18n';

defineProps({ items: { type: Array, required: true }, deletable: { type: Boolean, default: false } });
defineEmits(['delete']);
const t = useT();
const page = usePage();
</script>

<template>
    <ul class="divide-y divide-line">
        <li v-for="a in items" :key="a.id" class="py-3">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="font-semibold">{{ a.title }}</h3>
                <span class="rounded-full bg-surface px-2 py-0.5 text-xs text-muted">{{ a.section ?? t(`audience.${a.audience}`) }}</span>
                <span class="ms-auto text-xs text-muted">{{ formatDate(a.published_at, page.props.locale) }}<template v-if="a.author"> · {{ a.author }}</template></span>
                <button v-if="deletable" type="button" class="text-xs text-danger" @click="$emit('delete', a)">{{ t('Delete') }}</button>
            </div>
            <p class="mt-1 whitespace-pre-line text-sm">{{ a.body }}</p>
        </li>
        <li v-if="!items.length" class="py-3 text-sm text-muted">{{ t('No announcements yet.') }}</li>
    </ul>
</template>
