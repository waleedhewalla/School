<script setup>
import { reactive, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import FieldError from '../../components/FieldError.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ items: Object, categories: Array, totals: Object, staff: Array, conditions: Array, filters: Object });
const t = useT();

const filters = reactive({ search: props.filters.search ?? '', category: props.filters.category ?? '', condition: props.filters.condition ?? '' });
const clean = () => Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== ''));
let timer;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get('/inventory', clean(), { preserveState: true, replace: true }), 300);
});

const editing = ref(null);
const blank = { code: '', name: '', category: '', location: '', quantity: 1, condition: 'good', purchased_on: '', unit_value: '', custodian_id: '', notes: '' };
const form = useForm({ ...blank });
const start = (i = null) => {
    editing.value = i ? i.id : 'new';
    form.clearErrors();
    Object.assign(form, i ? Object.fromEntries(Object.keys(blank).map((k) => [k, i[k] ?? ''])) : blank);
};
const save = () => {
    form.transform((d) => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v])));
    const options = { preserveScroll: true, onSuccess: () => { editing.value = null; } };
    editing.value === 'new' ? form.post('/inventory', options) : form.put(`/inventory/${editing.value}`, options);
};
const remove = (i) => confirm(t('Delete this item?')) && router.delete(`/inventory/${i.id}`, { preserveScroll: true });
const money = (v) => Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 });
const exportUrl = () => `/inventory/export?${new URLSearchParams(clean())}`;
</script>

<template>
    <AppLayout :title="t('Inventory')">
        <div class="mb-4 grid gap-4 sm:grid-cols-3">
            <div class="card"><div class="text-sm text-muted">{{ t('Items in use') }}</div><div class="text-2xl font-semibold tabular-nums">{{ totals.items }}</div></div>
            <div class="card"><div class="text-sm text-muted">{{ t('Recorded value (SAR)') }}</div><div class="text-2xl font-semibold tabular-nums">{{ money(totals.value) }}</div></div>
            <div class="card"><div class="text-sm text-muted">{{ t('Need repair') }}</div><div class="text-2xl font-semibold tabular-nums" :class="totals.needs_repair && 'text-warn'">{{ totals.needs_repair }}</div></div>
        </div>

        <div class="mb-4 flex flex-wrap gap-3">
            <button type="button" class="btn-primary" @click="start()">{{ t('Add an item') }}</button>
            <input v-model="filters.search" type="search" class="input max-w-xs" :placeholder="t('Search')">
            <select v-model="filters.category" class="input max-w-xs" :aria-label="t('Category')"><option value="">{{ t('All categories') }}</option><option v-for="c in categories" :key="c" :value="c">{{ c }}</option></select>
            <select v-model="filters.condition" class="input max-w-xs" :aria-label="t('Condition')"><option value="">{{ t('Any condition') }}</option><option v-for="c in conditions" :key="c" :value="c">{{ t(`condition.${c}`) }}</option></select>
            <a :href="exportUrl()" class="btn-ghost">{{ t('Download Excel') }}</a>
        </div>

        <section v-if="editing" class="card mb-4">
            <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="save">
                <div class="sm:col-span-2"><label class="label" for="name">{{ t('Name') }}</label><input id="name" v-model="form.name" class="input" required><FieldError :message="form.errors.name" /></div>
                <div><label class="label" for="code">{{ t('Code') }}</label><input id="code" v-model="form.code" class="input" dir="ltr"></div>
                <div><label class="label" for="cat">{{ t('Category') }}</label><input id="cat" v-model="form.category" class="input" list="cats"><datalist id="cats"><option v-for="c in categories" :key="c" :value="c" /></datalist></div>
                <div><label class="label" for="loc">{{ t('Location') }}</label><input id="loc" v-model="form.location" class="input"></div>
                <div><label class="label" for="qty">{{ t('Quantity') }}</label><input id="qty" v-model.number="form.quantity" type="number" min="0" class="input" required></div>
                <div><label class="label" for="cond">{{ t('Condition') }}</label><select id="cond" v-model="form.condition" class="input"><option v-for="c in conditions" :key="c" :value="c">{{ t(`condition.${c}`) }}</option></select></div>
                <div><label class="label" for="cust">{{ t('Custodian') }}</label><select id="cust" v-model="form.custodian_id" class="input"><option value="">—</option><option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}</option></select></div>
                <div><label class="label" for="pd">{{ t('Purchased on') }}</label><input id="pd" v-model="form.purchased_on" type="date" class="input" dir="ltr"></div>
                <div><label class="label" for="val">{{ t('Unit value (SAR)') }}</label><input id="val" v-model="form.unit_value" type="number" step="0.01" min="0" class="input" dir="ltr"></div>
                <div class="sm:col-span-2"><label class="label" for="notes">{{ t('Notes') }}</label><input id="notes" v-model="form.notes" class="input"></div>
                <div class="flex gap-3 sm:col-span-2 lg:col-span-4">
                    <button class="btn-primary" :disabled="form.processing">{{ t('Save') }}</button>
                    <button type="button" class="btn-ghost" @click="editing = null">{{ t('Cancel') }}</button>
                </div>
            </form>
        </section>

        <div class="card overflow-x-auto p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-line text-muted">
                    <tr>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Name') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Location') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Quantity') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Condition') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('Custodian') }}</th>
                        <th class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="i in items.data" :key="i.id" class="border-b border-line last:border-0">
                        <td class="px-4 py-3"><div class="font-medium">{{ i.name }}</div><div class="text-xs text-muted">{{ [i.code, i.category].filter(Boolean).join(' · ') }}</div></td>
                        <td class="px-4 py-3">{{ i.location ?? '—' }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ i.quantity }}</td>
                        <td class="px-4 py-3" :class="{ 'text-warn': i.condition === 'needs_repair', 'text-danger': i.condition === 'damaged', 'text-muted': i.condition === 'disposed' }">{{ t(`condition.${i.condition}`) }}</td>
                        <td class="px-4 py-3">{{ i.custodian ?? '—' }}</td>
                        <td class="px-4 py-3 text-end whitespace-nowrap">
                            <button type="button" class="text-accent hover:underline" @click="start(i)">{{ t('Edit') }}</button>
                            <button type="button" class="ms-3 text-danger hover:underline" @click="remove(i)">{{ t('Delete') }}</button>
                        </td>
                    </tr>
                    <tr v-if="!items.data.length"><td colspan="6" class="px-4 py-8 text-center text-muted">{{ t('No items.') }}</td></tr>
                </tbody>
            </table>
        </div>
        <div v-if="items.last_page > 1" class="mt-4 flex flex-wrap gap-1">
            <template v-for="link in items.links" :key="link.label">
                <Link v-if="link.url" :href="link.url" class="rounded-lg px-3 py-1.5 text-sm" :class="link.active ? 'bg-accent text-accent-ink' : 'border border-line'" preserve-scroll><span v-html="link.label" /></Link>
            </template>
        </div>
    </AppLayout>
</template>
