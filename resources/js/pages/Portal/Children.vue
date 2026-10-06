<script setup>
import { usePage } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import { formatDate, useT } from '../../lib/i18n';

defineProps({ children: Array });
const t = useT();
const page = usePage();
</script>

<template>
    <AppLayout :title="t('My children')">
        <p v-if="!children.length" class="card text-muted">{{ t('No children are linked to your account yet. Please contact the school.') }}</p>

        <div class="grid gap-4 md:grid-cols-2">
            <section v-for="child in children" :key="child.id" class="card">
                <h2 class="text-lg font-semibold">{{ child.name }}</h2>
                <p class="mb-4 text-sm text-muted">{{ child.class || '—' }} · <span dir="ltr">{{ child.student_number }}</span></p>

                <h3 class="mb-2 text-sm font-semibold">{{ t('Recent attendance') }}</h3>
                <ul class="space-y-1 text-sm">
                    <li v-for="(r, i) in child.attendance" :key="i" class="flex items-center justify-between gap-2">
                        <span>{{ formatDate(r.date, page.props.locale) }}<span v-if="r.period" class="text-muted"> · {{ t('Period :n', { n: r.period }) }}</span></span>
                        <StatusBadge :kind="r.kind" :label="r.name" />
                    </li>
                    <li v-if="!child.attendance.length" class="text-muted">{{ t('No attendance recorded yet.') }}</li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
