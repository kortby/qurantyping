<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import ActionMessage from '@/Components/ActionMessage.vue';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useSettings } from '../../../useSettings';

const { t } = useSettings();
const page = usePage();

const reciters = page.props.reciters ?? {};

const form = useForm({
    reciter: page.props.auth?.user?.reciter ?? Object.keys(reciters)[0] ?? '',
});

const submit = () => {
    form.post(route('user.settings.reciter'), { preserveScroll: true });
};
</script>

<template>
    <FormSection @submitted="submit">
        <template #title>{{ t('recitation_title') }}</template>

        <template #description>{{ t('recitation_desc') }}</template>

        <template #form>
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="reciter" :value="t('reciter')" />
                <select
                    id="reciter"
                    v-model="form.reciter"
                    class="mt-1 block w-full bg-[var(--bg-color)] border border-[var(--border-color)] px-3 py-2 font-mono text-sm text-[var(--main-color)] focus:border-[var(--caret-color)] focus:outline-none"
                >
                    <option v-for="(meta, key) in reciters" :key="key" :value="key">{{ meta.name }}</option>
                </select>
                <InputError :message="form.errors.reciter" class="mt-2" />
            </div>
        </template>

        <template #actions>
            <ActionMessage :on="form.recentlySuccessful">{{ t('streak.saved') }}</ActionMessage>
            <PrimaryButton :class="{ 'opacity-50': form.processing }" :disabled="form.processing">
                {{ t('streak.save') }}
            </PrimaryButton>
        </template>
    </FormSection>
</template>
