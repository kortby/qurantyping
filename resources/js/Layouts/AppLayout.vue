<script setup>
import { ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useSettings } from '../useSettings';
import FeedbackModal from '../Components/FeedbackModal.vue';
import AuthWarningModal from '../Components/AuthWarningModal.vue';
import Banner from '../Components/Banner.vue';

const { currentLang, currentTheme, setLang, setTheme, t } = useSettings();
const mobileMenuOpen = ref(false);

const languages = [
    { code: 'en', label: 'EN' },
    { code: 'fr', label: 'FR' },
    { code: 'ar', label: 'AR' }
];

const page = usePage();
const showFeedbackModal = ref(false);
const showAuthWarningModal = ref(false);

const handleFeedbackClick = () => {
    if (page.props.auth.user) {
        showFeedbackModal.value = true;
    } else {
        showAuthWarningModal.value = true;
    }
};

</script>

<template>
    <div class="min-h-screen bg-[var(--bg-color)] text-[var(--main-color)] antialiased transition-colors duration-300">
        <Banner />
        <header class="sticky top-0 z-50 w-full bg-[var(--panel-color)]/80 backdrop-blur-xl border-b border-[var(--border-color)] transition-all duration-300">
            <div class="container mx-auto px-6 py-4 flex justify-between items-center relative">
                <!-- Header Glow Effect -->
                <div class="absolute -bottom-px left-0 right-0 h-px bg-gradient-to-r from-transparent via-[var(--caret-color)] to-transparent opacity-20"></div>
                
                <div class="flex items-center gap-12">
                    <Link href="/" class="flex items-center gap-4 group">
                        <div class="w-14 h-14 flex items-center justify-center bg-[var(--panel-color)] border border-[var(--border-color)] rounded-2xl shadow-xl transition-all group-hover:rotate-12 group-hover:scale-110">
                            <span class="text-3xl filter drop-shadow-lg">📖</span>
                        </div>
                        <span class="text-2xl md:text-3xl font-cinzel font-bold tracking-widest text-[var(--caret-color)] group-hover:opacity-80 transition-opacity whitespace-nowrap">
                            {{ t('title') }}
                        </span>
                    </Link>

                    <!-- Nav Links Desktop -->
                    <nav class="hidden lg:flex items-center gap-10 font-cinzel text-xs uppercase tracking-[0.3em] opacity-70">
                        <Link href="/" class="hover:text-[var(--caret-color)] hover:opacity-100 transition-all border-b border-transparent hover:border-[var(--caret-color)] pb-1">{{ t('navigation.home') }}</Link>
                        <Link href="/leaderboard" class="hover:text-[var(--caret-color)] hover:opacity-100 transition-all border-b border-transparent hover:border-[var(--caret-color)] pb-1">{{ t('leaderboard') }}</Link>
                        <template v-if="$page.props.auth.user">
                            <Link href="/dashboard" class="hover:text-[var(--caret-color)] hover:opacity-100 transition-all border-b border-transparent hover:border-[var(--caret-color)] pb-1">{{ t('navigation.dashboard') }}</Link>
                            <Link href="/user/profile" class="hover:text-[var(--caret-color)] hover:opacity-100 transition-all border-b border-transparent hover:border-[var(--caret-color)] pb-1">{{ t('navigation.profile') }}</Link>
                        </template>
                    </nav>
                </div>

                <div class="flex items-center gap-4 md:gap-10">
                    <!-- Donate Button Desktop -->
                    <a href="https://buy.stripe.com/dRmdRa1546e60jI2jZenS01" target="_blank" 
                       class="hidden xl:flex items-center gap-3 bg-[var(--caret-color)] text-[var(--bg-color)] px-6 py-3 rounded-2xl font-cinzel font-bold text-xs uppercase tracking-widest hover:scale-105 active:scale-95 transition-all shadow-xl shadow-emerald-950/40">
                        <span class="text-xl">❤️</span>
                        {{ t('donate') }}
                    </a>

                    <!-- Desktop Actions -->
                    <div class="hidden md:flex items-center gap-6">
                        <!-- Lang Switcher -->
                        <div class="flex items-center gap-1 bg-[var(--panel-color)] rounded-xl px-2 py-1 border border-[var(--border-color)] backdrop-blur-md">
                            <button v-for="lang in languages" :key="lang.code" @click="setLang(lang.code)"
                                class="px-4 py-2 rounded-lg text-xs font-cinzel font-bold transition-all"
                                :class="currentLang === lang.code ? 'bg-[var(--caret-color)] text-[var(--bg-color)]' : 'hover:opacity-70 text-[var(--sub-color)] opacity-60'">
                                {{ lang.label }}
                            </button>
                        </div>

                        <!-- Theme Switcher -->
                        <button @click="setTheme(currentTheme === 'dark' ? 'light' : 'dark')"
                            class="p-3 rounded-xl bg-[var(--panel-color)] border border-[var(--border-color)] backdrop-blur-md hover:bg-[var(--caret-color)] hover:text-[var(--bg-color)] transition-all text-xl shadow-lg">
                            {{ currentTheme === 'dark' ? '🌙' : '☀️' }}
                        </button>
                    </div>

                    <!-- Desktop Auth -->
                    <div class="hidden lg:flex items-center gap-8 font-cinzel text-xs uppercase tracking-widest">
                        <div v-if="$page.props.auth.user" class="flex items-center gap-6">
                            <Link href="/user/profile" class="flex flex-col items-end group text-right">
                                <span class="opacity-60 group-hover:text-[var(--caret-color)] group-hover:opacity-100 transition-all font-bold">
                                    {{ t('navigation.profile') }}
                                </span>
                                <span class="text-[9px] opacity-40 group-hover:text-[var(--caret-color)] group-hover:opacity-70 transition-all mt-0.5 font-mono normal-case tracking-normal truncate max-w-[100px]">
                                    {{ $page.props.auth.user.name }}
                                </span>
                            </Link>
                            <Link href="/logout" method="post" as="button" class="opacity-60 hover:text-[var(--error-color)] hover:opacity-100 transition-all font-bold">
                                {{ t('logout') }}
                            </Link>
                        </div>
                        <div v-else class="flex items-center gap-8">
                            <Link href="/login" class="opacity-60 hover:text-[var(--caret-color)] hover:opacity-100 transition-all font-bold">{{ t('login') }}</Link>
                            <Link href="/register" class="bg-[var(--caret-color)] text-[var(--bg-color)] px-8 py-3 rounded-xl font-bold hover:scale-105 active:scale-95 transition-all shadow-xl shadow-emerald-950/20">
                                {{ t('register') }}
                            </Link>
                        </div>
                    </div>

                    <!-- Mobile Menu Button -->
                    <button @click="mobileMenuOpen = !mobileMenuOpen" 
                        class="lg:hidden p-4 rounded-2xl bg-[var(--panel-color)] border border-[var(--border-color)] text-[var(--caret-color)] hover:bg-[var(--caret-color)] hover:text-[var(--bg-color)] transition-all shadow-lg active:scale-90">
                        <svg v-if="!mobileMenuOpen" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16m-7 6h7" />
                        </svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </header>

        <!-- Mobile Menu Overlay -->
        <transition name="fade">
            <div v-if="mobileMenuOpen" class="fixed inset-0 z-40 lg:hidden">
                <div class="absolute inset-0 bg-[var(--bg-color)]/95 backdrop-blur-xl"></div>
                <div class="relative h-full flex flex-col p-8 pt-32">
                    <nav class="flex flex-col gap-8 font-cinzel text-2xl font-bold tracking-widest text-center">
                        <Link href="/" @click="mobileMenuOpen = false" class="hover:text-[var(--caret-color)] transition-colors">{{ t('navigation.home') }}</Link>
                        <Link href="/leaderboard" @click="mobileMenuOpen = false" class="hover:text-[var(--caret-color)] transition-colors">{{ t('leaderboard') }}</Link>
                        <template v-if="$page.props.auth.user">
                            <Link href="/dashboard" @click="mobileMenuOpen = false" class="hover:text-[var(--caret-color)] transition-colors">{{ t('navigation.dashboard') }}</Link>
                            <Link href="/user/profile" @click="mobileMenuOpen = false" class="hover:text-[var(--caret-color)] transition-colors">{{ t('navigation.profile') }}</Link>
                        </template>
                    </nav>

                    <div class="mt-auto flex flex-col gap-6">
                        <div class="flex justify-center gap-4">
                            <button v-for="lang in languages" :key="lang.code" @click="setLang(lang.code)"
                                class="px-6 py-3 rounded-xl text-sm font-cinzel font-bold border border-[var(--border-color)]"
                                :class="currentLang === lang.code ? 'bg-[var(--caret-color)] text-[var(--bg-color)]' : 'bg-[var(--panel-color)] text-[var(--sub-color)]'">
                                {{ lang.label }}
                            </button>
                        </div>
                        <button @click="setTheme(currentTheme === 'dark' ? 'light' : 'dark')"
                            class="w-full py-4 rounded-xl bg-[var(--panel-color)] border border-[var(--border-color)] text-xl font-cinzel">
                            {{ currentTheme === 'dark' ? '🌙 Dark Mode' : '☀️ Light Mode' }}
                        </button>
                        <hr class="border-[var(--border-color)] opacity-20 my-4" />
                        <div v-if="$page.props.auth.user" class="flex flex-col gap-4">
                            <Link href="/logout" method="post" as="button" @click="mobileMenuOpen = false" class="w-full py-4 rounded-xl border border-[var(--error-color)] text-[var(--error-color)] font-bold uppercase tracking-widest text-sm">
                                {{ t('logout') }}
                            </Link>
                        </div>
                        <div v-else class="flex flex-col gap-4">
                            <Link href="/login" @click="mobileMenuOpen = false" class="w-full py-4 rounded-xl border border-[var(--border-color)] text-[var(--main-color)] text-center font-bold uppercase tracking-widest text-sm">
                                {{ t('login') }}
                            </Link>
                            <Link href="/register" @click="mobileMenuOpen = false" class="w-full py-4 rounded-xl bg-[var(--caret-color)] text-[var(--bg-color)] text-center font-bold uppercase tracking-widest text-sm shadow-xl shadow-emerald-950/20">
                                {{ t('register') }}
                            </Link>
                        </div>
                        <a href="https://buy.stripe.com/dRmdRa1546e60jI2jZenS01" target="_blank" 
                           class="w-full py-4 rounded-xl bg-red-600 text-white text-center font-bold uppercase tracking-widest text-sm shadow-xl shadow-red-950/20 flex items-center justify-center gap-3 mt-4">
                            <span>❤️</span> {{ t('donate') }}
                        </a>
                    </div>
                </div>
            </div>
        </transition>

        <main class="container mx-auto px-6 pb-12">
            <slot />
        </main>

        <!-- Islamic Footer -->
        <footer class="bg-[var(--panel-color)] border-t border-[var(--border-color)] pt-12 pb-10 mt-12 backdrop-blur-xl relative overflow-hidden">
            <!-- Decorative Background Element -->
            <div class="absolute top-0 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] border border-[var(--border-color)] opacity-[0.03] rounded-full"></div>
            
            <div class="container mx-auto px-6 relative z-10">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-16 mb-20">
                    <div class="col-span-1 md:col-span-1">
                        <Link href="/" class="flex items-center gap-4 mb-6 group">
                            <span class="text-3xl filter drop-shadow-lg group-hover:scale-110 transition-transform">📖</span>
                            <span class="text-2xl font-cinzel font-bold tracking-widest text-[var(--caret-color)]">{{ t('title') }}</span>
                        </Link>
                        <p class="text-sm border-l-2 border-[var(--caret-color)]/20 pl-4 py-1 italic opacity-60 leading-relaxed font-serif">
                            {{ t('footer_tagline') }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-6">
                        <h4 class="font-cinzel text-[10px] uppercase tracking-[0.4em] text-[var(--caret-color)]">{{ t('navigation.title') || 'Navigation' }}</h4>
                        <nav class="flex flex-col gap-4 font-cinzel text-xs uppercase tracking-widest opacity-60">
                            <Link href="/" class="hover:text-[var(--caret-color)] hover:opacity-100 transition-colors">{{ t('navigation.home') }}</Link>
                            <Link href="/dashboard" class="hover:text-[var(--caret-color)] hover:opacity-100 transition-colors">{{ t('navigation.dashboard') }}</Link>
                            <Link href="/leaderboard" class="hover:text-[var(--caret-color)] hover:opacity-100 transition-colors">{{ t('leaderboard') }}</Link>
                        </nav>
                    </div>

                    <div class="flex flex-col gap-6">
                        <h4 class="font-cinzel text-[10px] uppercase tracking-[0.4em] text-[var(--caret-color)]">{{ t('resources') }}</h4>
                        <nav class="flex flex-col gap-4 font-cinzel text-xs uppercase tracking-widest opacity-60">
                            <Link href="/" class="hover:text-[var(--caret-color)] hover:opacity-100 transition-colors">{{ t('surah_list') }}</Link>
                            <Link href="/leaderboard" class="hover:text-[var(--caret-color)] hover:opacity-100 transition-colors">{{ t('statistics') }}</Link>
                            <Link href="/work-in-progress" class="hover:text-[var(--caret-color)] hover:opacity-100 transition-colors">{{ t('help_center') }}</Link>
                        </nav>
                    </div>

                    <div class="flex flex-col gap-8">
                        <h4 class="font-cinzel text-[10px] uppercase tracking-[0.4em] text-[var(--caret-color)]">{{ t('support_project') }}</h4>
                        <p class="text-xs opacity-60 leading-relaxed">{{ t('support_message') }}</p>
                        <a href="https://buy.stripe.com/dRmdRa1546e60jI2jZenS01" target="_blank" 
                           class="flex items-center justify-center gap-4 bg-[var(--caret-color)] text-[var(--bg-color)] px-8 py-4 rounded-2xl font-cinzel font-bold text-sm uppercase tracking-widest hover:scale-105 active:scale-95 transition-all shadow-2xl shadow-emerald-950/40">
                            <span class="text-2xl">❤️</span>
                            {{ t('support_now') }}
                        </a>
                    </div>
                </div>

                <div class="pt-10 border-t border-[var(--border-color)] flex flex-col md:flex-row justify-between items-center gap-6 opacity-40">
                    <div class="flex items-center gap-4">
                        <p class="font-mono text-[10px] tracking-tighter uppercase">{{ t('copyright') }}</p>
                        <span class="px-2 py-0.5 rounded-md bg-[var(--caret-color)]/10 border border-[var(--caret-color)]/20 text-[var(--caret-color)] font-mono text-[8px] tracking-widest font-bold uppercase">v{{ $page.props.features.app_version }}</span>
                    </div>
                    <div class="flex gap-8 font-cinzel text-[10px] uppercase tracking-widest font-bold">
                        <button @click="handleFeedbackClick" class="hover:underline flex items-center gap-2">
                            <span>💬</span> {{ t('give_feedback') }}
                        </button>
                        <Link href="/privacy-policy" class="hover:underline">{{ t('auth.privacy_policy') }}</Link>
                        <Link href="/terms-of-service" class="hover:underline">{{ t('auth.terms_of_service') }}</Link>
                    </div>
                </div>
            </div>
        </footer>

        <FeedbackModal :show="showFeedbackModal" @close="showFeedbackModal = false" />
        <AuthWarningModal :show="showAuthWarningModal" @close="showAuthWarningModal = false" />
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