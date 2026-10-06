<script setup>
import { reactive } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import FieldError from '../../components/FieldError.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ members: Array, invitations: Array, roles: Array });
const t = useT();
const page = usePage();

const invite = useForm({ name: '', email: '', roles: ['teacher'] });
const sendInvite = () => invite.post('/users/invitations', { preserveScroll: true, onSuccess: () => invite.reset() });
const editing = reactive({});
const saveRoles = (m) => router.patch(`/users/${m.user_id}`, { roles: editing[m.user_id] }, { preserveScroll: true, onSuccess: () => { delete editing[m.user_id]; } });
const setStatus = (m, status) => router.patch(`/users/${m.user_id}`, { status }, { preserveScroll: true });
const revoke = (i) => router.delete(`/users/invitations/${i.id}`, { preserveScroll: true });
</script>

<template>
    <AppLayout :title="t('Users')">
        <div class="grid gap-4 lg:grid-cols-3">
            <section class="card overflow-x-auto p-0 lg:col-span-2">
                <table class="w-full text-sm">
                    <thead class="border-b border-line text-muted">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium">{{ t('Name') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ t('Roles') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ t('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="m in members" :key="m.user_id" class="border-b border-line last:border-0 align-top">
                            <td class="px-4 py-2">
                                <div class="font-medium">{{ m.name }}</div>
                                <div class="text-xs text-muted" dir="ltr">{{ m.email }}</div>
                            </td>
                            <td class="px-4 py-2">
                                <template v-if="editing[m.user_id]">
                                    <label v-for="r in roles" :key="r" class="me-3 inline-flex items-center gap-1 text-xs">
                                        <input v-model="editing[m.user_id]" type="checkbox" :value="r"> {{ t(`role.${r}`) }}
                                    </label>
                                    <div class="mt-1 flex gap-2">
                                        <button type="button" class="btn-primary py-1 text-xs" @click="saveRoles(m)">{{ t('Save') }}</button>
                                        <button type="button" class="text-xs text-muted" @click="delete editing[m.user_id]">{{ t('Cancel') }}</button>
                                    </div>
                                </template>
                                <template v-else>
                                    {{ m.roles.map((r) => t(`role.${r}`)).join('، ') || '—' }}
                                    <button type="button" class="ms-2 text-xs text-accent" @click="editing[m.user_id] = [...m.roles]">{{ t('Edit') }}</button>
                                </template>
                                <span v-if="m.two_factor" class="ms-2 text-xs text-muted">· 2FA</span>
                            </td>
                            <td class="px-4 py-2">
                                <span :class="m.status === 'active' ? 'text-accent' : 'text-muted'">{{ t(`member.${m.status}`) }}</span>
                                <button v-if="m.user_id !== page.props.auth.user.id" type="button" class="ms-2 text-xs text-muted hover:text-ink" @click="setStatus(m, m.status === 'active' ? 'inactive' : 'active')">
                                    {{ m.status === 'active' ? t('Deactivate') : t('Reactivate') }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="page.props.errors?.roles" class="px-4 py-2 text-sm text-danger">{{ page.props.errors.roles }}</p>
            </section>

            <div class="space-y-4">
                <form class="card space-y-3" @submit.prevent="sendInvite">
                    <h2 class="font-semibold">{{ t('Invite someone') }}</h2>
                    <div>
                        <label class="label" for="i-name">{{ t('Name') }}</label>
                        <input id="i-name" v-model="invite.name" class="input" required>
                    </div>
                    <div>
                        <label class="label" for="i-email">{{ t('Email') }}</label>
                        <input id="i-email" v-model="invite.email" type="email" class="input" dir="ltr" required>
                        <FieldError :message="invite.errors.email" />
                    </div>
                    <fieldset>
                        <legend class="label">{{ t('Roles') }}</legend>
                        <label v-for="r in roles" :key="r" class="me-3 inline-flex items-center gap-1 text-sm">
                            <input v-model="invite.roles" type="checkbox" :value="r"> {{ t(`role.${r}`) }}
                        </label>
                        <FieldError :message="invite.errors.roles" />
                    </fieldset>
                    <button type="submit" class="btn-primary" :disabled="invite.processing">{{ t('Send invitation') }}</button>
                </form>

                <section v-if="invitations.length" class="card">
                    <h2 class="mb-2 font-semibold">{{ t('Pending invitations') }}</h2>
                    <ul class="divide-y divide-line text-sm">
                        <li v-for="i in invitations" :key="i.id" class="flex items-center justify-between gap-2 py-2">
                            <span>{{ i.name }} <span class="text-xs text-muted" dir="ltr">{{ i.email }}</span></span>
                            <button type="button" class="text-xs text-danger" @click="revoke(i)">{{ t('Cancel') }}</button>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
