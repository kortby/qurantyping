<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    results: Object, // The paginated results from the controller
});

// A helper function to format dates
const formatDate = (dateString) => {
    return new Date(dateString).toLocaleString('ar-EG', { dateStyle: 'medium', timeStyle: 'short' });
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6">
                        <h2 class="text-2xl font-bold text-white mb-6">نتائج الاختبارات السابقة</h2>
                        
                        <div v-if="results.data.length > 0" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-700">
                                <thead class="bg-gray-700">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-300 uppercase tracking-wider">السورة</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-300 uppercase tracking-wider">السرعة (كلمة/دقيقة)</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-300 uppercase tracking-wider">الدقة</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-300 uppercase tracking-wider">التاريخ</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-gray-800 divide-y divide-gray-700">
                                    <tr v-for="result in results.data" :key="result.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-white">
                                            {{ result.quran_text.surah_name_arabic }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">{{ result.wpm }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">{{ result.accuracy }}%</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">{{ formatDate(result.created_at) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-center text-gray-400 py-8">
                            <p>لم تقم بإكمال أي اختبارات بعد. ابدأ الآن!</p>
                        </div>
                        
                        <!-- You can add pagination links here if you want -->
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>