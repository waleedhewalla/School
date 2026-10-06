<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { useT } from '../lib/i18n';

const t = useT();
const page = usePage();
const tabs = [
    { href: '/settings/school', label: 'School profile', permission: 'school.manage' },
    { href: '/settings/periods', label: 'Bell schedule', permission: 'timetable.manage' },
    { href: '/settings/grading', label: 'Grading scale', permission: 'grades.manage' },
    { href: '/settings/notifications', label: 'Guardian notifications', permission: 'school.manage' },
];
</script>

<template>
    <nav class="mb-5 flex gap-1 border-b border-line text-sm">
        <template v-for="tab in tabs" :key="tab.href">
            <Link
                v-if="page.props.can[tab.permission]"
                :href="tab.href"
                class="-mb-px border-b-2 px-3 py-2"
                :class="page.url.startsWith(tab.href) ? 'border-accent font-semibold text-accent' : 'border-transparent text-muted hover:text-ink'"
            >{{ t(tab.label) }}</Link>
        </template>
    </nav>
</template>
