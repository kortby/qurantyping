<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSettings } from '../../useSettings';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue';
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue';
import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm.vue';

const { t } = useSettings();

defineProps({
    confirmsTwoFactorAuthentication: Boolean,
    sessions: Array,
});

const activeTab = ref('profile'); // profile, security, account

const tabs = [
    { id: 'profile', label: 'navigation.profile', icon: '👤' },
    { id: 'security', label: 'auth.secure_area', icon: '🔒' },
    { id: 'account', label: 'delete_account', icon: '⚙️' },
];
</script>

<template>
    <Head :title="t('navigation.profile')" />

    <AppLayout>
        <div class="py-12 animate-fade-in min-h-screen">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-12">
                    <h1 class="text-5xl font-cinzel text-[var(--caret-color)] font-bold mb-3 tracking-wider">{{ t('navigation.profile') }}</h1>
                    <p class="text-[var(--sub-color)] font-mono text-sm uppercase tracking-[0.4em] opacity-80">{{ t('navigation.profile_subtitle') }}</p>
                </div>

                <div class="flex flex-col lg:flex-row gap-12">
                    <!-- Sidebar Navigation -->
                    <aside class="w-full lg:w-64 shrink-0">
                        <nav class="flex lg:flex-col gap-2 p-2 bg-[var(--panel-color)] rounded-3xl border border-[var(--border-color)] backdrop-blur-md shadow-xl overflow-x-auto lg:overflow-visible">
                            <button 
                                v-for="tab in tabs" 
                                :key="tab.id"
                                @click="activeTab = tab.id"
                                class="flex items-center gap-4 px-6 py-4 rounded-2xl font-cinzel text-xs font-bold uppercase tracking-widest transition-all whitespace-nowrap lg:w-full"
                                :class="activeTab === tab.id 
                                    ? 'bg-[var(--caret-color)] text-[var(--bg-color)] shadow-lg shadow-emerald-950/20' 
                                    : 'text-[var(--sub-color)] hover:bg-white/5 opacity-60 hover:opacity-100'"
                            >
                                <span class="text-xl">{{ tab.icon }}</span>
                                <span class="hidden sm:inline">{{ t(tab.id === 'security' ? 'auth.secure_area' : (tab.id === 'account' ? 'auth.delete_account' : 'navigation.profile')) }}</span>
                            </button>
                        </nav>
                    </aside>

                    <!-- Main Content Area -->
                    <main class="flex-1 space-y-12 pb-24">
                        <!-- Profile Section -->
                        <transition name="fade-slide" mode="out-in">
                            <div v-if="activeTab === 'profile'" key="profile" class="space-y-12">
                                <section class="bg-[var(--panel-color)] rounded-[2.5rem] p-1 border border-[var(--border-color)] overflow-hidden shadow-2xl">
                                    <UpdateProfileInformationForm :user="$page.props.auth.user" />
                                </section>
                            </div>

                            <!-- Security Section -->
                            <div v-else-if="activeTab === 'security'" key="security" class="space-y-12">
                                <section class="bg-[var(--panel-color)] rounded-[2.5rem] p-1 border border-[var(--border-color)] overflow-hidden shadow-2xl">
                                    <UpdatePasswordForm />
                                </section>

                                <section class="bg-[var(--panel-color)] rounded-[2.5rem] p-1 border border-[var(--border-color)] overflow-hidden shadow-2xl">
                                    <TwoFactorAuthenticationForm
                                        :requires-confirmation="confirmsTwoFactorAuthentication"
                                    />
                                </section>

                                <section class="bg-[var(--panel-color)] rounded-[2.5rem] p-1 border border-[var(--border-color)] overflow-hidden shadow-2xl">
                                    <LogoutOtherBrowserSessionsForm :sessions="sessions" />
                                </section>
                            </div>

                            <!-- Account Management -->
                            <div v-else-if="activeTab === 'account'" key="account" class="space-y-12">
                                <section class="bg-[var(--panel-color)] rounded-[2.5rem] p-1 border border-[var(--border-color)] overflow-hidden shadow-2xl border-red-500/20">
                                    <DeleteUserForm />
                                </section>
                            </div>
                        </transition>
                    </main>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active {
    transition: all 0.4s cubic-bezier(0.19, 1, 0.22, 1);
}

.fade-slide-enter-from {
    opacity: 0;
    transform: translateY(20px);
}

.fade-slide-leave-to {
    opacity: 0;
    transform: translateY(-20px);
}

/* Custom styling for standard forms injected via slots */
:deep(.bg-white), :deep(.bg-gray-800) {
    background: transparent !important;
}

:deep(.shadow) {
    box-shadow: none !important;
}

:deep(.md\:grid-cols-3) {
    display: flex !important;
    flex-direction: column !important;
    gap: 0 !important;
}

:deep(.md\:col-span-2) {
    width: 100% !important;
}

:deep(.px-4), :deep(.sm\:px-6), :deep(.lg\:px-8) {
    padding-left: 2rem !important;
    padding-right: 2rem !important;
}

:deep(.py-5), :deep(.sm\:p-6) {
    padding-top: 3rem !important;
    padding-bottom: 3rem !important;
}

:deep(h3) {
    font-family: 'Cinzel', serif !important;
    color: var(--caret-color) !important;
    font-size: 1.5rem !important;
    letter-spacing: 0.1em !important;
    text-transform: uppercase !important;
    margin-bottom: 0.5rem !important;
}

:deep(.text-gray-600), :deep(.dark\:text-gray-400) {
    color: var(--sub-color) !important;
    opacity: 0.7 !important;
    font-family: 'Inter', sans-serif !important;
}

:deep(input), :deep(textarea), :deep(select) {
    background: rgba(255, 255, 255, 0.03) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 1rem !important;
    color: white !important;
    padding: 0.75rem 1rem !important;
}

:deep(input:focus) {
    border-color: var(--caret-color) !important;
    box-shadow: 0 0 0 2px var(--caret-color-glow) !important;
}

:deep(label) {
    color: var(--sub-color) !important;
    font-family: 'Cinzel', serif !important;
    font-size: 0.75rem !important;
    letter-spacing: 0.2em !important;
    text-transform: uppercase !important;
}

:deep(.bg-gray-50), :deep(.dark\:bg-gray-800) {
    background: rgba(0, 0, 0, 0.2) !important;
    border-top: 1px solid var(--border-color) !important;
}
</style>
