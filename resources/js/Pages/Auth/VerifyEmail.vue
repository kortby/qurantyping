<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSettings } from '@/useSettings';

const { t } = useSettings();

const props = defineProps({
    status: String,
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <Head :title="t('auth.verify_email_title')" />

    <AppLayout>
        <div class="py-6">
            <AuthenticationCard>
                <template #logo>
                    <AuthenticationCardLogo />
                </template>

                <div class="mb-6 text-sm text-[var(--sub-color)] leading-relaxed">
                    {{ t('auth.verify_email_message') }}
                </div>

                <div v-if="verificationLinkSent" class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
                    {{ t('auth.verification_link_sent') }}
                </div>

                <form @submit.prevent="submit">
                    <div class="mt-4 flex flex-col md:flex-row items-center justify-between gap-6">
                        <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            {{ t('auth.resend_verification') }}
                        </PrimaryButton>

                        <div class="flex items-center gap-4">
                            <Link
                                :href="route('profile.show')"
                                class="underline text-sm text-[var(--sub-color)] hover:text-[var(--caret-color)] rounded-md focus:outline-none focus:ring-2 focus:ring-[var(--caret-color)] transition-colors"
                            >
                                {{ t('auth.edit_profile') }}
                            </Link>

                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="underline text-sm text-[var(--sub-color)] hover:text-[var(--error-color)] rounded-md focus:outline-none focus:ring-2 focus:ring-[var(--error-color)] transition-colors"
                            >
                                {{ t('logout') }}
                            </Link>
                        </div>
                    </div>
                </form>
            </AuthenticationCard>
        </div>
    </AppLayout>
</template>
