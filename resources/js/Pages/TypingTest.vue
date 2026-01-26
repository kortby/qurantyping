<script setup>
import { ref, onMounted, computed, onUnmounted } from 'vue';
import axios from 'axios';
import confetti from 'canvas-confetti';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSettings } from '../useSettings';

const { t, currentLang } = useSettings();

const props = defineProps({
    personalBestWpm: Number,
});

const currentPB = ref(props.personalBestWpm || 0);

// --- State for Filters and Test Data ---
const surahs = ref([]);
const selectedSurah = ref(1);
const startAyah = ref(1);
const endAyah = ref(5);
const isLoading = ref(true);

const quranText = ref({
    text: '',
    surah_name_arabic: '',
    surah_number: null,
    start_ayah: null,
    end_ayah: null,
});

// --- State for Test Mechanics ---
const userInput = ref('');
const timer = ref(0);
const intervalId = ref(null);
const testFinished = ref(false);
const isFocused = ref(false);
const showResults = ref(false);
const totalErrors = ref(0);

const normalizeForComparison = (text) => {
    if (!text) return '';
    return text
        .replace(/[أإآ]/g, 'ا')
        .replace(/[ۀة]/g, 'ه')
        .replace(/[ىي]/g, 'ي')
        .replace(/[\u064B-\u0652]/g, ''); // Remove tashkeel
};

// --- Computed Properties for Stats & Rendering ---
const sourceCharacters = computed(() => quranText.value?.text?.split('') || []);
const typedCharacters = computed(() => userInput.value.split(''));
const wpm = computed(() => {
    if (timer.value === 0) return 0;
    const wordCount = userInput.value.length / 5;
    const minutes = timer.value / 60;
    return Math.round(wordCount / minutes);
});
const accuracy = computed(() => {
    if (userInput.value.length === 0) return 100;
    let correctChars = 0;
    typedCharacters.value.forEach((char, index) => {
        if (normalizeForComparison(char) === normalizeForComparison(sourceCharacters.value[index])) {
            correctChars++;
        }
    });
    // Calculate accuracy based on total keystrokes (current length + mistakes made)
    const totalKeystrokes = userInput.value.length + totalErrors.value;
    return Math.round((correctChars / totalKeystrokes) * 100);
});
const firstErrorIndex = computed(() => {
    for (let i = 0; i < typedCharacters.value.length; i++) {
        if (normalizeForComparison(typedCharacters.value[i]) !== normalizeForComparison(sourceCharacters.value[i])) return i;
    }
    return -1;
});
const correctPart = computed(() => {
    const end = firstErrorIndex.value !== -1 ? firstErrorIndex.value : userInput.value.length;
    return quranText.value.text.substring(0, end);
});
const incorrectPart = computed(() => {
    if (firstErrorIndex.value === -1) return '';
    return quranText.value.text.substring(firstErrorIndex.value, userInput.value.length);
});
const untypedPart = computed(() => {
    return quranText.value.text.substring(userInput.value.length);
});

// --- Core Logic ---
const fetchSurahs = async () => {
    try {
        const response = await axios.get('/api/surahs');
        surahs.value = response.data;
    } catch (error) {
        console.error("Failed to fetch Surahs:", error);
    }
};

const fetchTestText = async (withParams = true) => {
    isLoading.value = true;
    try {
        let params = {};
        if (withParams) {
            params = {
                surah_number: selectedSurah.value,
                start_ayah: startAyah.value,
                end_ayah: endAyah.value,
            };
        }
        const response = await axios.get('/api/test/text', { params });
        quranText.value = response.data;
        
        // Sync UI filters with whatever the server returned (important for random selection)
        selectedSurah.value = quranText.value.surah_number;
        startAyah.value = quranText.value.start_ayah;
        endAyah.value = quranText.value.end_ayah;
        
        resetTest();
    } catch (error) {
        console.error("Failed to fetch test text:", error);
    } finally {
        isLoading.value = false;
    }
};

const handleInput = (event) => {
    if (testFinished.value || isLoading.value) return;
    if (!intervalId.value) startTimer();
    
    const newValue = event.target.value;
    
    // Count errors if the user typed something new and it's wrong
    if (newValue.length > userInput.value.length) {
        const lastTypedChar = newValue[newValue.length - 1];
        const expectedChar = sourceCharacters.value[newValue.length - 1];
        if (normalizeForComparison(lastTypedChar) !== normalizeForComparison(expectedChar)) {
            totalErrors.value++;
        }
    }

    userInput.value = newValue;
    // Only finish if the length matches and there are no active errors (100% correct text)
    if (userInput.value.length === sourceCharacters.value.length && firstErrorIndex.value === -1) {
        finishTest();
    }
};

