<script setup>
import { useForm } from '@inertiajs/vue3';
import GuestLayout from '../../layouts/GuestLayout.vue';
import FieldError from '../../components/FieldError.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ token: String, email: String });
const t = useT();
const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
</script>

<template>
    <GuestLayout :title="t('Choose a new password')">
        <form class="card space-y-4" @submit.prevent="form.post('/reset-password')">
            <h1 class="text-lg font-semibold">{{ t('Choose a new password') }}</h1>
            <div>
                <label for="email" class="label">{{ t('Email') }}</label>
                <input id="email" v-model="form.email" type="email" class="input" dir="ltr" required>
                <FieldError :message="form.errors.email" />
            </div>
            <div>
                <label for="password" class="label">{{ t('New password') }}</label>
                <input id="password" v-model="form.password" type="password" class="input" dir="ltr" autocomplete="new-password" required>
                <FieldError :message="form.errors.password" />
            </div>
            <div>
                <label for="password_confirmation" class="label">{{ t('Confirm password') }}</label>
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="input" dir="ltr" autocomplete="new-password" required>
            </div>
            <button type="submit" class="btn-primary w-full" :disabled="form.processing">{{ t('Save password') }}</button>
        </form>
    </GuestLayout>
</template>
