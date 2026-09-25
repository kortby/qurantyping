<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    conversations: Object,
    filters: Object,
    total: Number,
});

const timeAgo = (value) => {
    if (!value) return '—';
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
        <title>Chats - Admin | QuranTyping</title>
    </Head>

    <AppLayout>
        <div class="py-8 animate-fade-in min-h-[80vh]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
                    <div>
                        <h1 class="text-2xl font-cinzel font-semibold text-[var(--caret-color)]">Chats</h1>
                        <p class="text-[var(--sub-color)] font-mono text-[10px] uppercase tracking-[0.3em] mt-1">
                            {{ conversations.total }} conversations · {{ total }} messages
                        </p>
                    </div>
                    <Link href="/admin/users" class="font-mono text-[10px] uppercase tracking-[0.3em] text-[var(--sub-color)] hover:text-[var(--caret-color)] transition-colors self-start sm:self-end">
                        Users →
                    </Link>
                </div>

                <div v-if="filters.user" class="flex items-center gap-3 mb-4 font-mono text-[11px] uppercase tracking-[0.15em]">
                    <span class="px-3 py-1.5 border border-[var(--caret-color)] text-[var(--caret-color)]">
                        With {{ filters.user.name }}
                    </span>
                    <Link href="/admin/chats" class="text-[var(--sub-color)] hover:text-[var(--main-color)] transition-colors">Clear ×</Link>
                </div>

                <div class="border border-[var(--border-color)]">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[820px] text-left font-mono text-sm">
                            <thead>
                                <tr class="bg-[var(--caret-color)]/5 text-[var(--sub-color)] uppercase tracking-[0.15em] text-[10px]">
                                    <th class="px-6 py-3 font-semibold">Between</th>
                                    <th class="px-6 py-3 font-semibold text-center">Messages</th>
                                    <th class="px-6 py-3 font-semibold">Last message</th>
                                    <th class="px-6 py-3 font-semibold text-right">Last activity</th>
                                    <th class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--border-color)]">
                                <tr v-for="c in conversations.data" :key="`${c.user_a.id}-${c.user_b.id}`" class="hover:bg-[var(--caret-color)]/[0.03] transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <Link :href="`/admin/users/${c.user_a.id}`" class="text-[var(--main-color)] hover:text-[var(--caret-color)] transition-colors">{{ c.user_a.name }}</Link>
                                        <span class="mx-2 text-[var(--sub-color)]">↔</span>
                                        <Link :href="`/admin/users/${c.user_b.id}`" class="text-[var(--main-color)] hover:text-[var(--caret-color)] transition-colors">{{ c.user_b.name }}</Link>
                                    </td>
                                    <td class="px-6 py-4 text-center text-[var(--caret-color)] tabular-nums">{{ c.messages_count }}</td>
                                    <td class="px-6 py-4 text-[var(--sub-color)] max-w-xs">
                                        <span v-if="c.last_message" class="block truncate [unicode-bidi:plaintext]">
                                            <span class="text-[10px] uppercase tracking-[0.15em] opacity-70">{{ c.last_message.sender_id === c.user_a.id ? c.user_a.name : c.user_b.name }}:</span>
                                            {{ c.last_message.body }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-[var(--sub-color)] whitespace-nowrap" :title="c.last_message ? new Date(c.last_message.created_at).toLocaleString() : ''">
                                        {{ timeAgo(c.last_message?.created_at) }}
                                    </td>
                                    <td class="px-6 py-4 text-right whitespace-nowrap">
                                        <Link :href="`/admin/chats/${c.user_a.id}/${c.user_b.id}`" class="text-[10px] uppercase tracking-[0.2em] text-[var(--lapis-color)] hover:opacity-70 transition-opacity">
                                            Read →
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="conversations.data.length === 0" class="py-20 text-center text-[var(--sub-color)] font-mono text-sm">
                        No conversations yet.
                    </div>

                    <div v-if="conversations.data.length > 0" class="p-3 border-t border-[var(--border-color)] flex justify-between items-center font-mono text-[10px]">
                        <Link v-if="conversations.prev_page_url" :href="conversations.prev_page_url" preserve-scroll
                              class="px-3 py-1 border border-[var(--border-color)] text-[var(--caret-color)]">← prev</Link>
                        <span v-else></span>
                        <span class="text-[var(--sub-color)] uppercase tracking-[0.15em]">Page {{ conversations.current_page }} / {{ conversations.last_page }}</span>
                        <Link v-if="conversations.next_page_url" :href="conversations.next_page_url" preserve-scroll
                              class="px-3 py-1 border border-[var(--border-color)] text-[var(--caret-color)]">next →</Link>
                        <span v-else></span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
