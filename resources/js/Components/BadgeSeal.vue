<script>
// Per-family hue — deliberately more colourful than the rest of the app.
export const BADGE_COLORS = {
    star: '#c99a2e',      // amber
    bolt: '#3b6fb5',      // lapis
    flame: '#c1452f',     // tomato
    book: '#3f9d6b',      // green
    crescent: '#7d5ba6',  // violet
    trophy: '#b8732e',    // burnt orange
};

export const badgeColor = (icon) => BADGE_COLORS[icon] || BADGE_COLORS.star;
</script>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    icon: { type: String, default: 'star' },
    tier: { type: String, default: 'bronze' },
    earned: { type: Boolean, default: false },
    size: { type: Number, default: 56 },
});

// Simple line-art glyphs, drawn inside a 24-box.
const glyphs = {
    star: 'M12 3l2.6 5.6 6.1.8-4.5 4.2 1.1 6.1L12 17l-5.4 2.7 1.1-6.1L3.2 9.4l6.1-.8z',
    bolt: 'M13 2 4 14h6l-1 8 9-12h-6z',
    flame: 'M12 2c3 4 5 6 5 10a5 5 0 0 1-10 0c0-2 1-3.5 2.5-5C10 9 11 6 12 2z',
    book: 'M4 4h11a3 3 0 0 1 3 3v13H7a3 3 0 0 0-3 3zM7 20a3 3 0 0 0-3 3',
    trophy: 'M7 4h10v4a5 5 0 0 1-10 0zM5 5H3v2a3 3 0 0 0 3 3M19 5h2v2a3 3 0 0 1-3 3M9 15h6v3H9zM8 21h8',
    crescent: 'M17 3a9 9 0 1 0 4 12A7 7 0 0 1 17 3z',
};

const pip = computed(() => ({
    bronze: '#a97142',
    silver: 'var(--sub-color)',
    gold: 'var(--caret-color)',
}[props.tier] || '#a97142'));

const color = computed(() => (props.earned ? badgeColor(props.icon) : 'var(--border-color)'));
const d = computed(() => glyphs[props.icon] || glyphs.star);
</script>

<template>
    <span
        class="relative inline-flex items-center justify-center shrink-0"
        :style="{ width: size + 'px', height: size + 'px' }"
        :class="earned ? '' : 'opacity-45'"
    >
        <svg :width="size" :height="size" viewBox="0 0 56 56" aria-hidden="true">
            <circle cx="28" cy="28" r="25" fill="none" :stroke="color" stroke-width="1.5" />
            <circle cx="28" cy="28" r="21" fill="none" :stroke="color" stroke-width="0.75" />
            <circle v-if="earned" cx="28" cy="28" r="25" fill="none" :stroke="color" stroke-width="1.5" opacity="0.12" />
        </svg>
        <svg
            :width="size * 0.5"
            :height="size * 0.5"
            viewBox="0 0 24 24"
            fill="none"
            :stroke="color"
            stroke-width="1.7"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="absolute"
            aria-hidden="true"
        >
            <path :d="d" />
        </svg>
        <span
            v-if="earned"
            class="absolute bottom-0 right-0 w-2 h-2 rounded-full border border-[var(--bg-color)]"
            :style="{ backgroundColor: pip }"
        ></span>
    </span>
</template>
