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
    <div class="relative min-w-[200px]" ref="dropdownRef">
        <!-- Trigger -->
        <div @click="toggleDropdown" 
             class="flex items-center gap-3 bg-[var(--panel-color)] px-4 py-2 rounded-xl backdrop-blur-md border border-[var(--border-color)] cursor-pointer hover:border-[var(--caret-color)]/40 transition-all group">
            <span v-if="label" class="text-[var(--caret-color)] opacity-60 font-cinzel text-xs uppercase tracking-widest">{{ label }}</span>
            <div class="flex-1 flex items-center justify-between gap-4">
                <span v-if="selectedOption" class="text-[var(--main-color)] font-bold text-sm">
                    {{ selectedOption.surah_number }}. {{ selectedOption.surah_name_arabic }}
                </span>
                <span v-else class="text-[var(--sub-color)] opacity-60 italic text-sm">{{ placeholder }}</span>
                <svg xmlns="http://www.w3.org/2000/svg" 
                     class="w-4 h-4 text-[var(--caret-color)] transition-transform duration-300"
                     :class="{ 'rotate-180': isOpen }"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m6 9 6 6 6-6"/>
                </svg>
            </div>
        </div>

        <!-- Dropdown Menu -->
        <transition name="dropdown">
            <div v-if="isOpen" 
                 class="absolute top-full left-0 right-0 mt-3 bg-[var(--bg-color)] backdrop-blur-2xl border border-[var(--border-color)] rounded-2xl shadow-3xl z-[100] max-h-[400px] flex flex-col overflow-hidden">
                
                <!-- Search Input -->
                <div class="p-4 border-b border-[var(--border-color)] bg-[var(--panel-color)]/30">
                    <div class="relative">
                        <input v-model="searchQuery" 
                               type="text" 
                               :placeholder="placeholder" 
                               class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg px-4 py-2 text-sm text-[var(--main-color)] focus:ring-1 focus:ring-[var(--caret-color)] focus:border-[var(--caret-color)] outline-none placeholder:opacity-30"
                               @click.stop
                               autofocus />
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 opacity-40">🔍</span>
                    </div>
                </div>

                <!-- Options List -->
                <div class="overflow-y-auto flex-1 custom-scrollbar bg-[var(--panel-color)]/10">
                    <div v-for="option in filteredOptions" 
                         :key="option.surah_number"
                         @click="selectOption(option)"
                         class="px-5 py-4 hover:bg-[var(--caret-color)]/[0.08] cursor-pointer transition-all flex items-center justify-between group border-b border-[var(--border-color)]/30 last:border-0"
                         :class="{ 'bg-[var(--caret-color)]/[0.12]': modelValue == option.surah_number }">
                        
                        <div class="flex items-center gap-4 text-left">
                            <span class="w-8 h-8 flex items-center justify-center rounded-full bg-[var(--bg-color)] border border-[var(--border-color)] text-[10px] font-cinzel text-[var(--caret-color)] group-hover:bg-[var(--caret-color)] group-hover:text-[var(--bg-color)] transition-all">
                                {{ option.surah_number }}
                            </span>
                            <div class="flex flex-col">
                                <span class="text-xs uppercase tracking-widest opacity-40 font-cinzel text-[var(--main-color)]">{{ option.surah_name_english }}</span>
                                <span class="text-sm font-bold text-[var(--main-color)]">{{ option.surah_name_arabic }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <span class="text-[9px] font-mono opacity-40 uppercase tracking-tighter text-[var(--sub-color)]">{{ option.total_ayahs }} ayat</span>
                            <div v-if="modelValue == option.surah_number" class="text-[var(--caret-color)]">
                                ✨
                            </div>
                        </div>
                    </div>

                    <div v-if="filteredOptions.length === 0" class="py-12 text-center text-[var(--sub-color)] opacity-60 font-cinzel uppercase text-xs tracking-widest">
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
