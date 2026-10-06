<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import FieldError from '../../components/FieldError.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import { formatDate, ltr, useT } from '../../lib/i18n';

const props = defineProps({ application: Object, documents: Array, events: Array, duplicates: Array, sections: Array });
const t = useT();
const a = computed(() => props.application);

const move = useForm({ status: '', note: '', assessment_at: props.application.assessment_at ?? '' });
const choose = (status) => { move.status = move.status === status ? '' : status; };
const submitMove = () => move.transform((d) => ({ ...d, assessment_at: d.status === 'assessment_scheduled' ? d.assessment_at : null }))
    .post(`/admissions/${a.value.id}/status`, { preserveScroll: true, onSuccess: () => move.reset('status', 'note') });

const notes = useForm({ staff_note: props.application.staff_note ?? '', noor_transfer_done: props.application.noor_transfer_done });
const saveNotes = () => notes.patch(`/admissions/${a.value.id}`, { preserveScroll: true });

const rejecting = ref(null);
const reason = ref('');
const review = (doc, status) => {
    router.patch(`/admission-documents/${doc.id}`, { status, reason: status === 'rejected' ? reason.value : null }, {
        preserveScroll: true,
        onSuccess: () => { rejecting.value = null; reason.value = ''; },
    });
};

const enrol = useForm({ section_id: '' });
const submitEnrol = () => enrol.transform((d) => ({ section_id: d.section_id || null })).post(`/admissions/${a.value.id}/enrol`);

const staffMoves = computed(() => a.value.next.filter((s) => s !== 'enrolled'));
const missingDocs = computed(() => props.documents.filter((d) => d.status !== 'accepted').length);
</script>

