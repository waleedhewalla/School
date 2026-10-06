<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import HealthCardForm from '../../components/HealthCardForm.vue';
import { formatDate, useT } from '../../lib/i18n';

defineProps({ children: Array });
const t = useT();
</script>

<template>
    <AppLayout :title="t('Health information')">
        <p class="mb-4 text-sm text-muted">{{ t('Tell the school nurse about allergies, conditions and medication. Only the clinic staff and you can see this.') }}</p>
        <p v-if="!children.length" class="card text-muted">{{ t('No children are linked to your account.') }}</p>
        <section v-for="child in children" :key="child.id" class="card mb-4">
            <h2 class="mb-4 text-lg font-semibold">{{ child.name }}</h2>
            <HealthCardForm :record="child.record" :url="`/my/health/${child.id}`" />
            <template v-if="child.visits.length">
                <h3 class="mb-2 mt-6 text-sm font-semibold">{{ t('Recent clinic visits') }}</h3>
                <ul class="space-y-1 text-sm">
                    <li v-for="(v, i) in child.visits" :key="i">{{ formatDate(v.visited_at, $page.props.locale) }} · {{ v.complaint }} · <span class="text-muted">{{ t(`clinic.${v.outcome}`) }}</span></li>
                </ul>
            </template>
        </section>
    </AppLayout>
</template>
