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
    limits: { type: Object, default: () => ({ min_chars: 100, max_chars: 1000, min_capacity: 2, max_capacity: 8 }) },
    surahs: { type: Array, default: () => [] },
});

const { t } = useSettings();

// Live room settings (host edits these; everyone sees them via snapshots).
const cfg = reactive({
    char_target: props.race.char_target ?? 250,
    capacity: props.race.capacity ?? 5,
    scope_surah: props.race.scope_surah ?? '',
});

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
let lastRelay = 0;

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

const opponents = computed(() => {
    const ids = new Set([
        ...Object.keys(members).map(Number),
        ...standings.value.map((s) => s.id),
        ...props.participants.map((p) => p.id),
    ]);
    ids.delete(props.me);

    return [...ids].map((id) => ({
        id,
        name: nameFor(id),
        pct: standingPct(id),
        finished: standings.value.find((s) => s.id === id)?.finished ?? false,
    }));
});

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
    // Keep non-host lobby views in sync with the host's settings.
    if (!props.race.is_host && status.value === 'lobby') {
        if (e.char_target != null) cfg.char_target = e.char_target;
        if (e.capacity != null) cfg.capacity = e.capacity;
        if (e.scope_surah !== undefined) cfg.scope_surah = e.scope_surah ?? '';
    }
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

function pushProgress() {
    const payload = { id: props.me, pct: score.progress.value, wpm: score.wpm.value };
    const nowTs = Date.now();

    // Instant, zero-cost path (needs Soketi client messages enabled).
    if (channel && nowTs - lastWhisper > 200) {
        lastWhisper = nowTs;
        try { channel.whisper('progress', payload); } catch { /* client events off */ }
    }

    // Reliable path — a throttled server relay so bars still move if whispers are dropped.
    if (nowTs - lastRelay > 800) {
        lastRelay = nowTs;
        axios.post(`/races/${props.race.key}/progress`, { pct: payload.pct, wpm: payload.wpm }).catch(() => {});
    }
}

watch(input, () => {
    if (!racing.value) return;
    pushProgress();
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

const starting = ref(false);
function startRoom() {
    starting.value = true;
    router.post(`/races/${props.race.key}/start`, {
        char_target: cfg.char_target,
        capacity: cfg.capacity,
        scope_surah: cfg.scope_surah === '' ? null : cfg.scope_surah,
    }, {
        preserveScroll: true,
        onFinish: () => (starting.value = false),
    });
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
        .listen('.progress.tick', (e) => {
            if (e.id !== props.me) progressByUser[e.id] = { pct: e.pct, wpm: e.wpm };
        })
        .listenForWhisper('progress', (e) => {
            if (e.id !== props.me) progressByUser[e.id] = { pct: e.pct, wpm: e.wpm };
        });
});

onBeforeUnmount(() => {
    clearInterval(clock);
    clearTimeout(typingTimeout);
    if (channel) window.Echo.leave(`race.${props.race.key}`);
});

const shareUrl = computed(() => `${window.location.origin}/races/${props.race.key}`);
const copied = ref(false);

async function copyShare() {
    const url = shareUrl.value;
    let ok = false;

    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(url);
            ok = true;
        }
    } catch {
        ok = false;
    }

    if (!ok) {
        // Fallback for non-secure contexts (plain http).
        const ta = document.createElement('textarea');
        ta.value = url;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try {
            ok = document.execCommand('copy');
        } catch {
            ok = false;
        }
        document.body.removeChild(ta);
    }

    copied.value = ok ? 'ok' : 'fail';
    setTimeout(() => (copied.value = false), 2000);
}

const showSurface = computed(() => (status.value === 'racing' || myResult.value) && text.value);
const caretOk = computed(() => score.firstErrorIndex.value === -1);
</script>

