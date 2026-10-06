<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '../../layouts/GuestLayout.vue';
import FieldError from '../../components/FieldError.vue';
import { useT } from '../../lib/i18n';

const t = useT();
const form = useForm({ email: '', password: '', remember: false });
const submit = () => form.post('/login', { onFinish: () => form.reset('password') });
</script>

<template>
    <GuestLayout :title="t('Sign in')">
        <form class="card space-y-4" @submit.prevent="submit">
            <h1 class="text-lg font-semibold">{{ t('Sign in') }}</h1>
            <div>
                <label for="email" class="label">{{ t('Email') }}</label>
                <input id="email" v-model="form.email" type="email" class="input" dir="ltr" autocomplete="username" required autofocus>
                <FieldError :message="form.errors.email" />
            </div>
            <div>
                <label for="password" class="label">{{ t('Password') }}</label>
                <input id="password" v-model="form.password" type="password" class="input" dir="ltr" autocomplete="current-password" required>
            </div>
            <div class="flex items-center justify-between text-sm">
                <label class="flex items-center gap-2"><input v-model="form.remember" type="checkbox"> {{ t('Remember me') }}</label>
                <Link href="/forgot-password" class="text-accent">{{ t('Forgot your password?') }}</Link>
            </div>
            <button type="submit" class="btn-primary w-full" :disabled="form.processing">{{ t('Sign in') }}</button>
        </form>
    </GuestLayout>
</template>
