<script setup>
import { reactive, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import { formatDate, useT } from '../../lib/i18n';

const props = defineProps({ applications: Object, counts: Object, windows: Array, statuses: Array, filters: Object });
const t = useT();

const filters = reactive({ status: props.filters.status ?? '', window: props.filters.window ?? '', q: props.filters.q ?? '' });
let timer;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get('/admissions', Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '')), { preserveState: true, replace: true }), 300);
});
const total = () => Object.values(props.counts).reduce((a, b) => a + Number(b), 0);
</script>

<template>
    <AppLayout :title="t('Admissions')">
        <div class="mb-4 flex flex-wrap gap-3">
            <Link href="/admissions/windows" class="btn-ghost">{{ t('Admission windows') }}</Link>
            <input v-model="filters.q" type="search" class="input max-w-xs" :placeholder="t('Search by reference, name or ID')">
            <select v-model="filters.window" class="input max-w-xs" :aria-label="t('Grade level')">
                <option value="">{{ t('All grades') }}</option>
                <option v-for="w in windows" :key="w.id" :value="w.id">{{ w.label }} ({{ t(':left of :seats seats left', { left: w.seats_left, seats: w.seats }) }})</option>
            </select>
        </div>

        <div class="mb-4 flex flex-wrap gap-2 text-sm" role="tablist">
            <button type="button" class="rounded-full px-3 py-1" :class="filters.status === '' ? 'bg-accent text-accent-ink' : 'border border-line'" @click="filters.status = ''">
                {{ t('All') }} <span class="tabular-nums">{{ total() }}</span>
            </button>
            <button
                v-for="s in statuses" :key="s" type="button" class="rounded-full px-3 py-1"
                :class="filters.status === s ? 'bg-accent text-accent-ink' : 'border border-line'"
                @click="filters.status = s"
            >{{ t(`admission.status.${s}`) }} <span class="tabular-nums">{{ counts[s] ?? 0 }}</span></button>
        </div>
        <p v-if="filters.status === 'waitlisted'" class="mb-3 text-sm text-muted">{{ t('Waitlist order: siblings first, then by submission time.') }}</p>

        <div class="card overflow-x-auto p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-line text-muted">
                    <tr>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Reference') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Student') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Grade level') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Submitted') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in applications.data" :key="a.id" class="border-b border-line last:border-0 hover:bg-surface">
                        <td class="px-4 py-3 tabular-nums" dir="ltr">{{ a.reference }}</td>
                        <td class="px-4 py-3">
                            <Link :href="`/admissions/${a.id}`" class="font-medium text-accent hover:underline">{{ a.student }}</Link>
                            <span v-if="a.has_sibling" class="ms-2 rounded bg-accent-soft px-1.5 text-xs text-accent">{{ t('Sibling') }}</span>
                            <span v-if="a.age_check === 'exception'" class="ms-2 rounded bg-warn-soft px-1.5 text-xs text-warn">{{ t('Age exception') }}</span>
                        </td>
                        <td class="px-4 py-3">{{ a.grade }}</td>
                        <td class="px-4 py-3">{{ formatDate(a.submitted_at, $page.props.locale) }}</td>
                        <td class="px-4 py-3"><StatusBadge :kind="a.status" :label="t(`admission.status.${a.status}`)" /></td>
                    </tr>
                    <tr v-if="!applications.data.length">
                        <td colspan="5" class="px-4 py-8 text-center text-muted">{{ t('No applications yet.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="applications.last_page > 1" class="mt-4 flex flex-wrap gap-1">
            <template v-for="link in applications.links" :key="link.label">
                <Link v-if="link.url" :href="link.url" class="rounded-lg px-3 py-1.5 text-sm" :class="link.active ? 'bg-accent text-accent-ink' : 'border border-line'" preserve-scroll><span v-html="link.label" /></Link>
            </template>
        </div>
    </AppLayout>
</template>
