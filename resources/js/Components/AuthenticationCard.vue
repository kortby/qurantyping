<template>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-[#323437]">
        <div class="mb-12">
            <slot name="logo" />
        </div>

        <div class="w-full sm:max-w-md px-10 py-8 bg-[#2c2e31]/40 border border-gray-700/30 rounded-2xl shadow-2xl backdrop-blur-sm">
            <slot />
        </div>
    </div>
</template>
