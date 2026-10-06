<script setup>
import { computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ years: Array, yearId: Number, sections: Array, terms: Array, allowed: Object });
const t = useT();

const today = new Date().toISOString().slice(0, 10);
const opts = reactive({
    section_id: '',
    term_id: props.terms[0]?.id ?? '',
    from: props.terms[0]?.starts_on ?? today,
    to: today,
});
const q = (params) => new URLSearchParams(Object.entries({ year: props.yearId, ...params }).filter(([, v]) => v !== '' && v != null)).toString();
const studentsUrl = computed(() => `/exports/students?${q({ section_id: opts.section_id })}`);
const attendanceUrl = computed(() => `/exports/attendance?${q({ section_id: opts.section_id, from: opts.from, to: opts.to })}`);
const resultsUrl = computed(() => `/exports/results?${q({ section_id: opts.section_id, term_id: opts.term_id })}`);
const useTerm = () => {
    const term = props.terms.find((x) => x.id === Number(opts.term_id));
    if (term) Object.assign(opts, { from: term.starts_on, to: term.ends_on < today ? term.ends_on : today });
};
</script>

<template>
    <AppLayout :title="t('Reports and exports')">
        <section class="card mb-4 grid gap-4 sm:grid-cols-3">
            <div>
                <label class="label" for="year">{{ t('Academic year') }}</label>
                <select id="year" class="input" :value="yearId" @change="router.get('/exports', { year: $event.target.value })">
                    <option v-for="y in years" :key="y.id" :value="y.id">{{ y.name }}</option>
                </select>
            </div>
            <div>
                <label class="label" for="section">{{ t('Section') }}</label>
                <select id="section" v-model="opts.section_id" class="input">
                    <option value="">{{ t('All sections') }}</option>
                    <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.label }}</option>
                </select>
            </div>
            <div>
                <label class="label" for="term">{{ t('Term') }}</label>
                <select id="term" v-model="opts.term_id" class="input" @change="useTerm">
                    <option v-for="term in terms" :key="term.id" :value="term.id">{{ term.name }}</option>
                </select>
            </div>
        </section>

        <div class="grid gap-4 md:grid-cols-3">
            <section v-if="allowed.students" class="card flex flex-col">
                <h2 class="mb-1 font-semibold">{{ t('Student list (Noor layout)') }}</h2>
                <p class="mb-4 flex-1 text-sm text-muted">{{ t('ID, name, gender, Hijri birth date, nationality, grade, section and guardian, with the same headers as the Noor import.') }}</p>
                <a :href="studentsUrl" class="btn-primary">{{ t('Download Excel') }}</a>
            </section>
            <section v-if="allowed.attendance" class="card flex flex-col">
                <h2 class="mb-1 font-semibold">{{ t('Attendance summary') }}</h2>
                <div class="mb-4 grid flex-1 grid-cols-2 gap-2">
                    <div><label class="label" for="from">{{ t('From') }}</label><input id="from" v-model="opts.from" type="date" class="input" dir="ltr"></div>
                    <div><label class="label" for="to">{{ t('To') }}</label><input id="to" v-model="opts.to" type="date" class="input" dir="ltr"></div>
                </div>
                <a :href="attendanceUrl" class="btn-primary">{{ t('Download Excel') }}</a>
            </section>
            <section v-if="allowed.results" class="card flex flex-col">
                <h2 class="mb-1 font-semibold">{{ t('Term results') }}</h2>
                <p class="mb-4 flex-1 text-sm text-muted">{{ t('One sheet per section: each subject\'s percentage and grade, and the overall average.') }}</p>
                <a :href="resultsUrl" class="btn-primary" :class="!opts.term_id && 'pointer-events-none opacity-50'">{{ t('Download Excel') }}</a>
            </section>
        </div>
        <p class="mt-4 text-sm text-muted">{{ t('Exports hold personal data. They are logged; store and share them carefully.') }}</p>
    </AppLayout>
</template>
