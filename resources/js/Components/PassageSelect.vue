<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import SurahSelect from '@/Components/SurahSelect.vue';
import { useSettings } from '../useSettings';

const { t } = useSettings();

const props = defineProps({
    surah: { type: [Number, String], default: 1 },
    surahs: { type: Array, default: () => [] },
    label: { type: String, default: '' },
    placeholder: { type: String, default: 'Select Surah' },
});

const emit = defineEmits(['update:surah', 'surah-picked', 'scope-change', 'select']);

const scope = ref('surah');
const juz = ref([]);
const juzValue = ref(1);
const pageValue = ref(1);
const pageCount = ref(604);

const tabs = [
    { id: 'surah', label: () => t('passage.surah') },
    { id: 'juz', label: () => t('passage.juz') },
    { id: 'page', label: () => t('passage.page') },
];

onMounted(async () => {
    try {
        const { data } = await axios.get('/api/quran/scopes');
        juz.value = data.juz ?? [];
        pageCount.value = data.page_count ?? 604;
    } catch (e) {
        // The Sūrah tab still works without the index.
    }
});

const setScope = (id) => {
    scope.value = id;
    emit('scope-change', id);
    if (id === 'juz') emit('select', { scope: 'juz', value: Number(juzValue.value) });
    if (id === 'page') emit('select', { scope: 'page', value: Number(pageValue.value) });
};

const pickJuz = () => emit('select', { scope: 'juz', value: Number(juzValue.value) });

const pickPage = () => {
    let v = Math.min(pageCount.value, Math.max(1, parseInt(pageValue.value) || 1));
    pageValue.value = v;
    emit('select', { scope: 'page', value: v });
};
</script>

<template>
    <div class="w-full sm:w-auto flex flex-col gap-2">
        <div class="flex border border-[var(--border-color)] divide-x divide-[var(--border-color)] self-start">
            <button
                v-for="tab in tabs" :key="tab.id" type="button"
                @click="setScope(tab.id)"
                class="px-3 py-1.5 text-[11px] font-mono uppercase tracking-[0.12em] transition-colors"
                :class="scope === tab.id ? 'bg-[var(--caret-color)] text-[var(--bg-color)]' : 'text-[var(--sub-color)] hover:text-[var(--main-color)]'"
            >{{ tab.label() }}</button>
        </div>

        <SurahSelect
            v-if="scope === 'surah'"
            :model-value="surah"
            :options="surahs"
            :label="label"
            :placeholder="placeholder"
            @update:model-value="v => { emit('update:surah', v); emit('surah-picked', v); }"
        />

        <select
            v-else-if="scope === 'juz'"
            v-model="juzValue"
            @change="pickJuz"
            class="w-full sm:w-[220px] min-h-[40px] bg-[var(--bg-color)] border border-[var(--border-color)] px-3 text-sm text-[var(--main-color)] focus:border-[var(--caret-color)] focus:outline-none"
        >
            <option v-for="j in juz" :key="j.value" :value="j.value">
                {{ t('passage.juz') }} {{ j.value }} — {{ j.surah }} {{ j.ayah }}
            </option>
        </select>

        <div v-else class="w-full sm:w-[220px] flex items-center gap-2 border border-[var(--border-color)] px-3 min-h-[40px]">
            <span class="text-[var(--sub-color)] text-[10px] uppercase tracking-[0.2em]">{{ t('passage.page') }}</span>
            <input
                v-model.number="pageValue"
                type="number" inputmode="numeric" min="1" :max="pageCount"
                @change="pickPage"
                aria-label="Mushaf page"
                class="w-16 text-center font-bold text-[var(--main-color)] bg-transparent border-none focus:ring-0 p-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
            />
            <span class="text-[10px] text-[var(--sub-color)] font-mono">/ {{ pageCount }}</span>
        </div>
    </div>
</template>
