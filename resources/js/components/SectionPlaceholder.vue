<script setup lang="ts">
import { Hammer } from '@lucide/vue';
import type { Component } from 'vue';

defineProps<{
    icon: Component;
    title: string;
    eyebrow?: string;
    description: string;
}>();
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <section
            class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-gray-50 p-5 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p v-if="eyebrow" class="eyebrow mb-2">{{ eyebrow }}</p>
                <h1
                    class="flex items-center gap-2 text-2xl font-bold tracking-tight text-gray-900"
                >
                    <component :is="icon" class="h-7 w-7 text-[#003B7D]" />
                    {{ title }}
                </h1>
                <p class="mt-1 text-sm text-slate-500">{{ description }}</p>
            </div>
            <span
                class="inline-flex items-center gap-1.5 rounded-full border border-[#003B7D]/20 bg-[#003B7D]/5 px-3 py-1 text-xs font-bold text-[#003B7D]"
            >
                <Hammer class="h-3.5 w-3.5" /> Module in development
            </span>
        </section>

        <section
            class="flex flex-1 flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-gray-300 bg-white/50 p-10 text-center backdrop-blur-xl"
        >
            <div
                class="flex size-16 items-center justify-center rounded-2xl bg-[#003B7D]/10"
            >
                <component :is="icon" class="size-8 text-[#003B7D]" />
            </div>
            <h2 class="text-lg font-bold text-gray-900">
                {{ title }} module is under construction
            </h2>
            <p class="max-w-md text-sm text-slate-500">
                This section will be fully functional soon. Meanwhile, the rest
                of the shop dashboard continues to work as usual.
            </p>
        </section>
    </div>
</template>
