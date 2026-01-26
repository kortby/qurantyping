<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useSettings } from '../useSettings';

const { t } = useSettings();

defineProps({
    results: Object,
    bestWpm: Number,
});

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString(undefined, { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script>

<template>
    <Head :title="t('dashboard')" />

    <AppLayout>
        <div class="py-12 animate-fade-in">
            <div class="max-w-5xl mx-auto">
                <!-- Header -->
                <div class="flex flex-col md:flex-row justify-between items-center md:items-end mb-16 gap-8">
                    <div class="text-center md:text-left">
                        <h1 class="text-5xl font-mono text-[var(--main-color)] font-bold mb-3 tracking-tighter">{{ t('dashboard') }}</h1>
                        <p class="text-[var(--sub-color)] font-mono text-sm uppercase tracking-widest">{{ t('recent_performance') }}</p>
                    </div>
                    <div class="flex flex-col items-center md:items-end bg-[var(--panel-color)] px-10 py-6 rounded-3xl border border-white/5 backdrop-blur-md shadow-2xl">
                        <span class="text-[10px] text-[var(--sub-color)] uppercase tracking-widest font-mono mb-2">{{ t('personal_best') }}</span>
                        <div class="text-5xl text-[var(--caret-color)] font-mono font-bold flex items-center gap-4">
                            <span class="text-4xl">👑</span> {{ bestWpm }} <span class="text-lg opacity-60">{{ t('wpm') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Stats Table -->
                <div class="bg-[var(--panel-color)] rounded-[2.5rem] overflow-hidden border border-white/5 backdrop-blur-xl shadow-2xl">
                    <div v-if="results.data.length > 0">
                        <table class="w-full text-left font-mono text-sm border-collapse">
                            <thead>
                                <tr class="bg-white/5 text-[var(--sub-color)] uppercase tracking-widest text-[10px]">
                                    <th class="px-10 py-6 font-bold">{{ t('wpm') }}</th>
                                    <th class="px-10 py-6 font-bold text-center">{{ t('accuracy') }}</th>
                                    <th class="px-10 py-6 font-bold text-center">{{ t('surah') }}</th>
                                    <th class="px-10 py-6 font-bold text-center">action</th>
                                    <th class="px-10 py-6 font-bold text-right">{{ t('date') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <tr v-for="result in results.data" :key="result.id" 
                                    class="hover:bg-white/5 transition-all duration-300 group">
                                    <td class="px-10 py-8">
                                        <div class="flex items-center gap-4">
                                            <span class="text-3xl font-bold" :class="result.wpm === bestWpm ? 'text-[var(--caret-color)]' : 'text-[var(--main-color)]'">
                                                {{ result.wpm }}
                                            </span>
                                            <span v-if="result.wpm === bestWpm" class="text-xl animate-bounce" title="Personal Best">👑</span>
                                        </div>
                                    </td>
                                    <td class="px-10 py-8 text-center">
                                        <div class="inline-flex items-center justify-center px-4 py-1.5 rounded-full border" 
                                             :class="result.accuracy > 95 ? 'border-green-500/30 bg-green-500/10 text-green-500' : 'border-white/10 text-[var(--sub-color)]'">
                                            {{ Math.round(result.accuracy) }}%
                                        </div>
                                    </td>
                                    <td class="px-10 py-8 text-center text-2xl" dir="rtl" style="font-family: 'Noto Naskh Arabic', serif;">
                                        {{ result.quran_text.surah_name_arabic }}
                                    </td>
                                    <td class="px-10 py-8 text-center">
                                        <Link :href="`/?surah=${result.quran_text.surah_number}&start=${result.start_ayah || 1}&end=${result.end_ayah || 1}`" 
                                              class="text-xs bg-[var(--caret-color)] text-[var(--bg-color)] px-4 py-1.5 rounded-full font-bold hover:scale-105 transition-all inline-block">
                                            {{ t('retake') }}
                                        </Link>
                                    </td>
                                    <td class="px-10 py-8 text-right text-[var(--sub-color)] opacity-60 text-xs">
                                        {{ formatDate(result.created_at) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <!-- Pagination -->
                        <div class="p-8 bg-white/5 flex justify-center gap-8 font-mono">
                             <Link v-if="results.prev_page_url" :href="results.prev_page_url" class="px-6 py-2 rounded-xl bg-[var(--bg-color)] border border-white/5 text-[var(--caret-color)] hover:scale-105 transition-all">← previous</Link>
                             <Link v-if="results.next_page_url" :href="results.next_page_url" class="px-6 py-2 rounded-xl bg-[var(--bg-color)] border border-white/5 text-[var(--caret-color)] hover:scale-105 transition-all">next →</Link>
                        </div>
                    </div>

                    <div v-else class="flex flex-col items-center justify-center py-32 text-[var(--sub-color)] font-mono">
                        <span class="text-6xl mb-6 filter drop-shadow-xl animate-bounce">⌨️</span>
                        <p class="text-xl opacity-60 mb-8">{{ t('no_tests') }}</p>
                        <Link href="/" class="bg-[var(--caret-color)] text-[var(--bg-color)] px-8 py-3 rounded-2xl font-bold hover:scale-110 active:scale-95 transition-all shadow-xl shadow-yellow-500/10">
                            {{ t('start_first') }} →
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Extra polish for table rows */
tr:hover td {
    transform: translateX(4px);
}
tr td {
    transition: transform 0.3s ease;
}
</style>