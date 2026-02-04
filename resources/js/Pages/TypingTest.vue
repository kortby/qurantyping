<script setup>
import { ref, onMounted, computed, onUnmounted, watch } from 'vue';
import { usePage, Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import confetti from 'canvas-confetti';
import AppLayout from '@/Layouts/AppLayout.vue';
import ArabicKeyboard from '@/Components/ArabicKeyboard.vue';
import SurahSelect from '@/Components/SurahSelect.vue';
import QuranAudioPlayer from '@/Components/QuranAudioPlayer.vue';
import GuestTestModal from '@/Components/GuestTestModal.vue';
import { useSettings } from '../useSettings';

const activeKey = ref(null);
const activeCode = ref(null);

const { t, currentLang, usePunctuation, setPunctuation } = useSettings();
const page = usePage();

const showTashkilFeature = computed(() => page.props.features?.tashkil ?? false);

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
    text_simple: '',
    text_punctuated: '',
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
const isShiftPressed = ref(false);
const isTyping = ref(false);
const showLanguageWarning = ref(false);
let typingTimeout = null;

const showGuestModal = ref(false);

// Error Sound Logic
const errorSoundEnabled = ref(page.props.auth?.user?.error_sound ?? true);

const playErrorSound = () => {
    if (!errorSoundEnabled.value) return;
    
    const AudioContext = window.AudioContext || window.webkitAudioContext;
    if (!AudioContext) return;
    
    const ctx = new AudioContext();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    
    osc.connect(gain);
    gain.connect(ctx.destination);
    
    // Soft "thud" sound
    osc.type = 'triangle'; // Softer than sine for this low freq
    osc.frequency.setValueAtTime(100, ctx.currentTime);
    osc.frequency.exponentialRampToValueAtTime(40, ctx.currentTime + 0.1);
    
    gain.gain.setValueAtTime(0.2, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.1);
    
    osc.start();
    osc.stop(ctx.currentTime + 0.1);
};

const toggleErrorSound = () => {
    errorSoundEnabled.value = !errorSoundEnabled.value;
    
    if (page.props.auth?.user) {
        router.post(route('user.settings.error-sound'), {
            enabled: errorSoundEnabled.value
        }, {
            preserveScroll: true,
            preserveState: true,
        });
    }
};

// --- Caret Position State ---
const caretPosition = ref({ top: 0, left: 0, width: 0, height: 0, opacity: 0 });
const containerRef = ref(null);

const updateCaret = () => {
    if (showResults.value) {
        caretPosition.value.opacity = 0;
        return;
    }

    // Use nextTick or a slightly longer timeout to ensure layout is settled
    setTimeout(() => {
        const activeSpan = document.querySelector('.cluster-active');
        const container = containerRef.value;
        
        if (activeSpan && container) {
            const rect = activeSpan.getBoundingClientRect();
            const containerRect = container.getBoundingClientRect();
            
            // In RTL, we need to be careful with left/right. 
            // rect.left is the left edge of the character.
            caretPosition.value = {
                top: rect.bottom - containerRect.top - 2, // 2px offset for the underline
                left: rect.left - containerRect.left,
                width: rect.width,
                height: 5, 
                opacity: 1
            };
        }
    }, 32); 
};

watch([userInput, isFocused], updateCaret);
window.addEventListener('resize', updateCaret);

const normalizeForComparison = (text) => {
    if (!text) return '';
    let result = text.normalize('NFC')
        .replace(/[أإآٱ]/g, 'ا')
        .replace(/[ۀة]/g, 'ه')
        .replace(/[ىي]/g, 'ي');
    
    // If Tashkeel mode is ON, we only strip decorative/stop signs, but KEEP vowels (\u064B-\u065F)
    if (usePunctuation.value) {
        // Only strip Tatweel, stop signs, and the End of Ayah symbol
        // Keep diacritics: \u064B-\u065F and \u0670 (dagger alif)
        result = result.replace(/[\u0610-\u061A\u0640\u06D6-\u06ED\u06DD]/g, '');
    } else {
        // Strip everything if Tashkeel is off
        result = result.replace(/[\u0610-\u061A\u0640\u064B-\u065F\u0670\u06D6-\u06ED\u06DD]/g, '');
    }
    
    return result.trim();
};

// --- Computed Properties for Stats & Rendering ---
const currentDisplayText = computed(() => {
    const raw = usePunctuation.value ? (quranText.value.text_punctuated || quranText.value.text) : quranText.value.text_simple;
    // Normalize to NFC and strip Tatweel (aesthetic stretch)
    return raw?.normalize('NFC').replace(/\u0640/g, '').trim() || '';
});

// Map visual characters to logic ones (ignoring the ۝ decorative separator)
const visualMapping = computed(() => {
    const visual = currentDisplayText.value || '';
    let logicText = '';
    const vToL = new Array(visual.length).fill(-1);
    const lToVStart = [];
    
    let i = 0;
    while (i < visual.length) {
        // Match the pattern: [optional space]۝[arabic digits][optional space]
        // Which we treat as a single logic space
        const match = visual.substring(i).match(/^ ?۝[٠-٩]+ ?/);
        
        if (match) {
            const matchLen = match[0].length;
            lToVStart.push(i);
            logicText += ' ';
            vToL[i] = logicText.length - 1;
            // Mark the rest of the ornament characters as mapping to nothing
            for (let j = 1; j < matchLen; j++) {
                vToL[i + j] = -1;
            }
            i += matchLen;
        } else {
            lToVStart.push(i);
            logicText += visual[i];
            vToL[i] = logicText.length - 1;
            i++;
        }
    }
    return { logicText, vToL, lToVStart };
});

const sourceClusters = computed(() => {
    const text = currentDisplayText.value || '';
    const clusters = [];
    for (let i = 0; i < text.length; i++) {
        let cluster = text[i];
        let startIndex = i;

        // Special case: If we hit the Ayah symbol, group it with all following Arabic digits
        if (text[i] === '۝') {
            let numbers = '';
            while (i + 1 < text.length && /[٠-٩]/.test(text[i + 1])) {
                i++;
                numbers += text[i];
            }
            clusters.push({ 
                text: '۝', 
                numbers: numbers,
                isSeparator: true,
                start: startIndex, 
                end: i + 1 
            });
            continue;
        } else {
            // Standard cluster grouping: base character + any combining marks
            while (i + 1 < text.length && /[\u0610-\u061A\u064B-\u065F\u0670\u06D6-\u06ED\u06DD]/.test(text[i + 1])) {
                i++;
                cluster += text[i];
            }
        }
        clusters.push({ text: cluster, start: startIndex, end: i + 1 });
    }
    return clusters;
});

const sourceCharacters = computed(() => visualMapping.value.logicText.split(''));
const typedCharacters = computed(() => userInput.value.split(''));

const wpm = computed(() => {
    if (timer.value === 0) return 0;
    // For WPM calculation, we use the simple text length as a stable baseline
    const baseLength = quranText.value.text_simple?.length || userInput.value.length;
    const wordCount = baseLength / 5;
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
    const totalKeystrokes = userInput.value.length + totalErrors.value;
    return Math.round((correctChars / totalKeystrokes) * 100);
});

const firstErrorIndex = computed(() => {
    for (let i = 0; i < typedCharacters.value.length; i++) {
        const typed = normalizeForComparison(typedCharacters.value[i]);
        const source = normalizeForComparison(sourceCharacters.value[i]);
        if (typed !== source) return i;
    }
    return -1;
});

const currentMaxAyahs = computed(() => {
    if (!surahs.value.length) return 0;
    const surah = surahs.value.find(s => s.surah_number == selectedSurah.value);
    return surah ? surah.total_ayahs : 0;
});

// Helpers for template rendering
const getClusterStatus = (cluster) => {
    const uLen = userInput.value.length;
    const errorIdx = firstErrorIndex.value;

    // Find the logic range this cluster covers
    let logicStart = -1;
    let logicEnd = -1;
    for (let i = cluster.start; i < cluster.end; i++) {
        const lIdx = visualMapping.value.vToL[i];
        if (lIdx !== -1) {
            if (logicStart === -1) logicStart = lIdx;
            logicEnd = lIdx;
        }
    }

    // Decorative clusters (separators)
    if (logicStart === -1) {
        const prevLogicIdx = visualMapping.value.vToL[cluster.start - 1];
        if (prevLogicIdx !== -1 && uLen > prevLogicIdx) return 'correct';
        return 'untyped';
    }

    // Active state: Where the cursor currently resides
    if (uLen >= logicStart && uLen <= logicEnd) {
        // If an error happened before this cluster, it remains untyped
        if (errorIdx !== -1 && errorIdx < logicStart) return 'untyped';
        return 'active';
    }

    // Typed state
    if (uLen > logicEnd) {
        // If an error exists at or before this cluster
        if (errorIdx !== -1 && errorIdx <= logicEnd) {
            return errorIdx < logicStart ? 'ignored-error' : 'incorrect';
        }
        return 'correct';
    }

    return 'untyped';
};

// --- Core Logic ---
const increaseStartAyah = () => {
    const max = currentMaxAyahs.value || 286; // Default to max surah size if not loaded
    const current = parseInt(startAyah.value) || 1;
    if (current < max) {
        startAyah.value = current + 1;
        if (startAyah.value > endAyah.value) endAyah.value = startAyah.value;
    }
};
const decreaseStartAyah = () => {
    const current = parseInt(startAyah.value) || 1;
    if (current > 1) startAyah.value = current - 1;
};
const increaseEndAyah = () => {
    const max = currentMaxAyahs.value || 286;
    const current = parseInt(endAyah.value) || 1;
    if (current < max) {
        endAyah.value = current + 1;
    }
};
const decreaseEndAyah = () => {
    const current = parseInt(endAyah.value) || 1;
    if (current > 1) {
        endAyah.value = current - 1;
        if (endAyah.value < startAyah.value) startAyah.value = endAyah.value;
    }
};

const validateAyahs = () => {
    const max = currentMaxAyahs.value;
    // Don't validate against 0 if surahs haven't loaded yet
    if (max <= 0) return;
    
    let start = parseInt(startAyah.value) || 1;
    let end = parseInt(endAyah.value) || 1;

    if (start < 1) start = 1;
    if (start > max) start = max;
    if (end < 1) end = 1;
    if (end > max) end = max;
    
    // Ensure end >= start
    if (end < start) {
        end = start;
    }

    startAyah.value = start;
    endAyah.value = end;
};

// Clear warnings when user changes selection
watch([selectedSurah, startAyah, endAyah], () => {
    warningMessage.value = '';
    rangeError.value = '';
});

// Automatically pick a random 3-ayah range when a new surah is manually selected
watch(selectedSurah, (newSurah, oldSurah) => {
    // Skip during initial load to preserve URL params
    if (isLoading.value || !surahs.value.length) return;
    
    if (newSurah && newSurah !== oldSurah) {
        const surah = surahs.value.find(s => s.surah_number == newSurah);
        if (surah) {
            const total = surah.total_ayahs;
            // Generate a random start ayah that allows for a 3-ayah range
            const maxStart = Math.max(1, total - 2);
            const randomStart = Math.floor(Math.random() * maxStart) + 1;
            
            startAyah.value = randomStart;
            endAyah.value = Math.min(total, randomStart + 2);
        }
    }
});

const fetchSurahs = async () => {
    try {
        const response = await axios.get('/api/surahs');
        surahs.value = response.data;
    } catch (error) {
        console.error('Failed to fetch Surahs:', error);
    }
};
const warningMessage = ref('');
const rangeError = ref('');

const fetchTestText = async (withParams = true) => {
    // Validate range values before request
    if (withParams && selectedSurah.value) {
        const surah = surahs.value.find(s => s.surah_number == selectedSurah.value);
        if (surah) {
            // Clamp to valid bounds
            const max = surah.total_ayahs;
            let start = parseInt(startAyah.value) || 1;
            let end = parseInt(endAyah.value) || 1;

            start = Math.max(1, Math.min(start, max));
            end = Math.max(1, Math.min(end, max));

            if (start > end) end = start;

            startAyah.value = start;
            endAyah.value = end;
        }
    }

    // Reset warnings
    warningMessage.value = '';
    rangeError.value = '';

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
        // Update URL to reflect current test
        const url = new URL(window.location);
        url.searchParams.set('surah', selectedSurah.value);
        url.searchParams.set('start', startAyah.value);
        url.searchParams.set('end', endAyah.value);
        window.history.pushState({}, '', url);
        resetTest();
    } catch (error) {
        console.error("Failed to fetch test text:", error);
        if (error.response?.status === 400 && error.response?.data?.message) {
            // Determine if it's a range issue or word count issue
            if (error.response.data.message.includes('range')) {
                rangeError.value = t('range_error');
            } else {
                // If text is too short during random selection, try again
                if (!withParams) {
                    fetchTestText(false);
                } else {
                    warningMessage.value = t('text_too_short');
                }
            }
        } else if (error.response?.status === 404) {
            // If 404, the range was likely invalid, recover by fetching random
            fetchTestText(false);
        }
    } finally {
        isLoading.value = false;
    }
};

