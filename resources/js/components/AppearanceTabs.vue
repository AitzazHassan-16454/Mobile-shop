<script setup lang="ts">
import { Sun } from '@lucide/vue';
</script>

<template>
    <div class="flex flex-col gap-2">
        <div
            class="inline-flex w-fit items-center gap-2 rounded-xl border border-blue-200 bg-blue-50/80 px-4 py-2.5 text-sm font-semibold text-[#003B7D] shadow-2xs"
        >
            <Sun class="h-4 w-4 text-amber-500" />
            <span>Light Mode (Active)</span>
        </div>
        <p class="text-xs text-slate-500">
            The application operates exclusively in Light Mode.
        </p>
    </div>
</template>
