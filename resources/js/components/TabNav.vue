<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { useT } from '../lib/i18n';

// tabs: [{ href, label, permission? }]
defineProps({ tabs: { type: Array, required: true } });
const t = useT();
const page = usePage();
const active = (tab) => page.url === tab.href || page.url.startsWith(`${tab.href}?`) || (!tab.exact && page.url.startsWith(`${tab.href}/`));
</script>

<template>
    <nav class="mb-5 flex gap-1 overflow-x-auto border-b border-line text-sm">
        <template v-for="tab in tabs" :key="tab.href">
            <Link
                v-if="!tab.permission || page.props.can[tab.permission]"
                :href="tab.href"
                class="-mb-px whitespace-nowrap border-b-2 px-3 py-2"
                :class="active(tab) ? 'border-accent font-semibold text-accent' : 'border-transparent text-muted hover:text-ink'"
            >{{ t(tab.label) }}</Link>
        </template>
    </nav>
</template>
