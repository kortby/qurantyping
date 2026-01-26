<script setup>
import { computed } from 'vue';

const props = defineProps({
    activeKey: String, // The key that was just typed
    nextKey: String,   // The key that should be typed next
});

const rows = [
    ['ذ', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '٠', '-', '=', 'Backspace'],
    ['Tab', 'ض', 'ص', 'ث', 'ق', 'ف', 'غ', 'ع', 'ه', 'خ', 'ح', 'ج', 'د'],
    ['Caps', 'ش', 'س', 'ي', 'ب', 'ل', 'ا', 'ت', 'ن', 'م', 'ك', 'ط', 'Enter'],
    ['Shift', 'ئ', 'ء', 'ؤ', 'ر', 'لا', 'ى', 'ة', 'و', 'ز', 'ظ', 'Shift'],
    ['Space']
];

// Helper to check if a char matches a key (considering normalization or special keys)
const isKeyActive = (key) => {
    if (!props.activeKey) return false;
    return props.activeKey === key;
};

const isKeyNext = (key) => {
    if (!props.nextKey) return false;
    // Handle space as a special case
    if (props.nextKey === ' ' && key === 'Space') return true;
    return props.nextKey === key;
};
</script>

<template>
    <div class="keyboard-container mt-12 p-8 bg-[var(--panel-color)] rounded-[2.5rem] border border-[var(--border-color)] backdrop-blur-xl shadow-2xl overflow-hidden select-none">
        <div class="keyboard flex flex-col gap-2 mx-auto max-w-4xl" dir="rtl">
            <div v-for="(row, rowIndex) in rows" :key="rowIndex" class="flex gap-2 justify-center">
                <div v-for="key in row" :key="key" 
                    class="key-cap flex items-center justify-center transition-all duration-150 rounded-xl font-cinzel text-2xl border border-transparent shadow-md"
                    :class="[
                        key === 'Space' ? 'w-[450px] h-14' : (['Backspace', 'Tab', 'Caps', 'Enter', 'Shift'].includes(key) ? 'px-6 py-2 min-w-[90px] h-14 opacity-40 uppercase text-[10px]' : 'w-14 h-14'),
                        isKeyActive(key) ? 'key-active' : '',
                        isKeyNext(key) ? 'key-next' : '',
                        !isKeyActive(key) && !isKeyNext(key) ? 'bg-[var(--bg-color)]/40 text-[var(--main-color)]' : ''
                    ]">
                    {{ key === 'Space' ? '' : key }}
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.key-cap {
    font-family: 'Noto Naskh Arabic', serif;
    position: relative;
    overflow: hidden;
}

.key-next {
    background: var(--caret-color);
    color: var(--bg-color);
    border-color: var(--caret-color);
    box-shadow: 0 0 20px rgba(197, 160, 89, 0.3);
    animation: pulse-next 1.5s infinite;
    font-weight: bold;
    opacity: 1 !important;
}

.key-active {
    background: var(--main-color) !important;
    color: var(--bg-color) !important;
    transform: scale(0.9) translateY(2px);
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.4);
    z-index: 10;
}

@keyframes pulse-next {
    0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(197, 160, 89, 0.4); }
    70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(197, 160, 89, 0); }
    100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(197, 160, 89, 0); }
}

.keyboard-container {
    perspective: 1000px;
}

.keyboard {
    transform: rotateX(10deg);
}
</style>
