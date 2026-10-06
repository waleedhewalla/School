<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import FieldError from '../../components/FieldError.vue';
import ReviewButtons from '../../components/ReviewButtons.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import { formatDate, useT } from '../../lib/i18n';

defineProps({ pending: Array, recent: Array, awayToday: Array });
const t = useT();
</script>

<template>
    <AppLayout :title="t('Staff leave')">
        <FieldError :message="$page.props.errors?.status" />
        <div v-if="awayToday.length" class="mb-4 rounded-lg bg-warn-soft px-4 py-3 text-sm text-warn">
            {{ t('Away today:') }}
            <span v-for="a in awayToday" :key="a.name" class="ms-2 font-semibold">{{ a.name }} ({{ t(`leave.${a.type}`) }})</span>
        </div>

        <h2 class="mb-3 font-semibold">{{ t('Waiting for review') }} <span class="text-muted tabular-nums">({{ pending.length }})</span></h2>
        <div class="mb-6 space-y-3">
            <article v-for="l in pending" :key="l.id" class="card">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="font-semibold">{{ l.staff }}</span>
                    <span class="rounded bg-surface px-2 text-sm">{{ t(`leave.${l.type}`) }}</span>
                    <span class="text-sm">{{ formatDate(l.from_date, $page.props.locale) }} – {{ formatDate(l.to_date, $page.props.locale) }} ({{ t(':count days', { count: l.days }) }})</span>
                </div>
                <p v-if="l.reason" class="mt-2 text-sm">{{ l.reason }}</p>
                <div class="mt-2 flex flex-wrap items-center gap-3 text-sm">
                    <a v-if="l.attachment" :href="l.attachment.url" class="text-accent hover:underline" dir="auto">📎 {{ l.attachment.name }}</a>
                    <ReviewButtons class="ms-auto" :url="`/leave/${l.id}/review`" />
                </div>
            </article>
            <p v-if="!pending.length" class="card text-center text-muted">{{ t('Nothing waiting.') }}</p>
        </div>

        <h2 class="mb-3 font-semibold">{{ t('Recently reviewed') }}</h2>
        <div class="card overflow-x-auto p-0">
            <table class="w-full text-sm">
                <tbody>
                    <tr v-for="l in recent" :key="l.id" class="border-b border-line last:border-0">
                        <td class="px-4 py-3 font-medium">{{ l.staff }}</td>
                        <td class="px-4 py-3">{{ t(`leave.${l.type}`) }}</td>
                        <td class="px-4 py-3">{{ formatDate(l.from_date, $page.props.locale) }} – {{ formatDate(l.to_date, $page.props.locale) }}</td>
                        <td class="px-4 py-3"><StatusBadge :kind="l.status === 'approved' ? 'accepted' : l.status" :label="t(`request.${l.status}`)" /></td>
                        <td class="px-4 py-3 text-muted">{{ l.reviewer }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
