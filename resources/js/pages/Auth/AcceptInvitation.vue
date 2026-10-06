<script setup>
import { useForm } from '@inertiajs/vue3';
import GuestLayout from '../../layouts/GuestLayout.vue';
import FieldError from '../../components/FieldError.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ token: String, school: String, name: String, email: String, hasAccount: Boolean });
const t = useT();
const form = useForm({ name: props.name, password: '', password_confirmation: '' });
</script>

<template>
    <GuestLayout :title="t('Join :school', { school })">
        <form class="card space-y-4" @submit.prevent="form.post(`/invitations/${token}`)">
            <h1 class="text-lg font-semibold">{{ t('Join :school', { school }) }}</h1>
            <p class="text-sm text-muted" dir="ltr">{{ email }}</p>
            <template v-if="hasAccount">
                <p class="text-sm">{{ t('You already have an account. Enter its password to join this school.') }}</p>
                <div>
                    <label for="password" class="label">{{ t('Password') }}</label>
                    <input id="password" v-model="form.password" type="password" class="input" dir="ltr" required>
                    <FieldError :message="form.errors.password" />
                </div>
            </template>
            <template v-else>
                <div>
                    <label for="name" class="label">{{ t('Name') }}</label>
                    <input id="name" v-model="form.name" class="input" required>
                    <FieldError :message="form.errors.name" />
                </div>
                <div>
                    <label for="password" class="label">{{ t('Choose a password') }}</label>
                    <input id="password" v-model="form.password" type="password" class="input" dir="ltr" autocomplete="new-password" required>
                    <FieldError :message="form.errors.password" />
                </div>
                <div>
                    <label for="password_confirmation" class="label">{{ t('Confirm password') }}</label>
                    <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="input" dir="ltr" autocomplete="new-password" required>
                </div>
            </template>
            <button type="submit" class="btn-primary w-full" :disabled="form.processing">{{ t('Accept invitation') }}</button>
        </form>
    </GuestLayout>
</template>
