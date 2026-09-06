<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSettings } from '../../useSettings';

const props = defineProps({
    map: { type: Object, required: true },
});

const { t } = useSettings();

const filter = ref('all');       // all | progress | mastered | none
const sort = ref('order');       // order | most | least
const activeJuz = ref(null);

const detail = reactive({});     // surah number -> { ayahs: [...] } | 'loading'
const openSurah = ref(null);
const range = reactive({ surah: null, a: null, b: null });

// --- per-surah derived numbers ---
function seg(s) {
    const mem = Math.min(s.memorised, s.ayah_count);
    const mas = Math.max(0, Math.min(s.mastered - mem, s.ayah_count - mem));
    const touched = Math.min(s.ayah_count, Math.max(s.practiced, s.memorised));
    const pra = Math.max(0, touched - mem - mas);
    const rest = Math.max(0, s.ayah_count - mem - mas - pra);
    return { mem, mas, pra, rest, touched };
}
function pctOf(s) {
    return Math.round((seg(s).touched / s.ayah_count) * 100);
}

const surahs = computed(() => {
    let list = props.map.surahs.map((s) => ({ ...s, ...seg(s), pct: pctOf(s) }));

    if (activeJuz.value) list = list.filter((s) => s.juz === activeJuz.value);

    if (filter.value === 'progress') list = list.filter((s) => s.touched > 0 && s.touched < s.ayah_count);
    else if (filter.value === 'mastered') list = list.filter((s) => s.mastered >= s.ayah_count);
    else if (filter.value === 'none') list = list.filter((s) => s.touched === 0);

    if (sort.value === 'most') list.sort((a, b) => b.pct - a.pct || a.number - b.number);
    else if (sort.value === 'least') list.sort((a, b) => a.pct - b.pct || a.number - b.number);
    else list.sort((a, b) => a.number - b.number);

    return list;
});

const totals = computed(() => props.map.totals);

// --- juz rings ---
const R = 15;
const CIRC = 2 * Math.PI * R;
function juzFill(j) {
    return Math.min(1, Math.max(j.practiced, j.memorised) / j.ayah_count);
}
function juzTint(j) {
    if (j.memorised > 0) return 'var(--lapis-color)';
    if (j.mastered > 0) return 'var(--caret-color)';
    if (j.practiced > 0) return 'var(--sub-color)';
    return 'var(--border-color)';
}

// --- drill-down ---
async function toggleSurah(n) {
    if (openSurah.value === n) { openSurah.value = null; return; }
    openSurah.value = n;
    range.surah = n; range.a = null; range.b = null;
    if (!detail[n]) {
        detail[n] = 'loading';
        try {
            detail[n] = (await axios.get(`/map/${n}`)).data;
        } catch {
            detail[n] = { ayahs: [] };
        }
    }
}

function pickAyah(n, ayah) {
    if (range.surah !== n) { range.surah = n; range.a = null; range.b = null; }
    if (range.a === null || range.b !== null) { range.a = ayah; range.b = null; }
    else { range.b = ayah; }
}
const lo = computed(() => (range.a === null ? null : Math.min(range.a, range.b ?? range.a)));
const hi = computed(() => (range.a === null ? null : Math.max(range.a, range.b ?? range.a)));

function typeRange() {
    if (lo.value === null) return;
    router.visit(`/?surah=${openSurah.value}&start=${lo.value}&end=${hi.value}`);
}

const ayahCls = {
    untouched: 'border border-[var(--border-color)] text-[var(--sub-color)]',
    practiced: 'bg-[var(--sub-color)]/25 text-[var(--main-color)]',
    mastered: 'bg-[var(--caret-color)] text-[var(--bg-color)]',
    memorised: 'bg-[var(--lapis-color)] text-white',
};
</script>

