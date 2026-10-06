<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import HealthCardForm from '../../components/HealthCardForm.vue';
import { useT } from '../../lib/i18n';

defineProps({ student: Object, record: Object, visits: Array, outcomes: Array });
const t = useT();
const time = (iso) => new Date(iso).toLocaleString(undefined, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
</script>

<template>
    <AppLayout :title="student.name">
        <Link href="/clinic" class="mb-4 inline-block text-sm text-muted hover:text-ink"><span class="rtl:hidden">←</span><span class="ltr:hidden">→</span> {{ t('Clinic') }}</Link>
        <section class="card mb-4">
            <h2 class="mb-4 font-semibold">{{ t('Health card') }}</h2>
            <HealthCardForm :record="record" :url="`/clinic/students/${student.id}`" />
        </section>
        <section class="card">
            <h2 class="mb-3 font-semibold">{{ t('Clinic visits') }}</h2>
            <ul class="divide-y divide-line text-sm">
                <li v-for="v in visits" :key="v.id" class="py-2">
                    <div class="flex flex-wrap gap-x-3"><span class="text-muted">{{ time(v.visited_at) }}</span><span class="font-medium">{{ v.complaint }}</span><span v-if="v.temperature" dir="ltr">{{ v.temperature }}°</span><span class="ms-auto">{{ t(`clinic.${v.outcome}`) }}</span></div>
                    <div v-if="v.treatment || v.by" class="text-muted">{{ [v.treatment, v.by].filter(Boolean).join(' — ') }}</div>
                </li>
                <li v-if="!visits.length" class="py-2 text-muted">{{ t('No visits.') }}</li>
            </ul>
        </section>
    </AppLayout>
</template>
