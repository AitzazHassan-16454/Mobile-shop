<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Moon, ShoppingCart, Sun } from '@lucide/vue';
import { computed } from 'vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useAppearance } from '@/composables/useAppearance';
import pos from '@/routes/pos';
import type { BreadcrumbItem, Team } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const { resolvedAppearance, updateAppearance } = useAppearance();

const toggleTheme = () => {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
};

const currentTeamSlug = computed(
    () => (page.props.currentTeam as Team | undefined)?.slug || 'default',
);

const posUrl = computed(() => pos.index(currentTeamSlug.value).url);
</script>

<template>
    <header
        class="sticky top-0 z-20 flex h-14 shrink-0 items-center justify-between border-b border-white/15 bg-gradient-to-r from-[#002654] via-[#003B7D] to-[#004e9c] dark:from-[#090d16] dark:via-[#090d16] dark:to-[#090d16] dark:border-white/10 px-4 shadow-[0_4px_16px_rgba(0,35,80,0.15)] dark:shadow-[0_4px_16px_rgba(0,0,0,0.5)] backdrop-blur-xl transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12"
    >
        <!-- Left Side: Sidebar Toggle & Shop Heading -->
        <div class="flex min-w-0 items-center gap-3">
            <SidebarTrigger
                class="-ml-1 text-white/80 transition-colors hover:text-white"
            />
            <div class="h-4 w-px bg-white/20"></div>

            <h1
                class="truncate text-sm font-black tracking-tight text-white sm:text-base"
            >
                Horizon Studio
            </h1>
        </div>

        <!-- Right Side: Theme Toggle & POS Link -->
        <div class="flex items-center gap-2">
            <button
                type="button"
                @click="toggleTheme"
                class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-xl border border-white/25 bg-white/15 text-white shadow-2xs backdrop-blur-md transition hover:border-white/40 hover:bg-white/25 active:scale-95"
                :title="
                    resolvedAppearance === 'dark'
                        ? 'Switch to Light Mode'
                        : 'Switch to Dark Mode'
                "
            >
                <Sun
                    v-if="resolvedAppearance === 'dark'"
                    class="h-4 w-4 text-amber-300"
                />
                <Moon v-else class="h-4 w-4 text-blue-200" />
            </button>
            <Link
                :href="posUrl"
                class="inline-flex items-center gap-1.5 rounded-xl border border-white/25 bg-white/15 px-3.5 py-1.5 text-xs font-bold text-white shadow-2xs backdrop-blur-md transition hover:border-white/40 hover:bg-white/25 active:scale-95"
            >
                <ShoppingCart class="h-3.5 w-3.5 text-blue-200" />
                <span>POS</span>
            </Link>
        </div>
    </header>
</template>
