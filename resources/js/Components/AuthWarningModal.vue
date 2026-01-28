<script setup>
import { Link } from '@inertiajs/vue3';
import DialogModal from './DialogModal.vue';
import SecondaryButton from './SecondaryButton.vue';
import PrimaryButton from './PrimaryButton.vue';
import { useSettings } from '../useSettings';

const { t } = useSettings();

defineProps({
    show: Boolean,
});

const emit = defineEmits(['close']);
</script>

<template>
    <DialogModal :show="show" @close="emit('close')">
        <template #title>
            <span class="font-cinzel text-xl tracking-widest text-[var(--error-color)]">
                {{ t('auth.secure_area') }}
            </span>
        </template>

        <template #content>
            <div class="py-4 text-center">
                <div class="text-4xl mb-4">🔒</div>
                <p class="text-[var(--main-color)] font-medium mb-2">
                    {{ t('feedback_auth_warning') }}
                </p>
            </div>
        </template>

        <template #footer>
            <div class="flex gap-4 w-full">
                <SecondaryButton @click="emit('close')" class="flex-1 !rounded-xl">
                    {{ t('auth.cancel') }}
                </SecondaryButton>
                <Link :href="route('login')" class="flex-1">
                    <PrimaryButton class="w-full !rounded-xl">
                        {{ t('login') }}
                    </PrimaryButton>
                </Link>
            </div>
        </template>
    </DialogModal>
</template>
