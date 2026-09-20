<script setup lang="ts">
import { computed, ref } from 'vue';

interface Series {
    name: string;
    color: string;
    data: number[];
}

const props = withDefaults(
    defineProps<{
        labels: string[];
        series: Series[];
        height?: number;
        formatValue?: (value: number) => string;
        formatTick?: (value: number) => string;
        formatLabel?: (label: string) => string;
    }>(),
    {
        height: 264,
        formatValue: (value: number) =>
            `Rs ${Math.round(value).toLocaleString()}`,
        formatTick: (value: number) => {
            const abs = Math.abs(value);
            if (abs >= 1_000_000) return `${(value / 1_000_000).toFixed(1)}M`;
            if (abs >= 1_000) return `${Math.round(value / 1_000)}k`;
            return String(Math.round(value));
        },
        formatLabel: (label: string) => label,
    },
);

const wrapperRef = ref<HTMLElement | null>(null);
const hoverIndex = ref<number | null>(null);

const WIDTH = 720;
const PADDING = { top: 14, right: 14, bottom: 30, left: 58 };
const innerWidth = WIDTH - PADDING.left - PADDING.right;

const viewBoxHeight = computed(
    () => props.height + PADDING.top + PADDING.bottom,
);
const innerHeight = computed(() => props.height);

const allValues = computed(() => props.series.flatMap((series) => series.data));

const domain = computed(() => {
    const rawMin = allValues.value.length ? Math.min(...allValues.value, 0) : 0;
    const rawMax = allValues.value.length ? Math.max(...allValues.value, 1) : 1;
    const spread = rawMax - rawMin || 1;
    return { min: rawMin - spread * 0.08, max: rawMax + spread * 0.08 };
});

const ticks = computed(() => {
    const { min, max } = domain.value;
    return Array.from(
        { length: 5 },
        (_, index) => min + ((max - min) * index) / 4,
    );
});

const x = (index: number) => {
    if (props.labels.length <= 1) return innerWidth / 2;
    return (index / (props.labels.length - 1)) * innerWidth;
};

const y = (value: number) => {
    const { min, max } = domain.value;
    return (
        innerHeight.value - ((value - min) / (max - min)) * innerHeight.value
    );
};

const pointsFor = (data: number[]) =>
    data.map((value, index) => `${x(index)},${y(value)}`).join(' ');

const areaPath = (data: number[]) => {
    if (!data.length) return '';
    const points = data
        .map((value, index) => `${x(index)},${y(value)}`)
        .join(' L ');
    return `M ${PADDING.left},${innerHeight.value} L ${points} L ${x(data.length - 1)},${innerHeight.value} Z`;
};

const primaryAreaPath = computed(() =>
    props.series[0] ? areaPath(props.series[0].data) : '',
);

const visibleLabelIndexes = computed(() => {
    const total = props.labels.length;
    if (total <= 6) return props.labels.map((_, index) => index);
    const step = Math.ceil(total / 6);
    return props.labels
        .map((_, index) => index)
        .filter((index) => index % step === 0);
});

const tooltipStyle = computed(() => {
    if (hoverIndex.value === null || props.labels.length <= 1)
        return { display: 'none' };
    const ratio =
        props.labels.length > 1
            ? hoverIndex.value / (props.labels.length - 1)
            : 0.5;
    const clamped = Math.min(Math.max(ratio, 0.08), 0.92) * 100;

    return { left: `${clamped}%`, display: 'block' };
});

const tooltipLabel = computed(() =>
    hoverIndex.value === null
        ? ''
        : props.formatLabel(props.labels[hoverIndex.value] ?? ''),
);

function onPointerMove(event: PointerEvent) {
    const el = wrapperRef.value;
    if (!el || props.labels.length === 0) return;
    const rect = el.getBoundingClientRect();
    const ratio = Math.min(
        Math.max((event.clientX - rect.left) / rect.width, 0),
        1,
    );
    hoverIndex.value = Math.round(ratio * (props.labels.length - 1));
}

function onPointerLeave() {
    hoverIndex.value = null;
}
</script>

