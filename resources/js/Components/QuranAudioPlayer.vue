<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { useSettings } from '../useSettings';

const props = defineProps({
    surahNumber: Number,
    startAyah: Number,
    endAyah: Number,
    isTestStarted: Boolean,
});

const { t } = useSettings();

const audioUrls = ref([]);
const currentAyahIndex = ref(0);
const isPlaying = ref(false);
const autoPlayWhileTyping = ref(false);
const audioPlayer = ref(null);
const isLoading = ref(false);
const error = ref(null);

const fetchAudioUrls = async () => {
    if (!props.surahNumber || !props.startAyah || !props.endAyah) return;
    
    isLoading.value = true;
    error.value = null;
    audioUrls.value = [];
    currentAyahIndex.value = 0;
    
    try {
        const promises = [];
        for (let i = props.startAyah; i <= props.endAyah; i++) {
            promises.push(axios.get(`https://quranapi.pages.dev/api/audio/${props.surahNumber}/${i}.json`));
        }
        
        const results = await Promise.all(promises);
        // Default to reciter 1 (Mishary Rashid Al Afasy)
        audioUrls.value = results.map(res => res.data['1']?.url || res.data['1']?.originalUrl);
        
        if (audioUrls.value.every(url => !url)) {
            error.value = t('audio_error') || 'Audio unavailable';
        }
    } catch (err) {
        console.error("Failed to fetch audio urls:", err);
        error.value = t('audio_error') || 'Audio unavailable';
    } finally {
        isLoading.value = false;
    }
};

watch(() => [props.surahNumber, props.startAyah, props.endAyah], fetchAudioUrls, { immediate: true });

watch(() => props.isTestStarted, (started) => {
    if (started && autoPlayWhileTyping.value && !isPlaying.value && audioUrls.value.length > 0) {
        startPlayback();
    }
});

const startPlayback = () => {
    if (!audioPlayer.value || !audioUrls.value[currentAyahIndex.value]) return;
    
    audioPlayer.value.src = audioUrls.value[currentAyahIndex.value];
    audioPlayer.value.play().catch(e => {
        console.error("Playback failed:", e);
        isPlaying.value = false;
    });
    isPlaying.value = true;
};

const togglePlay = () => {
    if (!audioPlayer.value) return;
    
    if (isPlaying.value) {
        audioPlayer.value.pause();
        isPlaying.value = false;
    } else {
        if (!audioPlayer.value.src && audioUrls.value.length > 0) {
            startPlayback();
        } else {
            audioPlayer.value.play();
            isPlaying.value = true;
        }
    }
};

const handleEnded = () => {
    if (currentAyahIndex.value < audioUrls.value.length - 1) {
        currentAyahIndex.value++;
        startPlayback();
    } else {
        isPlaying.value = false;
        currentAyahIndex.value = 0;
        audioPlayer.value.src = '';
    }
};

const jumpToAyah = (index) => {
    currentAyahIndex.value = index;
    startPlayback();
};

const handleAudioError = () => {
    console.error("Audio player error");
    // If one ayah fails, try to skip to next
    if (currentAyahIndex.value < audioUrls.value.length - 1) {
        currentAyahIndex.value++;
        startPlayback();
    } else {
        isPlaying.value = false;
        error.value = t('audio_error') || 'Audio unavailable';
    }
};

onUnmounted(() => {
    if (audioPlayer.value) {
        audioPlayer.value.pause();
        audioPlayer.value.src = '';
    }
});
</script>

