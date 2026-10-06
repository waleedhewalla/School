<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import { useT, formatDateTime } from '../../lib/i18n';

defineProps({ children: Array });
const t = useT();
const when = (iso) => formatDateTime(iso, usePage().props.locale);
const badge = { open: 'bg-accent-soft text-accent', upcoming: 'bg-surface text-muted', done: 'bg-surface text-ink', missed: 'bg-danger-soft text-danger' };
</script>

<template>
    <AppLayout :title="t('Quizzes')">
        <p v-if="!children.length" class="card text-muted">{{ t('No children are linked to your account.') }}</p>
        <section v-for="child in children" :key="child.id" class="mb-6">
            <h2 v-if="!child.is_me || children.length > 1" class="mb-3 text-lg font-semibold">{{ child.name }}</h2>
            <div class="space-y-3">
                <article v-for="q in child.quizzes" :key="q.id" class="card flex flex-wrap items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold">{{ q.title }}</div>
                        <div class="text-sm text-muted">{{ q.subject }} · {{ t(':count questions', { count: q.questions }) }}<template v-if="q.time_limit"> · {{ t(':n minutes', { n: q.time_limit }) }}</template></div>
                        <div class="text-xs text-muted">{{ when(q.opens_at) }} – {{ when(q.closes_at) }}</div>
                    </div>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="badge[q.state]">{{ t(`quiz_state.${q.state}`) }}</span>
                    <span v-if="q.score !== null" class="font-semibold tabular-nums">{{ q.score }} / {{ q.max }}</span>
                    <Link v-if="child.is_me && q.state === 'open'" :href="`/my/quizzes/${q.id}`" class="btn-primary">{{ t('Start') }}</Link>
                </article>
                <p v-if="!child.quizzes.length" class="card text-center text-muted">{{ t('No quizzes yet.') }}</p>
            </div>
        </section>
    </AppLayout>
</template>
