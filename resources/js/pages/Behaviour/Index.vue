<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import FieldError from '../../components/FieldError.vue';
import { formatDate, useT } from '../../lib/i18n';

const props = defineProps({ sections: Array, sectionId: Number, students: Array, categories: Array, incidents: Array, canManage: Boolean });
const t = useT();

const kind = ref('negative');
const shownCategories = computed(() => props.categories.filter((c) => c.kind === kind.value));
const form = useForm({ student_ids: [], behaviour_category_id: '', occurred_on: new Date().toISOString().slice(0, 10), note: '', action_taken: '' });
const toggle = (id) => {
    const i = form.student_ids.indexOf(id);
    i === -1 ? form.student_ids.push(id) : form.student_ids.splice(i, 1);
};
const save = () => form.post('/behaviour', { preserveScroll: true, onSuccess: () => form.reset('student_ids', 'note', 'action_taken') });
const remove = (i) => confirm(t('Delete this record?')) && router.delete(`/behaviour/${i.id}`, { preserveScroll: true });
const scoreClass = (score) => (score >= 90 ? 'text-accent' : score >= 75 ? 'text-warn' : 'text-danger');
</script>

<template>
    <AppLayout :title="t('Behaviour')">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <select class="input max-w-xs" :value="sectionId ?? ''" :aria-label="t('Section')" @change="router.get('/behaviour', { section_id: $event.target.value })">
                <option value="">{{ t('Choose a section') }}</option>
                <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.label }}</option>
            </select>
            <Link v-if="canManage" href="/behaviour/categories" class="btn-ghost">{{ t('Behaviour categories') }}</Link>
        </div>
        <FieldError :message="$page.props.errors?.incident" />

        <section v-if="sectionId" class="card mb-4">
            <h2 class="mb-3 font-semibold">{{ t('Record behaviour') }}</h2>
            <p class="mb-2 text-sm text-muted">{{ t('Pick one or more students:') }}</p>
            <div class="mb-4 flex flex-wrap gap-2">
                <button
                    v-for="s in students" :key="s.id" type="button"
                    class="rounded-lg border px-3 py-1.5 text-sm"
                    :class="form.student_ids.includes(s.id) ? 'border-accent bg-accent-soft font-semibold text-accent' : 'border-line hover:bg-surface'"
                    @click="toggle(s.id)"
                >{{ s.name }} <span class="text-xs tabular-nums" :class="scoreClass(s.score)">{{ s.score }}</span></button>
            </div>
            <FieldError :message="form.errors.student_ids" />

            <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="save">
                <div class="sm:col-span-2 lg:col-span-4 flex gap-2">
                    <button type="button" class="rounded-full px-3 py-1 text-sm" :class="kind === 'negative' ? 'bg-danger-soft font-semibold text-danger' : 'border border-line'" @click="kind = 'negative'; form.behaviour_category_id = ''">{{ t('Violation') }}</button>
                    <button type="button" class="rounded-full px-3 py-1 text-sm" :class="kind === 'positive' ? 'bg-accent-soft font-semibold text-accent' : 'border border-line'" @click="kind = 'positive'; form.behaviour_category_id = ''">{{ t('Distinguished behaviour') }}</button>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="cat">{{ t('Category') }}</label>
                    <select id="cat" v-model="form.behaviour_category_id" class="input" required>
                        <option value="" disabled>—</option>
                        <option v-for="c in shownCategories" :key="c.id" :value="c.id">
                            {{ c.degree ? t('Degree :n', { n: c.degree }) + ' · ' : '' }}{{ c.name }} ({{ c.kind === 'positive' ? '+' : '−' }}{{ c.points }})
                        </option>
                    </select>
                </div>
                <div>
                    <label class="label" for="date">{{ t('Date') }}</label>
                    <input id="date" v-model="form.occurred_on" type="date" class="input" dir="ltr" required>
                    <FieldError :message="form.errors.occurred_on" />
                </div>
                <div>
                    <label class="label" for="action">{{ t('Action taken') }}</label>
                    <input id="action" v-model="form.action_taken" class="input" maxlength="300">
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <label class="label" for="note">{{ t('Note') }}</label>
                    <input id="note" v-model="form.note" class="input">
                </div>
                <div class="flex items-end"><button class="btn-primary w-full" :disabled="form.processing || !form.student_ids.length">{{ t('Save') }}</button></div>
            </form>
        </section>

        <div class="card overflow-x-auto p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-line text-muted">
                    <tr>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Date') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Student') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Category') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Points') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Recorded by') }}</th>
                        <th class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="i in incidents" :key="i.id" class="border-b border-line last:border-0 align-top">
                        <td class="px-4 py-3 whitespace-nowrap">{{ formatDate(i.occurred_on, $page.props.locale) }}</td>
                        <td class="px-4 py-3"><Link :href="`/students/${i.student_id}`" class="text-accent hover:underline">{{ i.student }}</Link></td>
                        <td class="px-4 py-3">
                            {{ i.category }}
                            <div v-if="i.note || i.action_taken" class="text-xs text-muted">{{ [i.note, i.action_taken].filter(Boolean).join(' — ') }}</div>
                        </td>
                        <td class="px-4 py-3 font-semibold tabular-nums" :class="i.points > 0 ? 'text-accent' : 'text-danger'" dir="ltr">{{ i.points > 0 ? '+' : '' }}{{ i.points }}</td>
                        <td class="px-4 py-3 text-muted">{{ i.by }}</td>
                        <td class="px-4 py-3 text-end"><button v-if="i.can_delete" type="button" class="text-danger hover:underline" @click="remove(i)">{{ t('Delete') }}</button></td>
                    </tr>
                    <tr v-if="!incidents.length"><td colspan="6" class="px-4 py-8 text-center text-muted">{{ t('No behaviour records yet.') }}</td></tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
