<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSettings } from '@/useSettings';

const { t } = useSettings();
</script>

<template>
    <Head :title="t('work_in_progress.title')" />

    <AppLayout>
        <div class="min-h-[60vh] flex items-center justify-center py-12">
            <div class="w-full max-w-2xl text-center px-6">
                <!-- Background Decorative Rings -->
                <div class="relative mb-12 flex justify-center">
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="w-64 h-64 border border-[var(--border-color)] rounded-full opacity-20 animate-pulse"></div>
                        <div class="w-48 h-48 border border-[var(--caret-color)] rounded-full opacity-10 absolute animate-[pulse_3s_infinite]"></div>
                    </div>
                    
                    <div class="relative z-10 w-32 h-32 flex items-center justify-center bg-[var(--panel-color)] border border-[var(--border-color)] rounded-3xl shadow-2xl backdrop-blur-xl rotate-12 hover:rotate-0 transition-transform duration-500 group">
                        <span class="text-5xl group-hover:scale-110 transition-transform duration-500">🌙</span>
                    </div>
                </div>

                <h2 class="font-cinzel text-[10px] uppercase tracking-[0.5em] text-[var(--caret-color)] mb-4 animate-fade-in opacity-60">
                    {{ t('work_in_progress.subtitle') }}
                </h2>
                
                <h1 class="text-4xl md:text-5xl font-cinzel font-bold text-[var(--main-color)] mb-8 tracking-wider">
                    {{ t('work_in_progress.title') }}
                </h1>
                
                <p class="text-sm md:text-base text-[var(--sub-color)] leading-relaxed mb-12 max-w-lg mx-auto italic font-serif opacity-80">
                    "{{ t('work_in_progress.message') }}"
                </p>

                <div class="flex flex-col sm:flex-row items-center justify-center gap-6">
                    <Link href="/" class="bg-[var(--caret-color)] text-[var(--bg-color)] px-10 py-4 rounded-2xl font-cinzel font-bold text-xs uppercase tracking-[0.2em] hover:scale-105 active:scale-95 transition-all shadow-xl shadow-emerald-950/40">
                        {{ t('work_in_progress.back_home') }}
                    </Link>
                    
                    <a href="https://buy.stripe.com/dRmdRa1546e60jI2jZenS01" target="_blank" class="text-[var(--caret-color)] font-cinzel text-[10px] uppercase tracking-widest hover:opacity-100 opacity-60 transition-opacity flex items-center gap-2">
                        <span>❤️</span> {{ t('support_now') }}
                    </a>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 0.1; }
    50% { transform: scale(1.1); opacity: 0.2; }
}
</style>
