<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    compact: { type: Boolean, default: false },
});

const streak = computed(() => usePage().props.auth?.streak ?? null);
</script>

<template>
    <div
        v-if="streak"
        class="inline-flex items-center gap-2 font-mono"
        :title="streak.practiced_today ? 'Practised today' : 'No practice yet today'"
    >
        <span
            class="w-1.5 h-1.5 rounded-full shrink-0"
            :class="streak.practiced_today ? 'bg-[var(--caret-color)]' : 'border border-[var(--sub-color)]'"
        />
        <span v-if="streak.current > 0" class="text-[var(--main-color)]">
            <span class="tabular-nums font-semibold">{{ streak.current }}</span>
            <span class="text-[var(--sub-color)] text-[10px] uppercase tracking-[0.15em] ml-1">{{ compact ? 'd' : 'day streak' }}</span>
        </span>
        <span v-else class="text-[var(--sub-color)] text-[10px] uppercase tracking-[0.15em]">
            {{ streak.practiced_today ? 'streak started' : 'start a streak' }}
        </span>
    </div>
</template>
