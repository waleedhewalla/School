<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import FieldError from '../../components/FieldError.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ quiz: Object, students: Array, stats: Array, components: Array });
const t = useT();
const done = computed(() => props.students.filter((s) => s.status === 'submitted'));
const average = computed(() => (done.value.length ? (done.value.reduce((a, s) => a + s.score, 0) / done.value.length).toFixed(1) : '—'));
const marks = useForm({ assessment_component_id: props.components[0]?.id ?? '' });
const send = () => marks.post(`/quizzes/${props.quiz.id}/marks`, { preserveScroll: true });
</script>

<template>
    <AppLayout :title="t('Results: :title', { title: quiz.title })">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <Link :href="`/quizzes/${quiz.id}/edit`" class="text-sm text-muted hover:text-ink"><span class="rtl:hidden">←</span><span class="ltr:hidden">→</span> {{ t('Edit quiz') }}</Link>
            <span class="text-sm text-muted">{{ quiz.section }} · {{ quiz.subject }}</span>
        </div>

        <div class="mb-4 grid gap-4 sm:grid-cols-3">
            <div class="card"><div class="text-sm text-muted">{{ t('Submitted') }}</div><div class="text-2xl font-semibold tabular-nums">{{ done.length }} / {{ students.length }}</div></div>
            <div class="card"><div class="text-sm text-muted">{{ t('Average') }}</div><div class="text-2xl font-semibold tabular-nums">{{ average }} <span class="text-base font-normal text-muted">/ {{ quiz.max }}</span></div></div>
            <form v-if="components.length" class="card" @submit.prevent="send">
                <label class="label" for="component">{{ t('Copy scores to the mark book') }}</label>
                <div class="flex gap-2">
                    <select id="component" v-model="marks.assessment_component_id" class="input"><option v-for="c in components" :key="c.id" :value="c.id">{{ c.label }}</option></select>
                    <button class="btn-primary" :disabled="marks.processing || !done.length">{{ t('Copy') }}</button>
                </div>
                <FieldError :message="Object.values(marks.errors)[0]" />
            </form>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="card overflow-x-auto p-0">
                <table class="w-full text-sm">
                    <thead class="border-b border-line text-muted"><tr><th class="px-4 py-3 text-start font-medium">{{ t('Student') }}</th><th class="px-4 py-3 text-start font-medium">{{ t('Score') }}</th></tr></thead>
                    <tbody>
                        <tr v-for="s in students" :key="s.id" class="border-b border-line last:border-0">
                            <td class="px-4 py-2">{{ s.name }}</td>
                            <td class="px-4 py-2 tabular-nums">
                                <template v-if="s.status === 'submitted'">{{ s.score }} / {{ quiz.max }}</template>
                                <span v-else class="text-muted">{{ t(`attempt.${s.status}`) }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <section class="card">
                <h2 class="mb-3 font-semibold">{{ t('Questions answered correctly') }}</h2>
                <ul class="space-y-3 text-sm">
                    <li v-for="(q, i) in stats" :key="q.id">
                        <div class="flex gap-2"><span class="tabular-nums text-muted">{{ i + 1 }}.</span><span class="min-w-0 flex-1 truncate">{{ q.body }}</span><span class="tabular-nums font-semibold">{{ q.correct_rate === null ? '—' : `${q.correct_rate}%` }}</span></div>
                        <div class="mt-1 h-2 rounded-full bg-surface" role="presentation"><div class="h-2 rounded-full" :class="q.correct_rate >= 50 ? 'bg-accent' : 'bg-danger'" :style="{ width: `${q.correct_rate ?? 0}%` }" /></div>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
