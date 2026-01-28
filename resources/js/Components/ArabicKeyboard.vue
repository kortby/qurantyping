<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useSettings } from '../useSettings';

const props = defineProps({
    activeKey: String, // The character that was just typed
    activeCode: String, // The physical key code that was just pressed
    hasError: Boolean,  // Whether there's a typing error that needs fixing
    nextKey: String,   // The character that should be typed next
    isShiftOn: Boolean, // Whether shift is currently being held
});

const { currentKeyboardLayout, setKeyboardLayout } = useSettings();

// Default Arabic 101 mapping (QWERTY-based positions)
const defaultArabic101 = {
    'Backquote': 'ذ', 'Digit1': '١', 'Digit2': '٢', 'Digit3': '٣', 'Digit4': '٤', 'Digit5': '٥', 'Digit6': '٦', 'Digit7': '٧', 'Digit8': '٨', 'Digit9': '٩', 'Digit0': '٠', 'Minus': '~', 'Equal': '!',
    'KeyQ': 'ض', 'KeyW': 'ص', 'KeyE': 'ث', 'KeyR': 'ق', 'KeyT': 'ف', 'KeyY': 'غ', 'KeyU': 'ع', 'KeyI': 'ه', 'KeyO': 'خ', 'KeyP': 'ح', 'BracketLeft': 'ج', 'BracketRight': 'د',
    'KeyA': 'ش', 'KeyS': 'س', 'KeyD': 'ي', 'KeyF': 'ب', 'KeyG': 'ل', 'KeyH': 'ا', 'KeyJ': 'ت', 'KeyK': 'ن', 'KeyL': 'م', 'Semicolon': 'ك', 'Quote': 'ط',
    'KeyZ': 'ئ', 'KeyX': 'ء', 'KeyC': 'ؤ', 'KeyV': 'ر', 'KeyB': 'لا', 'KeyN': 'ى', 'KeyM': 'ة', 'Comma': 'و', 'Period': 'ز', 'Slash': 'ظ',
    'Space': ' ', 'Backslash': 'ذ'
};

const shiftArabic101 = {
    'Backquote': 'ّ', 'Digit1': '!', 'Digit2': '@', 'Digit3': '#', 'Digit4': '$', 'Digit5': '%', 'Digit6': '^', 'Digit7': '&', 'Digit8': '*', 'Digit9': '(', 'Digit0': ')', 'Minus': '_', 'Equal': '+',
    'KeyQ': 'َ', 'KeyW': 'ً', 'KeyE': 'ُ', 'KeyR': 'ٌ', 'KeyT': 'ﻹ', 'KeyY': 'إ', 'KeyU': '‘', 'KeyI': 'ٱ', 'KeyO': '×', 'KeyP': '؛',
    'KeyA': 'ِ', 'KeyS': 'ٍ', 'KeyD': 'ْ', 'KeyF': ']', 'KeyG': '[', 'KeyH': 'ٰ', 'KeyJ': 'ـ', 'KeyK': '،', 'KeyL': '/', 'Semicolon': ':', 'Quote': '"',
    'KeyZ': '~', 'KeyX': 'ْ', 'KeyC': '{', 'KeyV': 'أ', 'KeyB': 'لآ', 'KeyN': 'آ', 'KeyM': '’', 'Comma': ',', 'Period': '.', 'Slash': '؟'
};

const layoutMap = ref(new Map());
const isAzerty = computed(() => currentKeyboardLayout.value === 'azerty');

const toggleLayout = () => {
    const nextLayout = isAzerty.value ? 'qwerty' : 'azerty';
    setKeyboardLayout(nextLayout);
};

const updateLayout = async () => {
    if (typeof navigator !== 'undefined' && navigator.keyboard) {
        try {
            const map = await navigator.keyboard.getLayoutMap();
            layoutMap.value = map;
            
            // Auto-detect AZERTY via KeyQ
            // On AZERTY, KeyQ produces 'a'
            if (map.get('KeyQ') === 'a' && currentKeyboardLayout.value !== 'azerty') {
                setKeyboardLayout('azerty');
            } else if (map.get('KeyQ') === 'q' && currentKeyboardLayout.value !== 'qwerty') {
                setKeyboardLayout('qwerty');
            }
        } catch (e) {
            console.warn('Keyboard layout map not available:', e);
        }
    }
};

