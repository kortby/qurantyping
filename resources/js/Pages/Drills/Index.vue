<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSettings } from '../../useSettings';

const props = defineProps({
    letters: { type: Array, default: () => [] },
    weak: { type: Array, default: () => [] },
    ready: { type: Boolean, default: false },
    minAttempts: { type: Number, default: 25 },
});

const { t } = useSettings();

const MARK = /[\u0610-\u061A\u064B-\u065F\u0670\u06D6-\u06ED\u0640]/;

const baseLetters = computed(() => props.letters.filter((l) => !MARK.test(l.character)));
const marks = computed(() => props.letters.filter((l) => MARK.test(l.character)));

const accent = (accuracy) => {
    if (accuracy < 90) return 'text-[var(--error-color)] border-[var(--error-color)]';
    if (accuracy < 97) return 'text-[var(--sub-color)] border-[var(--border-color)]';
    return 'text-[var(--main-color)] border-[var(--border-color)]';
};

const markLabel = (character) => {
    // A bare combining mark won't render on its own; hang it on a dotted circle.
    return `◌${character}`;
};
</script>

<template>
    <Head><title>{{ t('drills.title') }} - QuranTyping</title></Head>

    <AppLayout>
        <div class="py-10 sm:py-12 animate-fade-in min-h-[80vh]">
            <div class="max-w-4xl mx-auto px-4 sm:px-6">
                <header class="mb-8 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-cinzel font-semibold text-[var(--caret-color)]">{{ t('drills.title') }}</h1>
                        <p class="text-[var(--sub-color)] font-mono text-[10px] uppercase tracking-[0.3em] mt-1">
                            {{ t('drills.subtitle') }}
                        </p>
                    </div>

                    <Link
                        v-if="ready"
                        href="/?drill=1"
                        class="inline-flex items-center justify-center min-h-[44px] px-6 bg-[var(--caret-color)] text-[var(--bg-color)] font-cinzel font-semibold whitespace-nowrap"
                    >
                        {{ t('drills.start_drill') }}
                    </Link>
                    <span
                        v-else
                        class="inline-flex items-center justify-center min-h-[44px] px-6 border border-[var(--border-color)] text-[var(--sub-color)] font-cinzel whitespace-nowrap cursor-not-allowed"
                    >
                        {{ t('drills.locked') }}
                    </span>
                </header>

                <div v-if="!letters.length" class="border border-[var(--border-color)] p-8 text-center">
                    <p class="text-[var(--main-color)] mb-2">{{ t('drills.empty_title') }}</p>
                    <p class="text-[var(--sub-color)] text-sm mb-6 max-w-md mx-auto">
                        {{ t('drills.empty_desc') }}
                    </p>
                    <Link href="/" class="inline-flex items-center min-h-[44px] px-8 bg-[var(--caret-color)] text-[var(--bg-color)] font-cinzel font-semibold">
                        {{ t('drills.start_typing') }}
                    </Link>
                </div>

                <template v-else>
                    <section v-if="weak.length" class="mb-10 border border-[var(--border-color)] p-5">
                        <h2 class="font-mono text-[10px] uppercase tracking-[0.25em] text-[var(--sub-color)] mb-4">
                            {{ t('drills.weakest') }}
                        </h2>
                        <div class="flex flex-wrap gap-3">
                            <div
                                v-for="w in weak"
                                :key="w.character"
                                class="flex items-center gap-3 border border-[var(--error-color)] px-3 py-2"
                            >
                                <span class="text-2xl leading-none" style="font-family: 'Noto Naskh Arabic', serif;" dir="rtl">
                                    {{ MARK.test(w.character) ? markLabel(w.character) : w.character }}
                                </span>
                                <span class="font-mono text-xs text-[var(--sub-color)]">
                                    {{ w.accuracy }}% · {{ w.attempts }}
                                </span>
                            </div>
                        </div>
                        <p class="font-mono text-[11px] text-[var(--sub-color)] mt-4">
                            {{ t('drills.drill_hint') }}
                        </p>
                    </section>
                    <p v-else class="mb-10 font-mono text-[11px] text-[var(--sub-color)]">
                        {{ t('drills.not_enough').replace('{n}', minAttempts) }}
                    </p>

                    <section v-if="baseLetters.length" class="mb-10">
                        <h2 class="font-mono text-[10px] uppercase tracking-[0.25em] text-[var(--sub-color)] mb-4">{{ t('drills.letters') }}</h2>
                        <div class="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-7 gap-2">
                            <div
                                v-for="l in baseLetters"
                                :key="l.character"
                                class="border p-3 text-center"
                                :class="accent(l.accuracy)"
                            >
                                <div class="text-3xl leading-none mb-2" style="font-family: 'Noto Naskh Arabic', serif;" dir="rtl">{{ l.character }}</div>
                                <div class="font-mono text-sm tabular-nums">{{ l.accuracy }}%</div>
                                <div class="font-mono text-[10px] text-[var(--sub-color)]">{{ l.attempts }}</div>
                            </div>
                        </div>
                    </section>

                    <section v-if="marks.length">
                        <h2 class="font-mono text-[10px] uppercase tracking-[0.25em] text-[var(--sub-color)] mb-4">{{ t('drills.marks') }}</h2>
                        <div class="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-7 gap-2">
                            <div
                                v-for="m in marks"
                                :key="m.character"
                                class="border p-3 text-center"
                                :class="accent(m.accuracy)"
                            >
                                <div class="text-3xl leading-none mb-2" style="font-family: 'Noto Naskh Arabic', serif;" dir="rtl">{{ markLabel(m.character) }}</div>
                                <div class="font-mono text-sm tabular-nums">{{ m.accuracy }}%</div>
                                <div class="font-mono text-[10px] text-[var(--sub-color)]">{{ m.attempts }}</div>
                            </div>
                        </div>
                    </section>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
