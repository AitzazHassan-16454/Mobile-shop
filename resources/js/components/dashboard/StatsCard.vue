<script setup lang="ts">
import type { Component } from 'vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

export type StatsTone =
    | 'violet'
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
        tone: 'violet',
        icon: undefined,
        prefix: 'Rs',
    },
);

const toneClasses: Record<StatsTone, string> = {
    violet: 'bg-violet-100 text-violet-600 ring-violet-200',
    sky: 'bg-sky-100 text-sky-600 ring-sky-200',
    emerald: 'bg-emerald-100 text-emerald-600 ring-emerald-200',
    amber: 'bg-amber-100 text-amber-600 ring-amber-200',
    rose: 'bg-rose-100 text-rose-600 ring-rose-200',
    fuchsia: 'bg-fuchsia-100 text-fuchsia-600 ring-fuchsia-200',
    slate: 'bg-gray-100 text-slate-600 ring-gray-200',
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
        class="bg-card flex items-center gap-3.5 rounded-xl border border-gray-200 p-4 shadow-[0_1px_2px_rgba(2,43,90,0.06)]"
    >
        <div
            v-if="icon"
            :class="
                cn(
                    'flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset',
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
                class="tnum mt-1 truncate text-xl font-black tracking-tight text-gray-900"
            >
                {{ formattedValue }}
            </p>
            <p
                v-if="sublabel"
                class="mt-0.5 truncate text-[11px] text-slate-500"
            >
                {{ sublabel }}
            </p>
        </div>
    </div>
</template>
