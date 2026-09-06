<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSettings } from '../../useSettings';

defineProps({
    recent: { type: Array, default: () => [] },
});

const { t } = useSettings();
const busy = ref(false);

const go = (url) => {
    if (busy.value) return;
    busy.value = true;
    router.post(url, {}, { onFinish: () => (busy.value = false) });
};
</script>

<template>
    <Head><title>{{ t('races.title') }} - QuranTyping</title></Head>

    <AppLayout>
        <div class="py-10 sm:py-12 animate-fade-in min-h-[80vh]">
            <div class="max-w-3xl mx-auto px-4 sm:px-6">
                <header class="mb-8">
                    <h1 class="text-2xl font-cinzel font-semibold text-[var(--caret-color)]">{{ t('races.title') }}</h1>
                    <p class="text-[var(--sub-color)] font-mono text-[10px] uppercase tracking-[0.3em] mt-1">
                        {{ t('races.subtitle') }}
                    </p>
                </header>

                <div class="grid gap-4 sm:grid-cols-2 mb-10">
                    <button
                        type="button"
                        :disabled="busy"
                        @click="go('/races/quick')"
                        class="border border-[var(--caret-color)] text-[var(--caret-color)] px-6 py-5 font-cinzel font-semibold hover:bg-[var(--caret-color)] hover:text-[var(--bg-color)] transition-colors disabled:opacity-50"
                    >
                        {{ t('races.quick_match') }}
                        <span class="block font-mono text-[10px] font-normal uppercase tracking-[0.15em] mt-1 opacity-70">{{ t('races.quick_match_hint') }}</span>
                    </button>
                    <button
                        type="button"
                        :disabled="busy"
                        @click="go('/races')"
                        class="border border-[var(--border-color)] text-[var(--main-color)] px-6 py-5 font-cinzel font-semibold hover:border-[var(--caret-color)] transition-colors disabled:opacity-50"
                    >
                        {{ t('races.create_room') }}
                        <span class="block font-mono text-[10px] font-normal uppercase tracking-[0.15em] mt-1 text-[var(--sub-color)]">{{ t('races.create_room_hint') }}</span>
                    </button>
                </div>

                <section v-if="recent.length">
                    <h2 class="font-mono text-[10px] uppercase tracking-[0.25em] text-[var(--sub-color)] mb-3">{{ t('races.recent') }}</h2>
                    <div class="border border-[var(--border-color)] divide-y divide-[var(--border-color)] font-mono text-sm">
                        <div v-for="(r, i) in recent" :key="i" class="flex items-center justify-between px-4 py-3">
                            <span class="text-[var(--sub-color)]">{{ r.surah_number }}:{{ r.start_ayah }}–{{ r.end_ayah }}</span>
                            <span class="flex items-center gap-4">
                                <span :class="r.position === 1 ? 'text-[var(--caret-color)]' : 'text-[var(--main-color)]'">#{{ r.position }}</span>
                                <span class="text-[var(--main-color)] tabular-nums">{{ r.wpm }} wpm</span>
                                <span class="text-[var(--sub-color)] tabular-nums">{{ r.accuracy }}%</span>
                            </span>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
