<script setup>
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import FieldError from '../../components/FieldError.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ years: Array, report: Array, year: Object, columns: { type: Array, default: () => [] } });
const t = useT();

const form = useForm({ file: null, academic_year_id: props.years.find((y) => y.is_current)?.id ?? props.years[0]?.id ?? null });
const upload = () => form.post('/students/import/preview', { forceFormData: true });

const counts = computed(() => {
    const c = { ready: 0, error: 0, skipped: 0 };
    (props.report ?? []).forEach((r) => { c[r.status] = (c[r.status] ?? 0) + 1; });
    return c;
});

const tone = { ready: 'text-accent', error: 'text-danger', skipped: 'text-muted' };
const confirmImport = () => router.post('/students/import');
const cancel = () => router.delete('/students/import');
</script>

<template>
    <AppLayout :title="t('Import students')">
        <section v-if="!report" class="card max-w-2xl space-y-4">
            <p class="text-sm text-muted">{{ t('Upload the student list exported from Noor (Excel .xlsx). The first row must be the column headers. Nothing is saved until you review and confirm.') }}</p>
            <a href="/students/import/template" class="text-sm text-accent underline">{{ t('Download a blank template') }}</a>

            <div>
                <label class="label" for="year">{{ t('Academic year') }}</label>
                <select id="year" v-model="form.academic_year_id" class="input max-w-xs">
                    <option v-for="y in years" :key="y.id" :value="y.id">{{ y.name }}</option>
                </select>
            </div>
            <div>
                <label class="label" for="file">{{ t('File') }}</label>
                <input id="file" type="file" accept=".xlsx" class="input" @change="form.file = $event.target.files[0]">
                <FieldError :message="form.errors.file" />
            </div>
            <button type="button" class="btn-primary" :disabled="!form.file || form.processing" @click="upload">{{ t('Check file') }}</button>
        </section>

        <template v-else>
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <span class="text-sm">{{ t('Academic year') }} {{ year?.name }} ·
                    <span class="text-accent">{{ t(':n ready', { n: counts.ready }) }}</span> ·
                    <span class="text-danger">{{ t(':n with errors', { n: counts.error }) }}</span> ·
                    <span class="text-muted">{{ t(':n already registered', { n: counts.skipped }) }}</span>
                </span>
                <div class="ms-auto flex gap-2">
                    <button type="button" class="btn-ghost" @click="cancel">{{ t('Cancel') }}</button>
                    <button type="button" class="btn-primary" :disabled="!counts.ready" @click="confirmImport">{{ t('Import :n students', { n: counts.ready }) }}</button>
                </div>
            </div>
            <p class="mb-2 text-sm text-muted">{{ t('Recognised columns') }}: {{ columns.map((c) => t(`column.${c}`)).join('، ') }}</p>
            <p v-if="!columns.includes('national_id')" class="mb-3 rounded-lg bg-warn-soft px-4 py-2 text-sm text-warn">{{ t('No national ID column was found, so students will be imported without IDs and re-imports cannot detect duplicates. Check the file’s header row.') }}</p>
            <p v-if="counts.error" class="mb-3 text-sm text-muted">{{ t('Rows with errors are skipped. Fix them in the file and upload it again; already-imported students are skipped automatically.') }}</p>

            <div class="card overflow-x-auto p-0">
                <table class="w-full text-sm">
                    <thead class="border-b border-line text-muted">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium">{{ t('Row') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ t('Name') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ t('Result') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in report" :key="r.row" class="border-b border-line last:border-0 align-top">
                            <td class="px-4 py-2 tabular-nums text-muted">{{ r.row }}</td>
                            <td class="px-4 py-2">{{ r.name || '—' }}</td>
                            <td class="px-4 py-2">
                                <span class="font-semibold" :class="tone[r.status]">{{ t(`import.${r.status}`) }}</span>
                                <ul v-if="r.messages.length" class="mt-1 list-disc ps-5 text-muted">
                                    <li v-for="(m, i) in r.messages" :key="i">{{ m }}</li>
                                </ul>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </AppLayout>
</template>
