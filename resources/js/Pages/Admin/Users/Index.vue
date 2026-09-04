<script setup>
import { ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    users: Object,
    filters: Object,
});

const search = ref(props.filters?.search ?? '');

let debounce = null;
watch(search, (value) => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/admin/users', { search: value || undefined }, {
            preserveState: true,
            replace: true,
            only: ['users', 'filters'],
        });
    }, 300);
});

const formatDate = (value) => value ? new Date(value).toLocaleDateString() : '—';
</script>

<template>
    <Head>
        <title>Users - Admin | QuranTyping</title>
    </Head>

    <AppLayout>
        <div class="py-8 animate-fade-in min-h-[80vh]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-8">
                    <div>
                        <h1 class="text-3xl font-cinzel text-[var(--caret-color)] font-bold tracking-widest">Users</h1>
                        <p class="text-[var(--sub-color)] font-mono text-[10px] uppercase tracking-[0.4em] opacity-80 mt-1">
                            {{ users.total }} registered
                        </p>
                    </div>
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search name or email…"
                        class="w-full sm:w-72 bg-[var(--bg-color)] border border-[var(--border-color)] rounded-xl px-4 py-2 font-mono text-sm text-[var(--main-color)] focus:border-[var(--caret-color)] focus:outline-none"
                    />
                </div>

                <div class="bg-[var(--panel-color)] rounded-[2rem] overflow-hidden border border-[var(--border-color)] backdrop-blur-xl shadow-2xl">
                    <table class="w-full text-left font-mono text-sm border-collapse">
                        <thead>
                            <tr class="bg-[var(--caret-color)]/5 text-[var(--sub-color)] uppercase tracking-[0.3em] text-[10px]">
                                <th class="px-6 py-4 font-bold">Name</th>
                                <th class="px-6 py-4 font-bold">Email</th>
                                <th class="px-6 py-4 font-bold text-center">Tests</th>
                                <th class="px-6 py-4 font-bold text-center">Joined</th>
                                <th class="px-6 py-4 font-bold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--border-color)]">
                            <tr v-for="user in users.data" :key="user.id" class="hover:bg-[var(--caret-color)]/[0.03] transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <Link :href="`/admin/users/${user.id}`" class="font-cinzel font-bold text-[var(--main-color)] hover:text-[var(--caret-color)] transition-colors">
                                            {{ user.name }}
                                        </Link>
                                        <span v-if="user.is_super_admin" class="text-[8px] uppercase tracking-widest px-1.5 py-0.5 rounded bg-[var(--caret-color)]/10 text-[var(--caret-color)] border border-[var(--caret-color)]/20">admin</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <span class="opacity-80">{{ user.email }}</span>
                                        <span
                                            :title="user.email_verified_at ? 'Verified' : 'Unverified'"
                                            :class="user.email_verified_at ? 'text-emerald-500' : 'text-[var(--error-color)]'"
                                        >{{ user.email_verified_at ? '✓' : '✗' }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center font-bold text-[var(--caret-color)]">{{ user.tests_count }}</td>
                                <td class="px-6 py-4 text-center opacity-60">{{ formatDate(user.created_at) }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link :href="`/admin/users/${user.id}`" class="text-xs px-3 py-1 rounded-md border border-[var(--border-color)] hover:border-[var(--caret-color)] transition-colors">
                                            View
                                        </Link>
                                        <Link
                                            v-if="!user.is_super_admin"
                                            :href="`/admin/users/${user.id}/impersonate`"
                                            method="post"
                                            as="button"
                                            class="text-xs px-3 py-1 rounded-md bg-[var(--caret-color)] text-[var(--bg-color)] font-bold hover:scale-105 transition-transform"
                                        >
                                            Impersonate
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="users.data.length === 0" class="flex flex-col items-center justify-center py-24 text-[var(--sub-color)] font-mono">
                        <span class="text-5xl mb-4 opacity-20">∅</span>
                        <p class="opacity-40">No users found.</p>
                    </div>

                    <div v-if="users.data.length > 0" class="p-3 bg-[var(--caret-color)]/[0.03] flex justify-between items-center font-mono text-[10px]">
                        <Link
                            v-if="users.prev_page_url"
                            :href="users.prev_page_url"
                            :only="['users']"
                            preserve-scroll
                            preserve-state
                            class="px-3 py-1 rounded-md bg-[var(--bg-color)] border border-[var(--border-color)] text-[var(--caret-color)] hover:scale-105 transition-transform"
                        >← prev</Link>
                        <span v-else></span>
                        <span class="opacity-40 uppercase tracking-widest">Page {{ users.current_page }} / {{ users.last_page }}</span>
                        <Link
                            v-if="users.next_page_url"
                            :href="users.next_page_url"
                            :only="['users']"
                            preserve-scroll
                            preserve-state
                            class="px-3 py-1 rounded-md bg-[var(--bg-color)] border border-[var(--border-color)] text-[var(--caret-color)] hover:scale-105 transition-transform"
                        >next →</Link>
                        <span v-else></span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
