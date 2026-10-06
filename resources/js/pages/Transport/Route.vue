<script setup>
import { ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import FieldError from '../../components/FieldError.vue';
import { useT } from '../../lib/i18n';

const props = defineProps({ route: Object, stops: Array, riders: Array, buses: Array, results: Array, search: String });
const t = useT();
const base = `/transport/routes/${props.route.id}`;

const details = useForm({ name: props.route.name, bus_id: props.route.bus_id ?? '' });
const saveDetails = () => details.transform((d) => ({ ...d, bus_id: d.bus_id || null })).put(base, { preserveScroll: true });
const removeRoute = () => confirm(t('Delete this route?')) && router.delete(base);

const stop = useForm({ name: '', sequence: props.stops.length + 1, pickup_at: '', dropoff_at: '' });
const addStop = () => stop.transform((d) => ({ ...d, pickup_at: d.pickup_at || null, dropoff_at: d.dropoff_at || null }))
    .post(`${base}/stops`, { preserveScroll: true, onSuccess: () => stop.reset() });
const removeStop = (s) => confirm(t('Delete this stop?')) && router.delete(`${base}/stops/${s.id}`, { preserveScroll: true });

const query = ref(props.search ?? '');
let timer;
watch(query, (q) => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(base, q ? { search: q } : {}, { preserveState: true, preserveScroll: true, replace: true, only: ['results', 'search'] }), 300);
});
const stopFor = ref('');
const add = (s) => router.post(`${base}/riders`, { student_id: s.id, route_stop_id: stopFor.value || null }, { preserveScroll: true, onSuccess: () => { query.value = ''; } });
const changeStop = (r, value) => router.post(`${base}/riders`, { student_id: r.student_id, route_stop_id: value || null }, { preserveScroll: true });
const print = () => window.print();
const remove = (r) => router.delete(`${base}/riders/${r.id}`, { preserveScroll: true });
</script>