onMounted(() => {
    updateLayout();
    if (typeof navigator !== 'undefined' && navigator.keyboard) {
        navigator.keyboard.addEventListener('layoutchange', updateLayout);
    }
});

onUnmounted(() => {
    if (typeof navigator !== 'undefined' && navigator.keyboard) {
        navigator.keyboard.removeEventListener('layoutchange', updateLayout);
    }
});

const getLabel = (code) => {
    if (code === 'Backspace') return 'Backspace';
    if (code === 'Tab') return 'Tab';
    if (code === 'CapsLock') return 'Caps';
    if (code === 'Enter') return 'Enter';
    if (code === 'ShiftLeft' || code === 'ShiftRight') return 'Shift';
    if (code === 'Space') return 'Space';

    // 1. Try to get the character from the browser's layout map
    const char = layoutMap.value.get(code);

    // 2. If the browser gives us an Arabic character, prioritize it
    if (char && /[\u0600-\u06FF]/.test(char)) {
        return char;
    }

    // 3. Fallback to Arabic 101 positions
    // Special case for ذ which moves between Backquote and Backslash
    if (code === 'Backquote' && layoutMap.value.get('Backslash') === 'ذ') return '';
    
    // Manual Adjustment for Arabic 102/AZERTY if ذ is moved
    if (isAzerty.value && code === 'Backquote') return '';
    if (isAzerty.value && code === 'Backslash') return 'ذ';

    return defaultArabic101[code] || char || '';
};

const layoutRows = [
    ['Backquote', 'Digit1', 'Digit2', 'Digit3', 'Digit4', 'Digit5', 'Digit6', 'Digit7', 'Digit8', 'Digit9', 'Digit0', 'Minus', 'Equal', 'Backspace'],
    ['Tab', 'KeyQ', 'KeyW', 'KeyE', 'KeyR', 'KeyT', 'KeyY', 'KeyU', 'KeyI', 'KeyO', 'KeyP', 'BracketLeft', 'BracketRight'],
    ['CapsLock', 'KeyA', 'KeyS', 'KeyD', 'KeyF', 'KeyG', 'KeyH', 'KeyJ', 'KeyK', 'KeyL', 'Semicolon', 'Quote', 'Enter'],
    ['ShiftLeft', 'KeyZ', 'KeyX', 'KeyC', 'KeyV', 'KeyB', 'KeyN', 'KeyM', 'Comma', 'Period', 'Slash', 'ShiftRight'],
    ['Space']
];

const latinMapping = {
    'qwerty': {
        'KeyQ': 'q', 'KeyW': 'w', 'KeyE': 'e', 'KeyR': 'r', 'KeyT': 't', 'KeyY': 'y', 'KeyU': 'u', 'KeyI': 'i', 'KeyO': 'o', 'KeyP': 'p',
        'KeyA': 'a', 'KeyS': 's', 'KeyD': 'd', 'KeyF': 'f', 'KeyG': 'g', 'KeyH': 'h', 'KeyJ': 'j', 'KeyK': 'k', 'KeyL': 'l', 'Semicolon': ';', 'Quote': "'",
        'KeyZ': 'z', 'KeyX': 'x', 'KeyC': 'c', 'KeyV': 'v', 'KeyB': 'b', 'KeyN': 'n', 'KeyM': 'm', 'Comma': ',', 'Period': '.', 'Slash': '/'
    },
    'azerty': {
        'KeyQ': 'a', 'KeyW': 'z', 'KeyE': 'e', 'KeyR': 'r', 'KeyT': 't', 'KeyY': 'y', 'KeyU': 'u', 'KeyI': 'i', 'KeyO': 'o', 'KeyP': 'p',
        'KeyA': 'q', 'KeyS': 's', 'KeyD': 'd', 'KeyF': 'f', 'KeyG': 'g', 'KeyH': 'h', 'KeyJ': 'j', 'KeyK': 'k', 'KeyL': 'l', 'Semicolon': 'm', 'Quote': 'ù',
        'KeyZ': 'w', 'KeyX': 'x', 'KeyC': 'c', 'KeyV': 'v', 'KeyB': 'b', 'KeyN': 'n', 'KeyM': ',', 'Comma': ';', 'Period': ':', 'Slash': '!'
    }
};

