<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import FieldError from '../../components/FieldError.vue';
import { formatDate, useT } from '../../lib/i18n';

defineProps({ exams: Array, grades: Array, subjects: Array, canManage: Boolean });
const t = useT();
const adding = ref(false);
const form = useForm({ type: 'nafes', name: '', held_on: '', grade_level_id: '', subject_id: '', max_score: 100 });
const save = () => form.transform((d) => ({ ...d, held_on: d.held_on || null, grade_level_id: d.grade_level_id || null, subject_id: d.subject_id || null })).post('/external-exams');
</script>

<template>
    <AppLayout :title="t('National tests')">
        <p class="mb-4 text-sm text-muted">{{ t('Record the school\'s results in Nafes and Qiyas tests and compare sections.') }}</p>
        <button v-if="canManage" type="button" class="btn-primary mb-4" @click="adding = !adding">{{ t('Add a test') }}</button>

        <section v-if="adding" class="card mb-4">
            <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" @submit.prevent="save">
                <div>
                    <label class="label" for="type">{{ t('Type') }}</label>
                    <select id="type" v-model="form.type" class="input"><option v-for="ty in ['nafes', 'tahsili', 'qudrat', 'other']" :key="ty" :value="ty">{{ t(`exam_type.${ty}`) }}</option></select>
                </div>
                <div class="lg:col-span-2"><label class="label" for="name">{{ t('Name') }}</label><input id="name" v-model="form.name" class="input" required :placeholder="t('e.g. Nafes 1448 — Grade 6 maths')"><FieldError :message="form.errors.name" /></div>
                <div><label class="label" for="grade">{{ t('Grade level') }}</label><select id="grade" v-model="form.grade_level_id" class="input"><option value="">{{ t('All grades') }}</option><option v-for="g in grades" :key="g.id" :value="g.id">{{ g.name }}</option></select></div>
                <div><label class="label" for="subject">{{ t('Subject') }}</label><select id="subject" v-model="form.subject_id" class="input"><option value="">—</option><option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option></select></div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="label" for="date">{{ t('Date') }}</label><input id="date" v-model="form.held_on" type="date" class="input" dir="ltr"></div>
                    <div><label class="label" for="max">{{ t('Out of') }}</label><input id="max" v-model.number="form.max_score" type="number" min="1" class="input" required></div>
                </div>
                <div class="flex gap-3 sm:col-span-2 lg:col-span-3"><button class="btn-primary" :disabled="form.processing">{{ t('Save') }}</button><button type="button" class="btn-ghost" @click="adding = false">{{ t('Cancel') }}</button></div>
            </form>
        </section>

        <div class="card overflow-x-auto p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-line text-muted">
                    <tr>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Test') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Grade level') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Date') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Results') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Average') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="e in exams" :key="e.id" class="border-b border-line last:border-0">
                        <td class="px-4 py-3"><Link :href="`/external-exams/${e.id}`" class="font-medium text-accent hover:underline">{{ e.name }}</Link><div class="text-xs text-muted">{{ t(`exam_type.${e.type}`) }}<template v-if="e.subject"> · {{ e.subject }}</template></div></td>
                        <td class="px-4 py-3">{{ e.grade ?? '—' }}</td>
                        <td class="px-4 py-3">{{ e.held_on ? formatDate(e.held_on, $page.props.locale) : '—' }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ e.count }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ e.average ?? '—' }} <span class="text-muted">/ {{ e.max }}</span></td>
                    </tr>
                    <tr v-if="!exams.length"><td colspan="5" class="px-4 py-8 text-center text-muted">{{ t('No tests recorded yet.') }}</td></tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
