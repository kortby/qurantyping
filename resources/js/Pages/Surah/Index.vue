<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSettings } from '../../useSettings';

defineProps({
    surahs: { type: Array, default: () => [] },
});

const { t } = useSettings();
</script>

<template>
    <Head><title>{{ t('surah_index.title') }} - QuranTyping</title></Head>

    <AppLayout>
        <div class="py-10 sm:py-12 animate-fade-in min-h-[80vh]">
            <div class="max-w-4xl mx-auto px-4 sm:px-6">
                <header class="mb-8 text-center">
                    <h1 class="text-2xl font-cinzel font-semibold text-[var(--caret-color)]">{{ t('surah_index.title') }}</h1>
                    <p class="text-[var(--sub-color)] font-mono text-[10px] uppercase tracking-[0.3em] mt-1">
                        {{ t('surah_index.subtitle') }}
                    </p>
                </header>

                <div class="border border-[var(--border-color)] divide-y divide-[var(--border-color)] font-mono text-sm">
                    <Link v-for="s in surahs" :key="s.surah_number" :href="`/surah/${s.slug}`"
                          class="flex items-center justify-between px-4 py-3 hover:bg-[var(--caret-color)]/5 transition-colors">
                        <span class="flex items-center gap-3">
                            <span class="w-7 h-7 shrink-0 flex items-center justify-center border border-[var(--border-color)] text-[10px] text-[var(--caret-color)]">
                                {{ s.surah_number }}
                            </span>
                            <span class="text-[var(--main-color)]">{{ s.name_english }}</span>
                            <span class="text-[var(--sub-color)]" dir="rtl">{{ s.name_arabic }}</span>
                        </span>
                        <span class="text-[var(--sub-color)] text-xs">{{ s.ayah_count }} {{ t('surah_index.verses') }}</span>
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
