<script setup lang="ts">
import type { Component } from 'vue';
import { computed } from 'vue';
import { DollarSign } from '@lucide/vue';
import { cn } from '@/lib/utils';

export type IconTone = 'blue' | 'green' | 'red';
export type ValueColor = 'blue' | 'green' | 'red';

const props = withDefaults(
    defineProps<{
        label: string;
        value?: number;
        sublabel?: string;
        iconTone?: IconTone;
        valueColor?: ValueColor;
        icon?: Component;
        prefix?: string;
    }>(),
    {
        value: 0,
        sublabel: undefined,
        iconTone: 'blue',
        valueColor: 'blue',
        icon: undefined,
        prefix: 'Rs',
    },
);

const iconToneClasses: Record<IconTone, string> = {
    blue: 'bg-blue-100/90 text-blue-600',
    green: 'bg-emerald-100/90 text-emerald-600',
    red: 'bg-rose-100/90 text-rose-500',
};

const valueColorClasses: Record<ValueColor, string> = {
    blue: 'text-blue-600',
    green: 'text-emerald-600',
    red: 'text-rose-500',
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

    const formattedPrefix = props.prefix ? `${props.prefix} ` : '';
    return `${props.value < 0 ? '-' : ''}${formattedPrefix}${text}`;
});
</script>

<template>
    <div
        class="flex items-center gap-3.5 rounded-2xl border border-slate-200/90 bg-white p-4 shadow-2xs transition-all duration-150 hover:shadow-md"
    >
        <!-- Circular Icon Badge (Matches reference image) -->
        <div
            :class="
                cn(
                    'flex h-11 w-11 shrink-0 items-center justify-center rounded-full shadow-xs transition-transform duration-150',
                    iconToneClasses[iconTone],
                )
            "
        >
            <component :is="icon || DollarSign" class="h-5 w-5 stroke-[2.5]" />
        </div>

        <!-- Text Area -->
        <div class="min-w-0">
            <h3 class="truncate text-sm leading-tight font-bold text-slate-900">
                {{ label }}
            </h3>
            <p
                v-if="sublabel"
                class="mt-0.5 truncate text-[11px] font-normal text-slate-500"
            >
                {{ sublabel }}
            </p>
            <p
                class="tnum mt-0.5 text-base font-black tracking-tight sm:text-lg"
                :class="valueColorClasses[valueColor]"
            >
                {{ formattedValue }}
            </p>
        </div>
    </div>
</template>
