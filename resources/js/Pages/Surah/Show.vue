<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSettings } from '../../useSettings';

const props = defineProps({
    surah: { type: Object, required: true },
});

const { t } = useSettings();

const startUrl = computed(() => `/?surah=${props.surah.surah_number}&start=1&end=${props.surah.ayah_count}`);
const intro = computed(() => t('surah_page.intro_template').replace('{count}', props.surah.ayah_count));
const faqVersesAnswer = computed(() => t('surah_page.faq_verses_a_template').replace('{count}', props.surah.ayah_count));
</script>

<template>
    <Head><title>{{ props.surah.name_english }} - QuranTyping</title></Head>

    <AppLayout>
        <div class="max-w-3xl mx-auto py-12 px-6 md:px-0">
            <header class="text-center mb-10">
                <p class="font-mono text-[10px] uppercase tracking-[0.3em] text-[var(--sub-color)] mb-3">
                    {{ t('surah_page.verses_label') }}: {{ props.surah.ayah_count }}
                </p>
                <h1 class="font-cinzel text-2xl sm:text-3xl text-[var(--main-color)] mb-2">
                    {{ props.surah.name_english }}
                </h1>
                <p class="font-arabic text-2xl text-[var(--caret-color)]" dir="rtl">{{ props.surah.name_arabic }}</p>

                <p class="max-w-xl mx-auto mt-6 text-sm sm:text-base leading-relaxed text-[var(--sub-color)]">
                    {{ intro }}
                </p>

                <div class="mt-8 flex flex-col items-center gap-2">
                    <Link :href="startUrl"
                        class="inline-flex items-center justify-center bg-[var(--caret-color)] text-[var(--bg-color)] px-8 py-3.5 font-cinzel font-semibold text-sm uppercase tracking-[0.12em] hover:opacity-90 transition-opacity">
                        {{ t('surah_page.start_button') }}
                    </Link>
                    <span class="text-[11px] uppercase tracking-[0.15em] text-[var(--sub-color)] opacity-70">
                        {{ t('surah_page.cta_note') }}
                    </span>
                </div>
            </header>

            <p class="text-center text-sm text-[var(--sub-color)] mb-4">
                {{ t('surah_page.cross_link_typing_text') }}
                <Link href="/arabic-typing-test" class="text-[var(--lapis-color)] hover:opacity-80 transition-opacity underline underline-offset-4">
                    {{ t('surah_page.cross_link_typing_label') }}
                </Link>
            </p>
            <p class="text-center text-sm text-[var(--sub-color)] mb-4">
                {{ t('surah_page.cross_link_hifz_text') }}
                <Link href="/quran-memorization" class="text-[var(--lapis-color)] hover:opacity-80 transition-opacity underline underline-offset-4">
                    {{ t('surah_page.cross_link_hifz_label') }}
                </Link>
            </p>
            <p class="text-center text-sm mb-16">
                <Link href="/surah" class="text-[var(--sub-color)] hover:text-[var(--caret-color)] transition-colors underline underline-offset-4">
                    {{ t('surah_page.browse_all') }}
                </Link>
            </p>

            <div class="border-t border-[var(--rule-color)] pt-10">
                <h2 class="font-cinzel text-lg text-[var(--main-color)] mb-6 text-center">{{ t('surah_page.faq_title') }}</h2>
                <div class="flex flex-col gap-6 max-w-2xl mx-auto">
                    <div>
                        <h3 class="text-sm font-semibold text-[var(--main-color)] mb-1">{{ t('surah_page.faq_verses_q') }}</h3>
                        <p class="text-sm text-[var(--sub-color)] leading-relaxed">{{ faqVersesAnswer }}</p>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-[var(--main-color)] mb-1">{{ t('surah_page.faq_free_q') }}</h3>
                        <p class="text-sm text-[var(--sub-color)] leading-relaxed">{{ t('surah_page.faq_free_a') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
