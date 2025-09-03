<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

// --- State Management ---
const quranText = ref({
    id: null,
    text: '...جار التحميل',
    surah_name_arabic: '',
    surah_number: null,
    ayah_number: null,
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
    // Standard WPM calculation: (all typed chars / 5) / (time in minutes)
    const wordCount = userInput.value.length / 5;
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
    // Avoid division by zero
    if (userInput.value.length === 0) return 100;
    return Math.round((correctChars / userInput.value.length) * 100);
});


// --- Computed Properties for Correct Rendering ---
const firstErrorIndex = computed(() => {
    for (let i = 0; i < typedCharacters.value.length; i++) {
        if (typedCharacters.value[i] !== sourceCharacters.value[i]) {
            return i;
        }
    }
    return -1; // No errors
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
const fetchNewTest = async () => {
    try {
        const response = await axios.get('/api/test/new');
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
        <h1 class="text-4xl font-bold text-yellow-400 mb-4">إختبار سرعة الكتابة بالقرآن الكريم</h1>
        
        <!-- Display Surah and Ayah Info -->
        <div v-if="quranText.surah_name_arabic" class="text-gray-400 text-xl mb-6">
            {{ quranText.surah_name_arabic }} - الآية {{ quranText.ayah_number }}
        </div>

        <div class="stats-container w-full max-w-4xl mb-8 flex justify-around text-2xl text-gray-300">
            <div class="stat"><span class="font-bold text-yellow-500">{{ wpm }}</span> كلمة/دقيقة</div>
            <div class="stat"><span class="font-bold text-yellow-500">{{ accuracy }}%</span> الدقة</div>
            <div class="stat"><span class="font-bold text-yellow-500">{{ timer }}</span> ثانية</div>
        </div>

        <!-- Typing Area -->
        <div v-if="quranText.text" class="text-container w-full max-w-4xl bg-gray-800 p-6 rounded-lg shadow-lg relative text-3xl leading-loose select-none" style="font-family: 'Noto Naskh Arabic', serif;">
            
            <p>
                <span class="text-green-400">{{ correctPart }}</span>
                <span class="text-red-500 bg-red-900/50 rounded">{{ incorrectPart }}</span>
                <span class="caret-container relative">
                    <span class="caret absolute bg-yellow-400 animate-blink">&nbsp;</span>
                </span>
                <span class="text-gray-500">{{ untypedPart }}</span>
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

        <button @click="restartTest" class="mt-8 px-6 py-3 bg-yellow-500 text-gray-900 font-bold text-xl rounded-lg hover:bg-yellow-400 transition-colors duration-300 flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 mr-2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0011.664 0l3.181-3.183m-4.991-2.696a8.25 8.25 0 00-11.664 0l-3.181 3.183" />
            </svg>
            إعادة / نص جديد
        </button>
        <a class="mt-8 px-6 py-3 bg-green-500 text-gray-900 font-bold text-xl rounded-lg hover:bg-green-400 transition-colors duration-300" href="https://buy.stripe.com/dRmdRa1546e60jI2jZenS01">Donate - تبرع</a>
    </div>
</template>

<style scoped>
.text-container p {
    white-space: pre-wrap;
    word-wrap: break-word;
}

.caret {
    width: 2px;
    top: 10%;
    height: 80%;
    border-radius: 2px;
}

.animate-blink {
    animation: blink 1s infinite;
}

@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0; }
}
</style>