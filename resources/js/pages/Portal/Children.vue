<script setup>
import { usePage } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import AnnouncementList from '../../components/AnnouncementList.vue';
import { formatDate, useT } from '../../lib/i18n';

defineProps({ children: Array, announcements: Array });
const t = useT();
const page = usePage();
</script>

<template>
    <AppLayout :title="t('My children')">
        <section v-if="announcements.length" class="card mb-4">
            <h2 class="font-semibold">{{ t('School announcements') }}</h2>
            <AnnouncementList :items="announcements" />
        </section>

        <p v-if="!children.length" class="card text-muted">{{ t('No children are linked to your account yet. Please contact the school.') }}</p>

        <div class="grid gap-4 md:grid-cols-2">
            <section v-for="child in children" :key="child.id" class="card">
                <h2 class="text-lg font-semibold">{{ child.name }}</h2>
                <p class="mb-4 text-sm text-muted">{{ child.class || '—' }} · <span dir="ltr">{{ child.student_number }}</span></p>

                <template v-if="child.results.length">
                    <h3 class="mb-2 text-sm font-semibold">{{ t('Results') }}</h3>
                    <ul class="mb-4 space-y-1 text-sm">
                        <li v-for="r in child.results" :key="r.term" class="flex items-center justify-between gap-2">
                            <span>{{ r.term }}<template v-if="r.average !== null"> · {{ r.average.toFixed(2) }}٪ · {{ r.grade }}</template></span>
                            <a :href="r.url" target="_blank" class="text-accent">{{ t('Report card') }}</a>
                        </li>
                    </ul>
                </template>

                <h3 class="mb-2 text-sm font-semibold">{{ t('Recent attendance') }}</h3>
                <ul class="space-y-1 text-sm">
                    <li v-for="(r, i) in child.attendance" :key="i" class="flex items-center justify-between gap-2">
                        <span>{{ formatDate(r.date, page.props.locale) }}<span v-if="r.period" class="text-muted"> · {{ t('Period :n', { n: r.period }) }}</span></span>
                        <StatusBadge :kind="r.kind" :label="r.name" />
                    </li>
                    <li v-if="!child.attendance.length" class="text-muted">{{ t('No attendance recorded yet.') }}</li>
                </ul>

                <template v-if="child.behaviour.score !== null">
                    <h3 class="mb-2 mt-4 flex items-center gap-2 text-sm font-semibold">
                        {{ t('Behaviour') }}
                        <span class="ms-auto rounded bg-surface px-2 tabular-nums">{{ t('Score :n', { n: child.behaviour.score }) }}</span>
                    </h3>
                    <ul class="space-y-1 text-sm">
                        <li v-for="(b, i) in child.behaviour.recent" :key="i" class="flex items-center justify-between gap-2">
                            <span>{{ formatDate(b.date, page.props.locale) }} · {{ b.category }}</span>
                            <span class="font-semibold tabular-nums" :class="b.points > 0 ? 'text-accent' : 'text-danger'" dir="ltr">{{ b.points > 0 ? '+' : '' }}{{ b.points }}</span>
                        </li>
                    </ul>
                </template>
            </section>
        </div>
    </AppLayout>
</template>
