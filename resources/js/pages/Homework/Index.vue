<script setup>
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import FieldError from '../../components/FieldError.vue';
import HomeworkCard from '../../components/HomeworkCard.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ homework: Array, choices: Array, subjects: Array });
const t = useT();

const form = useForm({ section_id: props.choices[0]?.section_id ?? '', subject_id: '', title: '', body: '', due_on: '', attachment: null });
const choice = computed(() => props.choices.find((c) => c.section_id === Number(form.section_id)));
const subjectOptions = computed(() => (choice.value?.subjects ? props.subjects.filter((s) => choice.value.subjects.includes(s.id)) : props.subjects));
const save = () => form.post('/homework', { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset('title', 'body', 'attachment') });
const remove = (h) => confirm(t('Delete this homework?')) && router.delete(`/homework/${h.id}`, { preserveScroll: true });
</script>

<template>
    <AppLayout :title="t('Homework')">
        <section class="card mb-4">
            <h2 class="mb-4 font-semibold">{{ t('Post homework') }}</h2>
            <p v-if="!choices.length" class="text-sm text-muted">{{ t('You have no classes assigned this year.') }}</p>
            <form v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="save">
                <div>
                    <label class="label" for="section">{{ t('Section') }}</label>
                    <select id="section" v-model="form.section_id" class="input" required @change="form.subject_id = ''">
                        <option v-for="c in choices" :key="c.section_id" :value="c.section_id">{{ c.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="subject">{{ t('Subject') }}</label>
                    <select id="subject" v-model="form.subject_id" class="input" required>
                        <option value="" disabled>—</option>
                        <option v-for="s in subjectOptions" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <FieldError :message="form.errors.subject_id" />
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="title">{{ t('Title') }}</label>
                    <input id="title" v-model="form.title" class="input" required maxlength="200">
                    <FieldError :message="form.errors.title" />
                </div>
                <div class="sm:col-span-2 lg:col-span-4">
                    <label class="label" for="body">{{ t('Details') }}</label>
                    <textarea id="body" v-model="form.body" class="input" rows="3" />
                </div>
                <div>
                    <label class="label" for="due">{{ t('Due on') }}</label>
                    <input id="due" v-model="form.due_on" type="date" class="input" dir="ltr" required>
                    <FieldError :message="form.errors.due_on" />
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="file">{{ t('Attachment (optional)') }}</label>
                    <input id="file" type="file" class="input" accept=".pdf,.jpg,.jpeg,.png,.docx,.pptx" @input="form.attachment = $event.target.files[0]">
                    <FieldError :message="form.errors.attachment" />
                </div>
                <div class="flex items-end"><button class="btn-primary w-full" :disabled="form.processing">{{ t('Post') }}</button></div>
            </form>
        </section>

        <div class="space-y-3">
            <HomeworkCard v-for="h in homework" :key="h.id" :homework="h" show-section>
                <button v-if="h.can_delete" type="button" class="text-sm text-danger hover:underline" @click="remove(h)">{{ t('Delete') }}</button>
            </HomeworkCard>
            <p v-if="!homework.length" class="card text-center text-muted">{{ t('No homework in the last 30 days.') }}</p>
        </div>
    </AppLayout>
</template>
