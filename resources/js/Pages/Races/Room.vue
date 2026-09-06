<script setup>
import { computed, nextTick, onMounted, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSettings } from '../../useSettings';
import { useTypingScore } from '../../composables/useTypingScore';

const props = defineProps({
    race: { type: Object, required: true },
    participants: { type: Array, default: () => [] },
    me: { type: Number, required: true },
});

const { t } = useSettings();

const status = ref(props.race.status);
const text = ref(props.race.text || '');
const startsAtMs = ref(props.race.starts_at ? Date.parse(props.race.starts_at) : null);
const members = reactive({});
const progressByUser = reactive({});
const standings = ref(props.participants.slice());
const myResult = ref(null);
const now = ref(Date.now());
let clock = null;
let channel = null;
let lastWhisper = 0;

const input = ref('');
const inputEl = ref(null);
const surfaceEl = ref(null);
const startedAt = ref(null);
const isFocused = ref(false);
const isTyping = ref(false);
let typingTimeout = null;

const score = useTypingScore(text, input, startedAt);

props.participants.forEach((p) => { members[p.id] = p.name; });

const nameFor = (id) => members[id] || props.participants.find((p) => p.id === id)?.name || '…';

const countdownSeconds = computed(() => {
    if (status.value !== 'countdown' || !startsAtMs.value) return null;
    return Math.max(0, Math.ceil((startsAtMs.value - now.value) / 1000));
});

const racing = computed(() => status.value === 'racing' && !myResult.value);

const opponents = computed(() =>
    Object.keys(members)
        .map(Number)
        .filter((id) => id !== props.me)
        .map((id) => ({
            id,
            name: nameFor(id),
            pct: standingPct(id),
            finished: standings.value.find((s) => s.id === id)?.finished ?? false,
        })),
);

function standingPct(id) {
    const s = standings.value.find((x) => x.id === id);
    if (s?.finished) return 100;
    return Math.round((progressByUser[id]?.pct ?? 0) * 100);
}

const myPct = computed(() => Math.round(score.progress.value * 100));

const finalStandings = computed(() =>
    [...standings.value]
        .filter((s) => s.finished)
        .sort((a, b) => (a.position ?? 99) - (b.position ?? 99)),
);

// --- sliding caret ---
const caret = ref({ top: 0, left: 0, width: 0, opacity: 0 });

function updateCaret() {
    nextTick(() => {
        const host = surfaceEl.value;
        if (!host) return;
        const idx = Math.min(input.value.length, score.charStates.value.length - 1);
        const span = host.querySelector(`[data-i="${idx}"]`);
        if (!span) {
            caret.value.opacity = 0;
            return;
        }
        const s = span.getBoundingClientRect();
        const h = host.getBoundingClientRect();
        caret.value = {
            top: s.bottom - h.top - 2,
            left: s.left - h.left,
            width: s.width,
            opacity: isFocused.value ? 1 : 0,
        };
    });
}

function applySnapshot(e) {
    if (e.status) status.value = e.status;
    if (e.starts_at) startsAtMs.value = Date.parse(e.starts_at);
    if (Array.isArray(e.participants)) standings.value = e.participants;
}

function focusInput() {
    inputEl.value?.focus();
}

function beginRacingLocally() {
    if (startedAt.value) return;
    status.value = 'racing';
    startedAt.value = startsAtMs.value || Date.now();
    requestAnimationFrame(() => { focusInput(); updateCaret(); });
}

function handleInput(e) {
    const max = score.charStates.value.length;
    input.value = e.target.value.slice(0, max);
    isTyping.value = true;
    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(() => (isTyping.value = false), 700);
}

watch(countdownSeconds, (s) => {
    if (s === 0 && !startedAt.value) beginRacingLocally();
});

watch([input, isFocused, () => score.charStates.value], updateCaret);

watch(input, () => {
    if (!racing.value) return;

    const nowTs = Date.now();
    if (channel && nowTs - lastWhisper > 200) {
        lastWhisper = nowTs;
        channel.whisper('progress', { id: props.me, pct: score.progress.value, wpm: score.wpm.value });
    }

    if (score.isComplete.value && !myResult.value) submitFinish();
});

async function submitFinish() {
    myResult.value = { pending: true };
    try {
        const { data } = await axios.post(`/races/${props.race.key}/finish`, {
            chars: input.value.length,
            correct_chars: score.correctCount.value,
        });
        myResult.value = data;
    } catch (err) {
        myResult.value = { error: true };
    }
}

function startRoom() {
    router.post(`/races/${props.race.key}/start`, {}, { preserveScroll: true });
}

