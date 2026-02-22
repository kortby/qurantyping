<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useSettings } from '../useSettings';

const props = defineProps({
    config: {
        type: Object,
        required: true,
    }
});

const { t, currentLang } = useSettings();

const emit = defineEmits(['start', 'end']);

const now = ref(Date.now());
let timer = null;

onMounted(() => {
    timer = setInterval(() => {
        now.value = Date.now();
    }, 1000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});

const startMs = computed(() => new Date(props.config.start_time).getTime());
const endMs   = computed(() => new Date(props.config.end_time).getTime());
const target27thMs = computed(() => new Date(props.config.target_27th_night).getTime());

const isBeforeContest = computed(() => now.value < startMs.value);
const isDuringContest = computed(() => now.value >= startMs.value && now.value <= endMs.value);
const isAfterContest  = computed(() => now.value > endMs.value);

const isBefore27th = computed(() => now.value < target27thMs.value);

const formatDuration = (ms) => {
    if (ms < 0) ms = 0;
    const totalSeconds = Math.floor(ms / 1000);
    const d = Math.floor(totalSeconds / 86400);
    const h = Math.floor((totalSeconds % 86400) / 3600);
    const m = Math.floor((totalSeconds % 3600) / 60);
    const s = totalSeconds % 60;
    
    const parts = [];
    if (d > 0) parts.push(`${d}d`);
    parts.push(h.toString().padStart(2, '0'));
    parts.push(m.toString().padStart(2, '0'));
    parts.push(s.toString().padStart(2, '0'));
    return parts.join(':');
};

const preContestCountdown = computed(() => formatDuration(startMs.value - now.value));
const liveContestCountdown = computed(() => formatDuration(endMs.value - now.value));
const night27Countdown = computed(() => formatDuration(target27thMs.value - now.value));

const showRules = ref(false);

</script>

<template>
    <div v-if="config.enabled" 
         class="w-full max-w-6xl mb-6 relative overflow-hidden rounded-2xl bg-gradient-to-br from-[var(--caret-color)]/10 via-[var(--panel-color)] to-[var(--bg-color)] border border-[var(--border-color)] shadow-2xl">
        
        <!-- Animated Background Ornaments -->
        <div class="absolute inset-0 opacity-10 pointer-events-none overflow-hidden">
            <div class="absolute -top-10 -right-10 text-9xl">🌙</div>
            <div class="absolute bottom-4 left-10 text-6xl" :class="currentLang === 'ar' ? 'right-10' : ''">🌟</div>
            <div class="absolute top-1/2 w-32 h-32 bg-[var(--caret-color)] rounded-full blur-[100px]" :class="currentLang === 'ar' ? 'right-1/4' : 'left-1/4'"></div>
        </div>

        <div class="relative z-10 px-8 py-6 flex flex-col items-center justify-center text-center">
            
            <div v-if="isBeforeContest" class="flex flex-col items-center gap-2">
                <span class="text-[var(--caret-color)] font-cinzel text-lg md:text-xl font-bold tracking-wider">
                    {{ t('contest.global_title') }}
                </span>
                <h3 class="text-[var(--main-color)] text-sm md:text-base opacity-80 max-w-2xl font-sans mb-2" v-html="t('contest.global_desc')">
                </h3>
                <!-- Prize & Rules -->
                <div class="flex items-center gap-3 mb-3">
                    <div class="inline-flex items-center gap-2 bg-amber-500/10 text-amber-500 border border-amber-500/30 px-4 py-1.5 rounded-full text-[10px] md:text-xs font-bold uppercase tracking-widest shadow-[0_0_15px_rgba(245,158,11,0.15)]">
                        <span class="text-base leading-none">🏆</span> 
                        {{ t('contest.first_prize') }}
                    </div>
                    <button @click="showRules = !showRules" class="text-xs text-[var(--sub-color)] hover:text-[var(--main-color)] underline underline-offset-4 decoration-dashed transition-colors">
                        {{ showRules ? t('contest.hide_rules') : t('contest.view_rules') }}
                    </button>
                </div>
                
                <div class="bg-[var(--panel-color)]/50 backdrop-blur-md px-6 py-3 rounded-xl border border-[var(--border-color)]">
                    <p class="text-xs uppercase tracking-[0.2em] text-[var(--sub-color)] mb-1">{{ t('contest.begins_in') }}</p>
                    <div class="text-3xl md:text-4xl font-mono text-[var(--caret-color)] font-bold tracking-tight" :dir="'ltr'">
                        {{ preContestCountdown }}
                    </div>
                </div>
            </div>

            <div v-else-if="isDuringContest" class="flex flex-col md:flex-row items-center justify-center w-full gap-8">
                
                <div class="flex-1 flex flex-col items-center justify-center animate-pulse">
                    <span class="inline-flex items-center gap-2 bg-red-500/10 text-red-500 border border-red-500/20 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-widest mb-3">
                        <span class="w-2 h-2 rounded-full bg-red-500 "></span> {{ t('contest.live_now') }}
                    </span>
                    <h2 class="text-xl md:text-2xl font-cinzel font-bold text-[var(--main-color)] mb-1">
                        {{ t('contest.live_title') }}
                    </h2>
                    <p class="text-sm text-[var(--sub-color)] max-w-md mx-auto mb-4">
                        {{ t('contest.live_desc').replace('{wpm}', config.min_wpm).replace('{acc}', config.min_accuracy) }}
                    </p>
                    
                    <!-- Prize & Rules -->
                    <div class="flex items-center justify-center flex-wrap gap-3">
                        <div class="inline-flex items-center gap-2 bg-amber-500/10 text-amber-500 border border-amber-500/30 px-4 py-1.5 rounded-full text-[10px] md:text-xs font-bold uppercase tracking-widest shadow-[0_0_15px_rgba(245,158,11,0.15)]">
                            <span class="text-base leading-none">🏆</span> 
                            {{ t('contest.first_prize') }}
                        </div>
                        <button @click="showRules = !showRules" class="text-xs text-[var(--sub-color)] hover:text-[var(--main-color)] underline underline-offset-4 decoration-dashed transition-colors">
                            {{ showRules ? t('contest.hide_rules') : t('contest.view_rules') }}
                        </button>
                    </div>
                </div>

                <div class="flex gap-4">
                    <div v-if="isBefore27th" class="bg-[var(--panel-color)]/50 backdrop-blur-md px-6 py-3 rounded-xl border border-[var(--border-color)] flex flex-col items-center justify-center min-w-[140px]">
                        <p class="text-[10px] uppercase tracking-[0.2em] text-[var(--sub-color)] mb-1 text-center">{{ t('contest.peak') }}</p>
                        <div class="text-xl font-mono text-[#eab308] font-bold" :dir="'ltr'">
                            {{ night27Countdown }}
                        </div>
                    </div>
                    
                    <div class="bg-[var(--panel-color)]/50 backdrop-blur-md px-6 py-3 rounded-xl border border-[var(--border-color)] flex flex-col items-center justify-center min-w-[140px]">
                        <p class="text-[10px] uppercase tracking-[0.2em] text-[var(--sub-color)] mb-1 text-center">{{ t('contest.closes_in') }}</p>
                        <div class="text-xl font-mono text-[var(--caret-color)] font-bold" :dir="'ltr'">
                            {{ liveContestCountdown }}
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="isAfterContest" class="flex flex-col items-center gap-3">
                <span class="text-2xl">🏆</span>
                <h3 class="text-[var(--main-color)] font-cinzel text-xl font-bold">{{ t('contest.ended_title') }}</h3>
                <p class="text-[var(--sub-color)] text-sm mb-2">{{ t('contest.ended_desc') }}</p>
                <a :href="route('contest.index')" class="bg-[var(--caret-color)] text-emerald-950 px-6 py-2 rounded-lg font-bold hover:scale-105 transition-transform text-sm uppercase tracking-wider">
                    {{ t('contest.view_leaderboard') }}
                </a>
            </div>

            <!-- Expandable Rules Section -->
            <div v-show="showRules" class="w-full mt-6 pt-6 border-t border-[var(--border-color)] text-left"
                 :class="currentLang === 'ar' ? 'text-right' : 'text-left'">
                <h4 class="text-[var(--main-color)] font-bold text-sm uppercase tracking-widest mb-4 flex items-center gap-2" :class="currentLang === 'ar' ? 'justify-start rtl:flex-row-reverse' : ''">
                    <span class="text-lg">📜</span> {{ t('contest.rules_title') }}
                </h4>
                <ul class="space-y-3 text-sm text-[var(--sub-color)] w-full max-w-2xl mx-auto md:mx-0">
                    <li class="flex items-start gap-2">
                        <span class="text-[var(--caret-color)] mt-0.5 shadow-[0_0_10px_var(--caret-color)]">✦</span>
                        <span v-html="t('contest.rule_1').replace('{wpm}', config.min_wpm)"></span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-[var(--caret-color)] mt-0.5 shadow-[0_0_10px_var(--caret-color)]">✦</span>
                        <span v-html="t('contest.rule_2').replace('{acc}', config.min_accuracy)"></span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-[var(--caret-color)] mt-0.5 shadow-[0_0_10px_var(--caret-color)]">✦</span>
                        <span v-html="t('contest.rule_3')"></span>
                    </li>
                </ul>
            </div>

        </div>
    </div>
</template>
