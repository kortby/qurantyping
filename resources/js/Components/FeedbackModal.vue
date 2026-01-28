<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import DialogModal from './DialogModal.vue';
import InputError from './InputError.vue';
import InputLabel from './InputLabel.vue';
import PrimaryButton from './PrimaryButton.vue';
import SecondaryButton from './SecondaryButton.vue';
import TextInput from './TextInput.vue';
import { useSettings } from '../useSettings';

const { t } = useSettings();

const props = defineProps({
    show: Boolean,
});

const emit = defineEmits(['close']);

const form = useForm({
    message: '',
    type: 'suggestion',
});

const submit = () => {
    form.post(route('feedback.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit('close');
        },
    });
};
</script>

<template>
    <DialogModal :show="show" @close="emit('close')">
        <template #title>
            <span class="font-cinzel text-xl tracking-widest text-[var(--caret-color)]">
                {{ t('give_feedback') }}
            </span>
        </template>

        <template #content>
            <div class="space-y-6">
                <!-- Type Selection -->
                <div>
                    <InputLabel for="type" :value="t('feedback_type')" class="mb-2 !text-[10px] !tracking-[0.3em]" />
                    <div class="flex gap-4">
                        <button 
                            v-for="type in ['bug', 'suggestion', 'other']" 
                            :key="type"
                            type="button"
                            @click="form.type = type"
                            class="flex-1 py-3 px-4 rounded-xl border font-cinzel text-[10px] uppercase tracking-widest transition-all"
                            :class="form.type === type 
                                ? 'bg-[var(--caret-color)] text-[var(--bg-color)] border-[var(--caret-color)] shadow-lg' 
                                : 'bg-[var(--panel-color)] text-[var(--sub-color)] border-[var(--border-color)] opacity-60 hover:opacity-100'"
                        >
                            {{ t(type) }}
                        </button>
                    </div>
                    <InputError :message="form.errors.type" class="mt-2" />
                </div>

                <!-- Message -->
                <div>
                    <InputLabel for="message" :value="t('feedback')" class="mb-2 !text-[10px] !tracking-[0.3em]" />
                    <textarea
                        id="message"
                        v-model="form.message"
                        class="w-full h-40 bg-[var(--panel-color)] border border-[var(--border-color)] rounded-2xl p-4 text-sm text-[var(--main-color)] focus:border-[var(--caret-color)] focus:ring-1 focus:ring-[var(--caret-color)] outline-none transition-all placeholder:opacity-30"
                        :placeholder="t('feedback_placeholder')"
                    ></textarea>
                    <InputError :message="form.errors.message" class="mt-2" />
                </div>
            </div>
        </template>

        <template #footer>
            <div class="flex gap-4">
                <SecondaryButton @click="emit('close')" class="!rounded-xl !py-3 !px-8">
                    {{ t('auth.cancel') }}
                </SecondaryButton>

                <PrimaryButton 
                    @click="submit" 
                    :class="{ 'opacity-25': form.processing }" 
                    :disabled="form.processing"
                    class="!rounded-xl !py-3 !px-8"
                >
                    {{ t('send_feedback') }}
                </PrimaryButton>
            </div>
        </template>
    </DialogModal>
</template>

<style scoped>
:deep(.bg-white), :deep(.bg-gray-800) {
    background: var(--bg-color) !important;
}

:deep(.p-6) {
    background: var(--bg-color) !important;
}

:deep(.bg-gray-100), :deep(.dark\:bg-gray-900) {
    background: var(--panel-color) !important;
}
</style>
