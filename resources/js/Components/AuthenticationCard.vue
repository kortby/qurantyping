<template>
    <div class="flex flex-col items-center w-full px-4">
        <div class="mb-8">
            <slot name="logo" />
        </div>

        <div class="w-full sm:max-w-md px-6 py-8 sm:px-10 sm:py-10 bg-[var(--panel-color)] border border-[var(--border-color)]">
            <slot />
        </div>
    </div>
</template>
