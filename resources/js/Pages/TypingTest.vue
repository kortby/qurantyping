<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

// --- State Management ---
const quranText = ref({
    id: null,
    text: '...جار التحميل',
});
const userInput = ref('');
const timer = ref(0);
const intervalId = ref(null);
const testFinished = ref(false);

// --- Computed Properties for Real-time Stats ---
const sourceCharacters = computed(() => quranText.value.text.split(''));
const typedCharacters = computed(() => userInput.value.split(''));

const wpm = computed(() => {
    if (timer.value === 0) return 0;
    const wordCount = userInput.value.length / 5; // Standard WPM calculation
    const minutes = timer.value / 60;
    return Math.round(wordCount / minutes);
});

const accuracy = computed(() => {
    let correctChars = 0;
    typedCharacters.value.forEach((char, index) => {
        if (char === sourceCharacters.value[index]) {
            correctChars++;
        }
    });
    return userInput.value.length === 0 ? 100 : Math.round((correctChars / userInput.value.length) * 100);
});

// --- Core Logic ---
const fetchNewTest = async () => {
    try {
        const response = await axios.get('/test/new');
        quranText.value = response.data;
        resetTest();
    } catch (error) {
        console.error("Failed to fetch new test:", error);
        quranText.value.text = 'فشل تحميل النص. حاول مرة أخرى.';
    }
};

const handleInput = (event) => {
    if (testFinished.value) return;

    if (!intervalId.value) {
        startTimer();
    }

    userInput.value = event.target.value;

    if (userInput.value.length >= sourceCharacters.value.length) {
        userInput.value = userInput.value.substring(0, sourceCharacters.value.length);
        finishTest();
    }
};

const startTimer = () => {
    intervalId.value = setInterval(() => {
        timer.value++;
    }, 1000);
};

const stopTimer = () => {
    clearInterval(intervalId.value);
    intervalId.value = null;
};

const finishTest = async () => {
    stopTimer();
    testFinished.value = true;
    
    let correctCharsCount = 0;
    let incorrectCharsCount = 0;
    typedCharacters.value.forEach((char, index) => {
        if (char === sourceCharacters.value[index]) {
            correctCharsCount++;
        } else {
            incorrectCharsCount++;
        }
    });

    const resultData = {
        quran_text_id: quranText.value.id,
        wpm: wpm.value,
        raw_wpm: wpm.value,
        accuracy: accuracy.value,
        char_count: sourceCharacters.value.length,
        correct_chars: correctCharsCount,
        incorrect_chars: incorrectCharsCount,
        mode: 'quote',
        duration: timer.value,
    };

    try {
        await axios.post('/api/test/complete', resultData);
        console.log('Test result saved successfully!');
    } catch (error) {
        console.error('Failed to save test result:', error.response.data);
    }
};

const resetTest = () => {
    stopTimer();
    userInput.value = '';
    timer.value = 0;
    testFinished.value = false;
    document.getElementById('hidden-input')?.focus();
};

const restartTest = () => {
    fetchNewTest();
};

// --- Styling Logic ---
const getCharClass = (typedChar, sourceChar) => {
    if (typedChar === undefined) return ''; // Should not happen with the overlay
    return typedChar === sourceChar ? 'text-green-400' : 'text-red-500';
};

// --- Lifecycle Hooks ---
onMounted(() => {
    fetchNewTest();
    window.addEventListener('focus', () => {
        document.getElementById('hidden-input')?.focus();
    });
});

</script>

<template>
    <div class="min-h-screen flex flex-col items-center justify-center p-4" @click="() => document.getElementById('hidden-input')?.focus()">
        <h1 class="text-4xl font-bold text-yellow-400 mb-8">إختبار سرعة الكتابة بالقرآن الكريم</h1>

        <div class="stats-container w-full max-w-4xl mb-8 flex justify-around text-2xl text-gray-300">
            <div class="stat"><span class="font-bold text-yellow-500">{{ wpm }}</span> كلمة/دقيقة</div>
            <div class="stat"><span class="font-bold text-yellow-500">{{ accuracy }}%</span> الدقة</div>
            <div class="stat"><span class="font-bold text-yellow-500">{{ timer }}</span> ثانية</div>
        </div>

        <!-- Typing Area - New Overlay Logic -->
        <div v-if="quranText.text" class="text-container w-full max-w-4xl bg-gray-800 p-6 rounded-lg shadow-lg relative text-3xl leading-loose select-none" style="font-family: 'Noto Naskh Arabic', serif;">
            
            <!-- Layer 1: Untyped Text (Gray) -->
            <p class="text-gray-500">{{ quranText.text }}</p>

            <!-- Layer 2: Typed Text (Colored Overlay) -->
            <p class="absolute top-0 left-0 p-6">
                <span v-for="(char, index) in typedCharacters" :key="index" :class="getCharClass(char, sourceCharacters[index])">
                    {{ sourceCharacters[index] }}
                </span>
                <!-- The Caret -->
                <span class="caret absolute bg-yellow-400 animate-blink">&nbsp;</span>
            </p>

            <input
                id="hidden-input"
                type="text"
                class="absolute top-0 left-0 w-full h-full opacity-0 cursor-default"
                :value="userInput"
                @input="handleInput"
                autofocus
                :maxlength="sourceCharacters.length"
            />
        </div>

        <button @click="restartTest" class="mt-8 px-6 py-3 bg-yellow-500 text-gray-900 font-bold text-xl rounded-lg hover:bg-yellow-400 transition-colors duration-300">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 inline-block ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M20 20v-5h-5M4 4l16 16"/></svg>
            إعادة / نص جديد
        </button>
        <a href="https://buy.stripe.com/test_6oE14h8fVdld436144" class="mt-8 px-6 py-3 bg-green-500 text-gray-900 font-bold text-xl rounded-lg hover:bg-green-400 transition-colors duration-300">
            Donate - تبرع
            </a>
    </div>
</template>

<style scoped>
/* We need to use a trick to position the caret correctly.
   We can't just use left/right with RTL. We create a "ghost"
   text element to measure the width of the typed text. */
.text-container p {
    white-space: pre-wrap; /* Preserve whitespace and wrap */
    word-wrap: break-word; /* Break long words if necessary */
}

.caret {
    width: 2px;
    top: 10%;
    height: 80%;
    border-radius: 2px;
    /* The caret position will be set by JS if needed, but CSS can handle it in many cases */
    inset-inline-start: 0; /* Logical property for RTL */
}

/* This is a more complex part, for now, we'll keep it simple.
   A more advanced caret would need JS to measure text width.
   For now, we'll let it be at the end of the colored text. */
.animate-blink {
    animation: blink 1s infinite;
}

@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0; }
}
</style>