<template>
    <AppLayout :title="`${a.student} — ${ltr(a.reference)}`">
        <Link href="/admissions" class="mb-4 inline-block text-sm text-muted hover:text-ink"><span class="rtl:hidden">←</span><span class="ltr:hidden">→</span> {{ t('Admissions') }}</Link>

        <div v-if="duplicates.length" class="mb-4 rounded-lg bg-warn-soft px-4 py-3 text-sm text-warn" role="alert">
            {{ t('The same ID is already known to the school:') }}
            <Link v-for="d in duplicates" :key="d.url" :href="d.url" class="ms-2 font-semibold underline">{{ t(d.kind === 'student' ? 'Student' : 'Application') }}: {{ d.label }}</Link>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <section class="card">
                    <div class="mb-4 flex flex-wrap items-center gap-3">
                        <h2 class="font-semibold">{{ a.grade }} — {{ a.year }}</h2>
                        <StatusBadge :kind="a.status" :label="t(`admission.status.${a.status}`)" />
                        <span v-if="a.has_sibling" class="rounded bg-accent-soft px-1.5 text-xs text-accent">{{ t('Sibling') }}</span>
                        <span class="ms-auto text-sm text-muted">{{ t(':count seats left', { count: a.seats_left }) }}</span>
                    </div>
                    <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-muted">{{ t('Name') }}</dt><dd>{{ a.student }}<span v-if="a.name_en" class="block text-muted" dir="ltr">{{ a.name_en }}</span></dd></div>
                        <div><dt class="text-muted">{{ t('Gender') }}</dt><dd>{{ t(`gender.${a.gender}`) }}</dd></div>
                        <div>
                            <dt class="text-muted">{{ t('Date of birth') }}</dt>
                            <dd>
                                {{ formatDate(a.date_of_birth, $page.props.locale) }}
                                <span v-if="a.age_check === 'exception'" class="ms-1 rounded bg-warn-soft px-1.5 text-xs text-warn">{{ t('Age exception') }}</span>
                            </dd>
                            <dd v-if="a.born_from && a.born_to" class="text-xs text-muted">{{ t('Grade range: :from – :to', { from: formatDate(a.born_from, $page.props.locale), to: formatDate(a.born_to, $page.props.locale) }) }}</dd>
                        </div>
                        <div><dt class="text-muted">{{ t('National ID / Iqama') }}</dt><dd dir="ltr" class="text-start tabular-nums">{{ a.national_id ?? '—' }} · {{ a.nationality }}</dd></div>
                        <div><dt class="text-muted">{{ t('Current school') }}</dt><dd>{{ a.current_school ?? '—' }}<span v-if="a.from_private_school" class="text-muted"> ({{ t('private') }})</span></dd></div>
                        <div><dt class="text-muted">{{ t('Submitted') }}</dt><dd>{{ formatDate(a.submitted_at, $page.props.locale) }}</dd></div>
                        <div><dt class="text-muted">{{ t('Guardian') }}</dt><dd>{{ a.guardian_name }} <span class="text-muted">({{ t(`relationship.${a.guardian_relationship}`) }})</span></dd></div>
                        <div>
                            <dt class="text-muted">{{ t('Contact') }}</dt>
                            <dd dir="ltr" class="text-start tabular-nums">{{ a.guardian_phone }}</dd>
                            <dd v-if="a.guardian_email" dir="ltr" class="text-start">{{ a.guardian_email }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="card">
                    <h2 class="mb-4 font-semibold">{{ t('Documents') }} <span v-if="missingDocs" class="text-sm font-normal text-muted">— {{ t(':count not accepted yet', { count: missingDocs }) }}</span></h2>
                    <ul class="divide-y divide-line">
                        <li v-for="doc in documents" :key="doc.type" class="py-3">
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium">{{ t(`document.${doc.type}`) }}</div>
                                    <a v-if="doc.id" :href="`/admission-documents/${doc.id}`" class="truncate text-sm text-accent hover:underline" dir="auto">{{ doc.name }}</a>
                                    <div v-else class="text-sm text-muted">{{ t('Not uploaded') }}</div>
                                    <div v-if="doc.status === 'rejected'" class="text-sm text-danger">{{ doc.reason }}</div>
                                </div>
                                <StatusBadge v-if="doc.status" :kind="doc.status" :label="t(`document.status.${doc.status}`)" />
                                <template v-if="doc.id && doc.status !== 'accepted'">
                                    <button type="button" class="btn-ghost px-3 py-1" @click="review(doc, 'accepted')">{{ t('Accept') }}</button>
                                    <button v-if="doc.status !== 'rejected'" type="button" class="btn-ghost px-3 py-1" @click="rejecting = doc.type">{{ t('Reject') }}</button>
                                </template>
                            </div>
                            <div v-if="rejecting === doc.type" class="mt-2 flex gap-2">
                                <input v-model="reason" class="input" :placeholder="t('Reason shown to the family')" maxlength="200">
                                <button type="button" class="btn-primary" :disabled="!reason" @click="review(doc, 'rejected')">{{ t('Reject') }}</button>
                            </div>
                        </li>
                    </ul>
                </section>

                <section class="card">
                    <h2 class="mb-4 font-semibold">{{ t('History') }}</h2>
                    <ol class="space-y-3 text-sm">
                        <li v-for="(e, i) in events" :key="i" class="flex flex-wrap gap-x-3">
                            <span class="tabular-nums text-muted" dir="ltr">{{ e.at }}</span>
                            <span>{{ t(`admission.status.${e.to}`) }}</span>
                            <span v-if="e.by" class="text-muted">— {{ e.by }}</span>
                            <span v-if="e.note" class="w-full text-muted">{{ e.note }}</span>
                        </li>
                    </ol>
                </section>
            </div>

            <div class="space-y-4">
                <section v-if="staffMoves.length" class="card">
                    <h2 class="mb-3 font-semibold">{{ t('Next step') }}</h2>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="s in staffMoves" :key="s" type="button"
                            class="rounded-lg px-3 py-1.5 text-sm" :class="move.status === s ? 'bg-accent text-accent-ink' : 'border border-line hover:bg-surface'"
                            @click="choose(s)"
                        >{{ t(`admission.action.${s}`) }}</button>
                    </div>
                    <form v-if="move.status" class="mt-3 space-y-3" @submit.prevent="submitMove">
                        <div v-if="move.status === 'assessment_scheduled'">
                            <label class="label" for="assessment_at">{{ t('Interview time') }}</label>
                            <input id="assessment_at" v-model="move.assessment_at" type="datetime-local" class="input" dir="ltr" required>
                            <FieldError :message="move.errors.assessment_at" />
                        </div>
                        <div>
                            <label class="label" for="note">{{ t('Note (staff only)') }}</label>
                            <textarea id="note" v-model="move.note" class="input" rows="2" />
                        </div>
                        <FieldError :message="move.errors.status" />
                        <p v-if="['assessment_scheduled', 'offered', 'waitlisted', 'rejected'].includes(move.status)" class="text-xs text-muted">{{ t('The family will be notified.') }}</p>
                        <button type="submit" class="btn-primary w-full" :disabled="move.processing">{{ t('Confirm') }}</button>
                    </form>
                </section>

                <section v-if="a.status === 'accepted'" class="card">
                    <h2 class="mb-3 font-semibold">{{ t('Enrol the student') }}</h2>
                    <form class="space-y-3" @submit.prevent="submitEnrol">
                        <select v-model="enrol.section_id" class="input" :aria-label="t('Section')">
                            <option value="">{{ t('Assign later') }}</option>
                            <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <FieldError :message="enrol.errors.section_id || enrol.errors.national_id || enrol.errors.status" />
                        <button type="submit" class="btn-primary w-full" :disabled="enrol.processing">{{ t('Enrol') }}</button>
                    </form>
                </section>

                <section v-if="a.student_id" class="card text-sm">
                    <Link :href="`/students/${a.student_id}`" class="font-semibold text-accent hover:underline">{{ t('Open student record') }}</Link>
                </section>

                <section class="card">
                    <h2 class="mb-3 font-semibold">{{ t('Staff notes') }}</h2>
                    <form class="space-y-3" @submit.prevent="saveNotes">
                        <textarea v-model="notes.staff_note" class="input" rows="4" :aria-label="t('Staff notes')" />
                        <label class="flex items-center gap-2 text-sm"><input v-model="notes.noor_transfer_done" type="checkbox"> {{ t('Noor transfer done') }}</label>
                        <button type="submit" class="btn-ghost w-full" :disabled="notes.processing">{{ t('Save') }}</button>
                    </form>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
