<script setup>
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import SettingsTabs from '../../components/SettingsTabs.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ scale: Object, stages: Array, scope: String, usesDefault: Boolean });
const t = useT();

// "" = school default, "stage:ID" or "grade:ID".
const scope = ref(props.scope ?? '');
const [kind, scopeId] = (props.scope ?? '').split(':');
watch(scope, (value) => {
    const [k, id] = value.split(':');
    router.get('/settings/grading', k === 'grade' ? { grade_level_id: id } : (k === 'stage' ? { stage_id: id } : {}), { replace: true });
});

const form = useForm({
    stage_id: kind === 'stage' ? Number(scopeId) : null,
    grade_level_id: kind === 'grade' ? Number(scopeId) : null,
    pass_percent: props.scale?.pass_percent ?? 50,
    bands: (props.scale?.bands ?? []).map((b) => ({ ...b })),
});
const add = () => form.bands.push({ min_percent: 0, label_ar: '', label_en: '' });
const submit = () => form.transform((d) => ({ ...d, bands: [...d.bands].sort((a, b) => b.min_percent - a.min_percent) })).put('/settings/grading', { preserveScroll: true });
const backToDefault = () => router.put('/settings/grading', { stage_id: form.stage_id, grade_level_id: form.grade_level_id, use_default: true }, { preserveScroll: true });
</script>

<template>
    <AppLayout :title="t('Grading scale')">
        <SettingsTabs />
        <form class="max-w-2xl space-y-4" @submit.prevent="submit">
            <div>
                <label class="label" for="stage">{{ t('Applies to') }}</label>
                <select id="stage" v-model="scope" class="input max-w-sm">
                    <option value="">{{ t('School default (all stages)') }}</option>
                    <optgroup v-for="s in stages" :key="s.id" :label="s.name">
                        <option :value="`stage:${s.id}`">{{ t('Whole stage') }} — {{ s.name }}</option>
                        <option v-for="g in s.grades" :key="g.id" :value="`grade:${g.id}`">{{ g.name }}</option>
                    </optgroup>
                </select>
            </div>
            <p v-if="scope && usesDefault" class="rounded-lg bg-surface px-4 py-3 text-sm text-muted">{{ t('This uses a broader scale (shown below). Saving gives it its own scale.') }}</p>
            <p class="text-sm text-muted">{{ t('Each band starts at its minimum percentage and runs up to the next band. Check the bands and pass mark against current Ministry rules for each stage.') }}</p>
            <section class="card">
                <label class="label" for="pass">{{ t('Pass mark %') }}</label>
                <input id="pass" v-model.number="form.pass_percent" type="number" min="0" max="100" class="input w-28" dir="ltr">
            </section>
            <section class="card">
                <table class="w-full text-sm">
                    <thead class="text-muted">
                        <tr>
                            <th class="pb-1 text-start font-medium">{{ t('From %') }}</th>
                            <th class="pb-1 text-start font-medium">{{ t('Grade') }}</th>
                            <th class="pb-1 text-start font-medium">{{ t('Name (English)') }}</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(b, i) in form.bands" :key="i">
                            <td class="py-1 pe-2"><input v-model.number="b.min_percent" type="number" min="0" max="100" class="input w-24" dir="ltr"></td>
                            <td class="py-1 pe-2"><input v-model="b.label_ar" class="input" required></td>
                            <td class="py-1 pe-2"><input v-model="b.label_en" class="input" dir="ltr"></td>
                            <td class="py-1"><button type="button" class="text-sm text-danger" @click="form.bands.splice(i, 1)">{{ t('Remove') }}</button></td>
                        </tr>
                    </tbody>
                </table>
                <button type="button" class="btn-ghost mt-3" @click="add">{{ t('Add band') }}</button>
            </section>
            <p v-if="Object.keys(form.errors).length" class="text-sm text-danger" role="alert">{{ Object.values(form.errors)[0] }}</p>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary" :disabled="form.processing">{{ t('Save changes') }}</button>
                <button v-if="scope && !usesDefault" type="button" class="btn-ghost" @click="backToDefault">{{ t('Use the broader scale instead') }}</button>
            </div>
        </form>
    </AppLayout>
</template>