<template>
    <div
        ref="wrapperRef"
        class="relative w-full"
        @pointermove="onPointerMove"
        @pointerleave="onPointerLeave"
    >
        <div class="mb-3 flex flex-wrap items-center gap-4">
            <div
                v-for="(line, index) in series"
                :key="line.name"
                class="flex items-center gap-1.5"
            >
                <span
                    class="h-1 w-4 rounded-full"
                    :style="{ backgroundColor: line.color }"
                />
                <span
                    class="text-[11px] font-bold tracking-[0.12em] text-slate-500 uppercase"
                    >{{ line.name }}</span
                >
            </div>
        </div>

        <div class="relative">
            <svg
                :viewBox="`0 0 ${WIDTH} ${viewBoxHeight}`"
                class="h-auto w-full overflow-visible"
            >
                <defs>
                    <linearGradient
                        id="line-area-fill"
                        x1="0"
                        y1="0"
                        x2="0"
                        y2="1"
                    >
                        <stop offset="0%" stop-color="rgba(139,92,246,0.32)" />
                        <stop offset="100%" stop-color="rgba(139,92,246,0)" />
                    </linearGradient>
                </defs>

                <g v-for="(tick, index) in ticks" :key="index">
                    <line
                        :x1="PADDING.left"
                        :x2="PADDING.left + innerWidth"
                        :y1="y(tick)"
                        :y2="y(tick)"
                        stroke="rgba(2,43,90,0.1)"
                        stroke-width="1"
                        stroke-dasharray="3 5"
                    />
                    <text
                        :x="PADDING.left - 8"
                        :y="y(tick) + 3"
                        text-anchor="end"
                        class="fill-slate-500"
                        font-size="10"
                    >
                        {{ formatTick(tick) }}
                    </text>
                </g>

                <g v-for="index in visibleLabelIndexes" :key="index">
                    <text
                        :x="x(index)"
                        :y="viewBoxHeight - 8"
                        text-anchor="middle"
                        class="fill-slate-500"
                        font-size="10"
                    >
                        {{ formatLabel(labels[index]) }}
                    </text>
                </g>

                <path :d="primaryAreaPath" fill="url(#line-area-fill)" />

                <polyline
                    v-for="line in series"
                    :key="`line-${line.name}`"
                    :points="pointsFor(line.data)"
                    fill="none"
                    :stroke="line.color"
                    stroke-width="2.25"
                    stroke-linejoin="round"
                    stroke-linecap="round"
                />

                <g v-if="hoverIndex !== null">
                    <line
                        :x1="x(hoverIndex)"
                        :x2="x(hoverIndex)"
                        :y1="0"
                        :y2="innerHeight"
                        stroke="rgba(167,139,250,0.55)"
                        stroke-width="1"
                        stroke-dasharray="2 3"
                    />
                    <circle
                        v-for="line in series"
                        :key="`dot-${line.name}`"
                        :cx="x(hoverIndex)"
                        :cy="y(line.data[hoverIndex ?? 0] ?? 0)"
                        r="4"
                        :fill="line.color"
                        stroke="#ffffff"
                        stroke-width="2"
                    />
                </g>
            </svg>

            <div
                class="pointer-events-none absolute top-2 z-10 -translate-x-1/2 rounded-xl border border-gray-200 bg-white/95 px-3 py-2 shadow-[0_10px_30px_rgba(2,43,90,0.15)] backdrop-blur"
                :style="tooltipStyle"
            >
                <p
                    class="mb-1.5 text-[10px] font-bold tracking-[0.14em] text-slate-500 uppercase"
                >
                    {{ tooltipLabel }}
                </p>
                <div
                    v-for="line in series"
                    :key="`tip-${line.name}`"
                    class="flex items-center gap-2"
                >
                    <span
                        class="h-1.5 w-1.5 rounded-full"
                        :style="{ backgroundColor: line.color }"
                    />
                    <span class="text-[11px] font-semibold text-slate-600">{{
                        line.name
                    }}</span>
                    <span class="tnum text-[11px] font-black text-gray-900">{{
                        formatValue(line.data[hoverIndex ?? 0] ?? 0)
                    }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
