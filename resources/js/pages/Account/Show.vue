<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import FieldError from '../../components/FieldError.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ twoFactor: Object, recoveryCodes: Array });
const t = useT();
const page = usePage();

const profile = useForm({ name: page.props.auth.user.name, locale: page.props.auth.user.locale ?? page.props.locale });
const password = useForm({ current_password: '', password: '', password_confirmation: '' });
const confirm = useForm({ code: '' });
const disable = useForm({ current_password: '' });
</script>

<template>
    <AppLayout :title="t('My account')">
        <div class="grid max-w-3xl gap-4">
            <form class="card space-y-3" @submit.prevent="profile.put('/account', { preserveScroll: true })">
                <h2 class="font-semibold">{{ t('Profile') }}</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label" for="name">{{ t('Name') }}</label>
                        <input id="name" v-model="profile.name" class="input" required>
                    </div>
                    <div>
                        <label class="label" for="locale">{{ t('Language') }}</label>
                        <select id="locale" v-model="profile.locale" class="input">
                            <option value="ar">العربية</option>
                            <option value="en">English</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn-primary" :disabled="profile.processing">{{ t('Save changes') }}</button>
            </form>

            <form class="card space-y-3" @submit.prevent="password.put('/account/password', { preserveScroll: true, onSuccess: () => password.reset() })">
                <h2 class="font-semibold">{{ t('Change password') }}</h2>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="label" for="cp">{{ t('Current password') }}</label>
                        <input id="cp" v-model="password.current_password" type="password" class="input" dir="ltr" autocomplete="current-password" required>
                        <FieldError :message="password.errors.current_password" />
                    </div>
                    <div>
                        <label class="label" for="np">{{ t('New password') }}</label>
                        <input id="np" v-model="password.password" type="password" class="input" dir="ltr" autocomplete="new-password" required>
                        <FieldError :message="password.errors.password" />
                    </div>
                    <div>
                        <label class="label" for="np2">{{ t('Confirm password') }}</label>
                        <input id="np2" v-model="password.password_confirmation" type="password" class="input" dir="ltr" autocomplete="new-password" required>
                    </div>
                </div>
                <button type="submit" class="btn-primary" :disabled="password.processing">{{ t('Change password') }}</button>
            </form>

            <section class="card space-y-3">
                <h2 class="font-semibold">{{ t('Two-factor sign-in') }}</h2>
                <p class="text-sm text-muted">{{ t('Adds a code from an authenticator app (Google Authenticator, Microsoft Authenticator…) when you sign in.') }}</p>

                <div v-if="recoveryCodes" class="rounded-lg bg-warn-soft p-3 text-sm">
                    <p class="mb-2 font-semibold text-warn">{{ t('Save these recovery codes somewhere safe. Each can be used once if you lose your phone. They will not be shown again.') }}</p>
                    <ul class="grid grid-cols-2 gap-1 font-mono" dir="ltr"><li v-for="c in recoveryCodes" :key="c">{{ c }}</li></ul>
                </div>

                <template v-if="twoFactor.enabled">
                    <p class="text-sm text-accent">{{ t('Two-factor sign-in is on.') }}</p>
                    <form class="flex flex-wrap items-end gap-3" @submit.prevent="disable.delete('/account/two-factor', { preserveScroll: true })">
                        <div>
                            <label class="label" for="dp">{{ t('Current password') }}</label>
                            <input id="dp" v-model="disable.current_password" type="password" class="input" dir="ltr" required>
                            <FieldError :message="disable.errors.current_password" />
                        </div>
                        <button type="submit" class="btn-ghost text-danger">{{ t('Turn off') }}</button>
                    </form>
                </template>
                <template v-else-if="twoFactor.pending">
                    <p class="text-sm">{{ t('Scan this code with your authenticator app, then enter the 6-digit code it shows.') }}</p>
                    <div class="inline-block rounded-lg bg-white p-2" v-html="twoFactor.qr" />
                    <p class="text-xs text-muted">{{ t('Or enter this key manually:') }} <span class="font-mono" dir="ltr">{{ twoFactor.secret }}</span></p>
                    <form class="flex flex-wrap items-end gap-3" @submit.prevent="confirm.post('/account/two-factor/confirm', { preserveScroll: true })">
                        <div>
                            <label class="label" for="code">{{ t('Verification code') }}</label>
                            <input id="code" v-model="confirm.code" class="input w-36 text-center" dir="ltr" inputmode="numeric" autocomplete="one-time-code" required>
                            <FieldError :message="confirm.errors.code" />
                        </div>
                        <button type="submit" class="btn-primary">{{ t('Confirm') }}</button>
                    </form>
                </template>
                <button v-else type="button" class="btn-primary" @click="router.post('/account/two-factor', {}, { preserveScroll: true })">{{ t('Turn on') }}</button>
            </section>
        </div>
    </AppLayout>
</template>
