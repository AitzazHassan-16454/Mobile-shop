<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative flex min-h-svh items-center justify-center overflow-hidden bg-gray-100 px-4 py-8"
    >
        <div
            class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(0,59,125,0.08),_transparent_30%)]"
        />
        <div class="bg-grid-brand absolute inset-0" />

        <div class="relative w-full max-w-md">
            <div
                class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xl shadow-[#003b7d]/5 md:p-8"
            >
                <div class="flex flex-col gap-7">
                    <div class="flex flex-col items-center gap-4">
                        <Link
                            :href="home()"
                            class="flex flex-col items-center gap-3 font-medium"
                        >
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#003b7d] shadow-sm"
                            >
                                <AppLogoIcon
                                    class="size-8 fill-current text-white"
                                />
                            </div>
                            <span class="sr-only">{{ title }}</span>
                        </Link>
                        <div class="space-y-2 text-center">
                            <h1
                                class="text-2xl font-semibold tracking-tight text-gray-900"
                            >
                                {{ title }}
                            </h1>
                            <p class="text-sm text-slate-500">
                                {{ description }}
                            </p>
                        </div>
                    </div>
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
