<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useSettings } from '../useSettings';

const { t } = useSettings();

defineProps({
    topScorers: Array,
});
</script>

<template>
    <Head :title="t('leaderboard')" />

    <AppLayout>
        <div class="py-12 animate-fade-in min-h-[80vh]">
            <div class="max-w-4xl mx-auto">
                <!-- Header -->
                <div class="text-center mb-16">
                    <h1 class="text-6xl font-cinzel text-[var(--caret-color)] font-bold mb-4 tracking-widest">{{ t('leaderboard') }}</h1>
                    <p class="text-[var(--sub-color)] font-mono text-xs uppercase tracking-[0.5em] opacity-80">{{ t('leaderboard_subtitle') }}</p>
                    <div class="w-24 h-1 bg-[var(--caret-color)]/20 mx-auto mt-8 rounded-full"></div>
                </div>

                <!-- Leaderboard Table -->
                <div class="bg-[var(--panel-color)] rounded-[3rem] overflow-hidden border border-[var(--border-color)] backdrop-blur-xl shadow-2xl">
                    <table class="w-full text-left font-mono text-sm border-collapse">
                        <thead>
                            <tr class="bg-[var(--caret-color)]/5 text-[var(--sub-color)] uppercase tracking-[0.3em] text-[10px]">
                                <th class="px-12 py-8 font-bold text-center">{{ t('rank') }}</th>
                                <th class="px-12 py-8 font-bold">{{ t('seeker') }}</th>
                                <th class="px-12 py-8 font-bold text-center">{{ t('wpm') }}</th>
                                <th class="px-12 py-8 font-bold text-center">{{ t('accuracy') }}</th>
                                <th class="px-12 py-8 font-bold text-right">{{ t('tests_count') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--border-color)]">
                            <tr v-for="(scorer, index) in topScorers" :key="index" 
                                class="hover:bg-[var(--caret-color)]/[0.03] transition-all duration-500 group">
                                <td class="px-12 py-10 text-center">
                                    <div class="flex items-center justify-center">
                                        <div v-if="index === 0" class="text-4xl filter drop-shadow-md">🥇</div>
                                        <div v-else-if="index === 1" class="text-3xl filter drop-shadow-md">🥈</div>
                                        <div v-else-if="index === 2" class="text-2xl filter drop-shadow-md">🥉</div>
                                        <span v-else class="text-lg opacity-40 font-cinzel font-bold">#{{ index + 1 }}</span>
                                    </div>
                                </td>
                                <td class="px-12 py-10">
                                    <div class="flex flex-col">
                                        <span class="text-xl font-cinzel font-bold text-[var(--main-color)] group-hover:text-[var(--caret-color)] transition-colors">
                                            {{ scorer.name }}
                                        </span>
                                        <span class="text-[10px] uppercase tracking-widest opacity-40">{{ t('devoted_reader') }}</span>
                                    </div>
                                </td>
                                <td class="px-12 py-10 text-center">
                                    <div class="flex flex-col items-center">
                                        <span class="text-4xl font-cinzel font-bold text-[var(--caret-color)]">{{ scorer.best_wpm }}</span>
                                        <span class="text-[9px] uppercase tracking-widest opacity-40">{{ t('words_min') }}</span>
                                    </div>
                                </td>
                                <td class="px-12 py-10 text-center">
                                    <div class="inline-flex items-center justify-center px-4 py-1.5 rounded-full border border-[var(--border-color)] bg-[var(--caret-color)]/[0.02] text-[var(--caret-color)] font-bold">
                                        {{ Math.round(scorer.best_accuracy) }}%
                                    </div>
                                </td>
                                <td class="px-12 py-10 text-right opacity-40 font-bold">
                                    {{ scorer.total_tests }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Empty State -->
                    <div v-if="topScorers.length === 0" class="flex flex-col items-center justify-center py-32 text-[var(--sub-color)] font-mono">
                        <span class="text-6xl mb-6 opacity-20 capitalize">{{ t('no_scores') }}</span>
                        <p class="text-xl opacity-40">{{ t('claim_throne') }}</p>
                    </div>
                </div>

                <!-- Call to Action -->
                <div class="mt-20 text-center">
                    <p class="text-[var(--sub-color)] font-mono text-xs uppercase tracking-widest mb-8 opacity-60">{{ t('challenge_masters') }}</p>
                    <Link href="/" class="inline-flex items-center gap-4 bg-[var(--caret-color)] text-[var(--bg-color)] px-10 py-4 rounded-2xl font-cinzel font-bold uppercase tracking-widest hover:scale-105 active:scale-95 transition-all shadow-2xl shadow-emerald-950/40">
                        <span class="text-2xl">⌨️</span>
                        {{ t('start_testing') }}
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
tr:hover td {
    transform: scale(1.02);
}
tr td {
    transition: transform 0.4s cubic-bezier(0.2, 1, 0.3, 1);
}
</style>
