<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import FieldError from '../../components/FieldError.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import { formatDate, useT } from '../../lib/i18n';

defineProps({ hasStaffRecord: Boolean, types: Array, requests: Array, takenThisYear: [Object, Array] });
const t = useT();
const today = new Date().toISOString().slice(0, 10);
const form = useForm({ type: 'annual', from_date: today, to_date: today, reason: '', attachment: null });
const save = () => form.post('/my/leave', { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset('reason', 'attachment') });
</script>

<template>
    <AppLayout :title="t('My leave')">
        <p v-if="!hasStaffRecord" class="card text-muted">{{ t('Your account is not linked to a staff record; contact the office.') }}</p>
        <template v-else>
            <div v-if="Object.keys(takenThisYear).length" class="mb-4 flex flex-wrap gap-3 text-sm">
                <span v-for="(days, type) in takenThisYear" :key="type" class="rounded-lg bg-surface px-3 py-1.5">{{ t(`leave.${type}`) }}: <strong class="tabular-nums">{{ days }}</strong> {{ t('days') }}</span>
            </div>
            <section class="card mb-4">
                <h2 class="mb-4 font-semibold">{{ t('Request leave') }}</h2>
                <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="save">
                    <div>
                        <label class="label" for="type">{{ t('Type') }}</label>
                        <select id="type" v-model="form.type" class="input"><option v-for="ty in types" :key="ty" :value="ty">{{ t(`leave.${ty}`) }}</option></select>
                        <FieldError :message="form.errors.type" />
                    </div>
                    <div><label class="label" for="from">{{ t('From') }}</label><input id="from" v-model="form.from_date" type="date" class="input" dir="ltr" required><FieldError :message="form.errors.from_date" /></div>
                    <div><label class="label" for="to">{{ t('To') }}</label><input id="to" v-model="form.to_date" type="date" class="input" dir="ltr" required><FieldError :message="form.errors.to_date" /></div>
                    <div><label class="label" for="file">{{ t('Attachment (optional)') }}</label><input id="file" type="file" class="input" accept=".pdf,.jpg,.jpeg,.png" @input="form.attachment = $event.target.files[0]"></div>
                    <div class="sm:col-span-2 lg:col-span-3"><label class="label" for="reason">{{ t('Reason') }}</label><input id="reason" v-model="form.reason" class="input" maxlength="1000"><FieldError :message="form.errors.reason" /></div>
                    <div class="flex items-end"><button class="btn-primary w-full" :disabled="form.processing">{{ t('Send') }}</button></div>
                </form>
            </section>
            <div class="card overflow-x-auto p-0">
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-for="l in requests" :key="l.id" class="border-b border-line last:border-0">
                            <td class="px-4 py-3 font-medium">{{ t(`leave.${l.type}`) }}</td>
                            <td class="px-4 py-3">{{ formatDate(l.from_date, $page.props.locale) }} – {{ formatDate(l.to_date, $page.props.locale) }} <span class="text-muted">({{ l.days }})</span></td>
                            <td class="px-4 py-3"><StatusBadge :kind="l.status === 'approved' ? 'accepted' : l.status" :label="t(`request.${l.status}`)" /></td>
                            <td class="px-4 py-3 text-muted">{{ l.review_note }}</td>
                        </tr>
                        <tr v-if="!requests.length"><td class="px-4 py-8 text-center text-muted">{{ t('No requests yet.') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </template>
    </AppLayout>
</template>