const getLatinLabel = (code) => {
    // Priority 1: Browser's real layout if it's Latin
    const char = layoutMap.value.get(code);
    if (char && !/[\u0600-\u06FF]/.test(char)) return char;

    // Priority 2: Manual mapping based on selected mode
    return latinMapping[currentKeyboardLayout.value]?.[code] || '';
};

const rows = computed(() => {
    return layoutRows.map(row => row.map(code => ({
        code: code,
        label: getLabel(code),
        latinLabel: getLatinLabel(code),
        shiftLabel: shiftArabic101[code] || ''
    })));
});

const normalize = (char) => {
    if (!char) return '';
    return char.normalize('NFC')
        .replace(/[أإآٱ]/g, 'ا')
        .replace(/[ۀة]/g, 'ه')
        .replace(/[ىي]/g, 'ي')
        .replace(/[\u0610-\u061A\u0640\u064B-\u065F\u0670\u06D6-\u06ED\u06DD]/g, '')
        .trim();
};

const isKeyActive = (keyObj) => {
    if (props.activeCode === keyObj.code) return true;
    if (!props.activeKey) return false;
    if (props.activeKey === 'Space' && keyObj.code === 'Space') return true;
    
    const activeNorm = normalize(props.activeKey);
    const labelNorm = normalize(keyObj.label);
    const shiftLabel = shiftArabic101[keyObj.code];
    
    if (activeNorm === labelNorm && activeNorm !== '') return true;
    if (props.activeKey === shiftLabel) return true;
    
    return false;
};

const isKeyNext = (keyObj) => {
    if (!props.nextKey) return false;
    if (props.nextKey === ' ' && keyObj.code === 'Space') return true;
    
    const nextNorm = normalize(props.nextKey);
    const labelNorm = normalize(keyObj.label);
    const shiftLabel = shiftArabic101[keyObj.code];

    // Priority 1: Exact match with next key (for diacritics)
    if (props.nextKey === shiftLabel) return true;
    
    // Priority 2: Normalized match (for letters)
    if (nextNorm === labelNorm && nextNorm !== '') return true;
    
    return false;
};
</script>

