<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        items: { name: string; amount: number }[];
        size?: number;
        thickness?: number;
        colors?: string[];
    }>(),
    {
        size: 190,
        thickness: 22,
        colors: () => [
            '#003B7D',
            '#22d3ee',
            '#34d399',
            '#f59e0b',
            '#f43f5e',
            '#e879f9',
            '#94a3b8',
            '#fbbf24',
        ],
    },
);

const total = computed(() =>
    props.items.reduce((sum, item) => sum + item.amount, 0),
);

const segments = computed(() => {
    if (total.value <= 0) return [];

    const radius = (props.size - props.thickness) / 2;
    const circumference = 2 * Math.PI * radius;
    const multipleSegments = props.items.length > 1;
    let accumulated = 0;

    return props.items.map((item, index) => {
        const length = circumference * (item.amount / total.value);
        const gap =
            multipleSegments && length > 8 ? Math.min(4, length / 3) : 0;
        const segment = {
            name: item.name,
            amount: item.amount,
            color: props.colors[index % props.colors.length],
            dashArray: `${Math.max(length - gap, 0.5)} ${circumference}`,
            dashOffset: `-${accumulated}`,
        };
        accumulated += length;

        return segment;
    });
});

const percent = computed(
    () => (value: number) =>
        total.value > 0 ? Math.round((value / total.value) * 100) : 0,
);

const centerValue = computed(() => {
    if (total.value === 0) return '--';
    return total.value % 1 === 0
        ? total.value.toLocaleString()
        : total.value.toLocaleString(undefined, {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          });
});
</script>

<template>
    <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-center">
        <div
            class="relative shrink-0"
            :style="{ width: `${size}px`, height: `${size}px` }"
        >
            <svg
                :width="size"
                :height="size"
                :viewBox="`0 0 ${size} ${size}`"
                class="rotate-[-90deg]"
            >
                <circle
                    :cx="size / 2"
                    :cy="size / 2"
                    :r="(size - thickness) / 2"
                    fill="none"
                    stroke="rgba(2,43,90,0.08)"
                    :stroke-width="thickness"
                />
                <circle
                    v-for="(segment, index) in segments"
                    :key="index"
                    :cx="size / 2"
                    :cy="size / 2"
                    :r="(size - thickness) / 2"
                    fill="none"
                    :stroke="segment.color"
                    :stroke-width="thickness"
                    :stroke-dasharray="segment.dashArray"
                    :stroke-dashoffset="segment.dashOffset"
                    stroke-linecap="butt"
                    class="transition-all duration-500"
                />
            </svg>
            <div
                class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center"
            >
                <p
                    class="text-[10px] font-bold tracking-[0.16em] text-slate-500 uppercase"
                >
                    Total
                </p>
                <p class="tnum text-xl font-black tracking-tight text-gray-900">
                    {{ centerValue }}
                </p>
            </div>
        </div>

        <div v-if="segments.length" class="w-full min-w-0 flex-1 space-y-2">
            <div
                v-for="(segment, index) in segments"
                :key="index"
                class="flex items-center gap-2.5"
            >
                <span
                    class="h-2.5 w-2.5 shrink-0 rounded-full"
                    :style="{ backgroundColor: segment.color }"
                />
                <span
                    class="min-w-0 flex-1 truncate text-xs font-semibold text-slate-600"
                    >{{ segment.name }}</span
                >
                <span class="tnum text-xs font-black text-gray-900"
                    >{{ segment.amount.toLocaleString() }}
                    <span class="font-semibold text-slate-500"
                        >{{ percent(segment.amount) }}%</span
                    ></span
                >
            </div>
        </div>
        <p v-else class="text-sm text-slate-500 italic">
            No expenses recorded in this period.
        </p>
    </div>
</template>
