<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import FieldError from '../../components/FieldError.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ buses: Array, routes: Array });
const t = useT();

const editing = ref(null);
const blank = { number: '', plate: '', capacity: 30, driver_name: '', driver_phone: '', supervisor_name: '', supervisor_phone: '' };
const bus = useForm({ ...blank });
const startBus = (b = null) => {
    editing.value = b ? b.id : 'new';
    bus.clearErrors();
    Object.assign(bus, b ? Object.fromEntries(Object.keys(blank).map((k) => [k, b[k] ?? ''])) : blank);
};
const saveBus = () => {
    bus.transform((d) => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v])));
    const options = { preserveScroll: true, onSuccess: () => { editing.value = null; } };
    editing.value === 'new' ? bus.post('/transport/buses', options) : bus.put(`/transport/buses/${editing.value}`, options);
};
const route = useForm({ name: '', bus_id: '' });
const addRoute = () => route.transform((d) => ({ ...d, bus_id: d.bus_id || null })).post('/transport/routes');
</script>

<template>
    <AppLayout :title="t('Transport')">
        <div class="grid gap-4 lg:grid-cols-2">
            <section class="card">
                <div class="mb-3 flex items-center"><h2 class="font-semibold">{{ t('Routes') }}</h2></div>
                <ul class="divide-y divide-line text-sm">
                    <li v-for="r in routes" :key="r.id" class="flex items-center gap-3 py-2">
                        <Link :href="`/transport/routes/${r.id}`" class="font-medium text-accent hover:underline">{{ r.name }}</Link>
                        <span class="text-muted">{{ r.bus ? t('Bus :bus', { bus: r.bus }) : t('No bus') }} · {{ t(':count stops', { count: r.stops }) }}</span>
                        <span class="ms-auto tabular-nums" :class="r.capacity && r.riders >= r.capacity ? 'font-semibold text-danger' : ''">{{ r.riders }}<template v-if="r.capacity"> / {{ r.capacity }}</template></span>
                    </li>
                    <li v-if="!routes.length" class="py-2 text-muted">{{ t('No routes yet.') }}</li>
                </ul>
                <form class="mt-4 flex flex-wrap items-end gap-2" @submit.prevent="addRoute">
                    <div class="flex-1"><label class="label" for="rname">{{ t('New route') }}</label><input id="rname" v-model="route.name" class="input" required :placeholder="t('e.g. North district')"></div>
                    <select v-model="route.bus_id" class="input w-32" :aria-label="t('Bus')"><option value="">{{ t('No bus') }}</option><option v-for="b in buses" :key="b.id" :value="b.id">{{ b.number }}</option></select>
                    <button class="btn-primary" :disabled="route.processing">{{ t('Add') }}</button>
                    <FieldError class="w-full" :message="route.errors.name" />
                </form>
            </section>

            <section class="card">
                <div class="mb-3 flex items-center"><h2 class="font-semibold">{{ t('Buses') }}</h2><button type="button" class="ms-auto text-sm text-accent hover:underline" @click="startBus()">+ {{ t('Add a bus') }}</button></div>
                <form v-if="editing" class="mb-4 grid gap-3 sm:grid-cols-2" @submit.prevent="saveBus">
                    <div><label class="label" for="num">{{ t('Bus number') }}</label><input id="num" v-model="bus.number" class="input" required></div>
                    <div><label class="label" for="plate">{{ t('Plate') }}</label><input id="plate" v-model="bus.plate" class="input" dir="ltr"></div>
                    <div><label class="label" for="cap">{{ t('Seats') }}</label><input id="cap" v-model.number="bus.capacity" type="number" min="1" class="input" required></div>
                    <div />
                    <div><label class="label" for="dn">{{ t('Driver') }}</label><input id="dn" v-model="bus.driver_name" class="input"></div>
                    <div><label class="label" for="dp">{{ t('Driver mobile') }}</label><input id="dp" v-model="bus.driver_phone" class="input" dir="ltr"><FieldError :message="bus.errors.driver_phone" /></div>
                    <div><label class="label" for="sn">{{ t('Supervisor') }}</label><input id="sn" v-model="bus.supervisor_name" class="input"></div>
                    <div><label class="label" for="sp">{{ t('Supervisor mobile') }}</label><input id="sp" v-model="bus.supervisor_phone" class="input" dir="ltr"><FieldError :message="bus.errors.supervisor_phone" /></div>
                    <div class="flex gap-3 sm:col-span-2"><button class="btn-primary">{{ t('Save') }}</button><button type="button" class="btn-ghost" @click="editing = null">{{ t('Cancel') }}</button></div>
                </form>
                <ul class="divide-y divide-line text-sm">
                    <li v-for="b in buses" :key="b.id" class="flex items-center gap-3 py-2">
                        <span class="font-medium">{{ t('Bus :bus', { bus: b.number }) }}</span>
                        <span class="text-muted">{{ [b.plate, b.driver_name, t(':count seats', { count: b.capacity })].filter(Boolean).join(' · ') }}</span>
                        <button type="button" class="ms-auto text-accent hover:underline" @click="startBus(b)">{{ t('Edit') }}</button>
                    </li>
                    <li v-if="!buses.length" class="py-2 text-muted">{{ t('No buses yet.') }}</li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
