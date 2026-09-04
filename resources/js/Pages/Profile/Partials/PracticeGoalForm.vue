<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import ActionMessage from '@/Components/ActionMessage.vue';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const page = usePage();

const form = useForm({
    daily_goal_chars: page.props.auth?.streak?.goal?.target ?? 500,
});

const submit = () => {
    form.post(route('user.settings.daily-goal'), { preserveScroll: true });
};
</script>

<template>
    <FormSection @submitted="submit">
        <template #title>Daily practice goal</template>

        <template #description>
            The number of correctly typed characters that counts as a full day of
            practice. Your streak survives one missed day per week.
        </template>

        <template #form>
            <div class="col-span-6 sm:col-span-3">
                <InputLabel for="daily_goal_chars" value="Characters per day" />
                <TextInput
                    id="daily_goal_chars"
                    v-model.number="form.daily_goal_chars"
                    type="number"
                    inputmode="numeric"
                    min="50"
                    max="10000"
                    step="50"
                    class="mt-1 block w-full"
                />
                <InputError :message="form.errors.daily_goal_chars" class="mt-2" />
            </div>
        </template>

        <template #actions>
            <ActionMessage :on="form.recentlySuccessful">Saved.</ActionMessage>
            <PrimaryButton :class="{ 'opacity-50': form.processing }" :disabled="form.processing">
                Save
            </PrimaryButton>
        </template>
    </FormSection>
</template>
