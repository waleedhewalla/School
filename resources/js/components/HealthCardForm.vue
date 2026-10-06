<script setup>
import { useForm } from '@inertiajs/vue3';
import FieldError from './FieldError.vue';
import { useT } from '../lib/i18n';

const props = defineProps({ record: { type: Object, required: true }, url: { type: String, required: true } });
const t = useT();
const form = useForm({
    blood_type: props.record.blood_type ?? '', allergies: props.record.allergies ?? '', chronic_conditions: props.record.chronic_conditions ?? '',
    medications: props.record.medications ?? '', emergency_contact_name: props.record.emergency_contact_name ?? '',
    emergency_contact_phone: props.record.emergency_contact_phone ?? '', notes: props.record.notes ?? '',
});
const save = () => form.transform((d) => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v]))).put(props.url, { preserveScroll: true });
</script>

<template>
    <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="save">
        <div>
            <label class="label" :for="`${url}-blood`">{{ t('Blood type') }}</label>
            <select :id="`${url}-blood`" v-model="form.blood_type" class="input" dir="ltr">
                <option value="">—</option>
                <option v-for="b in ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']" :key="b" :value="b">{{ b }}</option>
            </select>
        </div>
        <div><label class="label" :for="`${url}-allergies`">{{ t('Allergies') }}</label><input :id="`${url}-allergies`" v-model="form.allergies" class="input"></div>
        <div><label class="label" :for="`${url}-conditions`">{{ t('Chronic conditions') }}</label><input :id="`${url}-conditions`" v-model="form.chronic_conditions" class="input"></div>
        <div><label class="label" :for="`${url}-meds`">{{ t('Regular medication') }}</label><input :id="`${url}-meds`" v-model="form.medications" class="input"></div>
        <div><label class="label" :for="`${url}-ecn`">{{ t('Emergency contact') }}</label><input :id="`${url}-ecn`" v-model="form.emergency_contact_name" class="input"></div>
        <div>
            <label class="label" :for="`${url}-ecp`">{{ t('Emergency phone') }}</label>
            <input :id="`${url}-ecp`" v-model="form.emergency_contact_phone" class="input" dir="ltr" inputmode="tel">
            <FieldError :message="form.errors.emergency_contact_phone" />
        </div>
        <div class="sm:col-span-2"><label class="label" :for="`${url}-notes`">{{ t('Notes') }}</label><textarea :id="`${url}-notes`" v-model="form.notes" class="input" rows="2" /></div>
        <div class="flex items-center gap-3 sm:col-span-2">
            <button class="btn-primary" :disabled="form.processing">{{ t('Save') }}</button>
            <span v-if="record.updated_at" class="text-xs text-muted">{{ t('Last updated :date', { date: record.updated_at }) }}</span>
        </div>
    </form>
</template>
