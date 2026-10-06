<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useT } from '../lib/i18n';

defineProps({ title: { type: String, default: '' } });

const t = useT();
const page = usePage();
const can = computed(() => page.props.can ?? {});
const otherLocale = computed(() => (page.props.locale === 'ar' ? 'en' : 'ar'));
const menuOpen = ref(false);
watch(() => page.url, () => { menuOpen.value = false; });

// Grouped navigation; items and empty groups hidden by permission.
const groups = computed(() => [
    {
        label: t('Home'),
        items: [
            { href: '/dashboard', label: t('Dashboard'), show: can.value['students.view'] || can.value['attendance.record'] },
            { href: '/my/children', label: t('My children'), show: !can.value['students.view'] },
            { href: '/announcements', label: t('Announcements'), show: can.value['academic-structure.view'] },
        ],
    },
    {
        label: t('Students'),
        items: [
            { href: '/students', label: t('Students'), show: can.value['students.view'] },
            { href: '/admissions', label: t('Admissions'), show: can.value['admissions.manage'] },
            { href: '/promotions', label: t('Promotion'), show: can.value['students.manage'] },
        ],
    },
    {
        label: t('School day'),
        items: [
            { href: '/attendance', label: t('Attendance'), show: can.value['attendance.record'] || can.value['attendance.view'] || can.value['attendance.manage'] },
            // Teachers get their own week; managers and office staff see section timetables.
            { href: '/my/timetable', label: t('My timetable'), show: can.value['attendance.record'] && !can.value['timetable.manage'] },
            { href: '/timetable', label: t('Timetable'), show: can.value['timetable.manage'] || (can.value['academic-structure.view'] && !can.value['attendance.record']) },
        ],
    },
    {
        label: t('Grades'),
        items: [
            { href: '/marks', label: t('Marks'), show: can.value['grades.record'] || can.value['grades.manage'] },
            { href: '/results', label: t('Results'), show: can.value['grades.view'] },
            { href: '/grading', label: t('Assessments'), show: can.value['grades.manage'] },
        ],
    },
    {
        label: t('Administration'),
        items: [
            { href: '/setup/years', match: '/setup', label: t('School setup'), show: can.value['academic-structure.manage'] },
            { href: '/staff', label: t('Staff'), show: can.value['staff.manage'] },
            { href: '/users', label: t('Users'), show: can.value['members.manage'] },
            {
                href: can.value['school.manage'] ? '/settings/school' : (can.value['timetable.manage'] ? '/settings/periods' : '/settings/grading'),
                match: '/settings',
                label: t('Settings'),
                show: can.value['timetable.manage'] || can.value['school.manage'] || can.value['grades.manage'],
            },
        ],
    },
].map((g) => ({ ...g, items: g.items.filter((i) => i.show) })).filter((g) => g.items.length));

const isActive = (item) => {
    const href = item.match ?? item.href;
    return page.url === href || page.url.startsWith(`${href}?`) || page.url.startsWith(`${href}/`);
};
</script>

<template>
    <Head :title="title" />
    <div class="min-h-screen lg:flex">
        <!-- Sidebar: on the start side (right in Arabic). -->
        <aside
            class="fixed inset-y-0 start-0 z-30 w-64 overflow-y-auto border-e border-line bg-card px-3 py-4 transition-transform lg:static"
            :class="menuOpen ? 'translate-x-0' : 'max-lg:ltr:-translate-x-full max-lg:rtl:translate-x-full'"
            :aria-label="t('Main menu')"
        >
            <Link href="/dashboard" class="mb-1 block px-3 text-lg font-semibold text-accent">{{ t('Madrasa') }}</Link>
            <div v-if="page.props.school" class="mb-4 px-3 text-sm text-muted">{{ page.props.school.name }}</div>

            <nav class="space-y-4 text-sm">
                <div v-for="group in groups" :key="group.label">
                    <div class="mb-1 px-3 text-xs font-semibold uppercase tracking-wide text-muted">{{ group.label }}</div>
                    <Link
                        v-for="item in group.items"
                        :key="item.href"
                        :href="item.href"
                        class="block rounded-lg px-3 py-1.5"
                        :class="isActive(item) ? 'bg-accent-soft font-semibold text-accent' : 'text-ink hover:bg-surface'"
                        :aria-current="isActive(item) ? 'page' : undefined"
                    >{{ item.label }}</Link>
                </div>
            </nav>
        </aside>
        <div v-if="menuOpen" class="fixed inset-0 z-20 bg-black/30 lg:hidden" @click="menuOpen = false" />

        <div class="min-w-0 flex-1">
            <header class="border-b border-line bg-card">
                <div class="flex items-center gap-3 px-4 py-3">
                    <button type="button" class="btn-ghost px-2 py-1 lg:hidden" :aria-expanded="menuOpen" :aria-label="t('Main menu')" @click="menuOpen = !menuOpen">☰</button>
                    <span class="font-semibold lg:hidden">{{ page.props.school?.name ?? t('Madrasa') }}</span>
                    <div class="ms-auto flex items-center gap-3 text-sm">
                        <a v-if="page.props.auth.platform" href="/platform" class="text-muted hover:text-ink">{{ t('Platform console') }}</a>
                        <Link v-else-if="page.props.schools.length > 1" href="/schools" class="text-muted hover:text-ink">{{ t('Switch school') }}</Link>
                        <a :href="`/locale/${otherLocale}`" class="text-muted hover:text-ink">{{ otherLocale === 'ar' ? 'العربية' : 'English' }}</a>
                        <Link href="/account" class="hidden text-muted hover:text-ink sm:inline">{{ page.props.auth.user?.name }}</Link>
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
    </div>
</template>
