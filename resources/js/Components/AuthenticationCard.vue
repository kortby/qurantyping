<template>
    <div class="flex flex-col items-center bg-transparent">
        <div class="mb-8">
            <slot name="logo" />
        </div>

        <div class="w-full sm:max-w-md px-10 py-10 bg-[var(--panel-color)] border border-[var(--border-color)] rounded-3xl shadow-2xl backdrop-blur-md">
            <slot />
        </div>
    </div>
</template>
