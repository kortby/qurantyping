<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen bg-gray-900 text-gray-200 antialiased">
        <header class="bg-gray-800 shadow-md">
            <nav class="container mx-auto px-6 py-4 flex justify-between items-center">
                <Link href="/" class="text-xl font-bold text-yellow-400">
                    إختبار سرعة الكتابة
                </Link>

                <!-- Navigation Links -->
                <div class="flex items-center space-x-4">
                    <!-- For Logged-in Users -->
                    <div v-if="$page.props.auth.user" class="flex items-center space-x-4">
                        <Link href="/dashboard" class="px-4 py-2 rounded hover:bg-gray-700">لوحة التحكم</Link>
                        <Link href="/logout" method="post" as="button" class="px-4 py-2 rounded hover:bg-gray-700">تسجيل الخروج</Link>
                    </div>

                    <!-- For Guests -->
                    <div v-else class="flex items-center space-x-4">
                        <Link href="/login" class="px-4 py-2 rounded hover:bg-gray-700">تسجيل الدخول</Link>
                        <Link href="/register" class="px-4 py-2 rounded hover:bg-gray-700">التسجيل</Link>
                    </div>
                </div>
            </nav>
        </header>

        <!-- Page Content -->
        <main>
            <slot />
        </main>
    </div>
</template>