const startTimer = () => {
    if (intervalId.value) return;
    intervalId.value = setInterval(() => { timer.value++; }, 1000);
};

const stopTimer = () => {
    clearInterval(intervalId.value);
    intervalId.value = null;
};

const finishTest = async () => {
    stopTimer();
    testFinished.value = true;
    showResults.value = true;

    // Trigger celebration ONLY if it's a new personal record
    if (wpm.value > currentPB.value) {
        currentPB.value = wpm.value;
        confetti({
            particleCount: 200,
            spread: 90,
            origin: { y: 0.6 },
            colors: ['#eab308', '#d1d0c5', '#646669']
        });
    }
    
    if (!quranText.value.id || userInput.value.length === 0) return;

    try {
        let correctChars = 0;
        typedCharacters.value.forEach((char, index) => {
            if (normalizeForComparison(char) === normalizeForComparison(sourceCharacters.value[index])) {
                correctChars++;
            }
        });

        await axios.post('/test/complete', {
            quran_text_id: quranText.value.id,
            wpm: wpm.value,
            raw_wpm: wpm.value,
            accuracy: accuracy.value,
            char_count: userInput.value.length,
            correct_chars: correctChars,
            incorrect_chars: userInput.value.length - correctChars,
            mode: 'quote',
            duration: timer.value || 1,
            start_ayah: quranText.value.start_ayah,
            end_ayah: quranText.value.end_ayah,
            total_errors: totalErrors.value,
        });
    } catch (error) {
        console.error("Failed to save test result:", error);
    }
};

const resetTest = () => {
    stopTimer();
    userInput.value = '';
    timer.value = 0;
    totalErrors.value = 0;
    testFinished.value = false;
    showResults.value = false;
    setTimeout(() => {
        document.getElementById('hidden-input')?.focus();
    }, 100);
};

const handleFocus = () => isFocused.value = true;
const handleBlur = () => isFocused.value = false;

// Global Tab handler for restart
const handleGlobalKeydown = (e) => {
    if (e.key === 'Tab') {
        e.preventDefault();
        resetTest();
    }
};

onMounted(async () => {
    await fetchSurahs();
    
    // Check for query parameters to pre-load a specific test
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('surah')) {
        selectedSurah.value = parseInt(urlParams.get('surah'));
        startAyah.value = parseInt(urlParams.get('start')) || 1;
        endAyah.value = parseInt(urlParams.get('end')) || 1;
        await fetchTestText(true);
    } else {
        // No params? Start with a fresh random selection of 3 ayas
        await fetchTestText(false);
    }
    
    isLoading.value = false;
    window.addEventListener('keydown', handleGlobalKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
});

defineOptions({ layout: AppLayout });
</script>

