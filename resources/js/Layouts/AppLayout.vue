<script setup>
import { ref, watch, onMounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import { useSettings } from '../useSettings';
import FeedbackModal from '../Components/FeedbackModal.vue';
import AuthWarningModal from '../Components/AuthWarningModal.vue';
import Banner from '../Components/Banner.vue';
import SocialButtons from '@/Components/SocialButtons.vue';
import StreakBadge from '@/Components/StreakBadge.vue';

const { currentLang, currentTheme, setLang, setTheme, t } = useSettings();
const mobileMenuOpen = ref(false);
const userMenuOpen = ref(false);

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

// Close dropdown on click outside
if (typeof window !== 'undefined') {
    window.addEventListener('click', (e) => {
        if (!e.target.closest('.user-menu-container')) {
            userMenuOpen.value = false;
        }
    });

    const syncCachedTest = () => {
        if (!page.props.auth?.user) return;

        const cachedTest = localStorage.getItem('cached_typing_test');
        if (!cachedTest) return;

        try {
            const data = JSON.parse(cachedTest);
            axios.post('/test/complete', data)
                .then(() => {
                    localStorage.removeItem('cached_typing_test');
                    // Refresh current page data to show new test results/stats
                    router.reload({ 
                        only: ['results', 'bestWpm', 'averageWpm', 'personalBestWpm', 'topScorers'],
                        preserveScroll: true,
                        preserveState: true
                    });
                })
                .catch(e => {
                    console.error('Failed to sync cached test:', e);
                    // If it's a validation error (422) or other definitive failure, remove it
                    if (e.response && (e.response.status === 422 || e.response.status === 401)) {
                        localStorage.removeItem('cached_typing_test');
                    }
                });
        } catch (e) {
            console.error('Failed to parse cached test:', e);
            localStorage.removeItem('cached_typing_test');
        }
    };

    onMounted(syncCachedTest);

    // Watch for auth changes (e.g., after login/registration without a full page reload)
    watch(() => page.props.auth?.user, (user) => {
        if (user) syncCachedTest();
    }, { immediate: true });
}

</script>

<template>
    <div class="min-h-screen bg-[var(--bg-color)] text-[var(--main-color)] antialiased transition-colors duration-300">
        <Banner />

        <!-- Impersonation Banner -->
        <div v-if="$page.props.auth.impersonating"
            class="sticky top-0 z-[60] w-full bg-[var(--lapis-color)] text-white px-6 py-2 flex items-center justify-center gap-4 text-xs uppercase tracking-[0.12em]">
            <span>{{ t('navigation.impersonating_as') }} {{ $page.props.auth.user.name }}</span>
            <Link href="/impersonate/leave" method="post" as="button"
                class="px-3 py-1 border border-white/60 hover:bg-white/10 transition-colors">
                {{ t('navigation.stop_impersonating') }}
            </Link>
        </div>
        <header class="sticky top-0 z-50 w-full bg-[var(--bg-color)] border-b border-[var(--rule-color)]">
            <div class="container mx-auto px-6 py-4 flex justify-between items-center">
                <div class="flex items-center gap-6 xl:gap-10 min-w-0">
                    <Link href="/" class="flex items-baseline gap-2.5 group shrink-0">
                        <span class="text-2xl md:text-[1.7rem] font-cinzel font-semibold text-[var(--main-color)] group-hover:text-[var(--caret-color)] transition-colors whitespace-nowrap">
                            {{ t('title') }}
                        </span>
                        <span class="hidden sm:inline text-[10px] uppercase tracking-[0.25em] text-[var(--sub-color)]">مصحف</span>
                    </Link>

                    <!-- Nav Links Desktop -->
                    <nav class="hidden lg:flex flex-wrap items-center gap-x-5 xl:gap-x-7 gap-y-1 text-sm text-[var(--sub-color)] [&>a]:whitespace-nowrap">
                        <Link href="/" class="hover:text-[var(--main-color)] transition-colors">{{ t('navigation.home') }}</Link>
                        <Link href="/leaderboard" class="hover:text-[var(--main-color)] transition-colors">{{ t('leaderboard') }}</Link>
                        <template v-if="$page.props.auth.user">
                            <Link href="/dashboard" class="hover:text-[var(--main-color)] transition-colors">{{ t('navigation.dashboard') }}</Link>
                            <Link href="/hifz" class="hover:text-[var(--main-color)] transition-colors">
                                {{ t('navigation.hifz') }}<span v-if="$page.props.auth.hifz_due" class="ml-1 text-[var(--caret-color)]">{{ $page.props.auth.hifz_due }}</span>
                            </Link>
                            <Link href="/drills" class="hover:text-[var(--main-color)] transition-colors">{{ t('navigation.drills') }}</Link>
                            <Link href="/races" class="hover:text-[var(--main-color)] transition-colors">{{ t('navigation.races') }}</Link>
                            <Link href="/friends" class="hover:text-[var(--main-color)] transition-colors">
                                {{ t('navigation.friends') }}<span v-if="$page.props.auth.friend_requests" class="ml-1 text-[var(--caret-color)]">{{ $page.props.auth.friend_requests }}</span>
                            </Link>
                            <Link href="/map" class="hover:text-[var(--main-color)] transition-colors">{{ t('navigation.map') }}</Link>
                            <Link href="/badges" class="hover:text-[var(--main-color)] transition-colors">{{ t('navigation.badges') }}</Link>
                            <Link v-if="$page.props.auth.user.is_super_admin" href="/admin/users" class="text-[var(--lapis-color)] hover:opacity-80 transition-opacity">{{ t('navigation.admin') }}</Link>
                        </template>
                    </nav>
                </div>

                <div class="flex items-center gap-3 md:gap-4 xl:gap-6 shrink-0">
                    <!-- Donate Button Desktop -->
                    <a href="https://buy.stripe.com/dRmdRa1546e60jI2jZenS01" target="_blank"
                       class="hidden xl:inline-flex items-center whitespace-nowrap border border-[var(--caret-color)] text-[var(--caret-color)] px-4 py-2 text-xs font-cinzel font-semibold uppercase tracking-[0.12em] hover:bg-[var(--caret-color)] hover:text-[var(--bg-color)] transition-colors">
                        {{ t('donate') }}
                    </a>

                    <!-- Desktop Actions -->
                    <div class="hidden md:flex items-center gap-4">
                        <!-- Lang Switcher -->
                        <div class="flex items-center border border-[var(--border-color)] divide-x divide-[var(--border-color)]">
                            <button v-for="lang in languages" :key="lang.code" @click="setLang(lang.code)"
                                class="px-3 py-1.5 text-[11px] font-mono transition-colors"
                                :class="currentLang === lang.code ? 'bg-[var(--caret-color)] text-[var(--bg-color)]' : 'text-[var(--sub-color)] hover:text-[var(--main-color)]'">
                                {{ lang.label }}
                            </button>
                        </div>

                        <!-- Theme Switcher -->
                        <button @click="setTheme(currentTheme === 'dark' ? 'light' : 'dark')"
                            :aria-label="currentTheme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
                            class="px-3 py-1.5 border border-[var(--border-color)] text-[11px] font-mono uppercase tracking-[0.15em] text-[var(--sub-color)] hover:text-[var(--main-color)] transition-colors">
                            {{ currentTheme === 'dark' ? 'Light' : 'Dark' }}
                        </button>
                    </div>

                    <!-- Desktop Auth -->
                    <div class="hidden lg:flex items-center gap-6 text-sm relative user-menu-container">
                        <StreakBadge v-if="$page.props.auth.user" class="text-sm" />
                        <div v-if="$page.props.auth.user" class="relative">
                            <button @click="userMenuOpen = !userMenuOpen"
                                class="flex items-center gap-2 px-3 py-1.5 border border-[var(--border-color)] hover:border-[var(--caret-color)] transition-colors group">
                                <span class="opacity-80 group-hover:text-[var(--main-color)] transition-colors truncate max-w-[120px]">
                                    {{ $page.props.auth.user.name }}
                                </span>
                                <span class="text-[10px] transition-transform duration-200" :class="{ 'rotate-180': userMenuOpen }">▾</span>
                            </button>

                            <transition name="dropdown">
                                <div v-if="userMenuOpen"
                                    class="absolute mt-2 w-56 bg-[var(--panel-color)] border border-[var(--border-color)] py-1 z-[60]"
                                    :class="currentLang === 'ar' ? 'left-0' : 'right-0'">
                                    <div class="px-4 py-3 border-b border-[var(--border-color)]">
                                        <p class="text-[10px] uppercase tracking-[0.15em] text-[var(--sub-color)] mb-0.5">{{ t('navigation.logged_in_as') }}</p>
                                        <p class="text-xs truncate text-[var(--main-color)]">{{ $page.props.auth.user.email }}</p>
                                    </div>
                                    <Link href="/user/profile" @click="userMenuOpen = false"
                                        class="block px-4 py-2.5 text-[var(--sub-color)] hover:bg-[var(--caret-color)]/10 hover:text-[var(--main-color)] transition-colors">
                                        {{ t('navigation.profile') }}
                                    </Link>
                                    <Link href="/dashboard" @click="userMenuOpen = false"
                                        class="block px-4 py-2.5 text-[var(--sub-color)] hover:bg-[var(--caret-color)]/10 hover:text-[var(--main-color)] transition-colors">
                                        {{ t('navigation.dashboard') }}
                                    </Link>
                                    <Link v-if="$page.props.auth.user.is_super_admin" href="/admin/users" @click="userMenuOpen = false"
                                        class="block px-4 py-2.5 text-[var(--lapis-color)] hover:bg-[var(--caret-color)]/10 transition-colors">
                                        {{ t('navigation.admin') }}
                                    </Link>
                                    <Link v-if="$page.props.auth.user.is_super_admin" href="/admin/feedback" @click="userMenuOpen = false"
                                        class="block px-4 py-2.5 text-[var(--lapis-color)] hover:bg-[var(--caret-color)]/10 transition-colors">
                                        {{ t('navigation.feedback') }}
                                    </Link>
                                    <div class="border-t border-[var(--border-color)] mt-1">
                                        <Link href="/logout" method="post" as="button"
                                            class="w-full text-left block px-4 py-2.5 text-[var(--sub-color)] hover:text-[var(--error-color)] transition-colors">
                                            {{ t('logout') }}
                                        </Link>
                                    </div>
                                </div>
                            </transition>
                        </div>
                        <div v-else class="flex items-center gap-5">
                            <Link href="/login" class="text-[var(--sub-color)] hover:text-[var(--main-color)] transition-colors">{{ t('login') }}</Link>
                            <Link href="/register" class="bg-[var(--caret-color)] text-[var(--bg-color)] px-5 py-2 font-cinzel font-semibold hover:opacity-90 transition-opacity">
                                {{ t('register') }}
                            </Link>
                        </div>
                    </div>

                    <!-- Mobile Menu Button -->
                    <button @click="mobileMenuOpen = !mobileMenuOpen" aria-label="Menu"
                        class="lg:hidden p-2.5 border border-[var(--border-color)] text-[var(--main-color)] hover:border-[var(--caret-color)] transition-colors">
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
                <div class="absolute inset-0 bg-[var(--bg-color)]"></div>
                <div class="relative h-full flex flex-col p-6 pt-24">
                    <nav class="flex flex-col gap-5 font-cinzel text-xl text-center">
                        <Link href="/" @click="mobileMenuOpen = false" class="hover:text-[var(--caret-color)] transition-colors">{{ t('navigation.home') }}</Link>
                        <Link href="/leaderboard" @click="mobileMenuOpen = false" class="hover:text-[var(--caret-color)] transition-colors">{{ t('leaderboard') }}</Link>
                        <template v-if="$page.props.auth.user">
                            <Link href="/dashboard" @click="mobileMenuOpen = false" class="hover:text-[var(--caret-color)] transition-colors">{{ t('navigation.dashboard') }}</Link>
                            <Link href="/hifz" @click="mobileMenuOpen = false" class="hover:text-[var(--caret-color)] transition-colors">
                                {{ t('navigation.hifz') }}<span v-if="$page.props.auth.hifz_due" class="ml-1 text-[var(--caret-color)]">{{ $page.props.auth.hifz_due }}</span>
                            </Link>
                            <Link href="/drills" @click="mobileMenuOpen = false" class="hover:text-[var(--caret-color)] transition-colors">{{ t('navigation.drills') }}</Link>
                            <Link href="/races" @click="mobileMenuOpen = false" class="hover:text-[var(--caret-color)] transition-colors">{{ t('navigation.races') }}</Link>
                            <Link href="/friends" @click="mobileMenuOpen = false" class="hover:text-[var(--caret-color)] transition-colors">
                                {{ t('navigation.friends') }}<span v-if="$page.props.auth.friend_requests" class="ml-1 text-[var(--caret-color)]">{{ $page.props.auth.friend_requests }}</span>
                            </Link>
                            <Link href="/map" @click="mobileMenuOpen = false" class="hover:text-[var(--caret-color)] transition-colors">{{ t('navigation.map') }}</Link>
                            <Link href="/badges" @click="mobileMenuOpen = false" class="hover:text-[var(--caret-color)] transition-colors">{{ t('navigation.badges') }}</Link>
                            <Link href="/user/profile" @click="mobileMenuOpen = false" class="hover:text-[var(--caret-color)] transition-colors">{{ t('navigation.profile') }}</Link>
                            <Link v-if="$page.props.auth.user.is_super_admin" href="/admin/users" @click="mobileMenuOpen = false" class="text-[var(--lapis-color)] transition-colors">{{ t('navigation.admin') }}</Link>
                            <Link v-if="$page.props.auth.user.is_super_admin" href="/admin/feedback" @click="mobileMenuOpen = false" class="text-[var(--lapis-color)] transition-colors">{{ t('navigation.feedback') }}</Link>
                            <StreakBadge class="justify-center pt-1" />
                        </template>
                    </nav>

                    <div class="mt-10 flex flex-col gap-4">
                        <div class="flex justify-center border border-[var(--border-color)] divide-x divide-[var(--border-color)] self-center">
                            <button v-for="lang in languages" :key="lang.code" @click="setLang(lang.code)"
                                class="px-4 py-2 text-xs font-mono"
                                :class="currentLang === lang.code ? 'bg-[var(--caret-color)] text-[var(--bg-color)]' : 'text-[var(--sub-color)]'">
                                {{ lang.label }}
                            </button>
                        </div>
                        <button @click="setTheme(currentTheme === 'dark' ? 'light' : 'dark')"
                            class="w-full py-3 border border-[var(--border-color)] text-sm font-cinzel">
                            {{ currentTheme === 'dark' ? t('light_mode') : t('dark_mode') }}
                        </button>
                        <hr class="border-[var(--border-color)] my-2" />
                        <div v-if="$page.props.auth.user" class="flex flex-col gap-4">
                            <div class="px-4 py-4 border border-[var(--border-color)]">
                                <p class="text-[10px] uppercase tracking-[0.15em] text-[var(--sub-color)] mb-0.5">{{ t('navigation.logged_in_as') }}</p>
                                <p class="text-lg truncate text-[var(--main-color)] font-cinzel">{{ $page.props.auth.user.name }}</p>
                                <p class="text-xs text-[var(--sub-color)] truncate">{{ $page.props.auth.user.email }}</p>
                            </div>
                            <Link href="/logout" method="post" as="button" @click="mobileMenuOpen = false" class="w-full py-3 border border-[var(--error-color)] text-[var(--error-color)] font-cinzel text-sm">
                                {{ t('logout') }}
                            </Link>
                        </div>
                        <div v-else class="flex flex-col gap-4">
                            <div v-if="$page.props.social?.has_any">
                                <SocialButtons />
                                <div class="relative flex items-center justify-center my-4">
                                    <div class="flex-grow border-t border-[var(--border-color)]"></div>
                                    <span class="flex-shrink mx-4 text-[9px] text-[var(--sub-color)] uppercase tracking-[0.2em]">
                                        {{ t('or_login_with_email') }}
                                    </span>
                                    <div class="flex-grow border-t border-[var(--border-color)]"></div>
                                </div>
                            </div>

                            <Link href="/login" @click="mobileMenuOpen = false" class="w-full py-3 border border-[var(--border-color)] text-[var(--main-color)] text-center font-cinzel text-sm">
                                {{ t('login') }}
                            </Link>
                            <Link href="/register" @click="mobileMenuOpen = false" class="w-full py-3 bg-[var(--caret-color)] text-[var(--bg-color)] text-center font-cinzel font-semibold text-sm">
                                {{ t('register') }}
                            </Link>
                        </div>
                        <a href="https://buy.stripe.com/dRmdRa1546e60jI2jZenS01" target="_blank"
                           class="w-full py-3 border border-[var(--caret-color)] text-[var(--caret-color)] text-center font-cinzel font-semibold uppercase tracking-[0.12em] text-xs mt-2">
                            {{ t('donate') }}
                        </a>
                    </div>
                </div>
            </div>
        </transition>

        <main class="container mx-auto px-6 pb-16">
            <slot />
        </main>

        <footer class="border-t border-[var(--rule-color)] pt-12 pb-10 mt-16">
            <div class="container mx-auto px-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-14">
                    <div>
                        <Link href="/" class="inline-flex items-baseline gap-2 mb-4">
                            <span class="text-xl font-cinzel font-semibold text-[var(--main-color)]">{{ t('title') }}</span>
                            <span class="text-[10px] uppercase tracking-[0.25em] text-[var(--sub-color)]">مصحف</span>
                        </Link>
                        <p class="text-sm border-l border-[var(--rule-color)] pl-4 text-[var(--sub-color)] leading-relaxed">
                            {{ t('footer_tagline') }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-4">
                        <h4 class="text-[10px] uppercase tracking-[0.3em] text-[var(--caret-color)]">{{ t('navigation.title') }}</h4>
                        <nav class="flex flex-col gap-3 text-sm text-[var(--sub-color)]">
                            <Link href="/" class="hover:text-[var(--main-color)] transition-colors">{{ t('navigation.home') }}</Link>
                            <Link href="/dashboard" class="hover:text-[var(--main-color)] transition-colors">{{ t('navigation.dashboard') }}</Link>
                            <Link href="/leaderboard" class="hover:text-[var(--main-color)] transition-colors">{{ t('leaderboard') }}</Link>
                        </nav>
                    </div>

                    <div class="flex flex-col gap-4">
                        <h4 class="text-[10px] uppercase tracking-[0.3em] text-[var(--caret-color)]">{{ t('resources') }}</h4>
                        <nav class="flex flex-col gap-3 text-sm text-[var(--sub-color)]">
                            <Link href="/" class="hover:text-[var(--main-color)] transition-colors">{{ t('surah_list') }}</Link>
                            <Link href="/leaderboard" class="hover:text-[var(--main-color)] transition-colors">{{ t('statistics') }}</Link>
                            <Link href="/work-in-progress" class="hover:text-[var(--main-color)] transition-colors">{{ t('help_center') }}</Link>
                        </nav>
                    </div>

                    <div class="flex flex-col gap-4">
                        <h4 class="text-[10px] uppercase tracking-[0.3em] text-[var(--caret-color)]">{{ t('support_project') }}</h4>
                        <p class="text-sm text-[var(--sub-color)] leading-relaxed">{{ t('support_message') }}</p>
                        <a href="https://buy.stripe.com/dRmdRa1546e60jI2jZenS01" target="_blank"
                           class="inline-flex items-center justify-center bg-[var(--caret-color)] text-[var(--bg-color)] px-6 py-3 font-cinzel font-semibold text-sm uppercase tracking-[0.12em] hover:opacity-90 transition-opacity">
                            {{ t('support_now') }}
                        </a>
                    </div>
                </div>

                <div class="pt-8 border-t border-[var(--border-color)] flex flex-col md:flex-row justify-between items-center gap-4 text-[var(--sub-color)]">
                    <div class="flex items-center gap-3">
                        <p class="font-mono text-[10px] uppercase tracking-[0.1em]">{{ t('copyright') }}</p>
                        <span class="px-1.5 py-0.5 border border-[var(--border-color)] font-mono text-[8px] tracking-[0.15em] uppercase">v{{ $page.props.features.app_version }}</span>
                    </div>
                    <div class="flex gap-6 text-[11px] uppercase tracking-[0.12em]">
                        <button @click="handleFeedbackClick" class="hover:text-[var(--main-color)] transition-colors">{{ t('give_feedback') }}</button>
                        <Link href="/privacy-policy" class="hover:text-[var(--main-color)] transition-colors">{{ t('auth.privacy_policy') }}</Link>
                        <Link href="/terms-of-service" class="hover:text-[var(--main-color)] transition-colors">{{ t('auth.terms_of_service') }}</Link>
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

/* Dropdown Animation */
.dropdown-enter-active {
    transition: all 0.3s cubic-bezier(0.19, 1, 0.22, 1);
}
.dropdown-leave-active {
    transition: all 0.2s cubic-bezier(0.47, 0, 0.745, 0.715);
}
.dropdown-enter-from,
.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-10px) scale(0.95);
}
</style>