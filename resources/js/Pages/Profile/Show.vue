<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSettings } from '../../useSettings';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue';
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue';
import PracticeGoalForm from '@/Pages/Profile/Partials/PracticeGoalForm.vue';
import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm.vue';

const { t } = useSettings();

defineProps({
    confirmsTwoFactorAuthentication: Boolean,
    sessions: Array,
});

const activeTab = ref('profile');

const tabs = [
    { id: 'profile', label: () => t('navigation.profile') },
    { id: 'security', label: () => t('auth.secure_area') || 'Security' },
    { id: 'account', label: () => t('auth.delete_account') || 'Account' },
];
</script>

<template>
    <Head :title="t('navigation.profile')" />

    <AppLayout>
        <div class="py-10 sm:py-12 animate-fade-in">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mb-8">
                    <h1 class="text-2xl font-cinzel font-semibold text-[var(--caret-color)]">{{ t('navigation.profile') }}</h1>
                    <p class="text-[var(--sub-color)] font-mono text-[10px] uppercase tracking-[0.3em] mt-1">{{ t('navigation.profile_subtitle') }}</p>
                </div>

                <!-- Tabs -->
                <nav class="flex border border-[var(--border-color)] divide-x divide-[var(--border-color)] mb-8 overflow-x-auto">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        @click="activeTab = tab.id"
                        class="flex-1 min-w-[110px] min-h-[44px] px-4 font-cinzel text-xs font-semibold uppercase tracking-[0.12em] transition-colors"
                        :class="activeTab === tab.id
                            ? 'bg-[var(--caret-color)] text-[var(--bg-color)]'
                            : 'text-[var(--sub-color)] hover:text-[var(--main-color)]'"
                    >
                        {{ tab.label() }}
                    </button>
                </nav>

                <main class="pb-16">
                    <transition name="fade-slide" mode="out-in">
                        <div v-if="activeTab === 'profile'" key="profile" class="space-y-10">
                            <UpdateProfileInformationForm :user="$page.props.auth.user" />
                            <PracticeGoalForm />
                        </div>

                        <div v-else-if="activeTab === 'security'" key="security" class="space-y-10">
                            <UpdatePasswordForm />
                            <TwoFactorAuthenticationForm :requires-confirmation="confirmsTwoFactorAuthentication" />
                            <LogoutOtherBrowserSessionsForm :sessions="sessions" />
                        </div>

                        <div v-else-if="activeTab === 'account'" key="account">
                            <DeleteUserForm />
                        </div>
                    </transition>
                </main>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active {
    transition: opacity 0.25s ease, transform 0.25s ease;
}

.fade-slide-enter-from {
    opacity: 0;
    transform: translateY(10px);
}

.fade-slide-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}

/* Neutralise leftover stock utilities inside the Jetstream form partials */
:deep(.bg-gray-100),
:deep(.dark\:bg-gray-900) {
    background-color: var(--bg-color) !important;
    border: 1px solid var(--border-color) !important;
    color: var(--main-color) !important;
}
</style>
