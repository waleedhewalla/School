<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import FieldError from '../../components/FieldError.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import { formatDate, useT } from '../../lib/i18n';

const props = defineProps({ children: Array, excuses: Array });
const t = useT();
const today = new Date().toISOString().slice(0, 10);
const form = useForm({ student_id: props.children[0]?.id ?? '', from_date: today, to_date: today, reason: '', attachment: null });
const save = () => form.post('/my/excuses', { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset('reason', 'attachment') });
</script>

<template>
    <AppLayout :title="t('Absence excuses')">
        <section v-if="children.length" class="card mb-4">
            <h2 class="mb-1 font-semibold">{{ t('Excuse an absence') }}</h2>
            <p class="mb-4 text-sm text-muted">{{ t('Once the school approves it, the absence is recorded as excused. Attach a medical report if you have one.') }}</p>
            <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="save">
                <div>
                    <label class="label" for="child">{{ t('Child') }}</label>
                    <select id="child" v-model="form.student_id" class="input"><option v-for="c in children" :key="c.id" :value="c.id">{{ c.name }}</option></select>
                </div>
                <div><label class="label" for="from">{{ t('From') }}</label><input id="from" v-model="form.from_date" type="date" class="input" dir="ltr" required><FieldError :message="form.errors.from_date" /></div>
                <div><label class="label" for="to">{{ t('To') }}</label><input id="to" v-model="form.to_date" type="date" class="input" dir="ltr" required><FieldError :message="form.errors.to_date" /></div>
                <div><label class="label" for="file">{{ t('Attachment (optional)') }}</label><input id="file" type="file" class="input" accept=".pdf,.jpg,.jpeg,.png" @input="form.attachment = $event.target.files[0]"><FieldError :message="form.errors.attachment" /></div>
                <div class="sm:col-span-2 lg:col-span-3"><label class="label" for="reason">{{ t('Reason') }}</label><input id="reason" v-model="form.reason" class="input" required maxlength="1000"><FieldError :message="form.errors.reason" /></div>
                <div class="flex items-end"><button class="btn-primary w-full" :disabled="form.processing">{{ t('Send') }}</button></div>
            </form>
        </section>
        <p v-else class="card mb-4 text-muted">{{ t('No children are linked to your account.') }}</p>

        <div class="space-y-3">
            <article v-for="e in excuses" :key="e.id" class="card">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="font-semibold">{{ e.student }}</span>
                    <span class="text-sm text-muted">{{ formatDate(e.from_date, $page.props.locale) }} – {{ formatDate(e.to_date, $page.props.locale) }}</span>
                    <StatusBadge class="ms-auto" :kind="e.status === 'approved' ? 'accepted' : e.status" :label="t(`request.${e.status}`)" />
                </div>
                <p class="mt-2 text-sm">{{ e.reason }}</p>
                <p v-if="e.review_note" class="mt-1 text-sm text-muted">{{ t('School note') }}: {{ e.review_note }}</p>
            </article>
        </div>
    </AppLayout>
</template>
