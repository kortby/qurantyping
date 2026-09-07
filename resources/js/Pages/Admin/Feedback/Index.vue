<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    feedback: Object,
    filters: Object,
    counts: Object,
});

const page = usePage();

const tabs = [
    { key: 'open', label: 'Open' },
    { key: 'handled', label: 'Handled' },
    { key: 'all', label: 'All' },
];

const types = ['bug', 'suggestion', 'other'];

const reload = (params) => {
    router.get('/admin/feedback', {
        filter: props.filters.filter,
        type: props.filters.type ?? undefined,
        ...params,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['feedback', 'filters', 'counts'],
    });
};

const setTab = (key) => reload({ filter: key });
const setType = (type) => reload({ type: props.filters.type === type ? undefined : type });

const typeStyles = {
    bug: 'border-[var(--error-color)]/40 text-[var(--error-color)]',
    suggestion: 'border-[var(--lapis-color)]/40 text-[var(--lapis-color)]',
    other: 'border-[var(--border-color)] text-[var(--sub-color)]',
};

const timeAgo = (value) => {
    if (!value) return '';
    const s = Math.round((Date.now() - new Date(value)) / 1000);
    if (s < 60) return 'just now';
    const m = Math.round(s / 60);
    if (m < 60) return `${m}m ago`;
    const h = Math.round(m / 60);
    if (h < 24) return `${h}h ago`;
    const d = Math.round(h / 24);
    if (d < 30) return `${d}d ago`;
    return new Date(value).toLocaleDateString();
};
</script>

<template>
    <Head>
        <title>Feedback - Admin | QuranTyping</title>
    </Head>

    <AppLayout>
        <div class="py-8 animate-fade-in min-h-[80vh]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
                    <div>
                        <h1 class="text-2xl font-cinzel font-semibold text-[var(--caret-color)]">Feedback</h1>
                        <p class="text-[var(--sub-color)] font-mono text-[10px] uppercase tracking-[0.3em] mt-1">
                            {{ counts.open }} open · {{ counts.all }} total
                        </p>
                    </div>
                    <Link href="/admin/users" class="font-mono text-[10px] uppercase tracking-[0.3em] text-[var(--sub-color)] hover:text-[var(--caret-color)] transition-colors self-start sm:self-end">
                        Users →
                    </Link>
                </div>

                <div v-if="page.props.flash.message" class="mb-4 border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 font-mono text-xs text-emerald-500">
                    {{ page.props.flash.message }}
                </div>

                <div class="flex flex-wrap items-center gap-2 mb-4 font-mono text-[11px] uppercase tracking-[0.15em]">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        @click="setTab(tab.key)"
                        class="px-3 py-1.5 border transition-colors"
                        :class="filters.filter === tab.key
                            ? 'border-[var(--caret-color)] text-[var(--caret-color)]'
                            : 'border-[var(--border-color)] text-[var(--sub-color)] hover:text-[var(--main-color)]'"
                    >
                        {{ tab.label }}
                        <span class="ml-1 opacity-60">{{ counts[tab.key] }}</span>
                    </button>

                    <span class="w-px h-5 bg-[var(--border-color)] mx-1"></span>

                    <button
                        v-for="type in types"
                        :key="type"
                        type="button"
                        @click="setType(type)"
                        class="px-3 py-1.5 border transition-colors"
                        :class="filters.type === type
                            ? 'border-[var(--caret-color)] text-[var(--caret-color)]'
                            : 'border-[var(--border-color)] text-[var(--sub-color)] hover:text-[var(--main-color)]'"
                    >
                        {{ type }}
                    </button>
                </div>

                <div class="border border-[var(--border-color)]">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-left font-mono text-sm">
                            <thead>
                                <tr class="bg-[var(--caret-color)]/5 text-[var(--sub-color)] uppercase tracking-[0.15em] text-[10px]">
                                    <th class="px-6 py-3 font-semibold">Type</th>
                                    <th class="px-6 py-3 font-semibold">Message</th>
                                    <th class="px-6 py-3 font-semibold">From</th>
                                    <th class="px-6 py-3 font-semibold text-center">Received</th>
                                    <th class="px-6 py-3 font-semibold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--border-color)]">
                                <tr v-for="item in feedback.data" :key="item.id" class="hover:bg-[var(--caret-color)]/[0.03] transition-colors">
                                    <td class="px-6 py-4 align-top whitespace-nowrap">
                                        <span class="text-[9px] uppercase tracking-[0.15em] px-1.5 py-0.5 border" :class="typeStyles[item.type] || typeStyles.other">
                                            {{ item.type }}
                                        </span>
                                        <span v-if="!item.handled_at" class="ml-2 inline-block w-1.5 h-1.5 rounded-full bg-[var(--caret-color)]" title="Open"></span>
                                    </td>
                                    <td class="px-6 py-4 align-top max-w-md">
                                        <Link :href="`/admin/feedback/${item.id}`" class="text-[var(--main-color)] hover:text-[var(--caret-color)] transition-colors line-clamp-2">
                                            {{ item.excerpt }}
                                        </Link>
                                        <span v-if="item.responded_at" class="mt-1 inline-block text-[9px] uppercase tracking-[0.15em] text-emerald-500" title="A reply was sent">Replied</span>
                                    </td>
                                    <td class="px-6 py-4 align-top text-[var(--sub-color)] whitespace-nowrap">
                                        <template v-if="item.user">
                                            <Link :href="`/admin/users/${item.user.id}`" class="hover:text-[var(--caret-color)] transition-colors">{{ item.user.name }}</Link>
                                            <span class="block text-[10px] opacity-60">{{ item.user.email }}</span>
                                        </template>
                                        <span v-else class="opacity-40">deleted user</span>
                                    </td>
                                    <td class="px-6 py-4 align-top text-center text-[var(--sub-color)] whitespace-nowrap" :title="new Date(item.created_at).toLocaleString()">
                                        {{ timeAgo(item.created_at) }}
                                    </td>
                                    <td class="px-6 py-4 align-top text-right">
                                        <Link :href="`/admin/feedback/${item.id}`" class="text-[11px] border border-[var(--border-color)] px-3 py-1.5 hover:border-[var(--caret-color)] transition-colors">
                                            View
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="feedback.data.length === 0" class="py-20 text-center text-[var(--sub-color)] font-mono text-sm">
                        No feedback here.
                    </div>

                    <div v-if="feedback.data.length > 0" class="p-3 border-t border-[var(--border-color)] flex justify-between items-center font-mono text-[10px]">
                        <Link
                            v-if="feedback.prev_page_url"
                            :href="feedback.prev_page_url"
                            :only="['feedback']"
                            preserve-scroll preserve-state
                            class="px-3 py-1 border border-[var(--border-color)] text-[var(--caret-color)]"
                        >← prev</Link>
                        <span v-else></span>
                        <span class="text-[var(--sub-color)] uppercase tracking-[0.15em]">Page {{ feedback.current_page }} / {{ feedback.last_page }}</span>
                        <Link
                            v-if="feedback.next_page_url"
                            :href="feedback.next_page_url"
                            :only="['feedback']"
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
