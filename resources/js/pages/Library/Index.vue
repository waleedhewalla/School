<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import FieldError from '../../components/FieldError.vue';
import { formatDate, useT } from '../../lib/i18n';

const props = defineProps({ books: Object, loans: Array, borrowers: Array, search: String, loanDays: Number });
const t = useT();

const query = ref(props.search ?? '');
let timer;
watch(query, (q) => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get('/library', q ? { search: q } : {}, { preserveState: true, replace: true }), 300);
});

// Add or edit a book.
const editing = ref(null);
const blank = { isbn: '', title: '', author: '', publisher: '', published_year: '', category: '', shelf: '', copies: 1 };
const book = useForm({ ...blank });
const startBook = (b = null) => {
    editing.value = b ? b.id : 'new';
    book.clearErrors();
    Object.assign(book, b ? Object.fromEntries(Object.keys(blank).map((k) => [k, b[k] ?? ''])) : blank);
};
const saveBook = () => {
    book.transform((d) => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v])));
    const options = { preserveScroll: true, onSuccess: () => { editing.value = null; } };
    editing.value === 'new' ? book.post('/library/books', options) : book.put(`/library/books/${editing.value}`, options);
};

// Lend.
const due = new Date(Date.now() + props.loanDays * 86400000).toISOString().slice(0, 10);
const lending = ref(null);
const loan = useForm({ library_book_id: null, borrower: '', due_on: due });
const who = ref('');
watch(who, (q) => {
    clearTimeout(timer);
    timer = setTimeout(() => q.length > 1 && router.reload({ data: { borrower: q }, only: ['borrowers'] }), 300);
});
const startLend = (b) => { lending.value = b; loan.library_book_id = b.id; loan.borrower = ''; who.value = ''; };
const saveLoan = () => loan.post('/library/loans', { preserveScroll: true, onSuccess: () => { lending.value = null; } });
const giveBack = (l) => router.post(`/library/loans/${l.id}/return`, {}, { preserveScroll: true });
const overdue = computed(() => props.loans.filter((l) => l.overdue).length);
</script>

