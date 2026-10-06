<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '../../layouts/GuestLayout.vue';
import FieldError from '../../components/FieldError.vue';
import { useT } from '../../lib/i18n';

const t = useT();
const form = useForm({ email: '' });
</script>

<template>
    <GuestLayout :title="t('Forgot your password?')">
        <form class="card space-y-4" @submit.prevent="form.post('/forgot-password')">
            <h1 class="text-lg font-semibold">{{ t('Forgot your password?') }}</h1>
            <p class="text-sm text-muted">{{ t('Enter your email and we will send you a link to choose a new password.') }}</p>
            <div>
                <label for="email" class="label">{{ t('Email') }}</label>
                <input id="email" v-model="form.email" type="email" class="input" dir="ltr" required autofocus>
                <FieldError :message="form.errors.email" />
            </div>
            <button type="submit" class="btn-primary w-full" :disabled="form.processing">{{ t('Send reset link') }}</button>
            <Link href="/login" class="block text-center text-sm text-muted">{{ t('Back to sign in') }}</Link>
        </form>
    </GuestLayout>
</template>
