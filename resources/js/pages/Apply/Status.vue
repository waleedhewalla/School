<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import PublicLayout from '../../layouts/PublicLayout.vue';
import FieldError from '../../components/FieldError.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import { formatDate, ltr, useT } from '../../lib/i18n';

const props = defineProps({ schoolName: String, token: String, application: Object, documents: Array });
const t = useT();
const base = window.location.pathname;

const upload = useForm({ type: '', file: null });
const uploading = ref(null);
const pick = (type, event) => {
    upload.type = type;
    upload.file = event.target.files[0];
    uploading.value = type;
    upload.post(`${base}/documents`, { forceFormData: true, preserveScroll: true, onFinish: () => { uploading.value = null; event.target.value = ''; } });
};

const respond = (action) => {
    const question = action === 'accept' ? t('Accept the offered place?') : t('Withdraw this application? This cannot be undone.');
    if (confirm(question)) router.post(`${base}/respond`, { action }, { preserveScroll: true });
};
</script>

<template>
    <PublicLayout :title="t('Application :reference', { reference: ltr(application.reference) })" :school-name="schoolName">
        <section class="card mb-4">
            <div class="flex flex-wrap items-center gap-3">
                <div>
                    <div class="text-lg font-semibold">{{ application.student }}</div>
                    <div class="text-sm text-muted">{{ application.grade }} — {{ application.year }} · {{ t('Submitted :date', { date: formatDate(application.submitted_at, $page.props.locale) }) }}</div>
                </div>
                <StatusBadge class="ms-auto" :kind="application.status" :label="t(`admission.status.${application.status}`)" />
            </div>
            <p class="mt-4 text-sm">{{ t(`admission.explain.${application.status}`) }}</p>
            <p v-if="application.assessment_at" class="mt-2 text-sm font-semibold" dir="auto">
                {{ t('Interview: :time', { time: application.assessment_at }) }}
            </p>
            <div v-if="application.can_accept || application.can_withdraw" class="mt-4 flex flex-wrap gap-3">
                <button v-if="application.can_accept" type="button" class="btn-primary" @click="respond('accept')">{{ t('Accept the place') }}</button>
                <button v-if="application.can_withdraw" type="button" class="btn-ghost" @click="respond('withdraw')">{{ t('Withdraw application') }}</button>
            </div>
        </section>

        <section class="card">
            <h2 class="mb-1 font-semibold">{{ t('Documents') }}</h2>
            <p class="mb-4 text-sm text-muted">{{ t('PDF, JPG or PNG, up to 5 MB each.') }}</p>
            <ul class="divide-y divide-line">
                <li v-for="doc in documents" :key="doc.type" class="flex flex-wrap items-center gap-3 py-3">
                    <div class="min-w-0 flex-1">
                        <div class="font-medium">{{ t(`document.${doc.type}`) }}</div>
                        <div v-if="doc.name" class="truncate text-sm text-muted" dir="auto">{{ doc.name }}</div>
                        <div v-if="doc.status === 'rejected'" class="text-sm text-danger">{{ t('Please upload again: :reason', { reason: doc.reason }) }}</div>
                    </div>
                    <StatusBadge v-if="doc.status" :kind="doc.status" :label="t(`document.status.${doc.status}`)" />
                    <label v-if="application.can_upload && doc.status !== 'accepted'" class="btn-ghost cursor-pointer">
                        {{ uploading === doc.type ? t('Uploading…') : (doc.status ? t('Replace') : t('Upload')) }}
                        <input type="file" class="sr-only" accept=".pdf,.jpg,.jpeg,.png" :disabled="upload.processing" @change="pick(doc.type, $event)">
                    </label>
                </li>
            </ul>
            <FieldError :message="upload.errors.file || upload.errors.type" />
        </section>

        <p class="mt-4 text-sm text-muted">{{ t('Keep this page\'s link private: anyone with it can see and change this application.') }}</p>
    </PublicLayout>
</template>
