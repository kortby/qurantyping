<script setup>
import { Link } from '@inertiajs/vue3';
import { useSettings } from '../useSettings';

const { currentLang, currentTheme, setLang, setTheme, t } = useSettings();

const languages = [
    { code: 'en', label: 'EN' },
    { code: 'fr', label: 'FR' },
    { code: 'ar', label: 'AR' }
];
</script>

<template>
    <div class="min-h-screen bg-[var(--bg-color)] text-[var(--main-color)] antialiased transition-colors duration-300">
        <header class="container mx-auto px-6 py-6 flex justify-between items-center bg-transparent">
            <div class="flex items-center gap-8">
                <Link href="/" class="flex items-center gap-3 group">
                    <span class="text-3xl filter drop-shadow-lg group-hover:scale-110 transition-transform duration-300">📖</span>
                    <span class="text-2xl font-mono font-bold tracking-tighter text-[var(--caret-color)] group-hover:opacity-80 transition-opacity">
                        {{ t('title') }}
                    </span>
                </Link>

                <!-- Nav Links -->
                <nav class="hidden md:flex items-center gap-6 font-mono text-sm opacity-60">
                    <Link href="/" class="hover:text-[var(--caret-color)] transition-colors">{{ t('navigation.home') }}</Link>
                    <Link v-if="$page.props.auth.user" href="/dashboard" class="hover:text-[var(--caret-color)] transition-colors">{{ t('navigation.dashboard') }}</Link>
                </nav>
            </div>

            <div class="flex items-center gap-6">
                <!-- Lang Switcher -->
                <div class="flex items-center gap-1 bg-[var(--panel-color)] rounded-full px-2 py-1 border border-white/5 backdrop-blur-md">
                    <button 
                        v-for="lang in languages" 
                        :key="lang.code"
                        @click="setLang(lang.code)"
                        class="px-3 py-1 rounded-full text-xs font-mono transition-all"
                        :class="currentLang === lang.code ? 'bg-[var(--caret-color)] text-[var(--bg-color)] font-bold' : 'hover:opacity-70 text-[var(--sub-color)]'"
                    >
                        {{ lang.label }}
                    </button>
                </div>

                <!-- Theme Switcher -->
                <button 
                    @click="setTheme(currentTheme === 'dark' ? 'light' : 'dark')"
                    class="p-2 rounded-full bg-[var(--panel-color)] border border-white/5 backdrop-blur-md hover:bg-white/10 transition-all text-xl"
                >
                    {{ currentTheme === 'dark' ? '☀️' : '🌙' }}
                </button>

                <!-- Login/Logout -->
                <div class="flex items-center gap-4 font-mono text-sm">
                    <div v-if="$page.props.auth.user" class="flex items-center gap-4">
                        <Link href="/logout" method="post" as="button" class="opacity-60 hover:text-[var(--error-color)] transition-colors">
                            {{ t('logout') }}
                        </Link>
                    </div>
                    <div v-else class="flex items-center gap-4">
                        <Link href="/login" class="opacity-60 hover:text-[var(--caret-color)] transition-colors">{{ t('login') }}</Link>
                        <Link href="/register" class="bg-[var(--caret-color)] text-[var(--bg-color)] px-4 py-1.5 rounded-full font-bold hover:scale-105 active:scale-95 transition-all">
                            {{ t('register') }}
                        </Link>
                    </div>
                </div>
            </div>
        </header>

        <main class="container mx-auto px-6">
            <slot />
        </main>
    </div>
</template>

<style>
/* Global colors based on variables */
.text-main { color: var(--main-color); }
.text-sub { color: var(--sub-color); }
.text-caret { color: var(--caret-color); }
.text-error { color: var(--error-color); }
.bg-main { background-color: var(--bg-color); }
.bg-panel { background-color: var(--panel-color); }
</style>