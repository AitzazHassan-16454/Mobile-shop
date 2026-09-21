<script setup lang="ts">
import type { Component } from 'vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

export type StatsTone =
    | 'brand'
    | 'sky'
    | 'emerald'
    | 'amber'
    | 'rose'
    | 'fuchsia'
    | 'slate';

const props = withDefaults(
    defineProps<{
        label: string;
        value?: number;
        sublabel?: string;
        tone?: StatsTone;
        icon?: Component;
        prefix?: string;
    }>(),
    {
        value: 0,
        sublabel: undefined,
        tone: 'brand',
        icon: undefined,
        prefix: 'Rs',
    },
);

const toneClasses: Record<StatsTone, string> = {
    brand: 'bg-gradient-to-br from-[#003B7D]/18 to-[#003B7D]/5 text-[#003B7D] ring-[#003B7D]/25',
    sky: 'bg-gradient-to-br from-sky-500/22 to-sky-500/5 text-sky-600 ring-sky-500/30',
    emerald:
        'bg-gradient-to-br from-emerald-500/22 to-emerald-500/5 text-emerald-600 ring-emerald-500/30',
    amber: 'bg-gradient-to-br from-amber-500/22 to-amber-500/5 text-amber-600 ring-amber-500/30',
    rose: 'bg-gradient-to-br from-rose-500/22 to-rose-500/5 text-rose-600 ring-rose-500/30',
    fuchsia:
        'bg-gradient-to-br from-fuchsia-500/22 to-fuchsia-500/5 text-fuchsia-600 ring-fuchsia-500/30',
    slate: 'bg-gradient-to-br from-slate-500/22 to-slate-500/5 text-slate-600 ring-slate-500/30',
};

const formattedValue = computed(() => {
    const amount = Math.abs(props.value);
    const text =
        amount % 1 === 0
            ? amount.toLocaleString()
            : amount.toLocaleString(undefined, {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2,
              });

    return `${props.value < 0 ? '-' : ''}${props.prefix} ${text}`;
});
</script>

<template>
    <div
        class="glass-card group flex items-center gap-3.5 rounded-2xl p-4 transition-all duration-200 hover:-translate-y-1 hover:shadow-[0_22px_45px_-12px_rgba(0,35,90,0.18),inset_0_1px_1.5px_0_rgba(255,255,255,1)]"
    >
        <div
            v-if="icon"
            :class="
                cn(
                    'flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl shadow-[inset_0_1px_1.5px_rgba(255,255,255,0.8),0_4px_12px_rgba(0,0,0,0.05)] ring-1 backdrop-blur-md transition-transform duration-200 ring-inset group-hover:scale-105',
                    toneClasses[tone],
                )
            "
        >
            <component :is="icon" class="h-5 w-5" />
        </div>
        <div class="min-w-0">
            <p
                class="truncate text-[11px] font-bold tracking-[0.14em] text-slate-500 uppercase"
            >
                {{ label }}
            </p>
            <p
                class="tnum mt-1 truncate text-xl font-black tracking-tight text-slate-900"
            >
                {{ formattedValue }}
            </p>
            <p
                v-if="sublabel"
                class="mt-0.5 truncate text-[11px] font-medium text-slate-500"
            >
                {{ sublabel }}
            </p>
        </div>
    </div>
</template>