<template>
    <AppLayout :title="route.name">
        <div class="mb-4 flex flex-wrap items-center gap-3 print:hidden">
            <Link href="/transport" class="text-sm text-muted hover:text-ink"><span class="rtl:hidden">←</span><span class="ltr:hidden">→</span> {{ t('Transport') }}</Link>
            <button type="button" class="btn-ghost ms-auto" @click="print">{{ t('Print list') }}</button>
        </div>
        <FieldError :message="$page.props.errors?.route || $page.props.errors?.student_id" />

        <div v-if="route.bus" class="card mb-4 text-sm">
            <strong>{{ t('Bus :bus', { bus: route.bus.number }) }}</strong>
            <span class="text-muted"> · {{ [route.bus.plate, t(':count seats', { count: route.bus.capacity })].filter(Boolean).join(' · ') }}</span>
            <div class="mt-1">
                <span v-if="route.bus.driver_name">{{ t('Driver') }}: {{ route.bus.driver_name }} <span dir="ltr">{{ route.bus.driver_phone }}</span></span>
                <span v-if="route.bus.supervisor_name" class="ms-4">{{ t('Supervisor') }}: {{ route.bus.supervisor_name }} <span dir="ltr">{{ route.bus.supervisor_phone }}</span></span>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3 print:block">
            <div class="space-y-4 print:hidden">
                <section class="card">
                    <h2 class="mb-3 font-semibold">{{ t('Route') }}</h2>
                    <form class="space-y-3" @submit.prevent="saveDetails">
                        <input v-model="details.name" class="input" required :aria-label="t('Name')">
                        <select v-model="details.bus_id" class="input" :aria-label="t('Bus')"><option value="">{{ t('No bus') }}</option><option v-for="b in buses" :key="b.id" :value="b.id">{{ t('Bus :bus', { bus: b.number }) }}</option></select>
                        <div class="flex gap-3"><button class="btn-primary">{{ t('Save') }}</button><button v-if="!riders.length" type="button" class="btn-ghost text-danger" @click="removeRoute">{{ t('Delete') }}</button></div>
                    </form>
                </section>
                <section class="card">
                    <h2 class="mb-3 font-semibold">{{ t('Stops') }}</h2>
                    <ol class="mb-3 space-y-1 text-sm">
                        <li v-for="s in stops" :key="s.id" class="flex items-center gap-2">
                            <span class="tabular-nums text-muted">{{ s.sequence }}.</span> {{ s.name }}
                            <span class="text-xs text-muted" dir="ltr">{{ [s.pickup_at, s.dropoff_at].filter(Boolean).join(' / ') }}</span>
                            <button type="button" class="ms-auto text-xs text-danger" @click="removeStop(s)">{{ t('Delete') }}</button>
                        </li>
                    </ol>
                    <form class="grid grid-cols-2 gap-2" @submit.prevent="addStop">
                        <input v-model="stop.name" class="input col-span-2" required :placeholder="t('Stop name')">
                        <div><label class="label text-xs" for="pu">{{ t('Pick-up') }}</label><input id="pu" v-model="stop.pickup_at" type="time" class="input" dir="ltr"></div>
                        <div><label class="label text-xs" for="do">{{ t('Drop-off') }}</label><input id="do" v-model="stop.dropoff_at" type="time" class="input" dir="ltr"></div>
                        <input v-model.number="stop.sequence" type="number" min="0" class="input" :aria-label="t('Order')">
                        <button class="btn-primary">{{ t('Add stop') }}</button>
                    </form>
                </section>
                <section class="card">
                    <h2 class="mb-3 font-semibold">{{ t('Add students') }}</h2>
                    <select v-model="stopFor" class="input mb-2" :aria-label="t('Stop')"><option value="">{{ t('Stop: later') }}</option><option v-for="s in stops" :key="s.id" :value="s.id">{{ s.name }}</option></select>
                    <input v-model="query" type="search" class="input" :placeholder="t('Search by name, number or ID')">
                    <ul class="mt-2 divide-y divide-line text-sm">
                        <li v-for="s in results" :key="s.id" class="flex items-center gap-2 py-2">
                            <span>{{ s.name }}</span>
                            <span v-if="s.current" class="text-xs text-muted">({{ s.current }})</span>
                            <button type="button" class="ms-auto text-accent hover:underline" @click="add(s)">{{ t('Add') }}</button>
                        </li>
                    </ul>
                </section>
            </div>

            <section class="card lg:col-span-2">
                <h2 class="mb-3 font-semibold">{{ t('Students on this route') }} <span class="text-muted tabular-nums">({{ riders.length }}<template v-if="route.bus"> / {{ route.bus.capacity }}</template>)</span></h2>
                <table class="w-full text-sm">
                    <thead class="border-b border-line text-muted">
                        <tr>
                            <th class="py-2 text-start font-medium">{{ t('Student') }}</th>
                            <th class="py-2 text-start font-medium">{{ t('Class') }}</th>
                            <th class="py-2 text-start font-medium">{{ t('Stop') }}</th>
                            <th class="py-2 text-start font-medium">{{ t('Guardian mobile') }}</th>
                            <th class="py-2 print:hidden" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in riders" :key="r.id" class="border-b border-line last:border-0">
                            <td class="py-2 font-medium">{{ r.name }}</td>
                            <td class="py-2">{{ r.class ?? '—' }}</td>
                            <td class="py-2">
                                <select class="input py-1 print:hidden" :value="r.stop_id ?? ''" :aria-label="t('Stop')" @change="changeStop(r, $event.target.value)">
                                    <option value="">—</option><option v-for="s in stops" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                                <span class="hidden print:inline">{{ r.stop }}</span>
                            </td>
                            <td class="py-2" dir="ltr">{{ r.guardian_phone }}</td>
                            <td class="py-2 text-end print:hidden"><button type="button" class="text-danger hover:underline" @click="remove(r)">{{ t('Remove') }}</button></td>
                        </tr>
                        <tr v-if="!riders.length"><td colspan="5" class="py-6 text-center text-muted">{{ t('No students on this route yet.') }}</td></tr>
                    </tbody>
                </table>
            </section>
        </div>
    </AppLayout>
</template>
