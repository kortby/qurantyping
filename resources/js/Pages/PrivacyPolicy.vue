<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSettings } from '@/useSettings';

const { t } = useSettings();

defineProps({
    policy: String,
});
</script>

<template>
    <Head :title="t('auth.privacy_policy')" />

    <AppLayout>
        <div class="py-6 flex flex-col items-center">
            <div class="mb-8">
                <AuthenticationCardLogo />
            </div>

            <div class="w-full sm:max-w-4xl p-10 bg-[var(--panel-color)] border border-[var(--border-color)] rounded-3xl shadow-2xl backdrop-blur-md prose prose-invert max-w-none" 
                 style="color: var(--main-color);"
                 v-html="policy" />
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(h1), :deep(h2), :deep(h3) {
    color: var(--caret-color);
    font-family: 'Cinzel', serif;
}
:deep(p), :deep(li) {
    color: var(--main-color);
    opacity: 0.8;
}
</style>
