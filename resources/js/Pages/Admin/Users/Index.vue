<script setup>
import { ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    users: Object,
    filters: Object,
});

const search = ref(props.filters?.search ?? '');

const reload = (params) => {
    router.get('/admin/users', {
        search: search.value || undefined,
        sort: props.filters?.sort,
        direction: props.filters?.direction,
        ...params,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['users', 'filters'],
    });
};

let debounce = null;
watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => reload({}), 300);
});

const sortBy = (column) => {
    const direction = props.filters?.sort === column && props.filters?.direction === 'asc' ? 'desc' : 'asc';
    reload({ sort: column, direction });
};

const columns = [
    { key: 'name', label: 'Name', align: 'text-left' },
    { key: 'email', label: 'Email', align: 'text-left' },
    { key: 'tests_count', label: 'Tests', align: 'text-center' },
    { key: 'email_verified_at', label: 'Verified', align: 'text-center' },
    { key: 'created_at', label: 'Joined', align: 'text-center' },
];

const formatDate = (value) => value ? new Date(value).toLocaleDateString() : '—';
</script>

<template>
    <Head>
        <title>Users - Admin | QuranTyping</title>
    </Head>

    <AppLayout>
        <div class="py-8 animate-fade-in min-h-[80vh]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
                    <div>
                        <h1 class="text-2xl font-cinzel font-semibold text-[var(--caret-color)]">Users</h1>
                        <p class="text-[var(--sub-color)] font-mono text-[10px] uppercase tracking-[0.3em] mt-1">
                            {{ users.total }} registered
                        </p>
                    </div>
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search name or email…"
                        class="w-full sm:w-72 min-h-[40px] bg-[var(--bg-color)] border border-[var(--border-color)] px-4 font-mono text-sm text-[var(--main-color)] focus:border-[var(--caret-color)] focus:outline-none"
                    />
                </div>

                <div class="border border-[var(--border-color)]">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px] text-left font-mono text-sm">
                            <thead>
                                <tr class="bg-[var(--caret-color)]/5 text-[var(--sub-color)] uppercase tracking-[0.15em] text-[10px]">
                                    <th v-for="col in columns" :key="col.key" class="px-6 py-3 font-semibold" :class="col.align">
                                        <button
                                            type="button"
                                            @click="sortBy(col.key)"
                                            class="inline-flex items-center gap-1.5 hover:text-[var(--main-color)] transition-colors"
                                            :class="{ 'text-[var(--caret-color)]': filters.sort === col.key }"
                                        >
                                            {{ col.label }}
                                            <span class="w-2 text-[8px] leading-none">
                                                <template v-if="filters.sort === col.key">{{ filters.direction === 'asc' ? '▲' : '▼' }}</template>
                                            </span>
                                        </button>
                                    </th>
                                    <th class="px-6 py-3 font-semibold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--border-color)]">
                                <tr v-for="user in users.data" :key="user.id" class="hover:bg-[var(--caret-color)]/[0.03] transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <Link :href="`/admin/users/${user.id}`" class="font-cinzel text-[var(--main-color)] hover:text-[var(--caret-color)] transition-colors">
                                                {{ user.name }}
                                            </Link>
                                            <span v-if="user.is_super_admin" class="text-[8px] uppercase tracking-[0.15em] px-1.5 py-0.5 border border-[var(--caret-color)]/30 text-[var(--caret-color)]">admin</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-[var(--sub-color)]">{{ user.email }}</td>
                                    <td class="px-6 py-4 text-center text-[var(--caret-color)] tabular-nums">{{ user.tests_count }}</td>
                                    <td class="px-6 py-4 text-center">
                                        <span
                                            :title="user.email_verified_at ? 'Verified' : 'Unverified'"
                                            :class="user.email_verified_at ? 'text-emerald-500' : 'text-[var(--error-color)]'"
                                        >{{ user.email_verified_at ? '✓' : '✗' }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-center text-[var(--sub-color)]">{{ formatDate(user.created_at) }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-2">
                                            <Link :href="`/admin/users/${user.id}`" class="text-[11px] border border-[var(--border-color)] px-3 py-1.5 hover:border-[var(--caret-color)] transition-colors">
                                                View
                                            </Link>
                                            <Link
                                                v-if="!user.is_super_admin"
                                                :href="`/admin/users/${user.id}/impersonate`"
                                                method="post"
                                                as="button"
                                                class="text-[11px] bg-[var(--caret-color)] text-[var(--bg-color)] font-semibold px-3 py-1.5 hover:opacity-90 transition-opacity"
                                            >
                                                Impersonate
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="users.data.length === 0" class="py-20 text-center text-[var(--sub-color)] font-mono text-sm">
                        No users found.
                    </div>

                    <div v-if="users.data.length > 0" class="p-3 border-t border-[var(--border-color)] flex justify-between items-center font-mono text-[10px]">
                        <Link
                            v-if="users.prev_page_url"
                            :href="users.prev_page_url"
                            :only="['users']"
                            preserve-scroll preserve-state
                            class="px-3 py-1 border border-[var(--border-color)] text-[var(--caret-color)]"
                        >← prev</Link>
                        <span v-else></span>
                        <span class="text-[var(--sub-color)] uppercase tracking-[0.15em]">Page {{ users.current_page }} / {{ users.last_page }}</span>
                        <Link
                            v-if="users.next_page_url"
                            :href="users.next_page_url"
                            :only="['users']"
                            preserve-scroll preserve-state
                            class="px-3 py-1 border border-[var(--border-color)] text-[var(--caret-color)]"
                        >next →</Link>
                        <span v-else></span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
