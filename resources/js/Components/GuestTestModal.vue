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
            <span class="font-cinzel text-xl tracking-widest text-[var(--caret-color)]">
                {{ t('guest_test_title') }}
            </span>
        </template>

        <template #content>
            <div class="py-4 text-center">
                <div class="text-5xl mb-6">🎉</div>
                <p class="text-[var(--main-color)] font-medium text-lg leading-relaxed mb-4">
                    {{ t('guest_test_message') }}
                </p>
            </div>
        </template>

        <template #footer>
            <div class="flex flex-col sm:flex-row gap-4 w-full">
                <SecondaryButton @click="emit('close')" class="flex-1 !rounded-xl !py-4 uppercase tracking-widest text-xs font-bold">
                    {{ t('guest_test_skip') }}
                </SecondaryButton>
                <Link :href="route('register')" class="flex-1">
                    <PrimaryButton class="w-full !rounded-xl !py-4 bg-[var(--caret-color)] !text-[var(--bg-color)] border-none hover:scale-[1.02] active:scale-[0.98] transition-all shadow-lg shadow-emerald-950/20 uppercase tracking-widest text-xs font-bold">
                        {{ t('guest_test_save_cta') }}
                    </PrimaryButton>
                </Link>
            </div>
        </template>
    </DialogModal>
</template>
