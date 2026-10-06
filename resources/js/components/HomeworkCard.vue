<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { formatDate, useT } from '../lib/i18n';

const props = defineProps({ homework: { type: Object, required: true }, showSection: Boolean });
const t = useT();
const page = usePage();
const overdue = computed(() => props.homework.due_on < new Date().toISOString().slice(0, 10));
</script>

<template>
    <article class="card">
        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded bg-accent-soft px-2 py-0.5 text-xs font-semibold text-accent">{{ homework.subject }}</span>
            <span v-if="showSection" class="text-xs text-muted">{{ homework.section }}</span>
            <span class="ms-auto text-sm" :class="overdue ? 'text-muted' : 'font-semibold'">{{ t('Due :date', { date: formatDate(homework.due_on, page.props.locale) }) }}</span>
        </div>
        <h3 class="mt-2 font-semibold">{{ homework.title }}</h3>
        <p v-if="homework.body" class="mt-1 whitespace-pre-line text-sm">{{ homework.body }}</p>
        <div class="mt-2 flex flex-wrap items-center gap-3 text-sm">
            <a v-if="homework.attachment" :href="homework.attachment.url" class="text-accent hover:underline" dir="auto">📎 {{ homework.attachment.name }}</a>
            <span v-if="homework.author" class="text-muted">{{ homework.author }}</span>
            <span class="ms-auto"><slot /></span>
        </div>
    </article>
</template>