onMounted(() => {
    clock = setInterval(() => { now.value = Date.now(); }, 200);

    if (status.value === 'countdown' && countdownSeconds.value === 0) beginRacingLocally();
    if (status.value === 'racing') beginRacingLocally();

    channel = window.Echo.join(`race.${props.race.key}`)
        .here((users) => users.forEach((u) => { members[u.id] = u.name; }))
        .joining((u) => { members[u.id] = u.name; })
        .leaving((u) => { delete members[u.id]; })
        .listen('.starting', (e) => {
            applySnapshot(e);
            if (e.text) text.value = e.text;
        })
        .listen('.started', applySnapshot)
        .listen('.lobby.updated', applySnapshot)
        .listen('.participant.finished', applySnapshot)
        .listen('.finished', (e) => {
            applySnapshot(e);
            status.value = 'finished';
        })
        .listenForWhisper('progress', (e) => {
            progressByUser[e.id] = { pct: e.pct, wpm: e.wpm };
        });
});

onBeforeUnmount(() => {
    clearInterval(clock);
    clearTimeout(typingTimeout);
    if (channel) window.Echo.leave(`race.${props.race.key}`);
});

const shareUrl = computed(() => `${window.location.origin}/races/${props.race.key}`);
const copied = ref(false);
function copyShare() {
    navigator.clipboard?.writeText(shareUrl.value).then(() => {
        copied.value = true;
        setTimeout(() => (copied.value = false), 1500);
    });
}

const showSurface = computed(() => (status.value === 'racing' || myResult.value) && text.value);
const caretOk = computed(() => score.firstErrorIndex.value === -1);
</script>

