<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSettings } from '../../useSettings';

defineProps({
    challenge: { type: Object, required: true },
    mine: { type: Object, default: () => null },
    board: { type: Array, default: () => [] },
});

const { t } = useSettings();

const prettyDate = (iso) => new Date(iso + 'T00:00:00').toLocaleDateString(undefined, {
    weekday: 'long', month: 'long', day: 'numeric',
});
</script>

<template>
    <Head><title>{{ t('daily.title') }} - QuranTyping</title></Head>

    <AppLayout>
        <div class="py-10 sm:py-12 animate-fade-in min-h-[80vh]">
            <div class="max-w-2xl mx-auto px-4 sm:px-6">
                <header class="mb-8">
                    <h1 class="text-2xl font-cinzel font-semibold text-[var(--caret-color)]">{{ t('daily.title') }}</h1>
                    <p class="text-[var(--sub-color)] font-mono text-[10px] uppercase tracking-[0.3em] mt-1">
                        {{ prettyDate(challenge.date) }}
                    </p>
                </header>

                <!-- The passage -->
                <div class="border border-[var(--border-color)] p-6 mb-8 flex flex-col items-center text-center gap-4">
                    <p class="font-mono text-[10px] uppercase tracking-[0.25em] text-[var(--sub-color)]">{{ t('daily.todays_passage') }}</p>
                    <p class="text-xl text-[var(--main-color)]" dir="rtl">{{ challenge.surah_name_arabic }}</p>
                    <p class="font-mono text-sm text-[var(--caret-color)] tabular-nums">
                        {{ challenge.surah_number }}:{{ challenge.start_ayah }}–{{ challenge.end_ayah }}
                    </p>

                    <p v-if="mine" class="font-mono text-xs text-[var(--sub-color)]">
                        {{ t('daily.your_result').replace('{wpm}', mine.wpm).replace('{acc}', mine.accuracy) }}
                    </p>

                    <Link href="/?daily=1"
                          class="mt-2 inline-flex items-center border border-[var(--caret-color)] text-[var(--caret-color)] px-6 py-2.5 font-cinzel font-semibold uppercase tracking-[0.12em] hover:bg-[var(--caret-color)] hover:text-[var(--bg-color)] transition-colors">
                        {{ mine ? t('daily.try_again') : t('daily.type_it') }}
                    </Link>
                </div>

                <!-- Friends board -->
                <section>
                    <h2 class="font-mono text-[10px] uppercase tracking-[0.25em] text-[var(--sub-color)] mb-3">{{ t('daily.friends_board') }}</h2>
                    <div v-if="board.length" class="border border-[var(--border-color)] divide-y divide-[var(--border-color)] font-mono text-sm">
                        <div v-for="(row, i) in board" :key="row.user_id"
                             class="flex items-center justify-between px-4 py-3"
                             :class="row.is_me ? 'bg-[var(--caret-color)]/[0.05]' : ''">
                            <span class="flex items-center gap-3">
                                <span class="opacity-40 tabular-nums w-5">#{{ i + 1 }}</span>
                                <span class="text-[var(--main-color)]">{{ row.name }}</span>
                            </span>
                            <span class="flex items-center gap-4">
                                <span class="text-[var(--caret-color)] tabular-nums">{{ row.wpm }} {{ t('wpm') }}</span>
                                <span class="text-[var(--sub-color)] tabular-nums">{{ row.accuracy }}%</span>
                            </span>
                        </div>
                    </div>
                    <p v-else class="font-mono text-xs text-[var(--sub-color)]">{{ t('daily.board_empty') }}</p>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
