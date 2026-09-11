<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSettings } from '@/useSettings';

const { t } = useSettings();
</script>

<template>
    <Head :title="t('arabic_typing_test_page.h1')" />

    <AppLayout>
        <div class="max-w-4xl mx-auto py-12 px-6 md:px-0">
            <header class="text-center mb-14">
                <h1 class="font-cinzel text-2xl sm:text-3xl text-[var(--main-color)] mb-4">
                    {{ t('arabic_typing_test_page.h1') }}
                </h1>
                <p class="max-w-2xl mx-auto text-sm sm:text-base leading-relaxed text-[var(--sub-color)]">
                    {{ t('arabic_typing_test_page.intro') }}
                </p>

                <div class="mt-8 flex flex-col items-center gap-2">
                    <Link href="/"
                        class="inline-flex items-center justify-center bg-[var(--caret-color)] text-[var(--bg-color)] px-8 py-3.5 font-cinzel font-semibold text-sm uppercase tracking-[0.12em] hover:opacity-90 transition-opacity">
                        {{ t('arabic_typing_test_page.cta') }}
                    </Link>
                    <span class="text-[11px] uppercase tracking-[0.15em] text-[var(--sub-color)] opacity-70">
                        {{ t('arabic_typing_test_page.cta_note') }}
                    </span>
                </div>
            </header>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-16">
                <div v-for="feature in t('arabic_typing_test_page.features')" :key="feature.title"
                    class="border border-[var(--border-color)] p-6">
                    <h3 class="font-cinzel text-sm text-[var(--caret-color)] mb-2">{{ feature.title }}</h3>
                    <p class="text-sm text-[var(--sub-color)] leading-relaxed">{{ feature.desc }}</p>
                </div>
            </div>

            <p class="text-center text-sm text-[var(--sub-color)] mb-16">
                {{ t('arabic_typing_test_page.cross_link_text') }}
                <Link href="/quran-memorization" class="text-[var(--lapis-color)] hover:opacity-80 transition-opacity underline underline-offset-4">
                    {{ t('arabic_typing_test_page.cross_link_label') }}
                </Link>
            </p>

            <div class="border-t border-[var(--rule-color)] pt-10">
                <h2 class="font-cinzel text-lg text-[var(--main-color)] mb-6 text-center">FAQ</h2>
                <div class="flex flex-col gap-6 max-w-2xl mx-auto">
                    <div v-for="item in t('arabic_typing_test_page.faq')" :key="item.q">
                        <h3 class="text-sm font-semibold text-[var(--main-color)] mb-1">{{ item.q }}</h3>
                        <p class="text-sm text-[var(--sub-color)] leading-relaxed">{{ item.a }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
