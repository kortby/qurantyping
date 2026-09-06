<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useSettings } from '../useSettings';

const props = defineProps({
    surahNumber: Number,
    startAyah: Number,
    endAyah: Number,
    reciters: { type: Object, default: () => ({}) },
    reciter: { type: String, default: '' },
});

const { t } = useSettings();

const fallbackKey = computed(() => Object.keys(props.reciters)[0] ?? '');
const selectedReciter = ref(props.reciter && props.reciters[props.reciter] ? props.reciter : fallbackKey.value);

const audioEl = ref(null);
const currentIndex = ref(0);
const isPlaying = ref(false);
const errored = ref(false);

const pad3 = (n) => String(n).padStart(3, '0');

const audioUrls = computed(() => {
    const folder = props.reciters[selectedReciter.value]?.folder;
    if (!folder || !props.surahNumber || !props.startAyah || !props.endAyah) return [];

    const urls = [];
    for (let ayah = props.startAyah; ayah <= props.endAyah; ayah++) {
        urls.push(`https://everyayah.com/data/${folder}/${pad3(props.surahNumber)}${pad3(ayah)}.mp3`);
    }
    return urls;
});

const stop = () => {
    if (audioEl.value) {
        audioEl.value.pause();
        audioEl.value.removeAttribute('src');
    }
    isPlaying.value = false;
    currentIndex.value = 0;
};

const playIndex = (index) => {
    if (!audioEl.value || !audioUrls.value[index]) return;
    currentIndex.value = index;
    errored.value = false;
    audioEl.value.src = audioUrls.value[index];
    audioEl.value.play().then(() => {
        isPlaying.value = true;
    }).catch(() => {
        isPlaying.value = false;
    });
};

const toggle = () => {
    if (!audioEl.value || audioUrls.value.length === 0) return;

    if (isPlaying.value) {
        audioEl.value.pause();
        isPlaying.value = false;
    } else if (audioEl.value.src) {
        audioEl.value.play().then(() => { isPlaying.value = true; }).catch(() => {});
    } else {
        playIndex(currentIndex.value);
    }
};

const onEnded = () => {
    if (currentIndex.value < audioUrls.value.length - 1) {
        playIndex(currentIndex.value + 1);
    } else {
        isPlaying.value = false;
        currentIndex.value = 0;
        audioEl.value?.removeAttribute('src');
    }
};

const onError = () => {
    if (currentIndex.value < audioUrls.value.length - 1) {
        playIndex(currentIndex.value + 1);
    } else {
        errored.value = true;
        isPlaying.value = false;
    }
};

const changeReciter = (event) => {
    const key = event.target.value;
    selectedReciter.value = key;
    stop();
    router.post('/user/settings/reciter', { reciter: key }, {
        preserveScroll: true,
        preserveState: true,
        only: ['auth'],
    });
};

watch(() => [props.surahNumber, props.startAyah, props.endAyah], stop);

onUnmounted(stop);

const statusLabel = computed(() => {
    if (errored.value) return t('audio_error');
    if (isPlaying.value) return t('reciting');
    return t('listen');
});
</script>

<template>
    <div class="flex items-center gap-3 border border-[var(--border-color)] px-3 py-1.5 font-mono text-[11px]">
        <button
            type="button"
            @click="toggle"
            :disabled="audioUrls.length === 0 || errored"
            :title="isPlaying ? t('reciting') : t('listen')"
            class="w-8 h-8 flex items-center justify-center border transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
            :class="isPlaying ? 'border-[var(--caret-color)] text-[var(--caret-color)]' : 'border-[var(--border-color)] text-[var(--sub-color)] hover:text-[var(--main-color)] hover:border-[var(--caret-color)]'"
        >
            <svg v-if="!isPlaying" width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5 3l14 9-14 9V3z" /></svg>
            <svg v-else width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 4h4v16H6zM14 4h4v16h-4z" /></svg>
        </button>

        <div class="flex flex-col gap-1 min-w-[92px]">
            <div class="flex items-center justify-between gap-2">
                <span class="uppercase tracking-[0.12em] text-[var(--sub-color)]">{{ statusLabel }}</span>
                <span v-if="audioUrls.length > 1 && !errored" class="text-[var(--caret-color)] tabular-nums">
                    {{ startAyah + currentIndex }}
                </span>
            </div>
            <div v-if="audioUrls.length > 1" class="flex gap-1">
                <button
                    v-for="(url, index) in audioUrls"
                    :key="url"
                    type="button"
                    @click="playIndex(index)"
                    class="h-1 transition-all"
                    :class="index === currentIndex
                        ? 'w-4 bg-[var(--caret-color)]'
                        : (index < currentIndex ? 'w-2 bg-[var(--caret-color)]/40' : 'w-2 bg-[var(--border-color)] hover:bg-[var(--sub-color)]')"
                    :aria-label="`Ayah ${startAyah + index}`"
                ></button>
            </div>
        </div>

        <span class="w-px h-6 bg-[var(--border-color)]"></span>

        <label class="flex items-center gap-1.5">
            <span class="sr-only">{{ t('reciter') }}</span>
            <select
                :value="selectedReciter"
                @change="changeReciter"
                class="bg-[var(--bg-color)] border border-[var(--border-color)] px-2 py-1 text-[11px] font-mono text-[var(--main-color)] focus:border-[var(--caret-color)] focus:outline-none max-w-[150px]"
            >
                <option v-for="(meta, key) in reciters" :key="key" :value="key">{{ meta.name }}</option>
            </select>
        </label>

        <audio ref="audioEl" preload="none" @ended="onEnded" @error="onError"></audio>
    </div>
</template>
