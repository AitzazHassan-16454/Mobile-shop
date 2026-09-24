<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown, Check } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useInitials } from '@/composables/useInitials';
import { useTranslation } from '@/composables/useTranslation';
import pos from '@/routes/pos';
import type { BreadcrumbItem, Team, User } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const user = computed(() => page.props.auth?.user as User | undefined);
const currentTeamSlug = computed(
    () => (page.props.currentTeam as Team | undefined)?.slug || 'default',
);

const posUrl = computed(() => pos.index(currentTeamSlug.value).url);
const { getInitials } = useInitials();
const { currentLanguage, setLanguage, t } = useTranslation();
</script>

<template>
    <header
        class="sticky top-0 z-20 flex h-16 shrink-0 items-center justify-end border-b border-white/15 bg-gradient-to-r from-[#00366b] via-[#003B7D] to-[#002f61] px-4 sm:px-6 shadow-[0_4px_16px_rgba(0,35,80,0.15)] backdrop-blur-xl transition-[height] ease-linear"
    >
        <!-- Right Side: POS | EN | (A) Admin (matching reference layout) -->
        <div class="flex items-center gap-5 sm:gap-7">
            <!-- POS Text Link (Larger & Bolder) -->
            <Link
                :href="posUrl"
                class="text-base sm:text-lg font-black uppercase tracking-widest text-white transition hover:text-blue-200 active:scale-95"
            >
                {{ t('POS') }}
            </Link>

            <!-- Language Switcher Dropdown (EN / UR) -->
            <DropdownMenu>
                <DropdownMenuTrigger
                    class="flex items-center gap-1 text-sm font-extrabold uppercase tracking-wider text-white transition hover:text-blue-200 focus:outline-none"
                >
                    <span>{{ currentLanguage.toUpperCase() }}</span>
                    <ChevronDown class="size-3.5 text-white/80" />
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-36 rounded-xl p-1 shadow-xl">
                    <DropdownMenuItem
                        @click="setLanguage('en')"
                        class="flex cursor-pointer items-center justify-between rounded-lg px-3 py-2 text-xs font-bold transition"
                        :class="
                            currentLanguage === 'en'
                                ? 'bg-[#003B7D] text-white font-black'
                                : 'text-slate-700 hover:bg-slate-100'
                        "
                    >
                        <span>EN (English)</span>
                        <Check v-if="currentLanguage === 'en'" class="size-3.5" />
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        @click="setLanguage('ur')"
                        class="flex cursor-pointer items-center justify-between rounded-lg px-3 py-2 text-xs font-bold transition"
                        :class="
                            currentLanguage === 'ur'
                                ? 'bg-[#003B7D] text-white font-black'
                                : 'text-slate-700 hover:bg-slate-100'
                        "
                    >
                        <span>UR (اردو)</span>
                        <Check v-if="currentLanguage === 'ur'" class="size-3.5" />
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <!-- User Admin Profile Dropdown ((A) Admin) -->
            <DropdownMenu>
                <DropdownMenuTrigger
                    class="flex items-center gap-2 rounded-full p-0.5 transition hover:opacity-90 focus:outline-none"
                >
                    <div
                        class="flex size-8 shrink-0 items-center justify-center rounded-full bg-[#1d8cd8] text-xs font-extrabold text-white shadow-xs ring-2 ring-white/20"
                    >
                        {{ getInitials(user?.name || 'Admin') }}
                    </div>
                    <span
                        class="hidden text-sm font-extrabold text-white transition hover:text-blue-100 sm:inline-block"
                    >
                        {{ user?.name ? user.name.split(' ')[0] : 'Admin' }}
                    </span>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-60 p-1.5 shadow-2xl">
                    <UserMenuContent v-if="user" :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>

