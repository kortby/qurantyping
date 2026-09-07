<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Certificate from '@/Components/Certificate.vue';
import { useSettings } from '../../useSettings';

const props = defineProps({
    certificate: { type: Object, required: true },
});

const { t } = useSettings();

const cert = computed(() => ({
    ...props.certificate,
    share_url: typeof window !== 'undefined' ? window.location.href : '',
}));

const title = computed(
    () => `${props.certificate.holder} — ${t('certificates.surah_prefix')} ${props.certificate.surah_name_english}`,
);
</script>

<template>
    <Head>
        <title>{{ title }} · QuranTyping</title>
        <meta name="description" :content="t('certificates.public_intro').replace('{holder}', certificate.holder)" />
    </Head>

    <AppLayout>
        <div class="py-10 sm:py-14 animate-fade-in min-h-[80vh]">
            <div class="max-w-2xl mx-auto px-4 sm:px-6">
                <header class="mb-8 text-center">
                    <p class="font-mono text-[10px] uppercase tracking-[0.3em] text-[var(--sub-color)]">
                        {{ t('certificates.public_eyebrow') }}
                    </p>
                    <h1 class="mt-2 text-xl sm:text-2xl font-cinzel font-semibold text-[var(--caret-color)]">
                        {{ t('certificates.public_intro').replace('{holder}', certificate.holder) }}
                    </h1>
                </header>

                <Certificate :cert="cert" />

                <div class="mt-10 border border-[var(--border-color)] p-6 text-center">
                    <p class="text-[var(--main-color)] mb-1">{{ t('certificates.public_cta_title') }}</p>
                    <p class="text-[var(--sub-color)] text-sm mb-5 max-w-md mx-auto">{{ t('certificates.public_cta_desc') }}</p>
                    <Link href="/" class="inline-flex items-center min-h-[44px] px-8 bg-[var(--caret-color)] text-[var(--bg-color)] font-cinzel font-semibold">
                        {{ t('certificates.public_cta_button') }}
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
