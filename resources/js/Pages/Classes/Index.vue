<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSettings } from '../../useSettings';

defineProps({
    owned: { type: Array, default: () => [] },
    joined: { type: Array, default: () => [] },
});

const { t } = useSettings();

const form = useForm({ name: '' });
const create = () => form.post('/classes', { preserveScroll: true, onSuccess: () => form.reset() });
</script>

<template>
    <Head><title>{{ t('classes.title') }} - QuranTyping</title></Head>

    <AppLayout>
        <div class="py-10 sm:py-12 animate-fade-in min-h-[80vh]">
            <div class="max-w-3xl mx-auto px-4 sm:px-6">
                <header class="mb-8">
                    <h1 class="text-2xl font-cinzel font-semibold text-[var(--caret-color)]">{{ t('classes.title') }}</h1>
                    <p class="text-[var(--sub-color)] font-mono text-[10px] uppercase tracking-[0.3em] mt-1">
                        {{ t('classes.subtitle') }}
                    </p>
                </header>

                <!-- Create a class -->
                <section class="mb-10">
                    <h2 class="font-mono text-[10px] uppercase tracking-[0.25em] text-[var(--sub-color)] mb-3">{{ t('classes.create') }}</h2>
                    <form @submit.prevent="create" class="flex gap-2">
                        <input
                            v-model="form.name"
                            type="text"
                            :placeholder="t('classes.name_placeholder')"
                            maxlength="120"
                            class="flex-1 min-w-0 bg-transparent border border-[var(--border-color)] px-4 py-2.5 font-mono text-xs text-[var(--main-color)] focus:outline-none focus:border-[var(--caret-color)]"
                        />
                        <button type="submit" :disabled="form.processing || !form.name"
                                class="shrink-0 border border-[var(--caret-color)] text-[var(--caret-color)] px-4 font-mono text-[10px] uppercase tracking-[0.2em] hover:bg-[var(--caret-color)]/10 transition-colors disabled:opacity-40">
                            {{ t('classes.create_button') }}
                        </button>
                    </form>
                </section>

                <!-- Classes I teach -->
                <section class="mb-10">
                    <h2 class="font-mono text-[10px] uppercase tracking-[0.25em] text-[var(--sub-color)] mb-3">{{ t('classes.teaching') }}</h2>
                    <p v-if="!owned.length" class="font-mono text-xs text-[var(--sub-color)] opacity-70">{{ t('classes.no_owned') }}</p>
                    <div v-else class="border border-[var(--border-color)] divide-y divide-[var(--border-color)] font-mono text-sm">
                        <Link v-for="c in owned" :key="c.id" :href="`/classes/${c.id}`"
                              class="flex items-center justify-between px-4 py-3 hover:bg-[var(--caret-color)]/5 transition-colors">
                            <span class="text-[var(--main-color)]">{{ c.name }}</span>
                            <span class="flex items-center gap-4 text-[var(--sub-color)]">
                                <span class="tabular-nums">{{ c.members_count }} {{ t('classes.members') }}</span>
                                <span class="text-[var(--caret-color)] tracking-[0.15em]">{{ c.code }}</span>
                            </span>
                        </Link>
                    </div>
                </section>

                <!-- Classes I've joined -->
                <section>
                    <h2 class="font-mono text-[10px] uppercase tracking-[0.25em] text-[var(--sub-color)] mb-3">{{ t('classes.joined') }}</h2>
                    <p v-if="!joined.length" class="font-mono text-xs text-[var(--sub-color)] opacity-70">{{ t('classes.no_joined') }}</p>
                    <div v-else class="border border-[var(--border-color)] divide-y divide-[var(--border-color)] font-mono text-sm">
                        <Link v-for="c in joined" :key="c.id" :href="`/classes/${c.id}`"
                              class="flex items-center justify-between px-4 py-3 hover:bg-[var(--caret-color)]/5 transition-colors">
                            <span class="text-[var(--main-color)]">{{ c.name }}</span>
                            <span class="text-[var(--sub-color)]">{{ t('classes.teacher') }}: {{ c.teacher }}</span>
                        </Link>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