<template>
    <Head><title>{{ t('races.title') }} - QuranTyping</title></Head>

    <AppLayout>
        <div class="py-10 sm:py-12 animate-fade-in min-h-[80vh]">
            <div class="max-w-5xl mx-auto px-4 sm:px-6">
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
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-lg tracking-[0.3em] text-[var(--caret-color)]">{{ race.code }}</span>
                        <button
                            type="button"
                            @click="copyShare"
                            class="border px-3 py-1 text-[11px] transition-colors"
                            :class="copied === 'ok'
                                ? 'border-[var(--caret-color)] text-[var(--caret-color)]'
                                : copied === 'fail'
                                    ? 'border-[var(--error-color)] text-[var(--error-color)]'
                                    : 'border-[var(--border-color)] hover:border-[var(--caret-color)]'"
                        >
                            {{ copied === 'ok' ? t('races.copied') : copied === 'fail' ? t('races.copy_failed') : t('races.share_link') }}
                        </button>
                    </div>
                    <input
                        :value="shareUrl"
                        readonly
                        @focus="$event.target.select()"
                        class="mt-3 w-full bg-[var(--bg-color)] border border-[var(--border-color)] px-3 py-2 text-[11px] text-[var(--sub-color)] focus:border-[var(--caret-color)] focus:outline-none"
                    />

                    <!-- Host: settings -->
                    <div v-if="race.is_host" class="mt-5 pt-4 border-t border-[var(--border-color)] space-y-4">
                        <p class="text-[10px] uppercase tracking-[0.2em] text-[var(--sub-color)]">{{ t('races.settings') }}</p>

                        <label class="block">
                            <span class="block text-[11px] text-[var(--sub-color)] mb-1">{{ t('races.char_target') }}</span>
                            <input
                                v-model.number="cfg.char_target"
                                type="number"
                                :min="limits.min_chars"
                                :max="limits.max_chars"
                                step="10"
                                class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] px-3 py-2 text-sm focus:border-[var(--caret-color)] focus:outline-none"
                            />
                        </label>

                        <label class="block">
                            <span class="block text-[11px] text-[var(--sub-color)] mb-1">{{ t('races.max_players') }}</span>
                            <input
                                v-model.number="cfg.capacity"
                                type="number"
                                :min="limits.min_capacity"
                                :max="limits.max_capacity"
                                class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] px-3 py-2 text-sm focus:border-[var(--caret-color)] focus:outline-none"
                            />
                        </label>

                        <label class="block">
                            <span class="block text-[11px] text-[var(--sub-color)] mb-1">{{ t('races.passage_scope') }}</span>
                            <select
                                v-model="cfg.scope_surah"
                                class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] px-3 py-2 text-sm focus:border-[var(--caret-color)] focus:outline-none"
                            >
                                <option value="">{{ t('races.scope_any') }}</option>
                                <option v-for="s in surahs" :key="s.surah_number" :value="s.surah_number">
                                    {{ s.surah_number }}. {{ s.surah_name_english }}
                                </option>
                            </select>
                        </label>

                        <button
                            type="button"
                            :disabled="starting"
                            @click="startRoom"
                            class="w-full bg-[var(--caret-color)] text-[var(--bg-color)] font-cinzel font-semibold py-2.5 disabled:opacity-50"
                        >
                            {{ t('races.start_now') }}
                        </button>
                    </div>

                    <!-- Guest: read-only summary -->
                    <div v-else class="mt-5 pt-4 border-t border-[var(--border-color)] text-[11px] text-[var(--sub-color)] space-y-1">
                        <p>{{ t('races.char_target') }}: <span class="text-[var(--main-color)]">{{ cfg.char_target }}</span></p>
                        <p>{{ t('races.max_players') }}: <span class="text-[var(--main-color)]">{{ cfg.capacity }}</span></p>
                        <p class="pt-1">{{ t('races.waiting_for_host') }}</p>
                    </div>
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
                    class="jadwal relative w-full mb-4 min-h-[140px] flex items-center transition-opacity duration-300"
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

                    <p class="mushaf-text race-passage select-none w-full relative z-0 whitespace-pre-wrap break-words" dir="rtl">
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

<style scoped>
/* Shorter, wider passage than the home page — race snippets are only a few ayahs. */
.race-passage {
    font-size: clamp(1.15rem, 2.6vw, 1.75rem);
    line-height: 1.9;
    word-spacing: 0.04em;
}
</style>
