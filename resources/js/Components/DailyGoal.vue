<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const streak = computed(() => usePage().props.auth?.streak ?? null);

const pct = computed(() => {
    if (!streak.value?.goal?.target) return 0;
    return Math.min(1, streak.value.goal.chars_today / streak.value.goal.target);
});

// SVG ring geometry
const R = 34;
const CIRC = 2 * Math.PI * R;
const dash = computed(() => `${pct.value * CIRC} ${CIRC}`);

const milestone = computed(() => {
    const c = streak.value?.current ?? 0;
    if (c >= 100) return '100-day streak';
    if (c >= 30) return '30-day streak';
    if (c >= 7) return '7-day streak';
    return null;
});
</script>

<template>
    <div v-if="streak" class="border border-[var(--border-color)] p-4 flex items-center gap-5">
        <svg width="84" height="84" viewBox="0 0 84 84" class="shrink-0 -rotate-90" aria-hidden="true">
            <circle cx="42" cy="42" :r="R" fill="none" stroke="var(--border-color)" stroke-width="6" />
            <circle
                cx="42" cy="42" :r="R" fill="none"
                :stroke="streak.goal.met ? 'var(--caret-color)' : 'var(--lapis-color)'"
                stroke-width="6" stroke-linecap="butt"
                :stroke-dasharray="dash"
            />
        </svg>

        <div class="min-w-0">
            <p class="font-mono text-[10px] uppercase tracking-[0.2em] text-[var(--sub-color)]">Today's goal</p>
            <p class="font-cinzel text-lg text-[var(--main-color)] tabular-nums leading-tight mt-0.5">
                {{ streak.goal.chars_today.toLocaleString() }}<span class="text-[var(--sub-color)]"> / {{ streak.goal.target.toLocaleString() }}</span>
                <span class="text-xs text-[var(--sub-color)] font-mono"> chars</span>
            </p>
            <p class="font-mono text-[11px] text-[var(--sub-color)] mt-1">
                {{ streak.goal.tests_today }} test{{ streak.goal.tests_today === 1 ? '' : 's' }} ·
                <span v-if="streak.goal.met" class="text-[var(--caret-color)]">goal met</span>
                <span v-else>{{ Math.max(0, streak.goal.target - streak.goal.chars_today).toLocaleString() }} to go</span>
            </p>
            <p v-if="milestone" class="font-mono text-[10px] uppercase tracking-[0.15em] text-[var(--caret-color)] mt-1.5">
                {{ milestone }} — keep going
            </p>
        </div>
    </div>
</template>
