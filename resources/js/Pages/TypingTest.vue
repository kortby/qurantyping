<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

import AppLayout from '@/Layouts/AppLayout.vue';

// --- State for Filters and Test Data ---
const surahs = ref([]); // To hold the list of all surahs
const selectedSurah = ref(1); // Default to Al-Fatiha
const startAyah = ref(1);
const endAyah = ref(5);
const isLoading = ref(true);

const quranText = ref({
    text: '...اختر سورة وابدأ الاختبار',
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


// --- Computed Properties for Stats & Rendering (No changes needed here) ---
const sourceCharacters = computed(() => quranText.value.text.split(''));
const typedCharacters = computed(() => userInput.value.split(''));
const wpm = computed(() => {
    if (timer.value === 0) return 0;
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
    if (userInput.value.length === 0) return 100;
    return Math.round((correctChars / userInput.value.length) * 100);
});
const firstErrorIndex = computed(() => {
    for (let i = 0; i < typedCharacters.value.length; i++) {
        if (typedCharacters.value[i] !== sourceCharacters.value[i]) return i;
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

const fetchTestText = async () => {
    isLoading.value = true;
    try {
        const params = {
            surah_number: selectedSurah.value,
            start_ayah: startAyah.value,
            end_ayah: endAyah.value,
        };
        const response = await axios.get('/api/test/text', { params });
        quranText.value = response.data;
        resetTest();
    } catch (error) {
        console.error("Failed to fetch test text:", error);
        quranText.value.text = 'فشل تحميل النص. تحقق من نطاق الآيات.';
    } finally {
        isLoading.value = false;
    }
};

const handleInput = (event) => {
    if (testFinished.value || isLoading.value) return;
    if (!intervalId.value) startTimer();
    userInput.value = event.target.value;
    if (userInput.value.length >= sourceCharacters.value.length) {
        userInput.value = userInput.value.substring(0, sourceCharacters.value.length);
        finishTest();
    }
};

const startTimer = () => {
    intervalId.value = setInterval(() => { timer.value++; }, 1000);
};

const stopTimer = () => {
    clearInterval(intervalId.value);
    intervalId.value = null;
};

const finishTest = async () => { /* ... (This function remains unchanged) ... */ };

const resetTest = () => {
    stopTimer();
    userInput.value = '';
    timer.value = 0;
    testFinished.value = false;
    document.getElementById('hidden-input')?.focus();
};

defineOptions({
    layout: AppLayout
});


// --- Lifecycle Hooks ---
onMounted(() => {
    fetchSurahs();
    isLoading.value = false;
});
</script>

<template>
    <div class="min-h-screen flex flex-col items-center justify-center p-4">
        <h1 class="text-4xl font-bold text-yellow-400 mb-6">إختبار سرعة الكتابة بالقرآن الكريم</h1>
        
        <!-- New Filters Section -->
        <form @submit.prevent="fetchTestText" class="w-full max-w-4xl bg-gray-800 p-4 rounded-lg mb-8 flex flex-col sm:flex-row items-center justify-center gap-4">
            <div class="flex-grow w-full sm:w-auto">
                <label for="surah-select" class="text-gray-300 mb-1 block">اختر السورة:</label>
                <select id="surah-select" v-model="selectedSurah" class="w-full p-2 rounded bg-gray-700 text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-yellow-500">
                    <option v-for="surah in surahs" :key="surah.surah_number" :value="surah.surah_number">
                        {{ surah.surah_number }} - {{ surah.surah_name_arabic }} ({{ surah.surah_name_english }})
                    </option>
                </select>
            </div>
            <div class="flex gap-4">
                <div>
                    <label for="start-ayah" class="text-gray-300 mb-1 block">من آية:</label>
                    <input type="number" id="start-ayah" v-model="startAyah" min="1" class="w-24 p-2 rounded bg-gray-700 text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-yellow-500">
                </div>
                <div>
                    <label for="end-ayah" class="text-gray-300 mb-1 block">إلى آية:</label>
                    <input type="number" id="end-ayah" v-model="endAyah" min="1" class="w-24 p-2 rounded bg-gray-700 text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-yellow-500">
                </div>
            </div>
            <button type="submit" class="self-end px-6 py-2 bg-yellow-500 text-gray-900 font-bold rounded-lg hover:bg-yellow-400 transition-colors duration-300">
                بدء الاختبار
            </button>
        </form>

        <!-- Display Surah and Ayah Info -->
        <div v-if="quranText.surah_name_arabic" class="text-gray-400 text-xl mb-6">
            {{ quranText.surah_name_arabic }} - الآيات {{ quranText.start_ayah }} إلى {{ quranText.end_ayah }}
        </div>

        <div class="stats-container w-full max-w-4xl mb-8 flex justify-around text-2xl text-gray-300">
             <div class="stat"><span class="font-bold text-yellow-500">{{ wpm }}</span> كلمة/دقيقة</div>
            <div class="stat"><span class="font-bold text-yellow-500">{{ accuracy }}%</span> الدقة</div>
            <div class="stat"><span class="font-bold text-yellow-500">{{ timer }}</span> ثانية</div>
        </div>

        <!-- Typing Area -->
        <div v-if="quranText.text" @click="() => document.getElementById('hidden-input')?.focus()" class="text-container w-full max-w-4xl bg-gray-800 p-6 rounded-lg shadow-lg relative text-3xl leading-loose select-none" style="font-family: 'Noto Naskh Arabic', serif;">
            <div v-if="isLoading" class="absolute inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center">
                <p class="text-2xl text-yellow-400">...جار التحميل</p>
            </div>
            <p>
                <span class="text-green-400">{{ correctPart }}</span>
                <span class="text-red-500 bg-red-900/50 rounded">{{ incorrectPart }}</span>
                <span class="caret-container relative">
                    <span class="caret absolute bg-yellow-400 animate-blink">&nbsp;</span>
                </span>
                <span class="text-gray-500">{{ untypedPart }}</span>
            </p>
            <input id="hidden-input" type="text" class="absolute top-0 left-0 w-full h-full opacity-0 cursor-default" :value="userInput" @input="handleInput" autofocus :maxlength="sourceCharacters.length" />
        </div>
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