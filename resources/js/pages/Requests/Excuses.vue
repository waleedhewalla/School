<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import FieldError from '../../components/FieldError.vue';
import ReviewButtons from '../../components/ReviewButtons.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import { formatDate, useT } from '../../lib/i18n';

defineProps({ pending: Array, recent: Array });
const t = useT();
</script>

<template>
    <AppLayout :title="t('Absence excuses')">
        <FieldError :message="$page.props.errors?.status" />
        <h2 class="mb-3 font-semibold">{{ t('Waiting for review') }} <span class="text-muted tabular-nums">({{ pending.length }})</span></h2>
        <div class="mb-6 space-y-3">
            <article v-for="e in pending" :key="e.id" class="card">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="font-semibold">{{ e.student }}</span>
                    <span v-if="e.class" class="text-sm text-muted">{{ e.class }}</span>
                    <span class="text-sm">{{ formatDate(e.from_date, $page.props.locale) }} – {{ formatDate(e.to_date, $page.props.locale) }}</span>
                </div>
                <p class="mt-2 text-sm">{{ e.reason }}</p>
                <div class="mt-2 flex flex-wrap items-center gap-3 text-sm">
                    <a v-if="e.attachment" :href="e.attachment.url" class="text-accent hover:underline" dir="auto">📎 {{ e.attachment.name }}</a>
                    <span v-if="e.by" class="text-muted">{{ t('Sent by :name', { name: e.by }) }}</span>
                    <ReviewButtons class="ms-auto" :url="`/excuses/${e.id}/review`" />
                </div>
            </article>
            <p v-if="!pending.length" class="card text-center text-muted">{{ t('Nothing waiting.') }}</p>
        </div>

        <h2 class="mb-3 font-semibold">{{ t('Recently reviewed') }}</h2>
        <div class="card overflow-x-auto p-0">
            <table class="w-full text-sm">
                <tbody>
                    <tr v-for="e in recent" :key="e.id" class="border-b border-line last:border-0">
                        <td class="px-4 py-3 font-medium">{{ e.student }}</td>
                        <td class="px-4 py-3">{{ formatDate(e.from_date, $page.props.locale) }} – {{ formatDate(e.to_date, $page.props.locale) }}</td>
                        <td class="px-4 py-3 text-muted">{{ e.reason }}</td>
                        <td class="px-4 py-3"><StatusBadge :kind="e.status === 'approved' ? 'accepted' : e.status" :label="t(`request.${e.status}`)" /></td>
                        <td class="px-4 py-3 text-muted">{{ e.reviewer }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
