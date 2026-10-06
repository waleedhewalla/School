<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import FieldError from '../../components/FieldError.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ quizzes: Array, choices: Array, subjects: Array });
const t = useT();

const adding = ref(false);
const local = (d) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
const form = useForm({
    section_id: props.choices[0]?.section_id ?? '', subject_id: '', title: '', instructions: '',
    opens_at: local(new Date()), closes_at: local(new Date(Date.now() + 7 * 86400000)), time_limit_minutes: 20,
});
const choice = computed(() => props.choices.find((c) => c.section_id === Number(form.section_id)));
const subjectOptions = computed(() => (choice.value?.subjects ? props.subjects.filter((s) => choice.value.subjects.includes(s.id)) : props.subjects));
const save = () => form.transform((d) => ({ ...d, time_limit_minutes: d.time_limit_minutes || null })).post('/quizzes');
const when = (iso) => new Date(iso).toLocaleString(undefined, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
</script>

<template>
    <AppLayout :title="t('Quizzes')">
        <button v-if="choices.length" type="button" class="btn-primary mb-4" @click="adding = !adding">{{ t('New quiz') }}</button>

        <section v-if="adding" class="card mb-4">
            <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="save">
                <div>
                    <label class="label" for="section">{{ t('Section') }}</label>
                    <select id="section" v-model="form.section_id" class="input" required @change="form.subject_id = ''"><option v-for="c in choices" :key="c.section_id" :value="c.section_id">{{ c.label }}</option></select>
                </div>
                <div>
                    <label class="label" for="subject">{{ t('Subject') }}</label>
                    <select id="subject" v-model="form.subject_id" class="input" required><option value="" disabled>—</option><option v-for="s in subjectOptions" :key="s.id" :value="s.id">{{ s.name }}</option></select>
                    <FieldError :message="form.errors.subject_id" />
                </div>
                <div class="sm:col-span-2"><label class="label" for="title">{{ t('Title') }}</label><input id="title" v-model="form.title" class="input" required><FieldError :message="form.errors.title" /></div>
                <div><label class="label" for="opens">{{ t('Opens') }}</label><input id="opens" v-model="form.opens_at" type="datetime-local" class="input" dir="ltr" required></div>
                <div><label class="label" for="closes">{{ t('Closes') }}</label><input id="closes" v-model="form.closes_at" type="datetime-local" class="input" dir="ltr" required><FieldError :message="form.errors.closes_at" /></div>
                <div><label class="label" for="limit">{{ t('Time limit (minutes)') }}</label><input id="limit" v-model.number="form.time_limit_minutes" type="number" min="1" max="300" class="input"></div>
                <div class="flex items-end"><button class="btn-primary w-full" :disabled="form.processing">{{ t('Create and add questions') }}</button></div>
            </form>
        </section>

        <div class="card overflow-x-auto p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-line text-muted">
                    <tr>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Quiz') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Section') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Open') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Submitted') }}</th>
                        <th class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="q in quizzes" :key="q.id" class="border-b border-line last:border-0">
                        <td class="px-4 py-3">
                            <Link :href="`/quizzes/${q.id}/edit`" class="font-medium text-accent hover:underline">{{ q.title }}</Link>
                            <div class="text-xs text-muted">{{ q.subject }} · {{ t(':count questions', { count: q.questions }) }}<span v-if="!q.published" class="ms-1 text-warn">· {{ t('Draft') }}</span></div>
                        </td>
                        <td class="px-4 py-3">{{ q.section }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-xs">{{ when(q.opens_at) }} – {{ when(q.closes_at) }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ q.submitted }}</td>
                        <td class="px-4 py-3 text-end"><Link :href="`/quizzes/${q.id}/results`" class="text-accent hover:underline">{{ t('Results') }}</Link></td>
                    </tr>
                    <tr v-if="!quizzes.length"><td colspan="5" class="px-4 py-8 text-center text-muted">{{ t('No quizzes yet.') }}</td></tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
