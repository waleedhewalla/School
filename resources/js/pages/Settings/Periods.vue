<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import SettingsTabs from '../../components/SettingsTabs.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ periods: Array, schoolDays: Array });
const t = useT();
const page = usePage();

const form = useForm({
    periods: props.periods.map((p) => ({ ...p })),
    school_days: [...props.schoolDays],
});

const add = (isBreak = false) => {
    const last = form.periods.at(-1);
    form.periods.push({ id: null, name_ar: isBreak ? 'الفسحة' : '', name_en: isBreak ? 'Break' : '', starts_at: last?.ends_at ?? '07:00', ends_at: '', is_break: isBreak });
};
const remove = (i) => form.periods.splice(i, 1);
const days = [0, 1, 2, 3, 4, 5, 6];
const firstError = () => Object.values(form.errors)[0];
</script>

<template>
    <AppLayout :title="t('Bell schedule')">
        <SettingsTabs />
        <form class="space-y-4" @submit.prevent="form.put('/settings/periods', { preserveScroll: true })">
            <section class="card">
                <h2 class="mb-3 font-semibold">{{ t('School days') }}</h2>
                <div class="flex flex-wrap gap-4 text-sm">
                    <label v-for="d in days" :key="d" class="flex items-center gap-2">
                        <input v-model="form.school_days" type="checkbox" :value="d"> {{ t(`day.${d}`) }}
                    </label>
                </div>
            </section>

            <section class="card overflow-x-auto">
                <h2 class="mb-3 font-semibold">{{ t('Periods') }}</h2>
                <table class="w-full min-w-[640px] text-sm">
                    <thead class="text-muted">
                        <tr>
                            <th class="pb-2 text-start font-medium">{{ t('Name') }}</th>
                            <th class="pb-2 text-start font-medium">{{ t('Name (English)') }}</th>
                            <th class="pb-2 text-start font-medium">{{ t('Starts') }}</th>
                            <th class="pb-2 text-start font-medium">{{ t('Ends') }}</th>
                            <th class="pb-2 text-start font-medium">{{ t('Break') }}</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(p, i) in form.periods" :key="i">
                            <td class="py-1 pe-2"><input v-model="p.name_ar" class="input" required></td>
                            <td class="py-1 pe-2"><input v-model="p.name_en" class="input" dir="ltr"></td>
                            <td class="py-1 pe-2"><input v-model="p.starts_at" type="time" class="input" dir="ltr" required></td>
                            <td class="py-1 pe-2"><input v-model="p.ends_at" type="time" class="input" dir="ltr" required></td>
                            <td class="py-1 pe-2 text-center"><input v-model="p.is_break" type="checkbox"></td>
                            <td class="py-1"><button type="button" class="text-sm text-danger" @click="remove(i)">{{ t('Remove') }}</button></td>
                        </tr>
                    </tbody>
                </table>
                <div class="mt-3 flex gap-2">
                    <button type="button" class="btn-ghost" @click="add(false)">{{ t('Add period') }}</button>
                    <button type="button" class="btn-ghost" @click="add(true)">{{ t('Add break') }}</button>
                </div>
            </section>

            <p v-if="firstError()" class="text-sm text-danger" role="alert">{{ firstError() }}</p>
            <button type="submit" class="btn-primary" :disabled="form.processing">{{ t('Save changes') }}</button>
        </form>
    </AppLayout>
</template>
