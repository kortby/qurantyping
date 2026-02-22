<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSettings } from '../../useSettings';

defineProps({
    leaderboard: Array,
    config: Object,
});

const { t } = useSettings();

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('contest.leaderboard_title')" />

    <div class="max-w-6xl mx-auto py-12 px-6 lg:px-0 min-h-[80vh]">
        <div class="text-center mb-12">
            <h1 class="text-4xl md:text-5xl font-cinzel font-bold text-[var(--caret-color)] mb-4">{{ t('contest.leaderboard_title') }}</h1>
            <p class="text-[var(--sub-color)] font-mono text-sm tracking-widest max-w-2xl mx-auto">
                <span class="bg-[var(--panel-color)] px-3 py-1 rounded-full border border-[var(--border-color)]">
                    {{ t('contest.qualification').replace('{wpm}', config.min_wpm).replace('{acc}', config.min_accuracy) }}
                </span>
            </p>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-[var(--border-color)] shadow-2xl bg-[var(--panel-color)]">
            <table class="w-full text-left border-collapse min-w-[800px]">
                <thead>
                    <tr class="bg-[var(--bg-color)] border-b border-[var(--border-color)]">
                        <th class="p-4 text-[var(--sub-color)] font-mono text-xs uppercase tracking-widest text-center w-16">{{ t('rank') }}</th>
                        <th class="p-4 text-[var(--sub-color)] font-mono text-xs uppercase tracking-widest">{{ t('contest.name') }}</th>
                        <th class="p-4 text-[var(--caret-color)] font-mono text-xs uppercase tracking-widest text-right">{{ t('wpm') }}</th>
                        <th class="p-4 text-[var(--sub-color)] font-mono text-xs uppercase tracking-widest text-right">{{ t('contest.raw') }}</th>
                        <th class="p-4 text-[var(--sub-color)] font-mono text-xs uppercase tracking-widest text-right">{{ t('accuracy') }}</th>
                        <th class="p-4 text-emerald-500/80 font-mono text-xs uppercase tracking-widest text-right hidden md:table-cell">{{ t('contest.correct') }}</th>
                        <th class="p-4 text-red-500/80 font-mono text-xs uppercase tracking-widest text-right hidden md:table-cell">{{ t('errors') }}</th>
                        <th class="p-4 text-[var(--sub-color)] font-mono text-xs uppercase tracking-widest text-right hidden lg:table-cell">{{ t('contest.length') }}</th>
                        <th class="p-4 text-[var(--sub-color)] font-mono text-xs uppercase tracking-widest text-right">{{ t('time') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!leaderboard.length" class="border-b border-[var(--border-color)]">
                        <td colspan="9" class="p-8 text-center text-[var(--sub-color)] font-cinzel py-20">
                            {{ t('contest.first_to_qualify') }}
                        </td>
                    </tr>
                    <tr v-for="(entry, i) in leaderboard" :key="entry.id" class="border-b border-[var(--border-color)] last:border-0 hover:bg-black/10 transition-colors">
                        <td class="p-4 text-center font-bold text-[var(--main-color)] opacity-70">
                            <span v-if="i === 0" class="text-2xl" title="1st Place">🥇</span>
                            <span v-else-if="i === 1" class="text-2xl" title="2nd Place">🥈</span>
                            <span v-else-if="i === 2" class="text-2xl" title="3rd Place">🥉</span>
                            <span v-else>{{ i + 1 }}</span>
                        </td>
                        <td class="p-4 text-[var(--main-color)] font-bold tracking-wide">{{ entry.user.name }}</td>
                        <td class="p-4 text-right text-[var(--caret-color)] font-bold text-lg">{{ entry.wpm }}</td>
                        <td class="p-4 text-right text-[var(--sub-color)]">{{ entry.raw_wpm }}</td>
                        <td class="p-4 text-right text-[var(--main-color)] font-mono">{{ entry.accuracy }}%</td>
                        <td class="p-4 text-right text-emerald-500 hidden md:table-cell font-mono">{{ entry.correct_chars }}</td>
                        <td class="p-4 text-right text-red-500 hidden md:table-cell font-mono">{{ entry.incorrect_chars }}</td>
                        <td class="p-4 text-right text-[var(--main-color)] hidden lg:table-cell font-mono">{{ entry.char_count }}</td>
                        <td class="p-4 text-right text-[var(--sub-color)] font-mono">{{ entry.duration }}s</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