<template>
    <div class="keyboard-container mt-6 p-8 bg-[var(--panel-color)] rounded-[2.5rem] border border-[var(--border-color)] backdrop-blur-xl shadow-2xl overflow-hidden select-none relative">
        <!-- Layout Toggle / Indicator -->
        <div class="absolute top-4 right-8 flex items-center gap-3">
            <button @click="toggleLayout" 
                    class="px-3 py-1 rounded-full bg-[var(--bg-color)]/60 border border-[var(--border-color)] text-[10px] font-mono uppercase tracking-widest text-[var(--caret-color)] hover:bg-[var(--caret-color)] hover:text-[var(--bg-color)] transition-all">
                {{ currentKeyboardLayout === 'azerty' ? 'AZERTY' : 'QWERTY' }}
            </button>
            <span class="text-[8px] font-mono uppercase tracking-widest text-[var(--sub-color)] opacity-40">
                Mode
            </span>
        </div>

        <div class="keyboard flex flex-col gap-2 mx-auto max-w-6xl" dir="ltr">
            <div v-for="(row, rowIndex) in rows" :key="rowIndex" class="flex gap-2 justify-center">
                <div v-for="key in row" :key="key.code" 
                    class="key-cap flex flex-col items-center justify-center transition-all duration-150 rounded-2xl font-cinzel border border-transparent shadow-md relative group"
                    :class="[
                        key.code === 'Space' ? 'w-[640px] h-10 md:h-20' : (['Backspace', 'Tab', 'CapsLock', 'Enter', 'ShiftLeft', 'ShiftRight'].includes(key.code) ? 'px-8 py-2 min-w-[120px] h-20 uppercase text-[10px]' : 'w-20 h-20'),
                        isKeyActive(key) ? 'key-active' : '',
                        isKeyNext(key) && !props.hasError ? 'key-next' : '',
                        key.code === 'Backspace' && props.hasError ? 'key-error' : '',
                        // Highlighting logic for shift characters remains...
                        (['ShiftLeft', 'ShiftRight'].includes(key.code) && Object.values(shiftArabic101).includes(props.nextKey)) ? 'key-shift-hint' : '',
                        // Base appearance
                        !isKeyActive(key) && !isKeyNext(key) && !(['ShiftLeft', 'ShiftRight'].includes(key.code) && Object.values(shiftArabic101).includes(props.nextKey)) && !(key.code === 'Backspace' && props.hasError) ? 'bg-[var(--bg-color)]/40 text-[var(--main-color)]' : (['Backspace', 'Tab', 'CapsLock', 'Enter', 'ShiftLeft', 'ShiftRight'].includes(key.code) && !isKeyActive(key) && !(key.code === 'Backspace' && props.hasError) ? 'opacity-40' : '')
                    ]">

                    <!-- Shift Character Label (Tiny, Top Left) - Shows base char when shift is on -->
                    <span v-if="key.shiftLabel || key.label"
                          class="absolute top-2 left-3 text-xs transition-opacity text-[var(--caret-color)] font-bold"
                          :class="isShiftOn ? 'opacity-40' : 'opacity-30 group-hover:opacity-100'">
                        {{ isShiftOn ? key.label : key.shiftLabel }}
                    </span>

                    <!-- Latin Alphabet Label (Small, Top Right) -->
                    <span v-if="key.latinLabel" 
                          class="absolute top-2 right-3 text-[10px] font-mono uppercase tracking-tighter opacity-40 transition-opacity group-hover:opacity-100">
                        {{ key.latinLabel }}
                    </span>

                    <!-- Arabic Character (Large, Center) -->
                    <span class="text-4xl mt-1 leading-none transition-all duration-100"
                          :class="{ 'scale-110 text-[var(--caret-color)]': isShiftOn && key.shiftLabel }">
                        {{ key.code === 'Space' ? '' : (isShiftOn && key.shiftLabel ? key.shiftLabel : key.label) }}
                    </span>
                    
                    <!-- Home row tactile markers (bumps) -->
                    <div v-if="key.code === 'KeyF' || key.code === 'KeyJ'" 
                         class="absolute bottom-1 left-1/2 -translate-x-1/2 w-4 h-[2px] bg-current opacity-40 rounded-full">
                    </div>
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

.key-error {
    background: var(--error-color) !important;
    color: white !important;
    border-color: var(--error-color) !important;
    box-shadow: 0 0 20px rgba(255, 49, 49, 0.4);
    animation: pulse-error 1.5s infinite;
    font-weight: bold;
    opacity: 1 !important;
}

@keyframes pulse-error {
    0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255, 49, 49, 0.4); }
    70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(255, 49, 49, 0); }
    100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255, 49, 49, 0); }
}

@keyframes pulse-next {
    0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(197, 160, 89, 0.4); }
    70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(197, 160, 89, 0); }
    100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(197, 160, 89, 0); }
}

.key-shift-hint {
    border: 2px solid var(--caret-color) !important;
    color: var(--caret-color) !important;
    opacity: 1 !important;
    animation: pulse-shift 1.5s infinite;
}

@keyframes pulse-shift {
    0% { transform: scale(1); }
    50% { transform: scale(1.02); opacity: 0.8; }
    100% { transform: scale(1); }
}

.keyboard-container {
    perspective: 1000px;
}

.keyboard {
    transform: rotateX(10deg);
}
</style>