<template>
    <AppLayout :title="t('Library')">
        <div class="mb-4 flex flex-wrap gap-3">
            <button type="button" class="btn-primary" @click="startBook()">{{ t('Add a book') }}</button>
            <input v-model="query" type="search" class="input max-w-xs" :placeholder="t('Search by title, author or ISBN')">
        </div>
        <FieldError :message="$page.props.errors?.book" />

        <section v-if="editing" class="card mb-4">
            <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="saveBook">
                <div class="sm:col-span-2"><label class="label" for="title">{{ t('Title') }}</label><input id="title" v-model="book.title" class="input" required><FieldError :message="book.errors.title" /></div>
                <div><label class="label" for="author">{{ t('Author') }}</label><input id="author" v-model="book.author" class="input"></div>
                <div><label class="label" for="isbn">ISBN</label><input id="isbn" v-model="book.isbn" class="input" dir="ltr"></div>
                <div><label class="label" for="publisher">{{ t('Publisher') }}</label><input id="publisher" v-model="book.publisher" class="input"></div>
                <div><label class="label" for="year">{{ t('Year') }}</label><input id="year" v-model="book.published_year" type="number" class="input" dir="ltr"></div>
                <div><label class="label" for="category">{{ t('Category') }}</label><input id="category" v-model="book.category" class="input"></div>
                <div><label class="label" for="shelf">{{ t('Shelf') }}</label><input id="shelf" v-model="book.shelf" class="input"></div>
                <div><label class="label" for="copies">{{ t('Copies') }}</label><input id="copies" v-model.number="book.copies" type="number" min="1" class="input" required><FieldError :message="book.errors.copies" /></div>
                <div class="flex items-end gap-3 sm:col-span-2 lg:col-span-3">
                    <button class="btn-primary" :disabled="book.processing">{{ t('Save') }}</button>
                    <button type="button" class="btn-ghost" @click="editing = null">{{ t('Cancel') }}</button>
                </div>
            </form>
        </section>

        <section v-if="lending" class="card mb-4">
            <h2 class="mb-3 font-semibold">{{ t('Lend :title', { title: lending.title }) }}</h2>
            <form class="grid gap-4 sm:grid-cols-3" @submit.prevent="saveLoan">
                <div class="sm:col-span-2">
                    <label class="label" for="who">{{ t('Borrower') }}</label>
                    <input id="who" v-model="who" class="input" :placeholder="t('Student or staff name or number')">
                    <select v-if="borrowers.length" v-model="loan.borrower" class="input mt-2" required :aria-label="t('Borrower')">
                        <option value="" disabled>—</option>
                        <option v-for="b in borrowers" :key="b.value" :value="b.value">{{ b.label }}</option>
                    </select>
                    <FieldError :message="loan.errors.borrower || loan.errors.library_book_id" />
                </div>
                <div><label class="label" for="due">{{ t('Return by') }}</label><input id="due" v-model="loan.due_on" type="date" class="input" dir="ltr" required></div>
                <div class="flex gap-3 sm:col-span-3">
                    <button class="btn-primary" :disabled="loan.processing || !loan.borrower">{{ t('Lend') }}</button>
                    <button type="button" class="btn-ghost" @click="lending = null">{{ t('Cancel') }}</button>
                </div>
            </form>
        </section>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="card overflow-x-auto p-0 lg:col-span-2">
                <table class="w-full text-sm">
                    <thead class="border-b border-line text-muted">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium">{{ t('Title') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ t('Shelf') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ t('Available') }}</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="b in books.data" :key="b.id" class="border-b border-line last:border-0">
                            <td class="px-4 py-3"><div class="font-medium">{{ b.title }}</div><div class="text-xs text-muted">{{ [b.author, b.category].filter(Boolean).join(' · ') }}</div></td>
                            <td class="px-4 py-3">{{ b.shelf ?? '—' }}</td>
                            <td class="px-4 py-3 tabular-nums" :class="!b.available && 'text-danger'">{{ b.available }} / {{ b.copies }}</td>
                            <td class="px-4 py-3 text-end whitespace-nowrap">
                                <button v-if="b.available" type="button" class="text-accent hover:underline" @click="startLend(b)">{{ t('Lend') }}</button>
                                <button type="button" class="ms-3 text-muted hover:underline" @click="startBook(b)">{{ t('Edit') }}</button>
                            </td>
                        </tr>
                        <tr v-if="!books.data.length"><td colspan="4" class="px-4 py-8 text-center text-muted">{{ t('No books yet.') }}</td></tr>
                    </tbody>
                </table>
                <div v-if="books.last_page > 1" class="flex flex-wrap gap-1 p-3">
                    <template v-for="link in books.links" :key="link.label">
                        <Link v-if="link.url" :href="link.url" class="rounded-lg px-3 py-1.5 text-sm" :class="link.active ? 'bg-accent text-accent-ink' : 'border border-line'" preserve-scroll><span v-html="link.label" /></Link>
                    </template>
                </div>
            </div>

            <section class="card">
                <h2 class="mb-3 font-semibold">{{ t('On loan') }} <span class="text-sm font-normal text-muted">({{ loans.length }}<template v-if="overdue">, {{ t(':count overdue', { count: overdue }) }}</template>)</span></h2>
                <ul class="divide-y divide-line text-sm">
                    <li v-for="l in loans" :key="l.id" class="flex items-start gap-2 py-2">
                        <div class="min-w-0 flex-1">
                            <div class="font-medium">{{ l.book }}</div>
                            <div class="text-muted">{{ l.borrower }}</div>
                            <div :class="l.overdue ? 'font-semibold text-danger' : 'text-muted'">{{ t('Return by :date', { date: formatDate(l.due_on, $page.props.locale) }) }}</div>
                        </div>
                        <button type="button" class="btn-ghost px-3 py-1" @click="giveBack(l)">{{ t('Returned') }}</button>
                    </li>
                    <li v-if="!loans.length" class="py-2 text-muted">{{ t('No books on loan.') }}</li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
