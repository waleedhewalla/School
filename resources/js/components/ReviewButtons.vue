<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useT } from '../lib/i18n';

// Approve, or reject with a reason, posting to `url`.
const props = defineProps({ url: { type: String, required: true } });
const t = useT();
const rejecting = ref(false);
const note = ref('');
const send = (status) => router.post(props.url, { status, note: note.value || null }, { preserveScroll: true });
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <template v-if="!rejecting">
            <button type="button" class="btn-primary px-3 py-1" @click="send('approved')">{{ t('Approve') }}</button>
            <button type="button" class="btn-ghost px-3 py-1" @click="rejecting = true">{{ t('Reject') }}</button>
        </template>
        <template v-else>
            <input v-model="note" class="input max-w-xs" :placeholder="t('Reason')" maxlength="300">
            <button type="button" class="btn-primary px-3 py-1" :disabled="!note" @click="send('rejected')">{{ t('Reject') }}</button>
            <button type="button" class="btn-ghost px-3 py-1" @click="rejecting = false">{{ t('Cancel') }}</button>
        </template>
    </div>
</template>
