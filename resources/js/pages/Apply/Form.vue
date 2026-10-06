<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import PublicLayout from '../../layouts/PublicLayout.vue';
import FieldError from '../../components/FieldError.vue';
import { formatDate, useT } from '../../lib/i18n';

const props = defineProps({ schoolName: String, windows: Array, relationships: Array, started: String });
const t = useT();

const form = useForm({
    admission_window_id: props.windows.length === 1 ? props.windows[0].id : '',
    first_name_ar: '', father_name_ar: '', grandfather_name_ar: '', family_name_ar: '', name_en: '',
    gender: '', date_of_birth: '', national_id: '', nationality: 'SA',
    current_school: '', from_private_school: false,
    guardian_name: '', guardian_national_id: '', guardian_phone: '', guardian_email: '', guardian_relationship: 'father',
    consent: false,
    started: props.started,
    website: '',
});

const selected = computed(() => props.windows.find((w) => w.id === Number(form.admission_window_id)));

const submit = () => {
    form.transform((data) => Object.fromEntries(Object.entries(data).map(([k, v]) => [k, v === '' ? null : v])))
        .post(window.location.pathname);
};
</script>

<template>
    <PublicLayout :title="t('Admission application')" :school-name="schoolName">
        <div v-if="!windows.length" class="card text-center text-muted">{{ t('Applications are not open at the moment.') }}</div>

        <form v-else class="space-y-4" @submit.prevent="submit">
            <section class="card">
                <h2 class="mb-4 font-semibold">{{ t('Grade applied for') }}</h2>
                <select id="window" v-model="form.admission_window_id" class="input" required :aria-label="t('Grade applied for')">
                    <option value="" disabled>{{ t('Choose a grade') }}</option>
                    <option v-for="w in windows" :key="w.id" :value="w.id">
                        {{ w.grade }} — {{ w.year }}{{ w.full ? ` (${t('waitlist only')})` : '' }}
                    </option>
                </select>
                <FieldError :message="form.errors.admission_window_id" />
                <p v-if="selected" class="mt-2 text-sm text-muted">
                    {{ t('Applications close on :date.', { date: formatDate(selected.closes_on, $page.props.locale) }) }}
                    <template v-if="selected.born_from && selected.born_to">
                        {{ t('Children born between :from and :to.', { from: formatDate(selected.born_from, $page.props.locale), to: formatDate(selected.born_to, $page.props.locale) }) }}
                    </template>
                </p>
            </section>

            <section class="card">
                <h2 class="mb-4 font-semibold">{{ t('Student') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="label" for="first">{{ t('First name') }} *</label>
                        <input id="first" v-model="form.first_name_ar" class="input" required>
                        <FieldError :message="form.errors.first_name_ar" />
                    </div>
                    <div>
                        <label class="label" for="father">{{ t('Father\'s name') }} *</label>
                        <input id="father" v-model="form.father_name_ar" class="input" required>
                        <FieldError :message="form.errors.father_name_ar" />
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
                        <label class="label" for="dob">{{ t('Date of birth') }} *</label>
                        <input id="dob" v-model="form.date_of_birth" type="date" class="input" dir="ltr" required>
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
                    <div>
                        <label class="label" for="national_id">{{ t('National ID / Iqama') }}</label>
                        <input id="national_id" v-model="form.national_id" class="input" dir="ltr" inputmode="numeric" maxlength="10">
                        <FieldError :message="form.errors.national_id" />
                    </div>
                    <div>
                        <label class="label" for="nationality">{{ t('Nationality (country code)') }}</label>
                        <input id="nationality" v-model="form.nationality" class="input" dir="ltr" maxlength="2">
                        <FieldError :message="form.errors.nationality" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label" for="current_school">{{ t('Current school (if any)') }}</label>
                        <input id="current_school" v-model="form.current_school" class="input">
                    </div>
                    <label class="flex items-center gap-2 pt-7 text-sm sm:col-span-2">
                        <input v-model="form.from_private_school" type="checkbox"> {{ t('The current school is a private school') }}
                    </label>
                </div>
            </section>

            <section class="card">
                <h2 class="mb-4 font-semibold">{{ t('Guardian') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="g_name">{{ t('Name') }} *</label>
                        <input id="g_name" v-model="form.guardian_name" class="input" required>
                        <FieldError :message="form.errors.guardian_name" />
                    </div>
                    <div>
                        <label class="label" for="g_rel">{{ t('Relationship') }}</label>
                        <select id="g_rel" v-model="form.guardian_relationship" class="input">
                            <option v-for="r in relationships" :key="r" :value="r">{{ t(`relationship.${r}`) }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label" for="g_phone">{{ t('Mobile') }} *</label>
                        <input id="g_phone" v-model="form.guardian_phone" class="input" dir="ltr" inputmode="tel" placeholder="05XXXXXXXX" required>
                        <FieldError :message="form.errors.guardian_phone" />
                    </div>
                    <div>
                        <label class="label" for="g_id">{{ t('National ID / Iqama') }}</label>
                        <input id="g_id" v-model="form.guardian_national_id" class="input" dir="ltr" inputmode="numeric" maxlength="10">
                        <FieldError :message="form.errors.guardian_national_id" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label" for="g_email">{{ t('Email') }}</label>
                        <input id="g_email" v-model="form.guardian_email" type="email" class="input" dir="ltr">
                        <FieldError :message="form.errors.guardian_email" />
                    </div>
                </div>
                <p class="mt-3 text-sm text-muted">{{ t('If a brother or sister already studies here, use the same mobile or ID so the application gets sibling priority.') }}</p>
            </section>

            <!-- Left empty by people; bots fill it. -->
            <div class="hidden" aria-hidden="true"><label>Website <input v-model="form.website" tabindex="-1" autocomplete="off"></label></div>

            <section class="card">
                <label class="flex items-start gap-3 text-sm">
                    <input v-model="form.consent" type="checkbox" class="mt-1" required>
                    <span>{{ t('admission.consent') }}</span>
                </label>
                <FieldError :message="form.errors.consent" />
            </section>

            <button type="submit" class="btn-primary w-full sm:w-auto" :disabled="form.processing">{{ t('Submit application') }}</button>
        </form>
    </PublicLayout>
</template>