<template>
    <Head><title>{{ t('races.title') }} - QuranTyping</title></Head>

    <AppLayout>
        <div class="py-10 sm:py-12 animate-fade-in min-h-[80vh]">
            <div class="max-w-3xl mx-auto px-4 sm:px-6">
                <header class="mb-6 flex items-center justify-between gap-4">
                    <div>
                        <h1 class="text-xl font-cinzel font-semibold text-[var(--caret-color)]">{{ t('races.title') }}</h1>
                        <p class="text-[var(--sub-color)] font-mono text-[10px] uppercase tracking-[0.25em] mt-1">
                            {{ race.surah_number }}:{{ race.start_ayah }}–{{ race.end_ayah }}
                        </p>
                    </div>
                    <Link href="/races" class="font-mono text-[11px] text-[var(--lapis-color)] hover:opacity-80">← {{ t('races.leave') }}</Link>
                </header>

                <!-- Private room share -->
                <div v-if="race.visibility === 'private' && status === 'lobby'" class="border border-[var(--border-color)] p-4 mb-6 font-mono text-sm">
                    <p class="text-[10px] uppercase tracking-[0.2em] text-[var(--sub-color)] mb-2">{{ t('races.room_code') }}</p>
                    <div class="flex items-center gap-3">
                        <span class="text-lg tracking-[0.3em] text-[var(--caret-color)]">{{ race.code }}</span>
                        <button type="button" @click="copyShare" class="border border-[var(--border-color)] px-3 py-1 text-[11px] hover:border-[var(--caret-color)]">
                            {{ copied ? t('races.copied') : t('races.share_link') }}
                        </button>
                    </div>
                    <button
                        v-if="race.is_host"
                        type="button"
                        @click="startRoom"
                        class="mt-4 w-full bg-[var(--caret-color)] text-[var(--bg-color)] font-cinzel font-semibold py-2.5"
                    >
                        {{ t('races.start_now') }}
                    </button>
                </div>

                <!-- Waiting -->
                <p v-if="status === 'lobby' && race.visibility === 'public'" class="border border-[var(--border-color)] p-6 text-center font-mono text-sm text-[var(--sub-color)] mb-6">
                    {{ t('races.waiting_for_players') }}
                </p>

                <!-- Countdown -->
                <div v-if="countdownSeconds !== null && countdownSeconds > 0" class="text-center py-8 mb-4">
                    <div class="text-6xl font-cinzel font-semibold text-[var(--caret-color)] tabular-nums">{{ countdownSeconds }}</div>
                    <p class="font-mono text-[10px] uppercase tracking-[0.3em] text-[var(--sub-color)] mt-2">{{ t('races.get_ready') }}</p>
                </div>

                <!-- Racers -->
                <div class="space-y-2 mb-6">
                    <div v-for="o in opponents" :key="o.id" class="font-mono text-xs">
                        <div class="flex justify-between mb-1">
                            <span class="text-[var(--sub-color)]">{{ o.name }}</span>
                            <span v-if="o.finished" class="text-[var(--caret-color)]">✓</span>
                        </div>
                        <div class="h-1.5 bg-[var(--border-color)]">
                            <div class="h-full bg-[var(--sub-color)] transition-all duration-200" :style="{ width: o.pct + '%' }"></div>
                        </div>
                    </div>
                    <div class="font-mono text-xs pt-1">
                        <div class="flex justify-between mb-1">
                            <span class="text-[var(--main-color)]">{{ t('races.you') }}</span>
                            <span class="text-[var(--caret-color)] tabular-nums">{{ score.wpm.value }} wpm · {{ myPct }}%</span>
                        </div>
                        <div class="h-1.5 bg-[var(--border-color)]">
                            <div class="h-full bg-[var(--caret-color)] transition-all duration-150" :style="{ width: myPct + '%' }"></div>
                        </div>
                    </div>
                </div>

                <!-- Typing surface — the jadwal -->
                <div
                    v-if="showSurface"
                    ref="surfaceEl"
                    @click="focusInput"
                    class="jadwal relative w-full mb-4 min-h-[180px] flex items-center transition-opacity duration-300"
                    :class="isFocused ? 'opacity-100' : 'opacity-50'"
                >
                    <svg class="jadwal-draw" preserveAspectRatio="none" aria-hidden="true">
                        <rect x="0" y="0" width="100%" height="100%" pathLength="1" />
                    </svg>

                    <div
                        v-if="!isFocused && !myResult"
                        class="absolute inset-0 z-30 flex items-center justify-center cursor-pointer"
                    >
                        <div class="bg-[var(--bg-color)] px-6 py-3 border border-[var(--border-color)]">
                            <p class="text-sm font-cinzel text-[var(--sub-color)]">{{ t('races.type_here') }}</p>
                        </div>
                    </div>

                    <div
                        class="absolute z-[60] pointer-events-none rounded-full transition-all duration-150"
                        :style="{
                            top: caret.top + 'px',
                            left: caret.left + 'px',
                            width: caret.width + 'px',
                            height: '4px',
                            opacity: caret.opacity,
                            transitionTimingFunction: 'cubic-bezier(0.19, 1, 0.22, 1)',
                            backgroundColor: caretOk ? '#3f9d6b' : '#c1452f',
                            boxShadow: caretOk ? '0 0 8px 1px rgba(63,157,107,0.55)' : '0 0 8px 1px rgba(193,69,47,0.55)',
                        }"
                        :class="{ 'animate-pulse': !isTyping && isFocused }"
                    ></div>

                    <p class="mushaf-text select-none w-full relative z-0 whitespace-pre-wrap break-words" dir="rtl">
                        <span
                            v-for="(c, i) in score.charStates.value"
                            :key="i"
                            :data-i="i"
                            :class="{
                                'text-[var(--main-color)]': c.status === 'correct',
                                'text-[var(--error-color)] bg-[var(--error-color)]/10': c.status === 'incorrect',
                                'text-[var(--sub-color)]': c.status === 'untyped' || c.status === 'active',
                            }"
                        >
                            <span v-if="c.brk" class="ornament-wrap"><span class="ornament-char">۝</span></span>
                            <template v-else>{{ c.ch }}</template>
                        </span>
                    </p>

                    <input
                        ref="inputEl"
                        type="text"
                        class="absolute inset-0 w-full h-full opacity-0 cursor-default z-20"
                        :value="input"
                        @input="handleInput"
                        @focus="isFocused = true"
                        @blur="isFocused = false"
                        autocomplete="off"
                        autocorrect="off"
                        autocapitalize="off"
                        spellcheck="false"
                        :maxlength="score.charStates.value.length"
                    />
                </div>

                <!-- My result -->
                <div v-if="myResult && !myResult.error && !myResult.pending" class="border border-[var(--caret-color)] p-5 text-center font-mono mb-4">
                    <p class="text-[10px] uppercase tracking-[0.25em] text-[var(--sub-color)]">{{ t('races.finished') }}</p>
                    <p class="text-3xl font-cinzel text-[var(--caret-color)] mt-2">#{{ myResult.position }}</p>
                    <p class="text-sm text-[var(--main-color)] mt-1">{{ myResult.wpm }} wpm · {{ myResult.accuracy }}%</p>
                </div>

                <!-- Final standings -->
                <section v-if="status === 'finished' && finalStandings.length" class="border border-[var(--border-color)] divide-y divide-[var(--border-color)] font-mono text-sm">
                    <div v-for="s in finalStandings" :key="s.id" class="flex items-center justify-between px-4 py-3">
                        <span :class="s.id === me ? 'text-[var(--caret-color)]' : 'text-[var(--main-color)]'">
                            #{{ s.position }} {{ nameFor(s.id) }}
                        </span>
                        <span class="text-[var(--sub-color)] tabular-nums">{{ s.wpm }} wpm · {{ s.accuracy }}%</span>
                    </div>
                    <div class="px-4 py-3 text-center">
                        <Link href="/races" class="text-[var(--lapis-color)]">{{ t('races.race_again') }}</Link>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
