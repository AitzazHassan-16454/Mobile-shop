<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Smartphone } from '@lucide/vue';

const name = usePage().props.name || 'Faizan Mobile';
</script>

<template>
    <div class="flex items-center gap-3">
        <div
            class="flex size-9 items-center justify-center rounded-xl bg-white shadow-md ring-1 ring-white/40"
        >
            <Smartphone class="size-4.5 text-[#003B7D]" />
        </div>
        <div class="grid flex-1 text-left leading-tight">
            <span
                class="truncate text-sm font-black tracking-tight text-white"
                >{{ name }}</span
            >
            <span
                class="truncate text-[10px] font-semibold tracking-[0.18em] text-blue-200 uppercase"
                >Mobile shop POS</span
            >
        </div>
    </div>
</template>
