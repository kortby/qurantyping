<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Certificate from '@/Components/Certificate.vue';
import { useSettings } from '../../useSettings';

defineProps({
    certificates: { type: Array, default: () => [] },
});

const { t } = useSettings();
</script>

<template>
    <Head><title>{{ t('certificates.title') }} - QuranTyping</title></Head>

    <AppLayout>
        <div class="py-10 sm:py-12 animate-fade-in min-h-[80vh]">
            <div class="max-w-4xl mx-auto px-4 sm:px-6">
                <header class="mb-8">
                    <h1 class="text-2xl font-cinzel font-semibold text-[var(--caret-color)]">{{ t('certificates.title') }}</h1>
                    <p class="text-[var(--sub-color)] font-mono text-[10px] uppercase tracking-[0.3em] mt-1">
                        {{ t('certificates.subtitle') }}
                    </p>
                </header>

                <div v-if="certificates.length" class="grid gap-6 sm:grid-cols-2">
                    <Certificate v-for="c in certificates" :key="c.surah_number" :cert="c" />
                </div>

                <div v-else class="border border-[var(--border-color)] p-8 text-center">
                    <p class="text-[var(--main-color)] mb-2">{{ t('certificates.empty_title') }}</p>
                    <p class="text-[var(--sub-color)] text-sm mb-6 max-w-md mx-auto">{{ t('certificates.empty_desc') }}</p>
                    <Link href="/hifz" class="inline-flex items-center min-h-[44px] px-8 bg-[var(--caret-color)] text-[var(--bg-color)] font-cinzel font-semibold">
                        {{ t('certificates.start') }}
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
