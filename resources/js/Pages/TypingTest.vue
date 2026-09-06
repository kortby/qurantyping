<script setup>
import { ref, onMounted, computed, onUnmounted, watch } from 'vue';
import { usePage, Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import confetti from 'canvas-confetti';
import AppLayout from '@/Layouts/AppLayout.vue';
import ArabicKeyboard from '@/Components/ArabicKeyboard.vue';
import PassageSelect from '@/Components/PassageSelect.vue';
import QuranAudioPlayer from '@/Components/QuranAudioPlayer.vue';
import GuestTestModal from '@/Components/GuestTestModal.vue';
import LunarCountdown from '@/Components/LunarCountdown.vue';
import { useSettings } from '../useSettings';

const activeKey = ref(null);
const activeCode = ref(null);

const { t, currentLang, usePunctuation, setPunctuation } = useSettings();
const page = usePage();

const showTashkilFeature = computed(() => page.props.features?.tashkil ?? false);

const props = defineProps({
    personalBestWpm: Number,
    contestConfig: Object,
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
const charStats = ref(new Map());
const isDrill = ref(false);
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

const logicCharacterCount = computed(() => visualMapping.value.logicText.length);

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

// Pick a random 3-ayah range only when the user actively chooses a surah from
// the dropdown. Wiring this to the select's event (instead of watching
// selectedSurah) keeps it from firing on URL-param loads or when fetchTestText
// syncs selectedSurah back from the server response — both of which would
// otherwise clobber a "retake" range.
const handleSurahSelected = (newSurah) => {
    if (!newSurah || !surahs.value.length) return;

    const surah = surahs.value.find(s => s.surah_number == newSurah);
    if (!surah) return;

    const total = surah.total_ayahs;
    const maxStart = Math.max(1, total - 2);
    const randomStart = Math.floor(Math.random() * maxStart) + 1;

    startAyah.value = randomStart;
    endAyah.value = Math.min(total, randomStart + 2);
};

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

// The passage scope currently driving the selector: 'surah' | 'juz' | 'page'.
const scope = ref('surah');

const applyPassage = (data) => {
    quranText.value = data;
    selectedSurah.value = data.surah_number;
    startAyah.value = data.start_ayah;
    endAyah.value = data.end_ayah;

    const url = new URL(window.location);
    ['scope', 'value', 'after'].forEach(k => url.searchParams.delete(k));
    url.searchParams.set('surah', data.surah_number);
    url.searchParams.set('start', data.start_ayah);
    url.searchParams.set('end', data.end_ayah);
    window.history.pushState({}, '', url);

    resetTest();
};

/**
 * opts: {} / true          → current sūrah + ayah range
 *       false / {random}    → a random passage
 *       { scope, value }    → structured navigation (juz' / page)
 *       { after }           → the passage after a given ayah (resume / advance)
 */
const fetchTestText = async (opts = {}) => {
    if (opts === false) opts = { random: true };
    if (opts === true) opts = {};

    warningMessage.value = '';
    rangeError.value = '';

    let params;
    if (opts.after) {
        params = { after: opts.after };
    } else if (opts.scope && opts.scope !== 'surah') {
        params = { scope: opts.scope, value: opts.value };
    } else if (opts.random) {
        params = {};
    } else {
        const surah = surahs.value.find(s => s.surah_number == selectedSurah.value);
        if (surah) {
            const max = surah.total_ayahs;
            let start = Math.max(1, Math.min(parseInt(startAyah.value) || 1, max));
            let end = Math.max(1, Math.min(parseInt(endAyah.value) || 1, max));
            if (start > end) end = start;
            startAyah.value = start;
            endAyah.value = end;
        }
        params = { surah_number: selectedSurah.value, start_ayah: startAyah.value, end_ayah: endAyah.value };
    }

    try {
        const response = await axios.get('/api/test/text', { params });
        applyPassage(response.data);
    } catch (error) {
        const status = error.response?.status;
        const message = error.response?.data?.message || '';

        if (status === 409 || status === 422) {
            warningMessage.value = message || t('range_error');
        } else if (status === 400 && message) {
            if (message.toLowerCase().includes('word')) {
                if (opts.scope || opts.after || opts.random) fetchTestText({ random: true });
                else warningMessage.value = t('text_too_short');
            } else {
                rangeError.value = t('range_error');
            }
        } else if (status === 404) {
            fetchTestText({ random: true });
        } else {
            console.error('Failed to fetch test text:', error);
        }
    } finally {
        isLoading.value = false;
    }
};

const autoAdvance = ref(page.props.auth?.user?.auto_advance ?? false);

const toggleAutoAdvance = () => {
    autoAdvance.value = !autoAdvance.value;
    if (page.props.auth?.user) {
        router.post(route('user.settings.auto-advance'), { enabled: autoAdvance.value }, {
            preserveScroll: true,
            preserveState: true,
        });
    }
};

const nextPassage = () => {
    if (quranText.value?.last_quran_text_id) {
        fetchTestText({ after: quranText.value.last_quran_text_id });
    }
};

const resumePractice = () => {
    const resume = page.props.auth?.resume;
    if (resume?.after) fetchTestText({ after: resume.after });
};

/* ---------- Hifz mode ---------- */
const hifzMode = ref(false);
const hifzSessionMode = ref('due');   // 'due' | 'new'
const revealLevel = ref(2);           // 1 guided · 2 faint · 3 blind
const peeking = ref(false);
const peeks = ref(0);
const lastTestId = ref(null);
const hifzMessage = ref('');

const effectiveLevel = computed(() => (peeking.value ? 1 : revealLevel.value));

const revealClass = (cluster) => {
    if (!hifzMode.value || effectiveLevel.value === 1 || cluster.isSeparator) return '';
    const status = getClusterStatus(cluster);
    if (status === 'correct' || status === 'incorrect') return '';
    return effectiveLevel.value === 2 ? 'hz-faint' : 'hz-blind';
};

const startPeek = () => { peeking.value = true; };
const endPeek = () => {
    if (peeking.value) { peeking.value = false; peeks.value++; }
};

const suggestedGrade = computed(() => {
    const words = (currentDisplayText.value || '').split(/\s+/).filter(Boolean).length;
    const budget = Math.max(1, Math.floor(words / 2));
    if (accuracy.value < 80 || peeks.value > budget) return 0;
    if (accuracy.value < 92 || peeks.value >= 3) return 1;
    if (accuracy.value < 98 || peeks.value >= 1) return 2;
    return 3;
});

const gradeLabels = () => [t('hifz.again'), t('hifz.hard'), t('hifz.good'), t('hifz.easy')];

const loadHifzSession = async (mode) => {
    hifzMode.value = true;
    hifzSessionMode.value = mode;
    hifzMessage.value = '';
    peeks.value = 0;
    try {
        const { data } = await axios.get('/hifz/session', { params: { mode } });
        scope.value = 'surah';
        selectedSurah.value = data.surah_number;
        startAyah.value = data.start_ayah;
        endAyah.value = data.end_ayah;
        await fetchTestText();
    } catch (e) {
        hifzMessage.value = e.response?.data?.message || t('hifz.load_failed');
        isLoading.value = false;
    }
};

const submitGrade = async (grade) => {
    if (!lastTestId.value) return;
    try {
        await axios.post('/hifz/grade', { test_id: lastTestId.value, grade });
    } catch (e) {
        // fall through to loading the next passage regardless
    }
    lastTestId.value = null;
    await loadHifzSession(hifzSessionMode.value);
    if (hifzMessage.value) router.visit('/hifz');
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
                const miss = normalizeForComparison(typedChar) !== normalizeForComparison(expectedChar);

                const stat = charStats.value.get(expectedChar) ?? { attempts: 0, misses: 0 };
                stat.attempts++;
                if (miss) {
                    stat.misses++;
                    totalErrors.value++;
                    playErrorSound();
                }
                charStats.value.set(expectedChar, stat);
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

        if (hifzMode.value) {
            testData.hifz_level = revealLevel.value;
            testData.peeks = peeks.value;
        }

        if (charStats.value.size > 0) {
            testData.char_stats = [...charStats.value].map(([c, s]) => ({
                c,
                attempts: s.attempts,
                misses: s.misses,
            }));
        }

        if (!page.props.auth?.user) {
            localStorage.setItem('cached_typing_test', JSON.stringify(testData));
            setTimeout(() => {
                showGuestModal.value = true;
            }, 1000);
        }

        const { data } = await axios.post('/test/complete', testData);
        lastTestId.value = data?.id ?? null;
    } catch (error) {
        console.error("Failed to save test result:", error);
    }
};

const resetTest = () => {
    stopTimer();
    userInput.value = '';
    timer.value = 0;
    totalErrors.value = 0;
    charStats.value = new Map();
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
    if (urlParams.get('hifz') === '1') {
        await loadHifzSession(urlParams.get('mode') === 'new' ? 'new' : 'due');
    } else if (urlParams.get('drill') === '1') {
        isDrill.value = true;
        try {
            const { data } = await axios.get('/test/drill');
            applyPassage(data);
        } catch {
            isDrill.value = false;
            await fetchTestText(false);
        }
    } else if (urlParams.has('after')) {
        await fetchTestText({ after: parseInt(urlParams.get('after')) });
    } else if (urlParams.has('scope') && urlParams.get('scope') !== 'surah') {
        scope.value = urlParams.get('scope');
        await fetchTestText({ scope: urlParams.get('scope'), value: parseInt(urlParams.get('value')) || 1 });
    } else if (urlParams.has('surah')) {
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

    <div class="flex flex-col items-center justify-start py-8 px-6 md:px-8 lg:px-0 min-h-[80vh]">
        <!-- Global Ramadan Countdown -->
        <LunarCountdown v-if="contestConfig?.enabled" :config="contestConfig" />

        <!-- Passage selectors -->
        <form @submit.prevent="fetchTestText()" class="w-full max-w-6xl mb-4 flex flex-wrap items-stretch sm:items-center gap-2 sm:gap-3 font-mono text-sm">
            <PassageSelect
                class="w-full sm:w-auto"
                :surah="selectedSurah"
                :surahs="surahs"
                :label="t('surah')"
                :placeholder="t('select_surah') || 'Select Surah'"
                @update:surah="v => selectedSurah = v"
                @surah-picked="handleSurahSelected"
                @scope-change="s => scope = s"
                @select="opts => fetchTestText(opts)"
            />
            <div v-if="scope === 'surah'" class="w-full sm:w-auto flex items-center justify-center gap-2 px-3 py-1.5 border border-[var(--border-color)]">
                <span class="text-[var(--sub-color)] text-[10px] uppercase tracking-[0.2em] hidden sm:inline">{{ t('ayats') }}</span>
                <button type="button" @click="decreaseStartAyah" aria-label="Start ayah down" class="w-9 h-9 shrink-0 flex items-center justify-center border border-[var(--border-color)] text-lg text-[var(--main-color)] hover:border-[var(--caret-color)] hover:text-[var(--caret-color)] transition-colors">−</button>
                <input
                    v-model.number="startAyah"
                    type="number" inputmode="numeric" min="1" :max="currentMaxAyahs"
                    @change="validateAyahs"
                    aria-label="Start ayah"
                    class="w-10 text-center font-bold text-[var(--main-color)] bg-transparent border-none focus:ring-0 p-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                />
                <button type="button" @click="increaseStartAyah" aria-label="Start ayah up" class="w-9 h-9 shrink-0 flex items-center justify-center border border-[var(--border-color)] text-lg text-[var(--main-color)] hover:border-[var(--caret-color)] hover:text-[var(--caret-color)] transition-colors">+</button>
                <span class="text-[var(--sub-color)] opacity-40 px-1">–</span>
                <button type="button" @click="decreaseEndAyah" aria-label="End ayah down" class="w-9 h-9 shrink-0 flex items-center justify-center border border-[var(--border-color)] text-lg text-[var(--main-color)] hover:border-[var(--caret-color)] hover:text-[var(--caret-color)] transition-colors">−</button>
                <input
                    v-model.number="endAyah"
                    type="number" inputmode="numeric" min="1" :max="currentMaxAyahs"
                    @change="validateAyahs"
                    aria-label="End ayah"
                    class="w-10 text-center font-bold text-[var(--main-color)] bg-transparent border-none focus:ring-0 p-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                />
                <button type="button" @click="increaseEndAyah" aria-label="End ayah up" class="w-9 h-9 shrink-0 flex items-center justify-center border border-[var(--border-color)] text-lg text-[var(--main-color)] hover:border-[var(--caret-color)] hover:text-[var(--caret-color)] transition-colors">+</button>
                <span class="text-[10px] opacity-40 text-[var(--sub-color)] hidden sm:inline">/ {{ currentMaxAyahs }}</span>
            </div>

            <button type="submit" :disabled="!!warningMessage || !!rangeError" class="flex-1 sm:flex-none min-h-[40px] bg-[var(--caret-color)] text-[var(--bg-color)] px-6 font-cinzel font-semibold hover:opacity-90 transition-opacity disabled:opacity-40 disabled:cursor-not-allowed">
                {{ t('start_testing') }}
            </button>
            <button type="button" @click="fetchTestText(false)" class="min-h-[40px] text-[var(--sub-color)] border border-[var(--border-color)] px-5 font-cinzel text-xs hover:text-[var(--main-color)] hover:border-[var(--caret-color)] transition-colors uppercase tracking-[0.12em]">
                {{ t('random') }}
            </button>

            <button
                v-if="page.props.auth?.resume"
                type="button"
                @click="resumePractice"
                class="min-h-[40px] border border-[var(--lapis-color)] text-[var(--lapis-color)] px-4 font-cinzel text-xs uppercase tracking-[0.12em] hover:opacity-80 transition-opacity"
            >
                {{ t('passage.continue') }} · {{ page.props.auth.resume.label }}
            </button>

            <button
                v-if="page.props.auth?.user"
                type="button"
                @click="toggleAutoAdvance"
                :title="autoAdvance ? 'Auto-advance to the next passage is on' : 'Turn on auto-advance'"
                class="min-h-[40px] border px-4 text-xs font-mono uppercase tracking-[0.12em] transition-colors"
                :class="autoAdvance ? 'text-[var(--caret-color)] border-[var(--caret-color)]' : 'text-[var(--sub-color)] border-[var(--border-color)] hover:text-[var(--main-color)]'"
            >
                {{ autoAdvance ? t('passage.auto_advance_on') : t('passage.auto_advance_off') }}
            </button>

            <button v-if="showTashkilFeature"
                    type="button"
                    @click="setPunctuation(!usePunctuation); resetTest()"
                    class="min-h-[40px] border border-[var(--border-color)] px-4 text-xs font-mono uppercase tracking-[0.12em] transition-colors"
                    :class="usePunctuation ? 'text-[var(--caret-color)] border-[var(--caret-color)]' : 'text-[var(--sub-color)] hover:text-[var(--main-color)]'">
                {{ t(usePunctuation ? 'tashkeel_on' : 'tashkeel_off') }}
            </button>

            <div v-if="logicCharacterCount > 0"
                 class="min-h-[40px] flex items-center border px-4 text-xs font-mono uppercase tracking-[0.12em] animate-fade-in"
                 :class="logicCharacterCount >= (contestConfig?.min_char_count || 100)
                    ? 'border-[var(--caret-color)] text-[var(--caret-color)]'
                    : 'border-[var(--border-color)] text-[var(--sub-color)]'">
                {{ logicCharacterCount }} {{ t('chars') }}
            </div>

            <div v-if="warningMessage || rangeError" class="w-full text-[var(--error-color)] text-xs text-center sm:text-left">
                {{ warningMessage || rangeError }}
            </div>
        </form>

        <!-- Live Stats (during test) -->
        <div v-if="!showResults" class="w-full max-w-6xl mb-3 flex items-center gap-4">
            <!-- Mobile: one compact line -->
            <div class="sm:hidden flex items-center gap-3 font-mono text-sm tabular-nums text-[var(--main-color)] select-none">
                <span>{{ wpm }}<span class="text-[var(--sub-color)] text-[10px] ml-0.5">wpm</span></span>
                <span class="text-[var(--sub-color)]">·</span>
                <span>{{ accuracy }}%</span>
                <span class="text-[var(--sub-color)]">·</span>
                <span>{{ timer }}s</span>
                <span class="text-[var(--sub-color)]">·</span>
                <span>{{ userInput.length }}<span class="text-[var(--sub-color)]">/{{ logicCharacterCount }}</span></span>
            </div>
            <!-- Desktop: labelled blocks -->
            <div class="hidden sm:flex gap-8 lg:gap-12 items-end font-mono text-[var(--main-color)] select-none">
                <div class="flex flex-col">
                    <span class="text-[10px] text-[var(--sub-color)] uppercase tracking-[0.2em] mb-1">{{ t('wpm') }}</span>
                    <span class="text-2xl md:text-3xl tabular-nums">{{ wpm }}</span>
                </div>
                <div class="flex flex-col">
                    <span class="text-[10px] text-[var(--sub-color)] uppercase tracking-[0.2em] mb-1">{{ t('accuracy') }}</span>
                    <span class="text-2xl md:text-3xl tabular-nums">{{ accuracy }}%</span>
                </div>
                <div class="flex flex-col">
                    <span class="text-[10px] text-[var(--sub-color)] uppercase tracking-[0.2em] mb-1">{{ t('time') }}</span>
                    <span class="text-2xl md:text-3xl tabular-nums">{{ timer }}s</span>
                </div>
                <div class="flex flex-col">
                    <span class="text-[10px] text-[var(--sub-color)] uppercase tracking-[0.2em] mb-1">Progress</span>
                    <span class="text-2xl md:text-3xl tabular-nums">{{ userInput.length }}<span class="text-[var(--sub-color)]">/{{ logicCharacterCount }}</span></span>
                </div>
            </div>

            <button type="button"
                   @click="toggleErrorSound"
                   :title="t('error_sound_tooltip')"
                   :aria-label="t('sound_label')"
                   class="ml-auto self-center min-h-[36px] border border-[var(--border-color)] px-3 text-[11px] font-mono uppercase tracking-[0.12em] transition-colors"
                   :class="errorSoundEnabled ? 'text-[var(--caret-color)] border-[var(--caret-color)]' : 'text-[var(--sub-color)] hover:text-[var(--main-color)]'">
                {{ t('sound_label') }} {{ errorSoundEnabled ? 'on' : 'off' }}
            </button>
        </div>

        <!-- Sürah header cartouche -->
        <div v-if="!showResults && quranText.surah_name_arabic" class="w-full max-w-6xl mb-2 flex flex-wrap gap-3 justify-center sm:justify-between items-center animate-fade-in">
            <span class="cartouche">
                <span class="name" dir="rtl">{{ quranText.surah_name_arabic }}</span>
                <span class="font-mono">{{ quranText.surah_number }}:{{ quranText.start_ayah }}–{{ quranText.end_ayah }}</span>
            </span>

            <span v-if="isDrill" class="font-mono text-[10px] uppercase tracking-[0.2em] text-[var(--lapis-color)] border border-[var(--border-color)] px-2 py-1">
                {{ t('navigation.drills') }}
            </span>

            <div v-if="hifzMode" class="flex items-center gap-2 font-mono text-[11px]">
                <div class="flex border border-[var(--border-color)] divide-x divide-[var(--border-color)]">
                    <button
                        v-for="(lbl, i) in [t('hifz.level_guided'), t('hifz.level_faint'), t('hifz.level_blind')]" :key="i"
                        type="button"
                        @click="revealLevel = i + 1"
                        class="px-2.5 py-1 uppercase tracking-[0.1em] transition-colors"
                        :class="revealLevel === i + 1 ? 'bg-[var(--caret-color)] text-[var(--bg-color)]' : 'text-[var(--sub-color)] hover:text-[var(--main-color)]'"
                    >{{ lbl }}</button>
                </div>
                <button
                    type="button"
                    @pointerdown.prevent="startPeek" @pointerup="endPeek" @pointerleave="endPeek"
                    class="px-3 py-1 border uppercase tracking-[0.1em] select-none transition-colors"
                    :class="peeking ? 'border-[var(--caret-color)] text-[var(--caret-color)]' : 'border-[var(--border-color)] text-[var(--sub-color)]'"
                >{{ t('hifz.peek') }}</button>
                <span class="text-[var(--sub-color)]">{{ peeks }}</span>
            </div>
        </div>

        <p v-if="hifzMessage" class="w-full max-w-6xl mb-4 text-sm font-mono text-[var(--sub-color)]">
            {{ hifzMessage }} · <Link href="/hifz" class="text-[var(--lapis-color)]">{{ t('hifz.back') }}</Link>
        </p>

        <!-- Typing Area — the jadwal -->
        <div v-if="currentDisplayText && !showResults"
             @click="focusInput"
             ref="containerRef"
             class="jadwal relative w-full max-w-6xl -mx-6 sm:mx-0 transition-opacity duration-500 min-h-[200px] sm:min-h-[220px] flex items-center"
             :class="{ 'opacity-100': isFocused, 'opacity-40': !isFocused }">

            <!-- The frame draws itself once -->
            <svg class="jadwal-draw" :key="currentDisplayText.slice(0, 16)" preserveAspectRatio="none" aria-hidden="true">
                <rect x="0" y="0" width="100%" height="100%" pathLength="1" />
            </svg>

            <!-- Language Switching Warning -->
            <transition name="fade">
                <div v-if="showLanguageWarning && isFocused" class="absolute inset-x-0 top-0 z-[100] flex justify-center -translate-y-1/2">
                    <div class="bg-[var(--error-color)] text-white px-6 py-3 border border-white/20 flex items-center gap-4">
                        <div class="flex flex-col text-left">
                            <span class="font-cinzel font-semibold text-base leading-tight">{{ t('switch_to_arabic') }}</span>
                            <span class="text-[10px] opacity-80 uppercase tracking-[0.15em] font-mono">English layout detected</span>
                        </div>
                        <button @click="showLanguageWarning = false" aria-label="Dismiss" class="ml-2 opacity-70 hover:opacity-100 transition-opacity">✕</button>
                    </div>
                </div>
            </transition>

            <!-- Smooth Sliding Underline Caret -->
            <div class="absolute transition-all duration-150 z-[60] pointer-events-none rounded-full"
                 :class="{ 'caret-blink-anim': !isTyping && isFocused }"
                 :style="{
                     transitionTimingFunction: 'cubic-bezier(0.19, 1, 0.22, 1)',
                     top: caretPosition.top + 'px',
                     left: caretPosition.left + 'px',
                     width: caretPosition.width + 'px',
                     height: caretPosition.height + 'px',
                     opacity: isFocused ? caretPosition.opacity : 0,
                     backgroundColor: firstErrorIndex === -1 ? '#3f9d6b' : '#c1452f',
                     boxShadow: firstErrorIndex === -1
                        ? '0 0 8px 1px rgba(63, 157, 107, 0.6)'
                        : '0 0 8px 1px rgba(193, 69, 47, 0.6)',
                 }">
            </div>

            <!-- Focus Message -->
            <div v-if="!isFocused" class="absolute inset-0 z-30 flex flex-col items-center justify-center cursor-pointer">
                <div class="bg-[var(--bg-color)] px-8 py-4 border border-[var(--border-color)]">
                    <p class="text-sm font-cinzel text-[var(--sub-color)]">{{ t('click_to_focus') }}</p>
                </div>
            </div>

            <div v-if="isLoading" role="status" :aria-label="t('loading')" class="absolute inset-0 bg-[var(--panel-color)] flex flex-col items-center justify-center gap-4 z-30 jadwal-skeleton px-8" dir="rtl">
                <div class="h-3 w-11/12 bg-[var(--rule-color)] opacity-30"></div>
                <div class="h-3 w-9/12 bg-[var(--rule-color)] opacity-30"></div>
                <div class="h-3 w-10/12 bg-[var(--rule-color)] opacity-30"></div>
            </div>

            <div class="mushaf-text select-none w-full transition-all duration-300"
                 :class="{ 'has-tashkeel': usePunctuation }"
                 dir="rtl">
                <p class="relative z-0 whitespace-pre-wrap break-words transition-all duration-300">
                    <span v-for="(cluster, idx) in sourceClusters" :key="idx" 
                          :class="[
                              getClusterStatus(cluster) === 'correct' ? 'text-[var(--main-color)]' : '',
                              getClusterStatus(cluster) === 'incorrect' ? 'text-[var(--error-color)] bg-[var(--error-color)]/5' : '',
                              getClusterStatus(cluster) === 'untyped' ? 'text-[var(--sub-color)]' : '',
                              getClusterStatus(cluster) === 'active' ? 'text-[var(--sub-color)] cluster-active' : '',
                              getClusterStatus(cluster) === 'ignored-error' ? 'text-[var(--sub-color)] opacity-50' : '',
                              cluster.isSeparator ? 'ayah-ornament' : '',
                              revealClass(cluster)
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

        <!-- Results — an ijāza-style record -->
        <div v-if="showResults" class="w-full max-w-2xl mx-auto py-8 sm:py-12 animate-fade-in">
            <div class="jadwal relative flex flex-col items-center text-center gap-6 sm:gap-8 py-8 sm:py-12">
                <p v-if="quranText.surah_name_arabic" class="font-mono text-[10px] uppercase tracking-[0.25em] text-[var(--sub-color)]">
                    {{ quranText.surah_number }}:{{ quranText.start_ayah }}–{{ quranText.end_ayah }}
                </p>

                <div class="grid grid-cols-3 gap-4 sm:gap-10 w-full px-4">
                    <div class="flex flex-col gap-1">
                        <span class="text-4xl sm:text-6xl font-cinzel font-semibold text-[var(--caret-color)] tabular-nums">{{ wpm }}</span>
                        <span class="text-[10px] font-mono uppercase tracking-[0.2em] text-[var(--sub-color)]">{{ t('wpm') }}</span>
                    </div>
                    <div class="flex flex-col gap-1">
                        <span class="text-4xl sm:text-6xl font-cinzel font-semibold text-[var(--caret-color)] tabular-nums">{{ accuracy }}%</span>
                        <span class="text-[10px] font-mono uppercase tracking-[0.2em] text-[var(--sub-color)]">{{ t('accuracy') }}</span>
                    </div>
                    <div class="flex flex-col gap-1">
                        <span class="text-4xl sm:text-6xl font-cinzel font-semibold text-[var(--error-color)] tabular-nums">{{ totalErrors }}</span>
                        <span class="text-[10px] font-mono uppercase tracking-[0.2em] text-[var(--sub-color)]">{{ t('errors') }}</span>
                    </div>
                </div>

                <p class="text-lg sm:text-xl text-[var(--main-color)]">
                    {{ accuracy === 100 ? t('perfect') : (accuracy > 90 ? t('excellent') : t('keep_practicing')) }}
                </p>

                <!-- Hifz grading -->
                <div v-if="hifzMode" class="w-full flex flex-col items-center gap-3">
                    <p class="font-mono text-[10px] uppercase tracking-[0.2em] text-[var(--sub-color)]">
                        {{ t('hifz.how_well') }}
                        <span v-if="peeks"> · {{ t('hifz.peeks').replace('{n}', peeks) }}</span>
                    </p>
                    <div class="flex gap-2 w-full max-w-md px-6 sm:px-0">
                        <button
                            v-for="(lbl, g) in gradeLabels()" :key="g"
                            @click="submitGrade(g)"
                            class="flex-1 min-h-[44px] font-cinzel text-xs uppercase tracking-[0.1em] border transition-colors"
                            :class="g === suggestedGrade
                                ? 'bg-[var(--caret-color)] text-[var(--bg-color)] border-[var(--caret-color)]'
                                : 'border-[var(--border-color)] text-[var(--sub-color)] hover:text-[var(--main-color)] hover:border-[var(--caret-color)]'"
                        >{{ lbl }}</button>
                    </div>
                    <Link href="/hifz" class="font-mono text-[10px] uppercase tracking-[0.15em] text-[var(--sub-color)] hover:text-[var(--main-color)] transition-colors">
                        {{ t('hifz.end_session') }}
                    </Link>
                </div>

                <div v-else class="flex flex-col sm:flex-row items-center gap-3 w-full px-6 sm:w-auto sm:px-0">
                    <button
                        v-if="autoAdvance && quranText.last_quran_text_id"
                        @click="nextPassage"
                        class="w-full sm:w-auto min-h-[44px] px-8 bg-[var(--caret-color)] text-[var(--bg-color)] font-cinzel font-semibold hover:opacity-90 transition-opacity"
                    >
                        {{ t('passage.next_passage') }} →
                    </button>
                    <button
                        @click="resetTest"
                        class="w-full sm:w-auto min-h-[44px] px-8 font-cinzel font-semibold transition-opacity hover:opacity-90"
                        :class="autoAdvance && quranText.last_quran_text_id
                            ? 'border border-[var(--border-color)] text-[var(--sub-color)]'
                            : 'bg-[var(--caret-color)] text-[var(--bg-color)]'"
                    >
                        {{ t('restart_hint') }}
                    </button>
                    <a href="https://buy.stripe.com/dRmdRa1546e60jI2jZenS01" target="_blank" class="w-full sm:w-auto min-h-[44px] flex items-center justify-center px-8 border border-[var(--border-color)] font-cinzel text-xs uppercase tracking-[0.12em] text-[var(--sub-color)] hover:text-[var(--main-color)] hover:border-[var(--caret-color)] transition-colors">
                        {{ t('donate') }}
                    </a>
                </div>
            </div>
        </div>

        <GuestTestModal :show="showGuestModal" @close="showGuestModal = false" />

        <!-- Restart hint -->
        <div v-if="!showResults && currentDisplayText" class="mt-auto pt-8 pb-4 text-[var(--sub-color)] font-mono text-[10px] uppercase tracking-[0.2em] flex items-center gap-3">
            <kbd class="border border-[var(--border-color)] px-1.5 py-0.5">Tab</kbd>
            <span>{{ t('restart') }}</span>
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

/* .ayah-ornament roundel lives in resources/css/app.css */
.ayah-ornament {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    vertical-align: middle;
    margin: 0 0.3rem;
    user-select: none;
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
