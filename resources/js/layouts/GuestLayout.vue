<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { useT } from '../lib/i18n';

defineProps({ title: { type: String, default: '' } });
const t = useT();
const page = usePage();
</script>

<template>
    <Head :title="title" />
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-sm">
            <div class="mb-6 flex items-center justify-between">
                <span class="text-xl font-semibold text-accent">{{ t('Madrasa') }}</span>
                <a :href="`/locale/${page.props.locale === 'ar' ? 'en' : 'ar'}`" class="text-sm text-muted">
                    {{ page.props.locale === 'ar' ? 'English' : 'العربية' }}
                </a>
            </div>
            <div v-if="page.props.flash?.success" class="mb-4 rounded-lg bg-accent-soft px-4 py-3 text-sm text-accent" role="status">{{ page.props.flash.success }}</div>
            <slot />
        </div>
    </div>
</template>