const handleInput = (event) => {
    if (testFinished.value || isLoading.value) return;
    
    // Activity tracking for caret blink
    isTyping.value = true;
    if (typingTimeout) clearTimeout(typingTimeout);
    typingTimeout = setTimeout(() => {
        isTyping.value = false;
    }, 1000);

    if (!intervalId.value) startTimer();
    
    const newValue = event.target.value.normalize('NFC');
    
    // Detect non-Arabic characters (Latin) to show warning
    const lastChar = newValue[newValue.length - 1];
    if (lastChar && /[a-zA-Z]/.test(lastChar)) {
        showLanguageWarning.value = true;
        // Don't process Latin characters - keep the input as is or strip it?
        // Let's just show the warning for now.
    } else if (lastChar) {
        showLanguageWarning.value = false;
    }

    const addedCount = newValue.length - userInput.value.length;
    
    // Set active key for keyboard animation
    if (addedCount > 0) {
        const char = newValue[newValue.length - 1];
        activeKey.value = (char === ' ') ? 'Space' : char;
        
        // activeCode is set in handleGlobalKeydown
        setTimeout(() => {
            if (activeKey.value === char || activeKey.value === 'Space') {
                activeKey.value = null;
                activeCode.value = null;
            }
        }, 150);

        // Count errors for all newly added characters
        for (let i = 0; i < addedCount; i++) {
            const index = userInput.value.length + i;
            if (index < sourceCharacters.value.length) {
                const typedChar = newValue[index];
                const expectedChar = sourceCharacters.value[index];
                if (normalizeForComparison(typedChar) !== normalizeForComparison(expectedChar)) {
                    totalErrors.value++;
                    playErrorSound();
                }
            }
        }
    }

    userInput.value = newValue;
    // Only finish if the length matches and there are no active errors (100% correct text)
    if (userInput.value.length >= sourceCharacters.value.length && firstErrorIndex.value === -1) {
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

        const testData = {
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
        };

        if (!page.props.auth?.user) {
            localStorage.setItem('cached_typing_test', JSON.stringify(testData));
            setTimeout(() => {
                showGuestModal.value = true;
            }, 1000);
        }

        await axios.post('/test/complete', testData);
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
        focusInput();
    }, 100);
};

