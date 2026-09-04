<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    modelValue: [String, Number],
    options: {
        type: Array,
        default: () => []
    },
    placeholder: {
        type: String,
        default: 'Select Surah'
    },
    label: String
});

const emit = defineEmits(['update:modelValue']);

const isOpen = ref(false);
const searchQuery = ref('');
const dropdownRef = ref(null);

const filteredOptions = computed(() => {
    if (!searchQuery.value) return props.options;
    const query = searchQuery.value.toLowerCase();
    return props.options.filter(option => 
        option.surah_number?.toString().includes(query) ||
        option.surah_name_arabic?.includes(query) ||
        option.surah_name_english?.toLowerCase().includes(query)
    );
});

const selectedOption = computed(() => {
    return props.options.find(opt => opt.surah_number == props.modelValue);
});

const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        searchQuery.value = '';
    }
};

const selectOption = (option) => {
    emit('update:modelValue', option.surah_number);
    isOpen.value = false;
    searchQuery.value = '';
};

const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', handleClickOutside);
});
</script>

<template>
    <div class="relative w-full sm:w-auto sm:min-w-[220px]" ref="dropdownRef">
        <!-- Trigger -->
        <button type="button" @click="toggleDropdown"
             class="w-full min-h-[40px] flex items-center gap-2 px-3 border border-[var(--border-color)] cursor-pointer hover:border-[var(--caret-color)] transition-colors">
            <span v-if="label" class="text-[var(--sub-color)] text-[10px] uppercase tracking-[0.2em] hidden sm:inline">{{ label }}</span>
            <span class="flex-1 flex items-center justify-between gap-3 text-left">
                <span v-if="selectedOption" class="text-[var(--main-color)] text-sm truncate">
                    {{ selectedOption.surah_number }}. {{ selectedOption.surah_name_arabic }}
                </span>
                <span v-else class="text-[var(--sub-color)] text-sm">{{ placeholder }}</span>
                <svg xmlns="http://www.w3.org/2000/svg"
                     class="w-4 h-4 shrink-0 text-[var(--sub-color)] transition-transform duration-200"
                     :class="{ 'rotate-180': isOpen }"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m6 9 6 6 6-6"/>
                </svg>
            </span>
        </button>

        <!-- Dropdown Menu -->
        <transition name="dropdown">
            <div v-if="isOpen"
                 class="absolute top-full left-0 right-0 mt-1 bg-[var(--panel-color)] border border-[var(--border-color)] z-[100] max-h-[60vh] sm:max-h-[400px] flex flex-col overflow-hidden shadow-lg">

                <!-- Search Input -->
                <div class="p-3 border-b border-[var(--border-color)]">
                    <input v-model="searchQuery"
                           type="text"
                           :placeholder="placeholder"
                           class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] px-3 py-2 text-sm text-[var(--main-color)] focus:border-[var(--caret-color)] focus:ring-0 outline-none placeholder-[var(--sub-color)]/50"
                           @click.stop
                           autofocus />
                </div>

                <!-- Options List -->
                <div class="overflow-y-auto flex-1">
                    <button type="button" v-for="option in filteredOptions"
                         :key="option.surah_number"
                         @click="selectOption(option)"
                         class="w-full px-4 py-3 hover:bg-[var(--caret-color)]/[0.08] cursor-pointer transition-colors flex items-center justify-between gap-3 text-left border-b border-[var(--border-color)] last:border-0"
                         :class="{ 'bg-[var(--caret-color)]/[0.12]': modelValue == option.surah_number }">

                        <span class="flex items-center gap-3">
                            <span class="w-7 h-7 shrink-0 flex items-center justify-center border border-[var(--border-color)] text-[10px] font-mono text-[var(--caret-color)]">
                                {{ option.surah_number }}
                            </span>
                            <span class="flex flex-col">
                                <span class="text-[10px] uppercase tracking-[0.15em] text-[var(--sub-color)]">{{ option.surah_name_english }}</span>
                                <span class="text-sm text-[var(--main-color)]">{{ option.surah_name_arabic }}</span>
                            </span>
                        </span>

                        <span class="text-[9px] font-mono text-[var(--sub-color)] uppercase tracking-tight shrink-0">{{ option.total_ayahs }} ayat</span>
                    </button>

                    <div v-if="filteredOptions.length === 0" class="py-10 text-center text-[var(--sub-color)] text-xs uppercase tracking-[0.15em]">
                        No matches found
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<style scoped>
.dropdown-enter-active,
.dropdown-leave-active {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.dropdown-enter-from,
.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-10px) scale(0.98);
}

.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: var(--caret-color);
}
</style>
