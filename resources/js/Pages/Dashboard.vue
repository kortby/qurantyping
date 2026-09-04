<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DailyGoal from '@/Components/DailyGoal.vue';
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
    <Head>
        <title>My Dashboard - QuranTyping Performance</title>
        <meta name="description" content="Track your progress, view your personal best WPM, and see your typing evolution over time in your personal QuranTyping dashboard.">
    </Head>

    <AppLayout>
        <div class="py-8 animate-fade-in">
            <div class="max-w-5xl mx-auto px-4 sm:px-6">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-end mb-6 gap-4">
                    <div>
                        <h1 class="text-2xl font-cinzel font-semibold text-[var(--caret-color)] tracking-tight">{{ t('dashboard') }}</h1>
                        <p class="text-[var(--sub-color)] font-mono text-[10px] uppercase tracking-[0.3em] mt-1">{{ t('recent_performance') }}</p>
                    </div>
                    <div class="flex gap-3">
                        <div class="flex-1 sm:flex-none border border-[var(--border-color)] px-4 py-2 text-center sm:text-right">
                            <span class="block text-[9px] text-[var(--sub-color)] uppercase tracking-[0.2em] font-mono">{{ t('personal_best') }}</span>
                            <span class="font-cinzel font-semibold text-xl text-[var(--caret-color)] tabular-nums">{{ bestWpm }} <span class="text-[10px] text-[var(--sub-color)] font-mono">{{ t('wpm') }}</span></span>
                        </div>
                        <div class="flex-1 sm:flex-none border border-[var(--border-color)] px-4 py-2 text-center sm:text-right">
                            <span class="block text-[9px] text-[var(--sub-color)] uppercase tracking-[0.2em] font-mono">{{ t('average_speed') }}</span>
                            <span class="font-cinzel font-semibold text-xl text-[var(--main-color)] tabular-nums">{{ averageWpm }} <span class="text-[10px] text-[var(--sub-color)] font-mono">{{ t('wpm') }}</span></span>
                        </div>
                    </div>
                </div>

                <DailyGoal class="mb-6" />

                <!-- Evolution Chart -->
                <div v-if="chartData.length > 1" class="mb-6 border border-[var(--border-color)] p-4">
                    <h3 class="text-[9px] text-[var(--sub-color)] uppercase tracking-[0.2em] font-mono mb-3">
                        {{ t('speed_evolution') }}
                    </h3>
                    <div class="overflow-x-auto">
                        <div class="h-[150px] min-w-[280px]">
                            <Line :data="chartDataValues" :options="chartOptions" />
                        </div>
                    </div>
                </div>

                <!-- Best Test -->
                <div v-if="bestTest" class="mb-6 border border-[var(--caret-color)]/40 p-4 flex flex-col sm:flex-row sm:items-center gap-5">
                    <div class="text-center sm:text-left">
                        <span class="block text-[9px] text-[var(--caret-color)] uppercase tracking-[0.3em] font-mono mb-1">{{ t('personal_best') }}</span>
                        <div class="flex items-end justify-center sm:justify-start gap-1.5">
                            <span class="text-4xl font-cinzel font-semibold text-[var(--caret-color)] tabular-nums leading-none">{{ bestTest.wpm }}</span>
                            <span class="text-xs text-[var(--sub-color)] font-mono mb-1">{{ t('wpm') }}</span>
                        </div>
                        <div class="flex justify-center sm:justify-start gap-4 mt-2 font-mono text-xs">
                            <span class="text-[var(--main-color)]">{{ Math.round(bestTest.accuracy) }}%</span>
                            <span class="text-[var(--error-color)]">{{ bestTest.total_errors ?? 0 }} err</span>
                            <span class="text-[var(--main-color)]">{{ formatDuration(bestTest.duration) }}</span>
                        </div>
                    </div>

                    <div class="flex-1 flex flex-col items-center sm:items-end gap-2 text-center sm:text-right">
                        <div class="text-xl lg:text-2xl text-[var(--main-color)]" dir="rtl" style="font-family: 'Noto Naskh Arabic', serif;">
                            {{ bestTest.quran_text.surah_name_arabic }}
                        </div>
                        <div class="font-mono text-[10px] text-[var(--sub-color)]">
                            {{ bestTest.quran_text.surah_number }}:{{ bestTest.start_ayah }}–{{ bestTest.end_ayah }} · {{ formatDate(bestTest.created_at) }}
                        </div>
                        <Link :href="`/?surah=${bestTest.quran_text.surah_number}&start=${bestTest.start_ayah || 1}&end=${bestTest.end_ayah || 1}`"
                              class="mt-1 min-h-[36px] inline-flex items-center bg-[var(--caret-color)] text-[var(--bg-color)] px-5 font-cinzel font-semibold text-xs hover:opacity-90 transition-opacity">
                            {{ t('retake') }}
                        </Link>
                    </div>
                </div>

                <!-- Recent tests -->
                <div class="border border-[var(--border-color)]">
                    <template v-if="results.data.length > 0">
                        <!-- Desktop / tablet: table -->
                        <table class="hidden sm:table w-full text-left font-mono text-sm">
                            <thead>
                                <tr class="bg-[var(--caret-color)]/5 text-[var(--sub-color)] uppercase tracking-[0.15em] text-[10px]">
                                    <th class="px-4 py-3 font-semibold">{{ t('wpm') }}</th>
                                    <th class="px-4 py-3 font-semibold text-center hidden md:table-cell">{{ t('time') }}</th>
                                    <th class="px-4 py-3 font-semibold text-center hidden md:table-cell">{{ t('errors') }}</th>
                                    <th class="px-4 py-3 font-semibold text-center">{{ t('accuracy') }}</th>
                                    <th class="px-4 py-3 font-semibold text-center">{{ t('surah') }}</th>
                                    <th class="px-4 py-3 font-semibold text-right">{{ t('date') }}</th>
                                    <th class="px-4 py-3 font-semibold text-right"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--border-color)]">
                                <tr v-for="result in results.data" :key="result.id" class="hover:bg-[var(--caret-color)]/[0.03] transition-colors">
                                    <td class="px-4 py-3">
                                        <span class="text-lg tabular-nums" :class="result.wpm === bestWpm ? 'text-[var(--caret-color)] font-semibold' : 'text-[var(--main-color)]'">{{ result.wpm }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center text-[var(--sub-color)] hidden md:table-cell">{{ formatDuration(result.duration) }}</td>
                                    <td class="px-4 py-3 text-center text-[var(--error-color)] hidden md:table-cell">{{ result.total_errors ?? 0 }}</td>
                                    <td class="px-4 py-3 text-center" :class="result.accuracy > 95 ? 'text-emerald-500' : 'text-[var(--sub-color)]'">{{ Math.round(result.accuracy) }}%</td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="text-base" dir="rtl" style="font-family: 'Noto Naskh Arabic', serif;">{{ result.quran_text.surah_name_arabic }}</div>
                                        <div class="text-[10px] text-[var(--sub-color)]">{{ result.start_ayah }}–{{ result.end_ayah }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right text-[var(--sub-color)] text-xs">{{ formatDate(result.created_at) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <Link :href="`/?surah=${result.quran_text.surah_number}&start=${result.start_ayah || 1}&end=${result.end_ayah || 1}`"
                                              class="text-[11px] border border-[var(--border-color)] px-3 py-1.5 text-[var(--sub-color)] hover:text-[var(--main-color)] hover:border-[var(--caret-color)] transition-colors">
                                            {{ t('retake') }}
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Mobile: stacked cards -->
                        <ul class="sm:hidden divide-y divide-[var(--border-color)]">
                            <li v-for="result in results.data" :key="result.id" class="p-4 flex flex-col gap-2">
                                <div class="flex items-baseline justify-between">
                                    <span class="text-2xl font-cinzel tabular-nums" :class="result.wpm === bestWpm ? 'text-[var(--caret-color)]' : 'text-[var(--main-color)]'">{{ result.wpm }}<span class="text-[10px] text-[var(--sub-color)] font-mono ml-1">wpm</span></span>
                                    <span class="text-[11px] font-mono text-[var(--sub-color)]">{{ formatDate(result.created_at) }}</span>
                                </div>
                                <div class="flex items-center gap-4 font-mono text-xs">
                                    <span :class="result.accuracy > 95 ? 'text-emerald-500' : 'text-[var(--sub-color)]'">{{ Math.round(result.accuracy) }}%</span>
                                    <span class="text-[var(--error-color)]">{{ result.total_errors ?? 0 }} err</span>
                                    <span class="text-[var(--sub-color)]">{{ formatDuration(result.duration) }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-base text-[var(--main-color)]" dir="rtl" style="font-family: 'Noto Naskh Arabic', serif;">
                                        {{ result.quran_text.surah_name_arabic }}
                                        <span class="text-[10px] text-[var(--sub-color)] font-mono" dir="ltr">{{ result.start_ayah }}–{{ result.end_ayah }}</span>
                                    </span>
                                    <Link :href="`/?surah=${result.quran_text.surah_number}&start=${result.start_ayah || 1}&end=${result.end_ayah || 1}`"
                                          class="shrink-0 min-h-[36px] inline-flex items-center bg-[var(--caret-color)] text-[var(--bg-color)] px-4 font-cinzel font-semibold text-xs">
                                        {{ t('retake') }}
                                    </Link>
                                </div>
                            </li>
                        </ul>

                        <!-- Pagination -->
                        <div class="p-3 border-t border-[var(--border-color)] flex justify-between font-mono text-[10px]">
                            <Link v-if="results.prev_page_url" :href="results.prev_page_url" :only="['results']" preserve-scroll class="px-3 py-1 border border-[var(--border-color)] text-[var(--caret-color)]">← prev</Link>
                            <span v-else></span>
                            <Link v-if="results.next_page_url" :href="results.next_page_url" :only="['results']" preserve-scroll class="px-3 py-1 border border-[var(--border-color)] text-[var(--caret-color)]">next →</Link>
                            <span v-else></span>
                        </div>
                    </template>

                    <div v-else class="flex flex-col items-center justify-center py-24 px-6 text-center text-[var(--sub-color)] font-mono">
                        <p class="text-lg mb-6">{{ t('no_tests') }}</p>
                        <Link href="/" class="min-h-[44px] inline-flex items-center bg-[var(--caret-color)] text-[var(--bg-color)] px-8 font-cinzel font-semibold">
                            {{ t('start_testing') }}
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