<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import FieldError from '../../components/FieldError.vue';
import { formatDate, useT } from '../../lib/i18n';

const props = defineProps({ schools: Array, totals: Object });
const t = useT();
const page = usePage();

const search = ref('');
const shown = computed(() => props.schools.filter((s) => !search.value || `${s.name_ar} ${s.name_en ?? ''} ${s.slug}`.toLowerCase().includes(search.value.toLowerCase())));

const adding = ref(false);
const form = useForm({ slug: '', name_ar: '', name_en: '', default_locale: 'ar', date_display: 'both', ministry_code: '', admin_name: '', admin_email: '' });
const save = () => form.transform((d) => ({ ...d, name_en: d.name_en || null, ministry_code: d.ministry_code || null }))
    .post('/platform/schools', { preserveScroll: true, onSuccess: () => { form.reset(); adding.value = false; } });

const toggle = (s) => {
    const status = s.status === 'active' ? 'suspended' : 'active';
    if (status === 'suspended' && !confirm(t('Suspend :school? Its users will not be able to sign in to it.', { school: s.name_ar }))) return;
    router.patch(`/platform/schools/${s.id}`, { status }, { preserveScroll: true });
};
const enter = (s) => router.post(`/platform/schools/${s.id}/enter`);
</script>

<template>
    <Head :title="t('Platform')" />
    <div class="min-h-screen bg-surface">
        <header class="border-b border-line bg-card">
            <div class="mx-auto flex max-w-6xl items-center gap-4 px-4 py-3 text-sm">
                <span class="text-lg font-semibold text-accent">{{ t('Madrasa') }} · {{ t('Platform') }}</span>
                <a :href="`/locale/${page.props.locale === 'ar' ? 'en' : 'ar'}`" class="ms-auto text-muted hover:text-ink">{{ page.props.locale === 'ar' ? 'English' : 'العربية' }}</a>
                <Link href="/account" class="text-muted hover:text-ink">{{ page.props.auth.user?.name }}</Link>
                <button type="button" class="text-muted hover:text-ink" @click="router.post('/logout')">{{ t('Sign out') }}</button>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-6">
            <div v-if="page.props.flash?.success" class="mb-4 rounded-lg bg-accent-soft px-4 py-3 text-sm text-accent" role="status">{{ page.props.flash.success }}</div>

            <div class="mb-6 grid gap-4 sm:grid-cols-4">
                <div class="card"><div class="text-sm text-muted">{{ t('Schools') }}</div><div class="text-2xl font-semibold tabular-nums">{{ totals.schools }}</div></div>
                <div class="card"><div class="text-sm text-muted">{{ t('Active') }}</div><div class="text-2xl font-semibold tabular-nums">{{ totals.active }}</div></div>
                <div class="card"><div class="text-sm text-muted">{{ t('Students') }}</div><div class="text-2xl font-semibold tabular-nums">{{ totals.students }}</div></div>
                <div class="card"><div class="text-sm text-muted">{{ t('Users') }}</div><div class="text-2xl font-semibold tabular-nums">{{ totals.members }}</div></div>
            </div>

            <div class="mb-4 flex flex-wrap gap-3">
                <button type="button" class="btn-primary" @click="adding = !adding">{{ t('Add a school') }}</button>
                <input v-model="search" type="search" class="input max-w-xs" :placeholder="t('Search')">
            </div>

            <section v-if="adding" class="card mb-4">
                <h2 class="mb-4 font-semibold">{{ t('Add a school') }}</h2>
                <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="save">
                    <div><label class="label" for="nar">{{ t('School name (Arabic)') }} *</label><input id="nar" v-model="form.name_ar" class="input" required><FieldError :message="form.errors.name_ar" /></div>
                    <div><label class="label" for="nen">{{ t('School name (English)') }}</label><input id="nen" v-model="form.name_en" class="input" dir="ltr"></div>
                    <div>
                        <label class="label" for="slug">{{ t('Short address') }} *</label>
                        <input id="slug" v-model="form.slug" class="input" dir="ltr" required placeholder="al-riyada">
                        <FieldError :message="form.errors.slug" />
                    </div>
                    <div><label class="label" for="code">{{ t('Ministry school code') }}</label><input id="code" v-model="form.ministry_code" class="input" dir="ltr"></div>
                    <div>
                        <label class="label" for="locale">{{ t('Default language') }}</label>
                        <select id="locale" v-model="form.default_locale" class="input"><option value="ar">العربية</option><option value="en">English</option></select>
                    </div>
                    <div>
                        <label class="label" for="dates">{{ t('Dates shown as') }}</label>
                        <select id="dates" v-model="form.date_display" class="input">
                            <option value="both">{{ t('Hijri and Gregorian') }}</option><option value="hijri">{{ t('Hijri') }}</option><option value="gregorian">{{ t('Gregorian') }}</option>
                        </select>
                    </div>
                    <div><label class="label" for="aname">{{ t('School admin name') }} *</label><input id="aname" v-model="form.admin_name" class="input" required><FieldError :message="form.errors.admin_name" /></div>
                    <div><label class="label" for="aemail">{{ t('School admin email') }} *</label><input id="aemail" v-model="form.admin_email" type="email" class="input" dir="ltr" required><FieldError :message="form.errors.admin_email" /></div>
                    <p class="text-sm text-muted sm:col-span-2 lg:col-span-4">{{ t('The school gets Saudi stages and grades, attendance codes, a bell schedule and grading scales. The admin receives an invitation email.') }}</p>
                    <div class="flex gap-3 sm:col-span-2 lg:col-span-4">
                        <button class="btn-primary" :disabled="form.processing">{{ t('Create school') }}</button>
                        <button type="button" class="btn-ghost" @click="adding = false">{{ t('Cancel') }}</button>
                    </div>
                </form>
            </section>

            <div class="card overflow-x-auto p-0">
                <table class="w-full text-sm">
                    <thead class="border-b border-line text-muted">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium">{{ t('School') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ t('Students') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ t('Users') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ t('Applications') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ t('Last register') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ t('Status') }}</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in shown" :key="s.id" class="border-b border-line last:border-0">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ s.name_ar }}</div>
                                <div class="text-xs text-muted" dir="ltr">{{ s.slug }} · {{ s.created_at }}</div>
                            </td>
                            <td class="px-4 py-3 tabular-nums">{{ s.students }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ s.members }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ s.applications }}</td>
                            <td class="px-4 py-3">{{ s.last_attendance ? formatDate(s.last_attendance, page.props.locale) : '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="s.status === 'active' ? 'bg-accent-soft text-accent' : 'bg-danger-soft text-danger'">{{ t(`school_status.${s.status}`) }}</span>
                            </td>
                            <td class="px-4 py-3 text-end whitespace-nowrap">
                                <button v-if="s.status === 'active'" type="button" class="text-accent hover:underline" @click="enter(s)">{{ t('Open') }}</button>
                                <button type="button" class="ms-3 hover:underline" :class="s.status === 'active' ? 'text-danger' : 'text-accent'" @click="toggle(s)">{{ s.status === 'active' ? t('Suspend') : t('Reactivate') }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</template>
