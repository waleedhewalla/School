<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import FieldError from '../../components/FieldError.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ quiz: Object, questions: Array });
const t = useT();

const details = useForm({
    title: props.quiz.title, instructions: props.quiz.instructions ?? '', opens_at: props.quiz.opens_at, closes_at: props.quiz.closes_at,
    time_limit_minutes: props.quiz.time_limit_minutes ?? '', published: props.quiz.published, show_results: props.quiz.show_results,
});
const saveDetails = () => details.transform((d) => ({ ...d, time_limit_minutes: d.time_limit_minutes || null })).put(`/quizzes/${props.quiz.id}`, { preserveScroll: true });
const removeQuiz = () => confirm(t('Delete this quiz and its answers?')) && router.delete(`/quizzes/${props.quiz.id}`);

const editing = ref(null);
const blank = () => ({ type: 'single', body: '', options: ['', '', '', ''], correct: [0], points: 1 });
const q = useForm(blank());
const start = (question = null) => {
    editing.value = question ? question.id : 'new';
    q.clearErrors();
    Object.assign(q, question
        ? { type: question.type, body: question.body, options: question.options ? [...question.options] : ['', ''], correct: [...question.correct], points: question.points }
        : blank());
};
const setType = (type) => {
    q.type = type;
    q.correct = type === 'true_false' ? [true] : type === 'short' ? [''] : [0];
};
const toggleCorrect = (i) => {
    const at = q.correct.indexOf(i);
    at === -1 ? q.correct.push(i) : q.correct.splice(at, 1);
};
const save = () => {
    q.transform((d) => ({ ...d, options: ['single', 'multiple'].includes(d.type) ? d.options.filter((o) => o !== '') : null }));
    const options = { preserveScroll: true, onSuccess: () => { editing.value = null; } };
    editing.value === 'new' ? q.post(`/quizzes/${props.quiz.id}/questions`, options) : q.put(`/quizzes/${props.quiz.id}/questions/${editing.value}`, options);
};
const remove = (question) => confirm(t('Delete this question?')) && router.delete(`/quizzes/${props.quiz.id}/questions/${question.id}`, { preserveScroll: true });
const total = () => props.questions.reduce((sum, x) => sum + Number(x.points), 0);
const answerText = (x) => (x.type === 'true_false' ? t(x.correct[0] ? 'True' : 'False') : x.type === 'short' ? x.correct.join(' / ') : x.correct.map((i) => x.options[i]).join('، '));
</script>

