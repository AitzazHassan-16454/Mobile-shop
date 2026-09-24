<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

const name = usePage().props.name || 'Horizon Studio';
</script>

<template>
    <div class="flex w-full items-center gap-3 px-1 py-1 group-data-[collapsible=icon]:justify-center">
        <!-- Modern Ribbon Loop Brand Icon matching the reference image -->
        <div class="flex size-9 shrink-0 items-center justify-center transition-transform duration-200 group-hover:scale-105">
            <svg
                viewBox="0 0 36 36"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
                class="size-8 text-[#FF3E6C] drop-shadow-[0_2px_8px_rgba(255,62,108,0.4)]"
            >
                <path
                    d="M9 13.5V20.5C9 23.5 11.5 26 14.5 26C17.5 26 20 23.5 20 20.5V13.5C20 10.5 22.5 8 25.5 8C28.5 8 31 10.5 31 13.5V22.5C31 25.5 28.5 28 25.5 28C22.5 28 20 25.5 20 22.5"
                    stroke="currentColor"
                    stroke-width="4.2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>
        </div>

        <!-- Clean Bold Brand Typography -->
        <div class="flex min-w-0 items-center text-left group-data-[collapsible=icon]:hidden">
            <span class="truncate text-lg font-black tracking-tight text-white font-sans">
                Horizon<span class="font-semibold text-white/95">Studio</span>
            </span>
        </div>
    </div>
</template>
