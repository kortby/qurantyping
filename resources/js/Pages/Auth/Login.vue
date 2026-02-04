<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import SocialButtons from '@/Components/SocialButtons.vue';
import { useSettings } from '@/useSettings';

const { t } = useSettings();

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head :title="t('login')" />

    <AppLayout>
        <div class="py-6">
            <AuthenticationCard>
                <template #logo>
                    <AuthenticationCardLogo />
                </template>

                <div v-if="$page.props.social?.has_any" class="mb-6">
                    <SocialButtons />
                    
                    <div class="relative flex items-center justify-center mt-6">
                        <div class="flex-grow border-t border-[var(--border-color)]"></div>
                        <span class="flex-shrink mx-4 text-[10px] font-cinzel text-[var(--sub-color)] uppercase tracking-[0.3em] font-bold">
                            {{ t('or_login_with_email') || 'OR LOGIN WITH EMAIL' }}
                        </span>
                        <div class="flex-grow border-t border-[var(--border-color)]"></div>
                    </div>
                </div>

                <div v-if="status" class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
                    {{ status }}
                </div>

                <div v-if="$page.props.flash.error" class="mb-4 font-medium text-sm text-[var(--error-color)] bg-[var(--error-color)]/10 p-4 rounded-xl border border-[var(--error-color)]/20">
                    {{ $page.props.flash.error }}
                </div>

                <form @submit.prevent="submit">
                    <div>
                        <InputLabel for="email" :value="t('auth.email')" />
                        <TextInput
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="mt-1 block w-full"
                            required
                            autofocus
                            autocomplete="username"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="password" :value="t('auth.password')" />
                        <TextInput
                            id="password"
                            v-model="form.password"
                            type="password"
                            class="mt-1 block w-full"
                            required
                            autocomplete="current-password"
                        />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div class="block mt-4">
                        <label class="flex items-center cursor-pointer group">
                            <Checkbox v-model:checked="form.remember" name="remember" />
                            <span class="ms-2 text-sm text-[var(--sub-color)] group-hover:text-[var(--main-color)] transition-colors">{{ t('auth.remember_me') }}</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-end mt-4">
                        <Link v-if="canResetPassword" :href="route('password.request')" class="underline text-sm text-[var(--sub-color)] hover:text-[var(--caret-color)] rounded-md focus:outline-none focus:ring-2 focus:ring-[var(--caret-color)] transition-colors">
                            {{ t('auth.forgot_password_question') }}
                        </Link>

                        <PrimaryButton class="ms-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            {{ t('login') }}
                        </PrimaryButton>
                    </div>
                </form>
            </AuthenticationCard>
        </div>
    </AppLayout>
</template>
