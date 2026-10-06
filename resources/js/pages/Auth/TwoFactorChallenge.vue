<script setup>
import { useForm } from '@inertiajs/vue3';
import GuestLayout from '../../layouts/GuestLayout.vue';
import FieldError from '../../components/FieldError.vue';
import { useT } from '../../lib/i18n';

const t = useT();
const form = useForm({ code: '' });
</script>

<template>
    <GuestLayout :title="t('Verification code')">
        <form class="card space-y-4" @submit.prevent="form.post('/two-factor-challenge')">
            <h1 class="text-lg font-semibold">{{ t('Verification code') }}</h1>
            <p class="text-sm text-muted">{{ t('Enter the 6-digit code from your authenticator app, or one of your recovery codes.') }}</p>
            <div>
                <input id="code" v-model="form.code" class="input text-center text-lg tracking-widest" dir="ltr" inputmode="numeric" autocomplete="one-time-code" required autofocus :aria-label="t('Verification code')">
                <FieldError :message="form.errors.code" />
            </div>
            <button type="submit" class="btn-primary w-full" :disabled="form.processing">{{ t('Continue') }}</button>
        </form>
    </GuestLayout>
</template>
