<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import FieldError from '../../components/FieldError.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ student: Object, year: Object, grades: Array, sections: Array, relationships: Array });
const t = useT();

const existing = props.student?.data ?? null;
const editing = existing !== null;

const form = useForm({
    first_name_ar: existing?.first_name_ar ?? '',
    father_name_ar: existing?.father_name_ar ?? '',
    grandfather_name_ar: existing?.grandfather_name_ar ?? '',
    family_name_ar: existing?.family_name_ar ?? '',
    name_en: existing?.name_en ?? '',
    gender: existing?.gender ?? '',
    date_of_birth: existing?.date_of_birth ?? '',
    national_id: existing?.national_id ?? '',
    ...(editing ? {} : {
        guardians: [{ guardian_id: null, name_ar: '', national_id: '', phone: '', relationship: 'father', is_primary: true }],
        enrollment: { academic_year_id: props.year?.id ?? null, grade_level_id: '', section_id: '' },
    }),
});

const sectionsForGrade = computed(() => props.sections.filter((s) => s.grade_level_id === Number(form.enrollment?.grade_level_id)));

// Look up an existing guardian (sibling already in the school) by ID or mobile.
const lookups = ref({});
const lookup = async (index) => {
    const g = form.guardians[index];
    const q = (g.national_id || g.phone || '').trim();
    if (q.length < 9) return;
    const response = await fetch(`/guardians/lookup?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
    lookups.value[index] = response.ok ? (await response.json()).data : [];
};
const useExisting = (index, guardian) => {
    Object.assign(form.guardians[index], { guardian_id: guardian.id, name_ar: guardian.name });
    lookups.value[index] = [];
};
const addGuardian = () => form.guardians.push({ guardian_id: null, name_ar: '', national_id: '', phone: '', relationship: 'mother', is_primary: false });

const submit = () => {
    const transform = (data) => {
        const clean = { ...data };
        if (!editing) {
            clean.guardians = data.guardians
                .filter((g) => g.guardian_id || g.name_ar)
                .map((g) => (g.guardian_id ? { guardian_id: g.guardian_id, relationship: g.relationship, is_primary: g.is_primary } : g));
            clean.enrollment = data.enrollment.grade_level_id
                ? { ...data.enrollment, section_id: data.enrollment.section_id || null }
                : undefined;
        }
        for (const key of ['national_id', 'date_of_birth', 'name_en', 'father_name_ar', 'grandfather_name_ar']) {
            if (clean[key] === '') clean[key] = null;
        }
        return clean;
    };
    form.transform(transform);
    editing ? form.put(`/students/${existing.id}`) : form.post('/students');
};
</script>

<template>
    <AppLayout :title="editing ? t('Edit student') : t('Admit a student')">
        <form class="space-y-4" @submit.prevent="submit">
            <section class="card">
                <h2 class="mb-4 font-semibold">{{ t('Student') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="label" for="first">{{ t('First name') }} *</label>
                        <input id="first" v-model="form.first_name_ar" class="input" required>
                        <FieldError :message="form.errors.first_name_ar" />
                    </div>
                    <div>
                        <label class="label" for="father">{{ t('Father\'s name') }}</label>
                        <input id="father" v-model="form.father_name_ar" class="input">
                    </div>
                    <div>
                        <label class="label" for="grandfather">{{ t('Grandfather\'s name') }}</label>
                        <input id="grandfather" v-model="form.grandfather_name_ar" class="input">
                    </div>
                    <div>
                        <label class="label" for="family">{{ t('Family name') }} *</label>
                        <input id="family" v-model="form.family_name_ar" class="input" required>
                        <FieldError :message="form.errors.family_name_ar" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label" for="name_en">{{ t('Name (English)') }}</label>
                        <input id="name_en" v-model="form.name_en" class="input" dir="ltr">
                    </div>
                    <div>
                        <label class="label" for="national_id">{{ t('National ID / Iqama') }}</label>
                        <input id="national_id" v-model="form.national_id" class="input" dir="ltr" inputmode="numeric" maxlength="10">
                        <FieldError :message="form.errors.national_id" />
                    </div>
                    <div>
                        <label class="label" for="dob">{{ t('Date of birth') }}</label>
                        <input id="dob" v-model="form.date_of_birth" type="date" class="input" dir="ltr">
                        <FieldError :message="form.errors.date_of_birth" />
                    </div>
                    <fieldset>
                        <legend class="label">{{ t('Gender') }} *</legend>
                        <div class="flex gap-4 pt-2 text-sm">
                            <label class="flex items-center gap-2"><input v-model="form.gender" type="radio" value="male" required> {{ t('gender.male') }}</label>
                            <label class="flex items-center gap-2"><input v-model="form.gender" type="radio" value="female"> {{ t('gender.female') }}</label>
                        </div>
                        <FieldError :message="form.errors.gender" />
                    </fieldset>
                </div>
            </section>

            <section v-if="!editing" class="card">
                <h2 class="mb-1 font-semibold">{{ t('Guardians') }}</h2>
                <p class="mb-4 text-sm text-muted">{{ t('Enter the ID or mobile first: if the guardian already has a child here, the siblings are linked.') }}</p>

                <div v-for="(g, i) in form.guardians" :key="i" class="mb-4 grid gap-4 border-b border-line pb-4 last:border-0 sm:grid-cols-2 lg:grid-cols-5">
                    <div>
                        <label class="label" :for="`g-id-${i}`">{{ t('National ID') }}</label>
                        <input :id="`g-id-${i}`" v-model="g.national_id" class="input" dir="ltr" inputmode="numeric" maxlength="10" :disabled="!!g.guardian_id" @blur="lookup(i)">
                        <FieldError :message="form.errors[`guardians.${i}.national_id`]" />
                    </div>
                    <div>
                        <label class="label" :for="`g-phone-${i}`">{{ t('Mobile') }}</label>
                        <input :id="`g-phone-${i}`" v-model="g.phone" class="input" dir="ltr" inputmode="tel" placeholder="05XXXXXXXX" :disabled="!!g.guardian_id" @blur="lookup(i)">
                        <FieldError :message="form.errors[`guardians.${i}.phone`]" />
                    </div>
                    <div>
                        <label class="label" :for="`g-name-${i}`">{{ t('Name') }}</label>
                        <input :id="`g-name-${i}`" v-model="g.name_ar" class="input" :disabled="!!g.guardian_id">
                        <FieldError :message="form.errors[`guardians.${i}.name_ar`]" />
                    </div>
                    <div>
                        <label class="label" :for="`g-rel-${i}`">{{ t('Relationship') }}</label>
                        <select :id="`g-rel-${i}`" v-model="g.relationship" class="input">
                            <option v-for="r in relationships" :key="r" :value="r">{{ t(`relationship.${r}`) }}</option>
                        </select>
                    </div>
                    <label class="flex items-center gap-2 pt-7 text-sm">
                        <input v-model="g.is_primary" type="checkbox"> {{ t('Primary contact') }}
                    </label>

                    <div v-if="lookups[i]?.length" class="rounded-lg bg-accent-soft p-3 text-sm sm:col-span-2 lg:col-span-5">
                        <div v-for="match in lookups[i]" :key="match.id" class="flex flex-wrap items-center gap-3">
                            <span>{{ t('Existing guardian') }}: <strong>{{ match.name }}</strong> — {{ match.children.join('، ') }}</span>
                            <button type="button" class="btn-ghost" @click="useExisting(i, match)">{{ t('Link as sibling') }}</button>
                        </div>
                    </div>
                    <p v-if="g.guardian_id" class="text-sm text-accent sm:col-span-2 lg:col-span-5">{{ t('Linked to the existing guardian; siblings will share a family.') }}</p>
                </div>

                <button v-if="form.guardians.length < 4" type="button" class="btn-ghost" @click="addGuardian">{{ t('Add another guardian') }}</button>
            </section>

            <section v-if="!editing" class="card">
                <h2 class="mb-4 font-semibold">{{ t('Enrollment') }} <span v-if="year" class="text-sm font-normal text-muted">— {{ t('Academic year') }} {{ year.name }}</span></h2>
                <p v-if="!year" class="text-sm text-muted">{{ t('No current academic year; the student will be saved without enrollment.') }}</p>
                <div v-else class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="grade">{{ t('Grade level') }}</label>
                        <select id="grade" v-model="form.enrollment.grade_level_id" class="input" @change="form.enrollment.section_id = ''">
                            <option value="">{{ t('Not now') }}</option>
                            <option v-for="g in grades" :key="g.id" :value="g.id">{{ g.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label" for="section">{{ t('Section') }}</label>
                        <select id="section" v-model="form.enrollment.section_id" class="input" :disabled="!form.enrollment.grade_level_id">
                            <option value="">{{ t('Assign later') }}</option>
                            <option v-for="s in sectionsForGrade" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <FieldError :message="form.errors['section_id'] || form.errors['enrollment.section_id']" />
                    </div>
                </div>
            </section>

            <div class="flex gap-3">
                <button type="submit" class="btn-primary" :disabled="form.processing">{{ editing ? t('Save changes') : t('Admit student') }}</button>
                <Link :href="editing ? `/students/${existing.id}` : '/students'" class="btn-ghost">{{ t('Cancel') }}</Link>
            </div>
        </form>
    </AppLayout>
</template>
