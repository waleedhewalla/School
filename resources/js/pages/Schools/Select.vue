<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { useT } from '../../lib/i18n';

const t = useT();
const page = usePage();
</script>

<template>
    <Head :title="t('Choose a school')" />
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-md space-y-3">
            <h1 class="text-xl font-semibold">{{ t('Choose a school') }}</h1>
            <a v-if="page.props.auth.platform" href="/platform" class="card block font-semibold text-accent hover:border-accent">{{ t('Platform console') }}</a>
            <p v-if="!page.props.schools.length && !page.props.auth.platform" class="card text-muted">{{ t('Your account is not linked to any school yet.') }}</p>
            <button
                v-for="school in page.props.schools"
                :key="school.id"
                type="button"
                class="card block w-full text-start hover:border-accent"
                @click="router.post('/schools', { school_id: school.id })"
            >
                <span class="font-semibold">{{ school.name }}</span>
            </button>
        </div>
    </div>
</template>
