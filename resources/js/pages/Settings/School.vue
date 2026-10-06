<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import SettingsTabs from '../../components/SettingsTabs.vue';
import FieldError from '../../components/FieldError.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ school: Object, campuses: Array, timezones: Array });
const t = useT();

const form = useForm({ ...props.school });
const save = () => form.transform((d) => ({ ...d, name_en: d.name_en || null, ministry_code: d.ministry_code || null })).put('/settings/school', { preserveScroll: true });

const editing = ref(null);
const campus = useForm({ name_ar: '', name_en: '', gender: 'mixed' });
const startCampus = (c = null) => {
    editing.value = c ? c.id : 'new';
    Object.assign(campus, c ? { name_ar: c.name_ar, name_en: c.name_en ?? '', gender: c.gender } : { name_ar: '', name_en: '', gender: 'mixed' });
};
const saveCampus = () => {
    const options = { preserveScroll: true, onSuccess: () => { editing.value = null; } };
    editing.value === 'new' ? campus.post('/settings/campuses', options) : campus.put(`/settings/campuses/${editing.value}`, options);
};
</script>

<template>
    <AppLayout :title="t('Settings')">
        <SettingsTabs />
        <form class="card mb-4 grid gap-4 sm:grid-cols-2" @submit.prevent="save">
            <div><label class="label" for="nar">{{ t('School name (Arabic)') }}</label><input id="nar" v-model="form.name_ar" class="input" required><FieldError :message="form.errors.name_ar" /></div>
            <div><label class="label" for="nen">{{ t('School name (English)') }}</label><input id="nen" v-model="form.name_en" class="input" dir="ltr"></div>
            <div><label class="label" for="code">{{ t('Ministry school code') }}</label><input id="code" v-model="form.ministry_code" class="input" dir="ltr"></div>
            <div>
                <label class="label" for="locale">{{ t('Default language') }}</label>
                <select id="locale" v-model="form.default_locale" class="input"><option value="ar">العربية</option><option value="en">English</option></select>
            </div>
            <div>
                <label class="label" for="dates">{{ t('Dates shown as') }}</label>
                <select id="dates" v-model="form.date_display" class="input">
                    <option value="both">{{ t('Hijri and Gregorian') }}</option>
                    <option value="hijri">{{ t('Hijri') }}</option>
                    <option value="gregorian">{{ t('Gregorian') }}</option>
                </select>
            </div>
            <div>
                <label class="label" for="tz">{{ t('Time zone') }}</label>
                <select id="tz" v-model="form.timezone" class="input" dir="ltr"><option v-for="z in timezones" :key="z" :value="z">{{ z }}</option></select>
            </div>
            <p class="text-sm text-muted sm:col-span-2">{{ t('School address for families') }}: <span dir="ltr">/apply/{{ school.slug }}</span></p>
            <div class="sm:col-span-2"><button class="btn-primary" :disabled="form.processing">{{ t('Save') }}</button></div>
        </form>

        <section class="card">
            <h2 class="mb-3 font-semibold">{{ t('Campuses') }}</h2>
            <ul class="divide-y divide-line text-sm">
                <li v-for="c in campuses" :key="c.id" class="flex items-center gap-3 py-2">
                    <span class="font-medium">{{ c.name_ar }}</span>
                    <span class="text-muted">{{ t(`campus.${c.gender}`) }} · {{ t(':count sections', { count: c.sections_count }) }}</span>
                    <button type="button" class="ms-auto text-accent hover:underline" @click="startCampus(c)">{{ t('Edit') }}</button>
                </li>
            </ul>
            <form v-if="editing" class="mt-3 flex flex-wrap items-end gap-3" @submit.prevent="saveCampus">
                <div><label class="label" for="c-ar">{{ t('Name (Arabic)') }}</label><input id="c-ar" v-model="campus.name_ar" class="input" required></div>
                <div><label class="label" for="c-en">{{ t('Name (English)') }}</label><input id="c-en" v-model="campus.name_en" class="input" dir="ltr"></div>
                <div>
                    <label class="label" for="c-g">{{ t('Students') }}</label>
                    <select id="c-g" v-model="campus.gender" class="input">
                        <option value="male">{{ t('campus.male') }}</option><option value="female">{{ t('campus.female') }}</option><option value="mixed">{{ t('campus.mixed') }}</option>
                    </select>
                </div>
                <button class="btn-primary">{{ t('Save') }}</button>
                <button type="button" class="btn-ghost" @click="editing = null">{{ t('Cancel') }}</button>
            </form>
            <button v-else type="button" class="btn-ghost mt-3" @click="startCampus()">+ {{ t('Add a campus') }}</button>
        </section>
    </AppLayout>
</template>
