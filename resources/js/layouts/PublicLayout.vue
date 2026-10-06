<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { useT } from '../lib/i18n';

defineProps({ title: { type: String, default: '' }, schoolName: { type: String, required: true } });
const t = useT();
const page = usePage();
</script>

<template>
    <Head :title="title" />
    <div class="min-h-screen bg-surface">
        <header class="border-b border-line bg-card">
            <div class="mx-auto flex max-w-3xl items-center gap-3 px-4 py-4">
                <span class="text-lg font-semibold text-accent">{{ schoolName }}</span>
                <a :href="`?lang=${page.props.locale === 'ar' ? 'en' : 'ar'}`" class="ms-auto text-sm text-muted hover:text-ink">
                    {{ page.props.locale === 'ar' ? 'English' : 'العربية' }}
                </a>
            </div>
        </header>
        <main class="mx-auto max-w-3xl px-4 py-6">
            <div v-if="page.props.flash?.success" class="mb-4 rounded-lg bg-accent-soft px-4 py-3 text-sm text-accent" role="status">{{ page.props.flash.success }}</div>
            <h1 v-if="title" class="mb-5 text-2xl font-semibold">{{ title }}</h1>
            <slot />
            <p class="mt-8 text-center text-xs text-muted">{{ t('Powered by Madrasa') }}</p>
        </main>
    </div>
</template>
