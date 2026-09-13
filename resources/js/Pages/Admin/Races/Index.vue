<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    races: Object,
    filters: Object,
    counts: Object,
});

const tabs = [
    { key: null, label: 'All' },
    { key: 'public', label: 'Public' },
    { key: 'private', label: 'Friend' },
];

const reload = (params) => {
    router.get('/admin/races', {
        visibility: props.filters.visibility ?? undefined,
        ...params,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['races', 'filters', 'counts'],
    });
};

const setTab = (key) => reload({ visibility: key ?? undefined });

const statusStyles = {
    lobby: 'border-[var(--border-color)] text-[var(--sub-color)]',
    countdown: 'border-[var(--lapis-color)]/40 text-[var(--lapis-color)]',
    racing: 'border-[var(--caret-color)]/40 text-[var(--caret-color)]',
    finished: 'border-[var(--border-color)] text-[var(--sub-color)]',
    abandoned: 'border-[var(--error-color)]/40 text-[var(--error-color)]',
};

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
        <title>Races - Admin | QuranTyping</title>
    </Head>

    <AppLayout>
        <div class="py-8 animate-fade-in min-h-[80vh]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
                    <div>
                        <h1 class="text-2xl font-cinzel font-semibold text-[var(--caret-color)]">Races</h1>
                        <p class="text-[var(--sub-color)] font-mono text-[10px] uppercase tracking-[0.3em] mt-1">
                            {{ counts.public }} public · {{ counts.private }} friend · {{ counts.all }} total
                        </p>
                    </div>
                    <Link href="/admin/users" class="font-mono text-[10px] uppercase tracking-[0.3em] text-[var(--sub-color)] hover:text-[var(--caret-color)] transition-colors self-start sm:self-end">
                        Users →
                    </Link>
                </div>

                <div class="flex flex-wrap items-center gap-2 mb-4 font-mono text-[11px] uppercase tracking-[0.15em]">
                    <button
                        v-for="tab in tabs"
                        :key="tab.label"
                        type="button"
                        @click="setTab(tab.key)"
                        class="px-3 py-1.5 border transition-colors"
                        :class="filters.visibility === tab.key
                            ? 'border-[var(--caret-color)] text-[var(--caret-color)]'
                            : 'border-[var(--border-color)] text-[var(--sub-color)] hover:text-[var(--main-color)]'"
                    >
                        {{ tab.label }}
                        <span class="ml-1 opacity-60">{{ counts[tab.key ?? 'all'] }}</span>
                    </button>
                </div>

                <div class="border border-[var(--border-color)]">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[880px] text-left font-mono text-sm">
                            <thead>
                                <tr class="bg-[var(--caret-color)]/5 text-[var(--sub-color)] uppercase tracking-[0.15em] text-[10px]">
                                    <th class="px-6 py-3 font-semibold">Race</th>
                                    <th class="px-6 py-3 font-semibold">Type</th>
                                    <th class="px-6 py-3 font-semibold">Status</th>
                                    <th class="px-6 py-3 font-semibold">Host</th>
                                    <th class="px-6 py-3 font-semibold">Passage</th>
                                    <th class="px-6 py-3 font-semibold text-center">Players</th>
                                    <th class="px-6 py-3 font-semibold">Winner</th>
                                    <th class="px-6 py-3 font-semibold text-right">Started</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--border-color)]">
                                <tr v-for="race in races.data" :key="race.id" class="hover:bg-[var(--caret-color)]/[0.03] transition-colors">
                                    <td class="px-6 py-4 text-[var(--main-color)] whitespace-nowrap">#{{ race.id }}<span v-if="race.code" class="text-[var(--sub-color)]"> · {{ race.code }}</span></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-[9px] uppercase tracking-[0.15em] px-1.5 py-0.5 border"
                                              :class="race.visibility === 'private' ? 'border-[var(--lapis-color)]/40 text-[var(--lapis-color)]' : 'border-[var(--border-color)] text-[var(--sub-color)]'">
                                            {{ race.visibility === 'private' ? 'Friend' : 'Public' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-[9px] uppercase tracking-[0.15em] px-1.5 py-0.5 border" :class="statusStyles[race.status] || statusStyles.lobby">
                                            {{ race.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-[var(--sub-color)] whitespace-nowrap">
                                        <Link v-if="race.host" :href="`/admin/users/${race.host_id}`" class="hover:text-[var(--caret-color)] transition-colors">{{ race.host }}</Link>
                                        <span v-else class="opacity-40">matchmade</span>
                                    </td>
                                    <td class="px-6 py-4 text-[var(--sub-color)] whitespace-nowrap">{{ race.surah_number }}:{{ race.start_ayah }}–{{ race.end_ayah }}</td>
                                    <td class="px-6 py-4 text-center text-[var(--caret-color)] tabular-nums">{{ race.participants_count }}</td>
                                    <td class="px-6 py-4 text-[var(--sub-color)] whitespace-nowrap">{{ race.winner ?? '—' }}</td>
                                    <td class="px-6 py-4 text-right text-[var(--sub-color)] whitespace-nowrap" :title="new Date(race.created_at).toLocaleString()">
                                        {{ timeAgo(race.created_at) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="races.data.length === 0" class="py-20 text-center text-[var(--sub-color)] font-mono text-sm">
                        No races here.
                    </div>

                    <div v-if="races.data.length > 0" class="p-3 border-t border-[var(--border-color)] flex justify-between items-center font-mono text-[10px]">
                        <Link
                            v-if="races.prev_page_url"
                            :href="races.prev_page_url"
                            :only="['races']"
                            preserve-scroll preserve-state
                            class="px-3 py-1 border border-[var(--border-color)] text-[var(--caret-color)]"
                        >← prev</Link>
                        <span v-else></span>
                        <span class="text-[var(--sub-color)] uppercase tracking-[0.15em]">Page {{ races.current_page }} / {{ races.last_page }}</span>
                        <Link
                            v-if="races.next_page_url"
                            :href="races.next_page_url"
                            :only="['races']"
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
