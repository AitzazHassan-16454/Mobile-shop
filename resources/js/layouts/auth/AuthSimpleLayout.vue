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
        class="relative flex min-h-screen items-center justify-center overflow-hidden bg-gray-100 px-4 py-8 sm:px-6 lg:px-8"
    >
        <div class="bg-grid-brand pointer-events-none absolute inset-0"></div>
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(900px_500px_at_12%_-8%,rgba(7,28,61,0.08),transparent_60%),radial-gradient(820px_620px_at_50%_118%,rgba(7,28,61,0.05),transparent_62%)]"
        ></div>

        <div class="relative flex w-full flex-col justify-center gap-6">
            <Link :href="home()" class="mx-auto flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#003B7D] text-white shadow-sm"
                >
                    <AppLogoIcon class="h-6 w-6 fill-current" />
                </div>
                <div>
                    <div
                        class="text-lg font-black tracking-tight text-gray-900"
                    >
                        Horizon Studio
                    </div>
                    <div class="eyebrow">Mobile shop POS</div>
                </div>
            </Link>

            <div
                class="mx-auto w-full max-w-md overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-xl shadow-[#003B7D]/5"
            >
                <div class="p-6 md:p-8">
                    <h1
                        class="text-2xl font-black tracking-tight text-gray-900"
                    >
                        {{ title }}
                    </h1>
                    <p v-if="description" class="mt-1.5 text-sm text-slate-500">
                        {{ description }}
                    </p>
                    <div class="mt-6">
                        <slot />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
