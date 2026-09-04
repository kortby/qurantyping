<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useSettings } from '../useSettings';

const { t } = useSettings();
const streak = computed(() => usePage().props.auth?.streak ?? null);

const pct = computed(() => {
    if (!streak.value?.goal?.target) return 0;
    return Math.min(1, streak.value.goal.chars_today / streak.value.goal.target);
});

const R = 34;
const CIRC = 2 * Math.PI * R;
const dash = computed(() => `${pct.value * CIRC} ${CIRC}`);

const milestone = computed(() => {
    const c = streak.value?.current ?? 0;
    const days = c >= 100 ? 100 : c >= 30 ? 30 : c >= 7 ? 7 : 0;
    return days ? t('streak.milestone').replace('{n}', days) : null;
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
            <p class="font-mono text-[10px] uppercase tracking-[0.2em] text-[var(--sub-color)]">{{ t('streak.todays_goal') }}</p>
            <p class="font-cinzel text-lg text-[var(--main-color)] tabular-nums leading-tight mt-0.5">
                {{ streak.goal.chars_today.toLocaleString() }}<span class="text-[var(--sub-color)]"> / {{ streak.goal.target.toLocaleString() }}</span>
                <span class="text-xs text-[var(--sub-color)] font-mono"> {{ t('streak.chars') }}</span>
            </p>
            <p class="font-mono text-[11px] text-[var(--sub-color)] mt-1">
                {{ streak.goal.tests_today }} {{ t('streak.tests') }} ·
                <span v-if="streak.goal.met" class="text-[var(--caret-color)]">{{ t('streak.goal_met') }}</span>
                <span v-else>{{ Math.max(0, streak.goal.target - streak.goal.chars_today).toLocaleString() }} {{ t('streak.to_go') }}</span>
            </p>
            <p v-if="milestone" class="font-mono text-[10px] uppercase tracking-[0.15em] text-[var(--caret-color)] mt-1.5">
                {{ milestone }} — {{ t('streak.keep_going') }}
            </p>
        </div>
    </div>
</template>
