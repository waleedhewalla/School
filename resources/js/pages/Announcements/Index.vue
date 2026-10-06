<script setup>
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import AnnouncementList from '../../components/AnnouncementList.vue';
import FieldError from '../../components/FieldError.vue';
import { useT } from '../../lib/i18n';

defineProps({ announcements: Array, canManage: Boolean, sections: Array, smsEnabled: Boolean });
const t = useT();

const form = useForm({ title: '', body: '', audience: 'guardians', section_id: '', send_sms: false });
const submit = () => form
    .transform((d) => ({ ...d, section_id: d.audience === 'staff' ? null : d.section_id || null, send_sms: d.audience !== 'staff' && d.send_sms }))
    .post('/announcements', { preserveScroll: true, onSuccess: () => form.reset() });
const remove = (a) => { if (confirm(t('Delete this announcement?'))) router.delete(`/announcements/${a.id}`, { preserveScroll: true }); };
</script>

<template>
    <AppLayout :title="t('Announcements')">
        <div class="grid gap-4 lg:grid-cols-3">
            <form v-if="canManage" class="card space-y-3 lg:order-2" @submit.prevent="submit">
                <h2 class="font-semibold">{{ t('New announcement') }}</h2>
                <div>
                    <label class="label" for="a-title">{{ t('Title') }}</label>
                    <input id="a-title" v-model="form.title" class="input" required maxlength="150">
                    <FieldError :message="form.errors.title" />
                </div>
                <div>
                    <label class="label" for="a-body">{{ t('Message') }}</label>
                    <textarea id="a-body" v-model="form.body" class="input min-h-32" required maxlength="5000" />
                    <FieldError :message="form.errors.body" />
                </div>
                <div>
                    <label class="label" for="a-aud">{{ t('Audience') }}</label>
                    <select id="a-aud" v-model="form.audience" class="input">
                        <option value="guardians">{{ t('audience.guardians') }}</option>
                        <option value="staff">{{ t('audience.staff') }}</option>
                        <option value="everyone">{{ t('audience.everyone') }}</option>
                    </select>
                </div>
                <div v-if="form.audience !== 'staff'">
                    <label class="label" for="a-sec">{{ t('Only one section') }}</label>
                    <select id="a-sec" v-model="form.section_id" class="input">
                        <option value="">{{ t('Whole school') }}</option>
                        <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.label }}</option>
                    </select>
                </div>
                <label v-if="form.audience !== 'staff'" class="flex items-center gap-2 text-sm" :class="smsEnabled ? '' : 'text-muted'">
                    <input v-model="form.send_sms" type="checkbox" :disabled="!smsEnabled"> {{ t('Also send by SMS to primary guardians') }}
                </label>
                <button type="submit" class="btn-primary" :disabled="form.processing">{{ t('Publish') }}</button>
            </form>

            <section class="card lg:col-span-2 lg:order-1">
                <AnnouncementList :items="announcements" :deletable="canManage" @delete="remove" />
            </section>
        </div>
    </AppLayout>
</template>