<template>
    <div class="audio-player-container flex items-center gap-4 bg-[var(--panel-color)] px-4 py-2 rounded-2xl border border-[var(--border-color)] backdrop-blur-md shadow-lg transition-all hover:border-[var(--caret-color)]/30">
        <!-- Main Play Button / Loader -->
        <button 
            @click="togglePlay"
            :disabled="isLoading || !!error || audioUrls.length === 0"
            class="w-10 h-10 flex items-center justify-center rounded-full bg-[var(--caret-color)] text-[var(--bg-color)] transition-all hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed shadow-md shadow-emerald-500/20"
            :title="isPlaying ? 'Pause' : 'Listen'"
        >
            <div v-if="isLoading" class="w-5 h-5 border-2 border-[var(--bg-color)] border-t-transparent rounded-full animate-spin"></div>
            <template v-else-if="error">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-octagon-alert"><path d="M12 16h.01"/><path d="M12 8v4"/><path d="M15.312 2a2 2 0 0 1 1.414.586l4.688 4.688A2 2 0 0 1 22 8.688v6.624a2 2 0 0 1-.586 1.414l-4.688 4.688a2 2 0 0 1-1.414.586H8.688a2 2 0 0 1-1.414-.586l-4.688-4.688A2 2 0 0 1 2 15.312V8.688a2 2 0 0 1 .586-1.414l4.688-4.688A2 2 0 0 1 8.688 2z"/></svg>
            </template>
            <template v-else>
                <svg v-if="!isPlaying" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" class="translate-x-0.5"><path d="M5 3l14 9-14 9V3z"/></svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
            </template>
        </button>

        <!-- Player Info & Progress -->
        <div class="flex flex-col min-w-[120px]">
            <div class="flex items-center justify-between gap-2 mb-1">
                <span class="text-[10px] uppercase tracking-widest font-mono text-[var(--sub-color)] opacity-60">
                    {{ error ? error : (isLoading ? t('loading_audio') : (isPlaying ? 'Reciting' : t('listen'))) }}
                </span>
                <span v-if="audioUrls.length > 0 && !error && !isLoading" class="text-[9px] font-mono text-[var(--caret-color)] bg-[var(--caret-color)]/10 px-1.5 py-0.5 rounded">
                    Ayah {{ props.startAyah + currentAyahIndex }}
                </span>
            </div>
            
            <!-- Progress Dots -->
            <div class="flex gap-1">
                <div v-for="(url, index) in audioUrls" :key="index"
                    @click="jumpToAyah(index)"
                    class="h-1 rounded-full transition-all cursor-pointer"
                    :class="[
                        index === currentAyahIndex ? 'w-4 bg-[var(--caret-color)]' : 'w-2 bg-[var(--border-color)] hover:bg-[var(--sub-color)]/30',
                        index < currentAyahIndex ? 'bg-[var(--caret-color)]/40' : ''
                    ]"
                ></div>
            </div>
        </div>

        <!-- Divider -->
        <div class="w-[1px] h-8 bg-[var(--border-color)] mx-1"></div>

        <!-- Listen While Typing Toggle -->
        <button 
            @click="autoPlayWhileTyping = !autoPlayWhileTyping"
            class="flex items-center gap-2 group"
            :title="t('listen_while_typing')"
        >
            <div class="relative w-8 h-4 bg-[var(--bg-color)] rounded-full border border-[var(--border-color)] transition-all overflow-hidden"
                 :class="{ 'border-[var(--caret-color)]/50': autoPlayWhileTyping }">
                <div class="absolute top-1/2 -translate-y-1/2 w-2.5 h-2.5 rounded-full transition-all"
                     :class="autoPlayWhileTyping ? 'right-1 bg-[var(--caret-color)]' : 'left-1 bg-[var(--sub-color)] opacity-40'"
                ></div>
            </div>
            <span class="text-[10px] font-mono uppercase tracking-tighter transition-colors"
                  :class="autoPlayWhileTyping ? 'text-[var(--caret-color)]' : 'text-[var(--sub-color)] opacity-60 group-hover:opacity-100'">
                {{ t('listen_while_typing') }}
            </span>
        </button>

        <!-- Hidden Audio Element -->
        <audio 
            ref="audioPlayer" 
            @ended="handleEnded" 
            @error="handleAudioError"
            preload="auto"
        ></audio>
    </div>
</template>

<style scoped>
.audio-player-container {
    font-family: 'Cinzel', serif;
}
</style>