<template>
    <Head><title>{{ t('map.title') }} - QuranTyping</title></Head>

    <AppLayout>
        <div class="py-10 sm:py-12 animate-fade-in min-h-[80vh]">
            <div class="max-w-6xl mx-auto px-4 sm:px-6">
                <header class="mb-6">
                    <h1 class="text-2xl font-cinzel font-semibold text-[var(--caret-color)]">{{ t('map.title') }}</h1>
                    <p class="text-[var(--sub-color)] font-mono text-[10px] uppercase tracking-[0.3em] mt-1">{{ t('map.subtitle') }}</p>
                </header>

                <!-- Overall -->
                <section class="border border-[var(--border-color)] p-5 mb-8">
                    <div class="h-2 flex mb-3 overflow-hidden">
                        <div class="bg-[var(--lapis-color)]" :style="{ width: totals.memorised_pct + '%' }"></div>
                        <div class="bg-[var(--caret-color)]" :style="{ width: Math.max(0, totals.mastered_pct - totals.memorised_pct) + '%' }"></div>
                        <div class="bg-[var(--sub-color)]/40" :style="{ width: Math.max(0, totals.practiced_pct - totals.mastered_pct) + '%' }"></div>
                        <div class="bg-[var(--border-color)] flex-1"></div>
                    </div>
                    <div class="flex flex-wrap gap-x-6 gap-y-1 font-mono text-xs text-[var(--sub-color)]">
                        <span><span class="text-[var(--sub-color)]">■</span> {{ t('map.practiced') }} {{ totals.practiced }} ({{ totals.practiced_pct }}%)</span>
                        <span><span class="text-[var(--caret-color)]">■</span> {{ t('map.mastered') }} {{ totals.mastered }} ({{ totals.mastered_pct }}%)</span>
                        <span><span class="text-[var(--lapis-color)]">■</span> {{ t('map.memorised') }} {{ totals.memorised }} ({{ totals.memorised_pct }}%)</span>
                        <span class="text-[var(--main-color)]">{{ totals.ayah_count }} ayahs</span>
                    </div>
                </section>

                <!-- Juz rings -->
                <section class="mb-8">
                    <div class="grid grid-cols-6 sm:grid-cols-10 md:grid-cols-[repeat(15,minmax(0,1fr))] gap-2">
                        <button
                            v-for="j in map.juz"
                            :key="j.juz"
                            type="button"
                            @click="activeJuz = activeJuz === j.juz ? null : j.juz"
                            class="flex flex-col items-center gap-1 p-1 transition-opacity"
                            :class="activeJuz && activeJuz !== j.juz ? 'opacity-30' : 'opacity-100'"
                        >
                            <svg viewBox="0 0 36 36" class="w-9 h-9 -rotate-90">
                                <circle cx="18" cy="18" :r="R" fill="none" stroke="var(--border-color)" stroke-width="3" />
                                <circle cx="18" cy="18" :r="R" fill="none" :stroke="juzTint(j)" stroke-width="3"
                                        stroke-linecap="round"
                                        :stroke-dasharray="CIRC"
                                        :stroke-dashoffset="CIRC * (1 - juzFill(j))" />
                            </svg>
                            <span class="font-mono text-[9px] text-[var(--sub-color)] tabular-nums">{{ j.juz }}</span>
                        </button>
                    </div>
                </section>

                <!-- Controls -->
                <div class="flex flex-wrap items-center gap-3 mb-4 font-mono text-[11px]">
                    <div class="flex border border-[var(--border-color)] divide-x divide-[var(--border-color)]">
                        <button v-for="f in ['all', 'progress', 'mastered', 'none']" :key="f" type="button" @click="filter = f"
                            class="px-3 py-1.5 uppercase tracking-[0.1em] transition-colors"
                            :class="filter === f ? 'bg-[var(--caret-color)] text-[var(--bg-color)]' : 'text-[var(--sub-color)] hover:text-[var(--main-color)]'">
                            {{ t('map.filter_' + f) }}
                        </button>
                    </div>
                    <select v-model="sort" class="bg-[var(--bg-color)] border border-[var(--border-color)] px-2 py-1.5 text-[11px] font-mono text-[var(--main-color)] focus:border-[var(--caret-color)] focus:outline-none">
                        <option value="order">{{ t('map.sort_order') }}</option>
                        <option value="most">{{ t('map.sort_most') }}</option>
                        <option value="least">{{ t('map.sort_least') }}</option>
                    </select>
                    <button v-if="activeJuz" type="button" @click="activeJuz = null" class="text-[var(--lapis-color)]">juz {{ activeJuz }} ✕</button>
                </div>

                <!-- Surah grid -->
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="s in surahs" :key="s.number" class="border border-[var(--border-color)]">
                        <button type="button" @click="toggleSurah(s.number)" class="w-full text-left p-3 hover:bg-[var(--caret-color)]/[0.03] transition-colors">
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="font-mono text-[10px] text-[var(--sub-color)] tabular-nums">{{ s.number }}</span>
                                <span class="font-['Noto_Naskh_Arabic'] text-lg text-[var(--main-color)]" dir="rtl">{{ s.name_ar }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-2 mt-1">
                                <span class="font-cinzel text-sm text-[var(--main-color)]">{{ s.name_en }}</span>
                                <span class="font-mono text-[10px] text-[var(--sub-color)] tabular-nums">{{ s.pct }}%</span>
                            </div>
                            <div class="h-1.5 flex mt-2 overflow-hidden">
                                <div class="bg-[var(--lapis-color)]" :style="{ width: (s.mem / s.ayah_count * 100) + '%' }"></div>
                                <div class="bg-[var(--caret-color)]" :style="{ width: (s.mas / s.ayah_count * 100) + '%' }"></div>
                                <div class="bg-[var(--sub-color)]/40" :style="{ width: (s.pra / s.ayah_count * 100) + '%' }"></div>
                                <div class="bg-[var(--border-color)] flex-1"></div>
                            </div>
                        </button>

                        <!-- Ayah drill-down -->
                        <div v-if="openSurah === s.number" class="border-t border-[var(--border-color)] p-3">
                            <p v-if="detail[s.number] === 'loading'" class="font-mono text-[11px] text-[var(--sub-color)]">…</p>
                            <template v-else>
                                <div class="flex flex-wrap gap-1">
                                    <button
                                        v-for="a in detail[s.number].ayahs"
                                        :key="a.n"
                                        type="button"
                                        @click="pickAyah(s.number, a.n)"
                                        class="w-7 h-7 flex items-center justify-center font-mono text-[10px] tabular-nums transition-all"
                                        :class="[ayahCls[a.state], (range.surah === s.number && lo !== null && a.n >= lo && a.n <= hi) ? 'ring-2 ring-[var(--caret-color)] ring-offset-1 ring-offset-[var(--bg-color)]' : '']"
                                    >{{ a.n }}</button>
                                </div>
                                <button
                                    v-if="range.surah === s.number && lo !== null"
                                    type="button"
                                    @click="typeRange"
                                    class="mt-3 w-full bg-[var(--caret-color)] text-[var(--bg-color)] font-cinzel font-semibold py-2 text-sm"
                                >
                                    {{ lo === hi ? t('map.type_ayah').replace('{n}', lo) : t('map.type_ayahs').replace('{a}', lo).replace('{b}', hi) }}
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <p v-if="surahs.length === 0" class="border border-[var(--border-color)] p-8 text-center font-mono text-sm text-[var(--sub-color)]">
                    {{ t('map.empty') }}
                </p>
            </div>
        </div>
    </AppLayout>
</template>
