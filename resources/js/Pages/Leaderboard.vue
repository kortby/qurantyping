<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useSettings } from '../useSettings';

const { t } = useSettings();

defineProps({
    topScorers: Array,
    contest_config: Object,
});
</script>

<template>
    <Head>
        <title>Leaderboard - Top Quran Typists | QuranTyping</title>
        <meta name="description" content="See the fastest Quran typists globally. Challenge the masters and claim your place on the leaderboard of truth.">
    </Head>

    <AppLayout>
        <div class="py-8 animate-fade-in min-h-[80vh]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="text-center mb-10">
                    <h1 class="text-4xl font-cinzel text-[var(--caret-color)] font-bold mb-2 tracking-widest">{{ t('leaderboard') }}</h1>
                    <p class="text-[var(--sub-color)] font-mono text-[10px] uppercase tracking-[0.5em] opacity-80">{{ t('leaderboard_subtitle') }}</p>
                    <div class="w-16 h-1 bg-[var(--caret-color)]/20 mx-auto mt-4 rounded-full"></div>
                </div>

                <!-- Leaderboard Table -->
                <div class="bg-[var(--panel-color)] rounded-[3rem] overflow-hidden border border-[var(--border-color)] backdrop-blur-xl shadow-2xl">
                    <table class="w-full text-left font-mono text-sm border-collapse">
                        <thead>
                            <tr class="bg-[var(--caret-color)]/5 text-[var(--sub-color)] uppercase tracking-[0.3em] text-[10px]">
                                <th class="px-8 py-4 font-bold text-center">{{ t('rank') }}</th>
                                <th class="px-8 py-4 font-bold">{{ t('seeker') }}</th>
                                <th class="px-8 py-4 font-bold text-center">{{ t('wpm') }}</th>
                                <th class="px-8 py-4 font-bold text-center">{{ t('accuracy') }}</th>
                                <th class="px-8 py-4 font-bold text-center">{{ t('errors') }}</th>
                                <th class="px-8 py-4 font-bold text-center">{{ t('chars') }}</th>
                                <th class="px-8 py-4 font-bold text-right">{{ t('tests_count') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--border-color)]">
                            <tr v-for="(scorer, index) in topScorers" :key="index" 
                                class="hover:bg-[var(--caret-color)]/[0.03] transition-all duration-500 group">
                                <td class="px-8 py-4 text-center relative overflow-hidden">
                                     <div v-if="scorer.is_eligible" 
                                          class="absolute left-[-36px] top-[10px] w-[140px] -rotate-45 bg-[var(--caret-color)] text-emerald-950 text-[8px] font-bold uppercase tracking-widest py-0.5 text-center shadow-[0_2px_4px_rgba(0,0,0,0.3)] opacity-95 cursor-help hover:opacity-100 transition-opacity"
                                          :title="t('contest.eligible_tooltip')
                                                 .replace('{wpm}', contest_config.min_wpm)
                                                 .replace('{acc}', contest_config.min_accuracy)
                                                 .replace('{chars}', contest_config.min_char_count)">
                                         {{ t('contest.eligible') }}
                                     </div>
                                    <div class="flex items-center justify-center">
                                        <div v-if="index === 0" class="text-4xl filter drop-shadow-md">🥇</div>
                                        <div v-else-if="index === 1" class="text-3xl filter drop-shadow-md">🥈</div>
                                        <div v-else-if="index === 2" class="text-2xl filter drop-shadow-md">🥉</div>
                                        <span v-else class="text-base opacity-40 font-cinzel font-bold">#{{ index + 1 }}</span>
                                    </div>
                                </td>
                                <td class="px-8 py-4">
                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-3">
                                            <span class="text-lg font-cinzel font-bold text-[var(--main-color)] group-hover:text-[var(--caret-color)] transition-colors">
                                                {{ scorer.name }}
                                            </span>
                                            <div v-if="scorer.badges && scorer.badges.length > 0" class="flex items-center gap-1.5">
                                                <div v-for="badge in scorer.badges" :key="badge.id" 
                                                      :title="badge.name + (badge.description ? ' - ' + badge.description : '')"
                                                      class="flex items-center justify-center w-6 h-6 rounded-full bg-amber-500/10 border border-amber-500/30 text-sm cursor-help hover:scale-110 hover:bg-amber-500/20 transition-all shadow-[0_0_10px_rgba(245,158,11,0.1)]"
                                                >
                                                    {{ badge.icon }}
                                                </div>
                                            </div>
                                        </div>
                                        <span class="text-[9px] uppercase tracking-widest opacity-40">{{ t('devoted_reader') }}</span>
                                    </div>
                                </td>
                                <td class="px-8 py-4 text-center">
                                    <div class="flex flex-col items-center">
                                        <span class="text-3xl font-cinzel font-bold text-[var(--caret-color)]">{{ scorer.best_wpm }}</span>
                                        <span class="text-[8px] uppercase tracking-widest opacity-40">{{ t('words_min') }}</span>
                                    </div>
                                </td>
                                <td class="px-8 py-4 text-center">
                                    <div class="inline-flex items-center justify-center px-4 py-1.5 rounded-full border border-[var(--border-color)] bg-[var(--caret-color)]/[0.02] text-[var(--caret-color)] font-bold">
                                        {{ Math.round(scorer.best_accuracy) }}%
                                    </div>
                                </td>
                                <td class="px-8 py-4 text-center font-bold text-[var(--error-color)]">
                                    {{ scorer.total_errors }}
                                </td>
                                <td class="px-8 py-4 text-center font-bold text-[var(--caret-color)] opacity-80">
                                    {{ scorer.char_count }}
                                </td>
                                <td class="px-8 py-4 text-right opacity-40 font-bold">
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
                <div class="mt-8 text-center text-xs">
                    <p class="text-[var(--sub-color)] font-mono text-[10px] uppercase tracking-widest mb-4 opacity-60">{{ t('challenge_masters') }}</p>
                    <Link href="/" class="inline-flex items-center gap-2 bg-[var(--caret-color)] text-[var(--bg-color)] px-6 py-2 rounded-xl font-cinzel font-bold uppercase tracking-widest hover:scale-105 active:scale-95 transition-all shadow-xl shadow-emerald-950/40">
                        <span class="text-lg">⌨️</span>
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