<template>
    <div class="flex flex-col items-center justify-start py-8 min-h-[80vh]">
        <!-- Minimalist Filters -->
        <form @submit.prevent="fetchTestText" class="w-full max-w-5xl mb-12 flex flex-wrap items-center gap-6 font-mono text-sm opacity-50 hover:opacity-100 transition-all">
            <div class="flex items-center gap-3 bg-[var(--panel-color)] px-4 py-2 rounded-xl backdrop-blur-md border border-white/5">
                <span class="text-[var(--caret-color)] opacity-60">{{ t('surah') }}</span>
                <select v-model="selectedSurah" class="bg-transparent border-none focus:ring-0 text-[var(--main-color)] cursor-pointer outline-none min-w-[120px]">
                    <option v-for="surah in surahs" :key="surah.surah_number" :value="surah.surah_number" class="bg-[var(--bg-color)]">
                        {{ surah.surah_number }}. {{ surah.surah_name_arabic }}
                    </option>
                </select>
            </div>
            <div class="flex items-center gap-3 bg-[var(--panel-color)] px-4 py-2 rounded-xl backdrop-blur-md border border-white/5">
                <span class="text-[var(--caret-color)] opacity-60">{{ t('range') }}</span>
                <input type="number" v-model="startAyah" class="w-16 bg-transparent border-none focus:ring-0 text-[var(--main-color)] p-0 text-center font-bold">
                <span class="text-[var(--sub-color)]">-</span>
                <input type="number" v-model="endAyah" class="w-16 bg-transparent border-none focus:ring-0 text-[var(--main-color)] p-0 text-center font-bold">
            </div>
            <button type="submit" class="bg-[var(--caret-color)] text-[var(--bg-color)] px-6 py-2 rounded-xl font-bold hover:scale-105 active:scale-95 transition-all shadow-lg shadow-yellow-500/10">
                {{ t('load_text') }}
            </button>
            <button type="button" @click="fetchTestText(false)" class="bg-[var(--panel-color)] text-[var(--caret-color)] border border-[var(--caret-color)]/20 px-6 py-2 rounded-xl font-mono text-xs hover:bg-[var(--caret-color)]/10 transition-all font-bold uppercase tracking-widest">
                {{ t('random') }}
            </button>
        </form>

        <!-- Live Stats (during test) -->
        <div v-if="!showResults" class="w-full max-w-5xl mb-8 flex gap-12 font-mono text-xl text-[var(--caret-color)] opacity-80 select-none">
            <div class="flex flex-col">
                <span class="text-[10px] text-[var(--sub-color)] uppercase tracking-[0.2em] mb-1">{{ t('wpm') }}</span>
                <span class="font-bold">{{ wpm }}</span>
            </div>
            <div class="flex flex-col">
                <span class="text-[10px] text-[var(--sub-color)] uppercase tracking-[0.2em] mb-1">{{ t('accuracy') }}</span>
                <span class="font-bold">{{ accuracy }}%</span>
            </div>
            <div class="flex flex-col">
                <span class="text-[10px] text-[var(--sub-color)] uppercase tracking-[0.2em] mb-1">{{ t('time') }}</span>
                <span class="font-bold">{{ timer }}s</span>
            </div>
            <div class="flex flex-col">
                <span class="text-[10px] text-[var(--sub-color)] uppercase tracking-[0.2em] mb-1">Errors</span>
                <span class="font-bold text-[var(--error-color)]">{{ totalErrors }}</span>
            </div>
        </div>

        <!-- Typing Area -->
        <div v-if="quranText.text && !showResults" 
             @click="() => document.getElementById('hidden-input')?.focus()" 
             class="relative w-full max-w-5xl py-12 transition-all duration-500 min-h-[300px] flex items-center"
             :class="{ 'opacity-100': isFocused, 'opacity-40 blur-[4px] scale-[0.98]': !isFocused }">
            
            <!-- Focus Message -->
            <div v-if="!isFocused" class="absolute inset-0 z-30 flex flex-col items-center justify-center cursor-pointer">
                <div class="bg-[var(--panel-color)] px-8 py-4 rounded-2xl backdrop-blur-xl border border-white/10 shadow-2xl">
                    <p class="text-sm font-mono text-[var(--main-color)] animate-pulse flex items-center gap-3">
                        <span class="text-[var(--caret-color)]">▶</span> {{ t('click_to_focus') }}
                    </p>
                </div>
            </div>

            <div v-if="isLoading" class="absolute inset-0 bg-[var(--bg-color)]/80 backdrop-blur-sm flex items-center justify-center z-10 transition-all">
                <div class="flex flex-col items-center gap-3">
                    <div class="w-12 h-12 border-4 border-[var(--caret-color)] border-t-transparent rounded-full animate-spin"></div>
                    <p class="text-xs font-mono text-[var(--caret-color)] uppercase tracking-widest">{{ t('loading') }}</p>
                </div>
            </div>

            <div class="text-4xl lg:text-5xl leading-[2] select-none text-right w-full" style="font-family: 'Noto Naskh Arabic', serif;" dir="rtl">
                <p class="relative z-0 whitespace-pre-wrap break-words transition-all duration-300">
                    <span class="text-[var(--main-color)]">{{ correctPart }}</span><span class="text-[var(--error-color)] border-b-2 border-[var(--error-color)] bg-[var(--error-color)]/10">{{ incorrectPart }}</span><template v-if="untypedPart.length > 0"><span class="text-[var(--sub-color)] border-b-2 border-[var(--caret-color)] animate-caret-blink inline-block" style="margin-bottom: -2px;">{{ untypedPart[0] }}</span><span class="text-[var(--sub-color)]">{{ untypedPart.slice(1) }}</span></template>
                </p>
                <input id="hidden-input" 
                       type="text" 
                       class="absolute top-0 left-0 w-full h-full opacity-0 cursor-default z-20" 
                       :value="userInput" 
                       @input="handleInput" 
                       @focus="handleFocus"
                       @blur="handleBlur"
                       autofocus 
                       autocomplete="off"
                       spellcheck="false"
                       :maxlength="sourceCharacters.length" />
            </div>
        </div>

        <!-- Results View -->
        <div v-if="showResults" class="w-full max-w-5xl flex flex-col items-center justify-center py-20 animate-fade-in">
            <div class="flex flex-col md:flex-row gap-20 mb-16 items-center">
                <div class="flex flex-col items-center group">
                    <span class="text-xs text-[var(--sub-color)] font-mono uppercase tracking-[0.3em] mb-4 group-hover:text-[var(--caret-color)] transition-colors">{{ t('wpm') }}</span>
                    <span class="text-8xl text-[var(--caret-color)] font-mono font-bold">{{ wpm }}</span>
                </div>
                <div class="flex flex-col items-center group">
                    <span class="text-xs text-[var(--sub-color)] font-mono uppercase tracking-[0.3em] mb-4 group-hover:text-[var(--caret-color)] transition-colors">{{ t('accuracy') }}</span>
                    <span class="text-8xl text-[var(--caret-color)] font-mono font-bold">{{ accuracy }}%</span>
                </div>
                <div class="flex flex-col items-center group">
                    <span class="text-xs text-[var(--sub-color)] font-mono uppercase tracking-[0.3em] mb-4 group-hover:text-[var(--error-color)] transition-colors">Errors</span>
                    <span class="text-8xl text-[var(--error-color)] font-mono font-bold">{{ totalErrors }}</span>
                </div>
            </div>
            
            <div class="flex flex-col items-center gap-10">
                <p class="text-2xl font-mono text-[var(--main-color)] opacity-60">
                    {{ accuracy === 100 ? t('perfect') : (accuracy > 90 ? t('excellent') : t('keep_practicing')) }}
                </p>
                
                <div class="flex items-center gap-8">
                    <button @click="resetTest" class="group flex flex-col items-center gap-4 transition-all">
                        <div class="p-6 rounded-2xl bg-[var(--panel-color)] border border-white/5 group-hover:bg-[var(--caret-color)] group-hover:text-[var(--bg-color)] transition-all transform group-hover:rotate-12 group-hover:scale-110 shadow-xl shadow-black/20">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path><path d="M21 3v5h-5"></path><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path><path d="M8 16H3v5"></path></svg>
                        </div>
                        <span class="text-xs font-mono text-[var(--sub-color)] uppercase tracking-widest">{{ t('restart_hint') }}</span>
                    </button>

                    <a href="https://buy.stripe.com/dRmdRa1546e60jI2jZenS01" target="_blank" class="group flex flex-col items-center gap-4 transition-all">
                        <div class="p-6 rounded-2xl bg-[var(--panel-color)] border border-white/5 group-hover:bg-green-500 group-hover:text-white transition-all transform group-hover:-rotate-12 group-hover:scale-110 shadow-xl shadow-black/20">
                            <span class="text-4xl">❤️</span>
                        </div>
                        <span class="text-xs font-mono text-[var(--sub-color)] uppercase tracking-widest">{{ t('donate') }}</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Footer Context Info -->
        <div v-if="!showResults && quranText.surah_name_arabic" class="mt-auto py-12 text-[var(--sub-color)] font-mono text-xs flex gap-8 items-center bg-[var(--panel-color)] px-8 rounded-full border border-white/5 backdrop-blur-md opacity-60 hover:opacity-100 transition-opacity">
            <div class="flex items-center gap-3">
                <kbd class="bg-[var(--main-color)] text-[var(--bg-color)] px-2 py-0.5 rounded font-bold">TAB</kbd>
                <span>{{ t('restart') }}</span>
            </div>
            <div class="w-1 h-1 bg-[var(--sub-color)] rounded-full"></div>
            <div class="text-[var(--caret-color)] font-bold">
                {{ quranText.surah_name_arabic }} ({{ quranText.surah_number }}:{{ quranText.start_ayah }}-{{ quranText.end_ayah }})
            </div>
        </div>
    </div>
</template>

<style scoped>
.animate-caret-blink {
    animation: caret-blink 1s ease-in-out infinite;
}

@keyframes caret-blink {
    0%, 100% { border-bottom-color: var(--caret-color); }
    50% { border-bottom-color: transparent; }
}

.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>