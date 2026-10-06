<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { useT } from '../../lib/i18n';

const t = useT();
const page = usePage();
const form = useForm({ email: '', password: '', remember: false });

const submit = () => form.post('/login', { onFinish: () => form.reset('password') });
</script>

<template>
    <Head :title="t('Sign in')" />
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-sm">
            <div class="mb-6 flex items-center justify-between">
                <span class="text-xl font-semibold text-accent">{{ t('Madrasa') }}</span>
                <a :href="`/locale/${page.props.locale === 'ar' ? 'en' : 'ar'}`" class="text-sm text-muted">
                    {{ page.props.locale === 'ar' ? 'English' : 'العربية' }}
                </a>
            </div>

            <form class="card space-y-4" @submit.prevent="submit">
                <h1 class="text-lg font-semibold">{{ t('Sign in') }}</h1>

                <div>
                    <label for="email" class="label">{{ t('Email') }}</label>
                    <input id="email" v-model="form.email" type="email" class="input" dir="ltr" autocomplete="username" required autofocus>
                    <p v-if="form.errors.email" class="mt-1 text-sm text-danger">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label for="password" class="label">{{ t('Password') }}</label>
                    <input id="password" v-model="form.password" type="password" class="input" dir="ltr" autocomplete="current-password" required>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.remember" type="checkbox"> {{ t('Remember me') }}
                </label>

                <button type="submit" class="btn-primary w-full" :disabled="form.processing">{{ t('Sign in') }}</button>
            </form>
        </div>
    </div>
</template>
