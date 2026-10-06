<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useT } from '../lib/i18n';

defineProps({ title: { type: String, default: '' } });

const t = useT();
const page = usePage();
const can = computed(() => page.props.can ?? {});
const otherLocale = computed(() => (page.props.locale === 'ar' ? 'en' : 'ar'));

const nav = computed(() => [
    { href: '/dashboard', label: t('Dashboard'), show: can.value['students.view'] || can.value['attendance.record'] },
    { href: '/students', label: t('Students'), show: can.value['students.view'] },
    { href: '/attendance', label: t('Attendance'), show: can.value['attendance.record'] || can.value['attendance.view'] || can.value['attendance.manage'] },
    // Teachers get their own week; managers and office staff see section timetables.
    { href: '/timetable', label: t('Timetable'), show: can.value['timetable.manage'] || (can.value['academic-structure.view'] && !can.value['attendance.record']) },
    { href: '/my/timetable', label: t('My timetable'), show: can.value['attendance.record'] && !can.value['timetable.manage'] },
    { href: '/marks', label: t('Marks'), show: can.value['grades.record'] || can.value['grades.manage'] },
    { href: '/results', label: t('Results'), show: can.value['grades.view'] },
    { href: '/grading', label: t('Assessments'), show: can.value['grades.manage'] },
    { href: '/promotions', label: t('Promotion'), show: can.value['students.manage'] },
    { href: can.value['timetable.manage'] ? '/settings/periods' : '/settings/notifications', label: t('Settings'), show: can.value['timetable.manage'] || can.value['school.manage'] || can.value['grades.manage'] },
    { href: '/my/children', label: t('My children'), show: !can.value['students.view'] },
].filter((item) => item.show));

const isActive = (href) => page.url === href || page.url.startsWith(`${href}?`) || page.url.startsWith(`${href}/`)
    || (href.startsWith('/settings') && page.url.startsWith('/settings'));
</script>

<template>
    <Head :title="title" />
    <div class="min-h-screen">
        <header class="border-b border-line bg-card">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-4 px-4 py-3">
                <Link href="/dashboard" class="text-lg font-semibold text-accent">{{ t('Madrasa') }}</Link>
                <span v-if="page.props.school" class="text-sm text-muted">{{ page.props.school.name }}</span>

                <nav class="flex flex-wrap gap-1 text-sm">
                    <Link
                        v-for="item in nav"
                        :key="item.href"
                        :href="item.href"
                        class="rounded-lg px-3 py-1.5"
                        :class="isActive(item.href) ? 'bg-accent-soft font-semibold text-accent' : 'text-muted hover:text-ink'"
                    >
                        {{ item.label }}
                    </Link>
                </nav>

                <div class="ms-auto flex items-center gap-3 text-sm">
                    <Link v-if="page.props.schools.length > 1" href="/schools" class="text-muted hover:text-ink">{{ t('Switch school') }}</Link>
                    <a :href="`/locale/${otherLocale}`" class="text-muted hover:text-ink">{{ otherLocale === 'ar' ? 'العربية' : 'English' }}</a>
                    <span class="hidden text-muted sm:inline">{{ page.props.auth.user?.name }}</span>
                    <button type="button" class="text-muted hover:text-ink" @click="router.post('/logout')">{{ t('Sign out') }}</button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-6">
            <div v-if="page.props.flash?.success" class="mb-4 rounded-lg bg-accent-soft px-4 py-3 text-sm text-accent" role="status">
                {{ page.props.flash.success }}
            </div>
            <h1 v-if="title" class="mb-5 text-2xl font-semibold">{{ title }}</h1>
            <slot />
        </main>
    </div>
</template>