<template>
    <AppLayout :title="quiz.title">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <Link href="/quizzes" class="text-sm text-muted hover:text-ink"><span class="rtl:hidden">←</span><span class="ltr:hidden">→</span> {{ t('Quizzes') }}</Link>
            <span class="text-sm text-muted">{{ quiz.section }} · {{ quiz.subject }}</span>
            <Link :href="`/quizzes/${quiz.id}/results`" class="btn-ghost ms-auto">{{ t('Results') }}</Link>
        </div>

        <section class="card mb-4">
            <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="saveDetails">
                <div class="sm:col-span-2"><label class="label" for="title">{{ t('Title') }}</label><input id="title" v-model="details.title" class="input" required></div>
                <div><label class="label" for="opens">{{ t('Opens') }}</label><input id="opens" v-model="details.opens_at" type="datetime-local" class="input" dir="ltr" required></div>
                <div><label class="label" for="closes">{{ t('Closes') }}</label><input id="closes" v-model="details.closes_at" type="datetime-local" class="input" dir="ltr" required><FieldError :message="details.errors.closes_at" /></div>
                <div class="sm:col-span-2"><label class="label" for="ins">{{ t('Instructions') }}</label><input id="ins" v-model="details.instructions" class="input"></div>
                <div><label class="label" for="limit">{{ t('Time limit (minutes)') }}</label><input id="limit" v-model.number="details.time_limit_minutes" type="number" min="1" class="input"></div>
                <div class="flex flex-col justify-end gap-1 text-sm">
                    <label class="flex items-center gap-2"><input v-model="details.published" type="checkbox"> {{ t('Published to students') }}</label>
                    <label class="flex items-center gap-2"><input v-model="details.show_results" type="checkbox"> {{ t('Show scores after submitting') }}</label>
                </div>
                <FieldError class="sm:col-span-4" :message="details.errors.published" />
                <div class="flex gap-3 sm:col-span-2 lg:col-span-4">
                    <button class="btn-primary" :disabled="details.processing">{{ t('Save') }}</button>
                    <button type="button" class="btn-ghost text-danger" @click="removeQuiz">{{ t('Delete') }}</button>
                </div>
            </form>
        </section>

        <div class="mb-3 flex items-center">
            <h2 class="font-semibold">{{ t('Questions') }} <span class="text-sm font-normal text-muted">({{ questions.length }} · {{ t(':n points', { n: total() }) }})</span></h2>
            <button v-if="!quiz.has_attempts" type="button" class="btn-primary ms-auto" @click="start()">+ {{ t('Add a question') }}</button>
        </div>
        <p v-if="quiz.has_attempts" class="mb-3 text-sm text-warn">{{ t('Students have started this quiz; its questions can no longer change.') }}</p>
        <FieldError :message="$page.props.errors?.body" />

        <section v-if="editing" class="card mb-4">
            <form class="space-y-4" @submit.prevent="save">
                <div class="flex flex-wrap gap-2">
                    <button v-for="type in ['single', 'multiple', 'true_false', 'short']" :key="type" type="button" class="rounded-full px-3 py-1 text-sm"
                        :class="q.type === type ? 'bg-accent text-accent-ink' : 'border border-line'" @click="setType(type)">{{ t(`question.${type}`) }}</button>
                </div>
                <div><label class="label" for="body">{{ t('Question') }}</label><textarea id="body" v-model="q.body" class="input" rows="2" required /><FieldError :message="q.errors.body" /></div>

                <div v-if="['single', 'multiple'].includes(q.type)" class="space-y-2">
                    <p class="text-sm text-muted">{{ q.type === 'single' ? t('Choose the correct answer:') : t('Tick every correct answer:') }}</p>
                    <div v-for="(o, i) in q.options" :key="i" class="flex items-center gap-2">
                        <input v-if="q.type === 'single'" type="radio" :checked="q.correct[0] === i" :aria-label="t('Correct')" @change="q.correct = [i]">
                        <input v-else type="checkbox" :checked="q.correct.includes(i)" :aria-label="t('Correct')" @change="toggleCorrect(i)">
                        <input v-model="q.options[i]" class="input" :placeholder="t('Option :n', { n: i + 1 })">
                    </div>
                    <button v-if="q.options.length < 8" type="button" class="text-sm text-accent" @click="q.options.push('')">+ {{ t('Option') }}</button>
                </div>
                <div v-else-if="q.type === 'true_false'" class="flex gap-4 text-sm">
                    <label class="flex items-center gap-2"><input type="radio" :checked="q.correct[0] === true" @change="q.correct = [true]"> {{ t('True') }}</label>
                    <label class="flex items-center gap-2"><input type="radio" :checked="q.correct[0] === false" @change="q.correct = [false]"> {{ t('False') }}</label>
                </div>
                <div v-else class="space-y-2">
                    <p class="text-sm text-muted">{{ t('Accepted answers (spelling of hamza, taa marbuta and diacritics is ignored):') }}</p>
                    <input v-for="(a, i) in q.correct" :key="i" v-model="q.correct[i]" class="input" :placeholder="t('Accepted answer')">
                    <button v-if="q.correct.length < 8" type="button" class="text-sm text-accent" @click="q.correct.push('')">+ {{ t('Another accepted answer') }}</button>
                </div>
                <FieldError :message="q.errors.correct || q.errors.options" />

                <div class="flex flex-wrap items-end gap-3">
                    <div><label class="label" for="points">{{ t('Points') }}</label><input id="points" v-model.number="q.points" type="number" min="0.5" step="0.5" class="input w-24"></div>
                    <button class="btn-primary" :disabled="q.processing">{{ t('Save') }}</button>
                    <button type="button" class="btn-ghost" @click="editing = null">{{ t('Cancel') }}</button>
                </div>
            </form>
        </section>

        <ol class="space-y-3">
            <li v-for="(x, i) in questions" :key="x.id" class="card">
                <div class="flex gap-3">
                    <span class="font-semibold tabular-nums">{{ i + 1 }}.</span>
                    <div class="min-w-0 flex-1">
                        <p class="whitespace-pre-line">{{ x.body }}</p>
                        <p class="mt-1 text-sm text-accent">✓ {{ answerText(x) }}</p>
                    </div>
                    <span class="text-sm text-muted">{{ t(':n points', { n: x.points }) }}</span>
                </div>
                <div v-if="!quiz.has_attempts" class="mt-2 flex gap-3 text-sm">
                    <button type="button" class="text-accent hover:underline" @click="start(x)">{{ t('Edit') }}</button>
                    <button type="button" class="text-danger hover:underline" @click="remove(x)">{{ t('Delete') }}</button>
                </div>
            </li>
        </ol>
    </AppLayout>
</template>