const focusInput = () => {
    document.getElementById('hidden-input')?.focus();
};

const handleFocus = () => isFocused.value = true;
const handleBlur = () => isFocused.value = false;

// Global Tab handler for restart
const handleGlobalKeydown = (e) => {
    activeCode.value = e.code;
    if (e.shiftKey) isShiftPressed.value = true;
    
    if (e.key === 'Tab') {
        e.preventDefault();
        resetTest();
    }

    if (e.key === 'Escape') {
        e.preventDefault();
        resetTest();
    }
};

const handleGlobalKeyup = (e) => {
    if (e.key === 'Shift') isShiftPressed.value = false;
    if (activeCode.value === e.code) {
        activeCode.value = null;
        activeKey.value = null;
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
    window.addEventListener('keyup', handleGlobalKeyup);
    
    // Initialize caret position
    setTimeout(updateCaret, 500);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
    window.removeEventListener('keyup', handleGlobalKeyup);
});

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head>
        <title>Quran Typing Test - Speed & Memorization | QuranTyping</title>
        <meta name="description" content="Test your Quranic typing speed and accuracy. Practice memorization by typing surahs in Arabic with real-time feedback.">
    </Head>

    <div class="flex flex-col items-center justify-start py-8 px-4 md:px-0 min-h-[80vh]">
        <!-- Minimalist Filters -->
        <form @submit.prevent="fetchTestText" class="w-full max-w-6xl mb-2 flex flex-wrap items-center justify-center md:justify-start gap-4 md:gap-6 font-mono text-sm max-w-full">
            <SurahSelect 
                v-model="selectedSurah" 
                :options="surahs" 
                :label="t('surah')"
                :placeholder="t('select_surah') || 'Select Surah'"
            />
            <div class="flex items-center gap-3 bg-[var(--panel-color)] px-4 py-2 rounded-xl backdrop-blur-md border border-[var(--border-color)]">
                <span class="text-[var(--caret-color)] opacity-60 font-cinzel text-xs uppercase tracking-widest">{{ t('ayats') }}</span>
                <div class="flex items-center gap-2">
                    <!-- Start Ayah Stepper -->
                    <button type="button" @click="decreaseStartAyah" class="w-6 h-6 flex items-center justify-center bg-[var(--panel-color)] border border-[var(--border-color)] rounded-full text-[var(--main-color)] hover:bg-[var(--caret-color)] hover:text-[var(--bg-color)] transition-colors">
                        -
                    </button>
                    <input 
                        v-model.number="startAyah" 
                        type="number" 
                        min="1" 
                        :max="currentMaxAyahs"
                        @change="validateAyahs"
                        class="w-10 text-center font-bold text-[var(--main-color)] bg-transparent border-none focus:ring-0 p-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                    />
                    <button type="button" @click="increaseStartAyah" class="w-6 h-6 flex items-center justify-center bg-[var(--panel-color)] border border-[var(--border-color)] rounded-full text-[var(--main-color)] hover:bg-[var(--caret-color)] hover:text-[var(--bg-color)] transition-colors">
                        +
                    </button>
                    <span class="text-[var(--sub-color)] opacity-40">-</span>
                    <!-- End Ayah Stepper -->
                    <button type="button" @click="decreaseEndAyah" class="w-6 h-6 flex items-center justify-center bg-[var(--panel-color)] border border-[var(--border-color)] rounded-full text-[var(--main-color)] hover:bg-[var(--caret-color)] hover:text-[var(--bg-color)] transition-colors">
                        -
                    </button>
                    <input 
                        v-model.number="endAyah" 
                        type="number" 
                        min="1" 
                        :max="currentMaxAyahs"
                        @change="validateAyahs"
                        class="w-10 text-center font-bold text-[var(--main-color)] bg-transparent border-none focus:ring-0 p-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                    />
                    <button type="button" @click="increaseEndAyah" class="w-6 h-6 flex items-center justify-center bg-[var(--panel-color)] border border-[var(--border-color)] rounded-full text-[var(--main-color)] hover:bg-[var(--caret-color)] hover:text-[var(--bg-color)] transition-colors">
                        +
                    </button>
                    <span class="text-[10px] opacity-40 font-mono text-[var(--sub-color)] ml-2">/ {{ currentMaxAyahs }}</span>
                </div>
            </div>
            <div v-if="warningMessage" class="text-[var(--error-color)] font-bold mb-2 text-center">
                {{ warningMessage }}
            </div>
            <div v-else-if="rangeError" class="text-[var(--error-color)] font-bold mb-2 text-center">
                {{ rangeError }}
            </div>
            <button type="submit" :disabled="!!warningMessage || !!rangeError" class="bg-[var(--caret-color)] text-[var(--bg-color)] px-6 py-2 rounded-xl font-cinzel font-bold hover:scale-105 active:scale-95 transition-all shadow-lg shadow-emerald-950/20 disabled:opacity-50 disabled:cursor-not-allowed">
                {{ t('start_testing') }}
            </button>
            <button type="button" @click="fetchTestText(false)" class="bg-[var(--panel-color)] text-[var(--caret-color)] border border-[var(--border-color)] px-6 py-2 rounded-xl font-cinzel text-xs hover:bg-[var(--caret-color)]/[0.05] transition-all font-bold uppercase tracking-widest">
                {{ t('random') }}
            </button>

            

            <!-- Punctuation Toggle -->
            <button v-if="showTashkilFeature"
                    type="button" 
                    @click="setPunctuation(!usePunctuation); resetTest()"
                    class="flex items-center gap-2 bg-[var(--panel-color)] border border-[var(--border-color)] px-4 py-2 rounded-xl text-xs font-mono uppercase tracking-widest transition-all"
                    :class="usePunctuation ? 'text-[var(--caret-color)] border-[var(--caret-color)]/40 shadow-lg shadow-emerald-950/10' : 'text-[var(--sub-color)] opacity-60 hover:opacity-100'">
                <span class="text-lg">{{ usePunctuation ? '✨' : '📝' }}</span>
                {{ t(usePunctuation ? 'tashkeel_on' : 'tashkeel_off') }}
            </button>

        </form>

        <!-- Live Stats (during test) -->
        <div v-if="!showResults" class="w-full max-w-6xl mb-2 flex flex-wrap gap-4 justify-between md:justify-start md:gap-12 font-cinzel text-sm md:text-xl text-[var(--caret-color)] select-none px-2 md:px-0">
            <div class="flex flex-col">
                <span class="text-[10px] text-[var(--sub-color)] uppercase tracking-[0.2em] mb-1 font-mono">{{ t('wpm') }}</span>
                <span class="font-bold border-b border-[var(--border-color)] pb-1">{{ wpm }}</span>
            </div>
            <div class="flex flex-col">
                <span class="text-[10px] text-[var(--sub-color)] uppercase tracking-[0.2em] mb-1 font-mono">{{ t('accuracy') }}</span>
                <span class="font-bold border-b border-[var(--border-color)] pb-1">{{ accuracy }}%</span>
            </div>
            <div class="flex flex-col">
                <span class="text-[10px] text-[var(--sub-color)] uppercase tracking-[0.2em] mb-1 font-mono">{{ t('time') }}</span>
                <span class="font-bold border-b border-[var(--border-color)] pb-1">{{ timer }}s</span>
            </div>
            <div class="flex flex-col">
                <span class="text-[10px] text-[var(--sub-color)] uppercase tracking-[0.2em] mb-1 font-mono">Errors</span>
                <span class="font-bold text-[var(--error-color)] border-b border-[var(--error-color)]/20 pb-1">{{ totalErrors }}</span>
            </div>

             <!-- Error Sound Toggle (Right Float) -->
             <button type="button" 
                    @click="toggleErrorSound"
                    class="ml-auto relative group flex items-center gap-2 bg-[var(--panel-color)] border border-[var(--border-color)] px-4 py-2 rounded-xl text-xs font-mono uppercase tracking-widest transition-all self-center"
                    :class="errorSoundEnabled ? 'text-[var(--caret-color)] border-[var(--caret-color)]/40 shadow-lg shadow-emerald-950/10' : 'text-[var(--sub-color)] opacity-60 hover:opacity-100'">
                <span class="text-lg">{{ errorSoundEnabled ? '🔊' : '🔇' }}</span>
                {{ t('sound_label') }}
                
                <!-- Tooltip -->
                <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-3 px-3 py-1.5 bg-[var(--bg-color)] border border-[var(--border-color)] text-[var(--main-color)] text-[10px] rounded-lg shadow-xl opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-50 font-sans normal-case tracking-normal backdrop-blur-md">
                    {{ t('error_sound_tooltip') }}
                    <span class="absolute top-full left-1/2 -translate-x-1/2 -mt-1 border-4 border-transparent border-t-[var(--border-color)]"></span>
                </span>
            </button>

            <!-- Audio Player
            <QuranAudioPlayer 
                v-if="!showResults"
                :surah-number="quranText.surah_number"
                :start-ayah="quranText.start_ayah"
                :end-ayah="quranText.end_ayah"
                :is-test-started="isTyping"
            /> -->

        </div>

        <!-- Typing Area -->
        <div v-if="currentDisplayText && !showResults" 
             @click="focusInput" 
             ref="containerRef"
             class="relative w-full max-w-6xl py-2 transition-all duration-500 min-h-[200px] flex items-center"
             :class="{ 'opacity-100': isFocused, 'opacity-40 blur-[4px] scale-[0.98]': !isFocused }">
            
            <!-- Language Switching Warning -->
            <transition name="fade">
                <div v-if="showLanguageWarning && isFocused" class="absolute inset-x-0 top-0 z-[100] flex justify-center -translate-y-1/2">
                    <div class="bg-red-500/90 text-white px-8 py-4 rounded-2xl backdrop-blur-xl border border-white/20 shadow-2xl flex items-center gap-4 animate-bounce">
                        <span class="text-3xl">⌨️</span>
                        <div class="flex flex-col text-left">
                            <span class="font-cinzel font-bold text-lg leading-tight">{{ t('switch_to_arabic') }}</span>
                            <span class="text-[10px] opacity-80 uppercase tracking-widest font-mono">English layout detected</span>
                        </div>
                        <button @click="showLanguageWarning = false" class="ml-4 opacity-60 hover:opacity-100 transition-opacity">✕</button>
                    </div>
                </div>
            </transition>
            
            <!-- Smooth Sliding Underline Caret -->
            <div class="absolute bg-[var(--caret-color)] transition-all duration-150 z-[60] pointer-events-none rounded-full"
                 :class="{ 'caret-blink-anim': !isTyping && isFocused }"
                 :style="{ 
                     transitionTimingFunction: 'cubic-bezier(0.19, 1, 0.22, 1)',
                     top: caretPosition.top + 'px',
                     left: caretPosition.left + 'px',
                     width: caretPosition.width + 'px',
                     height: caretPosition.height + 'px',
                     opacity: isFocused ? caretPosition.opacity : 0,
                     backgroundColor: firstErrorIndex === -1 ? '#10b981' : '#ff3131',
                     boxShadow: firstErrorIndex === -1 
                        ? '0 0 25px 3px #10b981, 0 0 10px #10b981, 0 0 2px white' 
                        : '0 0 25px 3px #ff3131, 0 0 10px #ff3131, 0 0 2px white',
                     border: '1px solid rgba(255,255,255,0.8)',
                 }">
            </div>
            
            <!-- Focus Message -->
            <div v-if="!isFocused" class="absolute inset-0 z-30 flex flex-col items-center justify-center cursor-pointer">
                <div class="bg-[var(--panel-color)] px-10 py-5 rounded-2xl backdrop-blur-xl border border-[var(--border-color)] shadow-2xl overflow-hidden relative group">
                    <div class="absolute inset-0 bg-[var(--caret-color)] opacity-[0.03] translate-y-full group-hover:translate-y-0 transition-transform duration-500"></div>
                    <p class="text-sm font-cinzel text-[var(--caret-color)] animate-pulse flex items-center gap-3 relative z-10">
                        <span class="text-xl">🌙</span> {{ t('click_to_focus') }}
                    </p>
                </div>
            </div>

            <div v-if="isLoading" class="absolute inset-0 bg-[var(--bg-color)]/80 backdrop-blur-sm flex items-center justify-center z-30 transition-all rounded-2xl">
                <div class="flex flex-col items-center gap-3">
                    <div class="w-12 h-12 border-4 border-[var(--caret-color)] border-t-transparent rounded-full animate-spin"></div>
                    <p class="text-xs font-cinzel text-[var(--caret-color)] uppercase tracking-widest">{{ t('loading') }}</p>
                </div>
            </div>

            <div class="text-2xl md:text-4xl lg:text-5xl select-none text-right w-full transition-all duration-300" 
                 :style="{ 
                     fontFamily: 'Noto Naskh Arabic, serif', 
                 }" 
                 :class="[
                     usePunctuation ? 'py-4 leading-[4rem] md:leading-[6.5rem]' : 'py-2 leading-[3rem] md:leading-[4.5rem]'
                 ]"
                 dir="rtl">
                <p class="relative z-0 whitespace-pre-wrap break-words transition-all duration-300">
                    <span v-for="(cluster, idx) in sourceClusters" :key="idx" 
                          :class="[
                              getClusterStatus(cluster) === 'correct' ? 'text-[var(--main-color)]' : '',
                              getClusterStatus(cluster) === 'incorrect' ? 'text-[var(--error-color)] bg-[var(--error-color)]/5' : '',
                              getClusterStatus(cluster) === 'untyped' ? 'text-[var(--sub-color)]' : '',
                              getClusterStatus(cluster) === 'active' ? 'text-[var(--sub-color)] cluster-active' : '',
                              getClusterStatus(cluster) === 'ignored-error' ? 'text-[var(--sub-color)] opacity-50' : '',
                              cluster.isSeparator ? 'ayah-ornament' : ''
                          ]">
                        <template v-if="cluster.isSeparator">
                            <span class="ornament-wrap">
                                <span class="ornament-char">{{ cluster.text }}</span>
                                <span class="ornament-num">{{ cluster.numbers }}</span>
                            </span>
                        </template>
                        <template v-else>
                            {{ cluster.text }}
                        </template>
                    </span>
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

        <!-- Animated Keyboard -->
        <ArabicKeyboard v-if="currentDisplayText && !showResults" 
                        class="hidden lg:block"
                        :active-key="activeKey" 
                        :active-code="activeCode"
                        :is-shift-on="isShiftPressed"
                        :has-error="firstErrorIndex !== -1"
                        :next-key="sourceCharacters[userInput.length]" />

        <!-- Results View -->
        <div v-if="showResults" class="w-full max-w-6xl flex flex-col items-center justify-center py-20 animate-fade-in font-cinzel">
            <div class="flex flex-col md:flex-row gap-8 md:gap-20 mb-16 items-center">
                <div class="flex flex-col items-center group">
                    <span class="text-xs text-[var(--sub-color)] font-mono uppercase tracking-[0.3em] mb-4 group-hover:text-[var(--caret-color)] transition-colors">{{ t('wpm') }}</span>
                    <span class="text-6xl md:text-8xl text-[var(--caret-color)] font-bold drop-shadow-sm">{{ wpm }}</span>
                </div>
                <div class="flex flex-col items-center group">
                    <span class="text-xs text-[var(--sub-color)] font-mono uppercase tracking-[0.3em] mb-4 group-hover:text-[var(--caret-color)] transition-colors">{{ t('accuracy') }}</span>
                    <span class="text-6xl md:text-8xl text-[var(--caret-color)] font-bold drop-shadow-sm">{{ accuracy }}%</span>
                </div>
                <div class="flex flex-col items-center group">
                    <span class="text-xs text-[var(--sub-color)] font-mono uppercase tracking-[0.3em] mb-4 group-hover:text-[var(--error-color)] transition-colors">Errors</span>
                    <span class="text-6xl md:text-8xl text-[var(--error-color)] font-bold drop-shadow-sm">{{ totalErrors }}</span>
                </div>
            </div>
            
            <div class="flex flex-col items-center gap-10">
                <p class="text-2xl text-[var(--main-color)] opacity-60 font-medium">
                    {{ accuracy === 100 ? t('perfect') : (accuracy > 90 ? t('excellent') : t('keep_practicing')) }}
                </p>
                
                <div class="flex items-center gap-8">
                    <button @click="resetTest" class="group flex flex-col items-center gap-4 transition-all">
                        <div class="p-6 rounded-2xl bg-[var(--panel-color)] border border-[var(--border-color)] group-hover:bg-[var(--caret-color)] group-hover:text-[var(--bg-color)] transition-all transform group-hover:rotate-12 group-hover:scale-110 shadow-xl shadow-black/20">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path><path d="M21 3v5h-5"></path><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path><path d="M8 16H3v5"></path></svg>
                        </div>
                        <span class="text-xs font-mono text-[var(--sub-color)] uppercase tracking-widest">{{ t('restart_hint') }}</span>
                    </button>

                    <a href="https://buy.stripe.com/dRmdRa1546e60jI2jZenS01" target="_blank" class="group flex flex-col items-center gap-4 transition-all">
                        <div class="p-6 rounded-2xl bg-[var(--panel-color)] border border-[var(--border-color)] group-hover:bg-emerald-600 group-hover:text-white transition-all transform group-hover:-rotate-12 group-hover:scale-110 shadow-xl shadow-black/20">
                            <span class="text-4xl">❤️</span>
                        </div>
                        <span class="text-xs font-mono text-[var(--sub-color)] uppercase tracking-widest">{{ t('donate') }}</span>
                    </a>
                </div>
            </div>
        </div>

        <GuestTestModal :show="showGuestModal" @close="showGuestModal = false" />

        <!-- Footer Context Info -->
        <div v-if="!showResults && quranText.surah_name_arabic" class="mt-auto py-6 md:py-12 text-[var(--sub-color)] font-mono text-xs flex flex-col md:flex-row gap-4 md:gap-8 items-center bg-[var(--panel-color)] px-8 rounded-2xl md:rounded-full border border-white/5 backdrop-blur-md opacity-60 hover:opacity-100 transition-opacity text-center md:text-left">
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

.ayah-ornament {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    vertical-align: middle;
    color: var(--caret-color);
    opacity: 0.8;
    margin: 0 0.5rem;
    position: relative;
    user-select: none;
    transition: all 0.3s ease;
}

.ornament-wrap {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

.ornament-char {
    font-size: 1.2em;
    line-height: 1;
}

.ornament-num {
    position: absolute;
    font-family: 'Noto Naskh Arabic', serif;
    font-size: 0.35em;
    font-weight: bold;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: var(--caret-color);
}

.ayah-ornament:hover {
    opacity: 1;
    text-shadow: 0 0 10px var(--caret-color);
}

.caret-blink-anim {
    animation: caret-pulse 1.5s ease-in-out infinite;
}

@keyframes caret-pulse {
    0%, 100% { opacity: 1; filter: brightness(1); }
    50% { opacity: 0.4; filter: brightness(1.2); }
}

/* Smooth character color transitions */
.cluster-active {
    position: relative;
}

span {
    transition: color 0.2s ease, border-color 0.2s ease, opacity 0.2s ease;
}
</style>
