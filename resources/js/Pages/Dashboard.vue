<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSettings } from '../useSettings';
import { Line } from 'vue-chartjs';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
} from 'chart.js';

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
);

const { t } = useSettings();

const props = defineProps({
    results: Object,
    bestWpm: Number,
    averageWpm: Number,
    chartData: Array,
    bestTest: Object,
});

const chartDataValues = computed(() => {
    return {
        labels: props.chartData.map((_, index) => index + 1),
        datasets: [
            {
                label: t('wpm'),
                data: props.chartData.map(d => d.wpm),
                borderColor: '#fbbf24',
                backgroundColor: 'rgba(251, 191, 36, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointHoverRadius: 6,
                borderWidth: 3,
            },
            {
                label: t('accuracy'),
                data: props.chartData.map(d => d.accuracy),
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.05)',
                fill: true,
                tension: 0.4,
                pointRadius: 0,
                borderWidth: 2,
                borderDash: [5, 5],
                yAxisID: 'y1',
            },
            {
                label: 'errors',
                data: props.chartData.map(d => d.total_errors),
                borderColor: '#ef4444',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 2,
                pointHoverRadius: 4,
                borderWidth: 2,
                yAxisID: 'y2',
            }
        ]
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: true,
            position: 'top',
            align: 'end',
            labels: {
                color: '#94a3b8',
                font: {
                    family: 'Inter, sans-serif',
                    size: 10,
                },
                usePointStyle: true,
                padding: 20
            }
        },
        tooltip: {
            mode: 'index',
            intersect: false,
            backgroundColor: 'rgba(15, 23, 42, 0.9)',
            titleColor: '#94a3b8',
            bodyColor: '#fff',
            borderColor: 'rgba(251, 191, 36, 0.2)',
            borderWidth: 1,
            padding: 12,
            displayColors: true,
        },
    },
    scales: {
        x: {
            display: false,
        },
        y: {
            beginAtZero: true,
            grid: {
                color: 'rgba(255, 255, 255, 0.03)',
            },
            ticks: {
                color: '#64748b',
                font: { size: 10 }
            }
        },
        y1: {
            position: 'right',
            beginAtZero: true,
            max: 100,
            display: false,
        },
        y2: {
            position: 'right',
            beginAtZero: true,
            grid: {
                drawOnChartArea: false,
            },
            display: false,
        }
    },
    interaction: {
        intersect: false,
        mode: 'nearest',
    }
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString(undefined, { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const formatDuration = (seconds) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return mins > 0 ? `${mins}m ${secs}s` : `${secs}s`;
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
                        <h1 class="text-5xl font-cinzel text-[var(--caret-color)] font-bold mb-3 tracking-wider">{{ t('dashboard') }}</h1>
                        <p class="text-[var(--sub-color)] font-mono text-sm uppercase tracking-[0.4em] opacity-80">{{ t('recent_performance') }}</p>
                    </div>
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex flex-col items-center md:items-end bg-[var(--panel-color)] px-10 py-6 rounded-3xl border border-[var(--border-color)] backdrop-blur-md shadow-2xl relative overflow-hidden group">
                            <div class="absolute top-0 right-0 w-24 h-24 bg-[var(--caret-color)] opacity-[0.03] -mr-8 -mt-8 rounded-full transition-all group-hover:scale-150"></div>
                            <span class="text-[10px] text-[var(--sub-color)] uppercase tracking-widest font-mono mb-2 relative z-10">{{ t('personal_best') }}</span>
                            <div class="text-5xl text-[var(--caret-color)] font-cinzel font-bold flex items-center gap-4 relative z-10">
                                <span class="text-4xl filter drop-shadow-md">🌙</span> {{ bestWpm }} <span class="text-lg opacity-60 font-mono tracking-tighter">{{ t('wpm') }}</span>
                            </div>
                        </div>

                        <div class="flex flex-col items-center md:items-end bg-[var(--panel-color)] px-10 py-6 rounded-3xl border border-[var(--border-color)] backdrop-blur-md shadow-2xl relative overflow-hidden group">
                            <div class="absolute top-0 right-0 w-24 h-24 bg-[var(--main-color)] opacity-[0.03] -mr-8 -mt-8 rounded-full transition-all group-hover:scale-150"></div>
                            <span class="text-[10px] text-[var(--sub-color)] uppercase tracking-widest font-mono mb-2 relative z-10">{{ t('average_speed') }}</span>
                            <div class="text-5xl text-[var(--main-color)] font-cinzel font-bold flex items-center gap-4 relative z-10">
                                <span class="text-4xl filter drop-shadow-md">📊</span> {{ averageWpm }} <span class="text-lg opacity-60 font-mono tracking-tighter">{{ t('wpm') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Evolution Chart -->
                <div v-if="chartData.length > 1" class="mb-16">
                    <div class="bg-[var(--panel-color)] p-8 rounded-[2.5rem] border border-[var(--border-color)] shadow-2xl backdrop-blur-md">
                        <div class="flex justify-between items-center mb-8">
                            <h3 class="text-[10px] text-[var(--sub-color)] uppercase tracking-[0.2em] font-mono opacity-80">
                                {{ t('speed_evolution') }}
                            </h3>
                        </div>
                        <div class="h-[250px] w-full">
                            <Line :data="chartDataValues" :options="chartOptions" />
                        </div>
                    </div>
                </div>

                <!-- Best Test Showcase -->
                <div v-if="bestTest" class="mb-16">
                    <div class="relative group bg-gradient-to-br from-amber-500/10 via-[var(--panel-color)] to-[var(--panel-color)] rounded-[2.5rem] border border-amber-500/20 shadow-2xl overflow-hidden backdrop-blur-md">
                        <div class="absolute top-0 right-0 p-8">
                             <span class="text-6xl opacity-20 filter grayscale group-hover:grayscale-0 transition-all duration-700 -rotate-12 group-hover:rotate-0 inline-block">🏆</span>
                        </div>
                        
                        <div class="p-10 flex flex-col md:flex-row items-center gap-12 relative z-10">
                            <div class="flex flex-col items-center md:items-start text-center md:text-left">
                                <span class="text-[10px] text-amber-500 uppercase tracking-[0.4em] font-mono mb-4">{{ t('personal_best') }}</span>
                                <div class="flex items-end gap-3 mb-2">
                                    <span class="text-7xl font-cinzel font-bold text-amber-500 leading-none">{{ bestTest.wpm }}</span>
                                    <span class="text-xl text-amber-500/60 font-mono mb-2">{{ t('wpm') }}</span>
                                </div>
                                <div class="flex gap-6 mt-4">
                                    <div class="flex flex-col">
                                        <span class="text-[8px] text-[var(--sub-color)] uppercase tracking-widest font-mono text-left">{{ t('accuracy') }}</span>
                                        <span class="text-lg text-[var(--main-color)] font-bold">{{ Math.round(bestTest.accuracy) }}%</span>
                                    </div>
                                    <div class="flex flex-col border-l border-[var(--border-color)] pl-6">
                                        <span class="text-[8px] text-[var(--sub-color)] uppercase tracking-widest font-mono text-left">errors</span>
                                        <span class="text-lg text-[var(--error-color)] font-bold">{{ bestTest.total_errors ?? 0 }}</span>
                                    </div>
                                    <div class="flex flex-col border-l border-[var(--border-color)] pl-6">
                                        <span class="text-[8px] text-[var(--sub-color)] uppercase tracking-widest font-mono text-left">{{ t('time') }}</span>
                                        <span class="text-lg text-[var(--main-color)] font-bold">{{ formatDuration(bestTest.duration) }}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex-1 flex flex-col items-center md:items-end text-right" dir="rtl">
                                <div class="text-[10px] text-[var(--sub-color)] uppercase tracking-[0.4em] font-mono mb-4 text-left w-full" dir="ltr" style="text-align: right;">{{ t('surah') }}</div>
                                <div class="text-4xl lg:text-5xl font-bold bg-gradient-to-l from-[var(--main-color)] to-amber-200 bg-clip-text text-transparent mb-3" style="font-family: 'Noto Naskh Arabic', serif;">
                                    {{ bestTest.quran_text.surah_name_arabic }}
                                </div>
                                <div class="flex items-center gap-4 text-[var(--sub-color)] opacity-60 font-mono text-xs w-full justify-center md:justify-end" dir="ltr">
                                    <span class="bg-white/5 px-3 py-1 rounded-full border border-white/5 whitespace-nowrap">{{ t('ayats') }} {{ bestTest.start_ayah }} - {{ bestTest.end_ayah }}</span>
                                    <span class="bg-white/5 px-3 py-1 rounded-full border border-white/5 whitespace-nowrap">{{ formatDate(bestTest.created_at) }}</span>
                                </div>
                                
                                <Link :href="`/?surah=${bestTest.quran_text.surah_number}&start=${bestTest.start_ayah || 1}&end=${bestTest.end_ayah || 1}`" 
                                      class="mt-8 bg-amber-500 text-[var(--bg-color)] px-8 py-3 rounded-2xl font-cinzel font-bold hover:scale-105 active:scale-95 transition-all shadow-xl shadow-amber-950/20 group-hover:shadow-amber-500/20" dir="ltr">
                                    {{ t('retake') }} →
                                </Link>
                            </div>
                        </div>
                        
                        <!-- Decorative background element -->
                        <div class="absolute -bottom-12 -left-12 w-48 h-48 bg-amber-500/10 rounded-full blur-3xl group-hover:bg-amber-500/20 transition-all duration-700"></div>
                    </div>
                </div>

                <!-- Stats Table -->
                <div class="bg-[var(--panel-color)] rounded-[2.5rem] overflow-hidden border border-[var(--border-color)] backdrop-blur-xl shadow-2xl transition-all duration-500 hover:shadow-emerald-900/10">
                    <div v-if="results.data.length > 0">
                        <table class="w-full text-left font-mono text-sm border-collapse">
                            <thead>
                                <tr class="bg-[var(--caret-color)]/5 text-[var(--sub-color)] uppercase tracking-[0.2em] text-[10px]">
                                    <th class="px-10 py-6 font-bold">{{ t('wpm') }}</th>
                                    <th class="px-10 py-6 font-bold text-center">{{ t('time') }}</th>
                                    <th class="px-10 py-6 font-bold text-center">errors</th>
                                    <th class="px-10 py-6 font-bold text-center">{{ t('accuracy') }}</th>
                                    <th class="px-10 py-6 font-bold text-center">{{ t('surah') }}</th>
                                    <th class="px-10 py-6 font-bold text-center">action</th>
                                    <th class="px-10 py-6 font-bold text-right">{{ t('date') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--border-color)]">
                                <tr v-for="result in results.data" :key="result.id" 
                                    class="hover:bg-[var(--caret-color)]/[0.02] transition-all duration-300 group">
                                    <td class="px-10 py-8">
                                        <div class="flex items-center gap-4">
                                            <span class="text-3xl font-bold" :class="result.wpm === bestWpm ? 'text-[var(--caret-color)]' : 'text-[var(--main-color)]'">
                                                {{ result.wpm }}
                                            </span>
                                            <span v-if="result.wpm === bestWpm" class="text-xl animate-bounce" title="Personal Best">👑</span>
                                        </div>
                                    </td>
                                    <td class="px-10 py-8 text-center text-xl text-[var(--main-color)] font-mono opacity-80">
                                        {{ formatDuration(result.duration) }}
                                    </td>
                                    <td class="px-10 py-8 text-center text-xl text-[var(--error-color)] font-bold">
                                        {{ result.total_errors ?? 0 }}
                                    </td>
                                    <td class="px-10 py-8 text-center">
                                        <div class="inline-flex items-center justify-center px-4 py-1.5 rounded-full border" 
                                             :class="result.accuracy > 95 ? 'border-green-500/30 bg-green-500/10 text-green-500' : 'border-white/10 text-[var(--sub-color)]'">
                                            {{ Math.round(result.accuracy) }}%
                                        </div>
                                    </td>
                                    <td class="px-10 py-8 text-center" dir="rtl">
                                        <div class="text-2xl mb-1" style="font-family: 'Noto Naskh Arabic', serif;">
                                            {{ result.quran_text.surah_name_arabic }}
                                        </div>
                                        <div class="text-[10px] font-mono text-[var(--sub-color)] opacity-60 flex items-center justify-center gap-1" dir="ltr">
                                            <span>{{ result.start_ayah }}</span>
                                            <span class="opacity-40">→</span>
                                            <span>{{ result.end_ayah }}</span>
                                        </div>
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
                             <Link v-if="results.prev_page_url" :href="results.prev_page_url" :only="['results']" preserve-scroll class="px-6 py-2 rounded-xl bg-[var(--bg-color)] border border-white/5 text-[var(--caret-color)] hover:scale-105 transition-all">← previous</Link>
                             <Link v-if="results.next_page_url" :href="results.next_page_url" :only="['results']" preserve-scroll class="px-6 py-2 rounded-xl bg-[var(--bg-color)] border border-white/5 text-[var(--caret-color)] hover:scale-105 transition-all">next →</Link>
                        </div>
                    </div>

                    <div v-else class="flex flex-col items-center justify-center py-32 text-[var(--sub-color)] font-mono">
                        <span class="text-6xl mb-6 filter drop-shadow-xl animate-bounce">⌨️</span>
                        <p class="text-xl opacity-60 mb-8">{{ t('no_tests') }}</p>
                        <Link href="/" class="bg-[var(--caret-color)] text-[var(--bg-color)] px-8 py-3 rounded-2xl font-cinzel font-bold hover:scale-110 active:scale-95 transition-all shadow-xl shadow-emerald-950/20">
                            {{ t('start_testing') }} →
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