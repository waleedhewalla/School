<script setup>
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ quiz: Object, deadline: String, questions: Array });
const t = useT();

const answers = reactive(Object.fromEntries(props.questions.map((q) => [q.id, q.type === 'multiple' ? [] : null])));
const left = ref(0);
const sending = ref(false);
let timer;

const submit = () => {
    if (sending.value) return;
    sending.value = true;
    router.post(`/my/quizzes/${props.quiz.id}`, { answers }, { onFinish: () => { sending.value = false; } });
};
const tick = () => {
    left.value = Math.max(0, Math.floor((new Date(props.deadline) - Date.now()) / 1000));
    if (left.value === 0) { clearInterval(timer); submit(); }
};
onMounted(() => { tick(); timer = setInterval(tick, 1000); });
onBeforeUnmount(() => clearInterval(timer));
const clock = () => `${Math.floor(left.value / 60)}:${String(left.value % 60).padStart(2, '0')}`;
const confirmSubmit = () => confirm(t('Submit your answers? You cannot change them afterwards.')) && submit();
</script>

<template>
    <AppLayout :title="quiz.title">
        <div class="sticky top-0 z-10 mb-4 flex items-center gap-3 rounded-lg bg-card px-4 py-2 shadow-sm">
            <span class="text-sm text-muted">{{ quiz.subject }}</span>
            <span class="ms-auto font-semibold tabular-nums" :class="left < 60 ? 'text-danger' : ''" dir="ltr" role="timer" :aria-label="t('Time left')">⏱ {{ clock() }}</span>
        </div>
        <p v-if="quiz.instructions" class="mb-4 text-sm text-muted">{{ quiz.instructions }}</p>

        <form class="space-y-4" @submit.prevent="confirmSubmit">
            <fieldset v-for="(q, i) in questions" :key="q.id" class="card">
                <legend class="mb-3 flex w-full gap-2">
                    <span class="font-semibold tabular-nums">{{ i + 1 }}.</span>
                    <span class="min-w-0 flex-1 whitespace-pre-line">{{ q.body }}</span>
                    <span class="text-xs text-muted">{{ t(':n points', { n: q.points }) }}</span>
                </legend>
                <div v-if="q.type === 'single'" class="space-y-2">
                    <label v-for="(o, k) in q.options" :key="k" class="flex items-center gap-3 rounded-lg border border-line px-3 py-2 hover:bg-surface">
                        <input v-model="answers[q.id]" type="radio" :name="`q${q.id}`" :value="k"> {{ o }}
                    </label>
                </div>
                <div v-else-if="q.type === 'multiple'" class="space-y-2">
                    <label v-for="(o, k) in q.options" :key="k" class="flex items-center gap-3 rounded-lg border border-line px-3 py-2 hover:bg-surface">
                        <input v-model="answers[q.id]" type="checkbox" :value="k"> {{ o }}
                    </label>
                </div>
                <div v-else-if="q.type === 'true_false'" class="flex gap-3">
                    <label class="flex items-center gap-2 rounded-lg border border-line px-4 py-2"><input v-model="answers[q.id]" type="radio" :name="`q${q.id}`" :value="true"> {{ t('True') }}</label>
                    <label class="flex items-center gap-2 rounded-lg border border-line px-4 py-2"><input v-model="answers[q.id]" type="radio" :name="`q${q.id}`" :value="false"> {{ t('False') }}</label>
                </div>
                <input v-else v-model="answers[q.id]" class="input" maxlength="500" :aria-label="t('Your answer')">
            </fieldset>
            <button class="btn-primary w-full sm:w-auto" :disabled="sending">{{ t('Submit answers') }}</button>
        </form>
    </AppLayout>
</template>
