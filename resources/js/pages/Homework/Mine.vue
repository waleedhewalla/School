<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import HomeworkCard from '../../components/HomeworkCard.vue';
import { useT } from '../../lib/i18n';

defineProps({ children: Array });
const t = useT();
</script>

<template>
    <AppLayout :title="t('Homework')">
        <p v-if="!children.length" class="card text-muted">{{ t('No children are linked to your account.') }}</p>
        <section v-for="child in children" :key="child.id" class="mb-6">
            <h2 class="mb-3 text-lg font-semibold">{{ child.name }}</h2>
            <div class="space-y-3">
                <HomeworkCard v-for="h in child.homework" :key="h.id" :homework="h" />
                <p v-if="!child.homework.length" class="card text-center text-muted">{{ t('No homework right now.') }}</p>
            </div>
        </section>
    </AppLayout>
</template>
