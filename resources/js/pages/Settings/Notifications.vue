<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import SettingsTabs from '../../components/SettingsTabs.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ settings: Object, codes: Array });
const t = useT();

const form = useForm({
    channels: { ...props.settings.channels },
    quiet_hours: { ...props.settings.quiet_hours },
    codes: props.codes.map(({ id, notify_guardian }) => ({ id, notify_guardian })),
});
</script>

<template>
    <AppLayout :title="t('Guardian notifications')">
        <SettingsTabs />
        <form class="max-w-2xl space-y-4" @submit.prevent="form.put('/settings/notifications', { preserveScroll: true })">
            <section class="card space-y-3">
                <h2 class="font-semibold">{{ t('Channels') }}</h2>
                <label class="flex items-center gap-2 text-sm"><input v-model="form.channels.sms" type="checkbox"> {{ t('SMS') }}</label>
                <label class="flex items-center gap-2 text-sm"><input v-model="form.channels.whatsapp" type="checkbox"> {{ t('WhatsApp') }}</label>
                <label class="flex items-center gap-2 text-sm"><input v-model="form.channels.email" type="checkbox"> {{ t('Email') }}</label>
            </section>

            <section class="card space-y-3">
                <h2 class="font-semibold">{{ t('Which statuses alert guardians') }}</h2>
                <label v-for="(c, i) in codes" :key="c.id" class="flex items-center gap-3 text-sm">
                    <input v-model="form.codes[i].notify_guardian" type="checkbox">
                    <StatusBadge :kind="c.kind" :label="c.name" />
                </label>
            </section>

            <section class="card space-y-3">
                <h2 class="font-semibold">{{ t('Quiet hours') }}</h2>
                <label class="flex items-center gap-2 text-sm"><input v-model="form.quiet_hours.enabled" type="checkbox"> {{ t('Hold alerts during quiet hours and send them when they end') }}</label>
                <div class="flex flex-wrap gap-4">
                    <div>
                        <label class="label" for="qs">{{ t('From') }}</label>
                        <input id="qs" v-model="form.quiet_hours.start" type="time" class="input" dir="ltr" :disabled="!form.quiet_hours.enabled">
                    </div>
                    <div>
                        <label class="label" for="qe">{{ t('To') }}</label>
                        <input id="qe" v-model="form.quiet_hours.end" type="time" class="input" dir="ltr" :disabled="!form.quiet_hours.enabled">
                    </div>
                </div>
            </section>

            <p v-if="Object.keys(form.errors).length" class="text-sm text-danger" role="alert">{{ Object.values(form.errors)[0] }}</p>
            <button type="submit" class="btn-primary" :disabled="form.processing">{{ t('Save changes') }}</button>
        </form>
    </AppLayout>
</template>
