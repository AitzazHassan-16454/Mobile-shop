<script setup lang="ts">
import {
    Activity,
    ArrowUpRight,
    Award,
    Banknote,
    BarChart2,
    Calendar,
    Coins,
    Flame,
    Layers,
    ShoppingCart,
    Sparkles,
    TrendingUp,
} from '@lucide/vue';
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
        height: 290,
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

// View Modes
type ActiveView = 'sales' | 'profit' | 'payments' | 'expenses' | 'all';
const activeView = ref<ActiveView>('sales');
const chartStyle = ref<'area' | 'bar'>('area');
const hoverIndex = ref<number | null>(null);
const wrapperRef = ref<HTMLElement | null>(null);

// Metric Theme System
interface MetricTheme {
    key: ActiveView;
    name: string;
    shortTitle: string;
    color: string;
    gradientFrom: string;
    gradientTo: string;
    icon: any;
    badgeBg: string;
    badgeText: string;
    badgeBorder: string;
}

const metricThemes: Record<string, MetricTheme> = {
    sales: {
        key: 'sales',
        name: 'Sales Revenue',
        shortTitle: 'Sales',
        color: '#003B7D',
        gradientFrom: '#003B7D',
        gradientTo: '#0284c7',
        icon: ShoppingCart,
        badgeBg: 'bg-blue-50',
        badgeText: 'text-[#003B7D]',
        badgeBorder: 'border-blue-200/80',
    },
    profit: {
        key: 'profit',
        name: 'Net Profit',
        shortTitle: 'Profit',
        color: '#059669',
        gradientFrom: '#059669',
        gradientTo: '#10b981',
        icon: TrendingUp,
        badgeBg: 'bg-emerald-50',
        badgeText: 'text-emerald-700',
        badgeBorder: 'border-emerald-200/80',
    },
    payments: {
        key: 'payments',
        name: 'Cash Collections',
        shortTitle: 'Collections',
        color: '#0284c7',
        gradientFrom: '#0284c7',
        gradientTo: '#38bdf8',
        icon: Banknote,
        badgeBg: 'bg-sky-50',
        badgeText: 'text-sky-700',
        badgeBorder: 'border-sky-200/80',
    },
    expenses: {
        key: 'expenses',
        name: 'Shop Expenses',
        shortTitle: 'Expenses',
        color: '#e11d48',
        gradientFrom: '#e11d48',
        gradientTo: '#fb7185',
        icon: Coins,
        badgeBg: 'bg-rose-50',
        badgeText: 'text-rose-700',
        badgeBorder: 'border-rose-200/80',
    },
};

function getMetricTheme(name: string): MetricTheme {
    const k = name.toLowerCase();
    return (
        metricThemes[k] || {
            key: 'sales',
            name: name,
            shortTitle: name,
            color: '#003B7D',
            gradientFrom: '#003B7D',
            gradientTo: '#0284c7',
            icon: ShoppingCart,
            badgeBg: 'bg-slate-50',
            badgeText: 'text-slate-800',
            badgeBorder: 'border-slate-200',
        }
    );
}

// Global Totals & Calculations
const totals = computed(() => {
    const map = new Map<string, number>();
    for (const s of props.series) {
        const sum = s.data.reduce((acc, v) => acc + (v || 0), 0);
        map.set(s.name.toLowerCase(), sum);
    }
    return map;
});

const totalSales = computed(() => totals.value.get('sales') ?? 0);
const totalProfit = computed(() => totals.value.get('profit') ?? 0);
const totalPayments = computed(() => totals.value.get('payments') ?? 0);
const totalExpenses = computed(() => totals.value.get('expenses') ?? 0);

const profitMargin = computed(() => {
    if (totalSales.value <= 0) return 0;
    return Math.round((totalProfit.value / totalSales.value) * 100);
});

const collectionRate = computed(() => {
    if (totalSales.value <= 0) return 100;
    return Math.min(
        Math.round((totalPayments.value / totalSales.value) * 100),
        100,
    );
});

const currentActiveSeries = computed(() => {
    if (activeView.value === 'all') {
        return (
            props.series.find((s) => s.name.toLowerCase() === 'sales') ??
            props.series[0]
        );
    }
    const target =
        activeView.value === 'payments'
            ? 'payments'
            : activeView.value === 'profit'
              ? 'profit'
              : activeView.value === 'expenses'
                ? 'expenses'
                : 'sales';
    return (
        props.series.find((s) => s.name.toLowerCase() === target) ??
        props.series[0]
    );
});

const dailyAverageValue = computed(() => {
    const days = Math.max(props.labels.length, 1);
    const series = currentActiveSeries.value;
    if (!series) return 0;
    const sum = series.data.reduce((acc, v) => acc + (v || 0), 0);
    return Math.round(sum / days);
});

// Best Performing Day
const bestDay = computed(() => {
    const series = currentActiveSeries.value;
    if (!series || !series.data.length || !props.labels.length) return null;
    let maxIdx = 0;
    let maxVal = series.data[0] ?? 0;
    for (let i = 1; i < series.data.length; i++) {
        if ((series.data[i] ?? 0) > maxVal) {
            maxVal = series.data[i] ?? 0;
            maxIdx = i;
        }
    }
    if (maxVal <= 0) return null;
    return {
        label: props.formatLabel(props.labels[maxIdx] ?? ''),
        amount: maxVal,
        index: maxIdx,
    };
});

// Hero Display Data based on Active Tab
const heroDisplay = computed(() => {
    if (activeView.value === 'all') {
        return {
            title: 'Overall Financial Pulse',
            amount: formatValueWithRs(totalSales.value),
            badge: `${profitMargin.value}% Margin`,
            badgeColor: 'text-emerald-700 bg-emerald-50 border-emerald-200/80',
            subtext: `Net Profit: ${formatValueWithRs(totalProfit.value)} • Expenses: ${formatValueWithRs(totalExpenses.value)}`,
        };
    }

    if (activeView.value === 'profit') {
        return {
            title: 'Net Profit Performance',
            amount: formatValueWithRs(totalProfit.value),
            badge: `${profitMargin.value}% Net Margin`,
            badgeColor: 'text-emerald-700 bg-emerald-50 border-emerald-200/80',
            subtext: `Earned after inventory cost and operational overheads`,
        };
    }

    if (activeView.value === 'payments') {
        return {
            title: 'Cash Collections',
            amount: formatValueWithRs(totalPayments.value),
            badge: `${collectionRate.value}% Realized`,
            badgeColor: 'text-sky-700 bg-sky-50 border-sky-200/80',
            subtext: `Direct cash & digital payments received from sales`,
        };
    }

    if (activeView.value === 'expenses') {
        return {
            title: 'Shop Operational Expenses',
            amount: formatValueWithRs(totalExpenses.value),
            badge: `${totalSales.value > 0 ? Math.round((totalExpenses.value / totalSales.value) * 100) : 0}% of Sales`,
            badgeColor: 'text-rose-700 bg-rose-50 border-rose-200/80',
            subtext: `Store bills, rent, daily supplies and utility costs`,
        };
    }

    // Default: Sales
    return {
        title: 'Sales Revenue',
        amount: formatValueWithRs(totalSales.value),
        badge: `Avg ${formatValueWithRs(dailyAverageValue.value)}/day`,
        badgeColor: 'text-[#003B7D] bg-blue-50 border-blue-200/80',
        subtext: `Net revenue generated across selected date range`,
    };
});

function formatValueWithRs(val: number): string {
    return `Rs ${Math.round(val).toLocaleString()}`;
}

// Active Series for SVG Rendering
const activeSeries = computed(() => {
    if (activeView.value === 'all') {
        return props.series;
    }
    const found = props.series.find(
        (s) => s.name.toLowerCase() === activeView.value,
    );
    return found ? [found] : props.series.slice(0, 1);
});

// SVG Geometry & Math
const WIDTH = 860;
const PADDING = { top: 28, right: 30, bottom: 44, left: 66 };
const innerWidth = WIDTH - PADDING.left - PADDING.right;
const innerHeight = computed(() => props.height);
const viewBoxHeight = computed(
    () => innerHeight.value + PADDING.top + PADDING.bottom,
);

const allValues = computed(() => {
    const list = activeSeries.value.flatMap((s) => s.data);
    return list.length ? list : [0];
});

const domain = computed(() => {
    const rawMin = Math.min(...allValues.value, 0);
    const rawMax = Math.max(...allValues.value, 100);
    const spread = rawMax - rawMin || 1;
    return {
        min: rawMin < 0 ? rawMin - spread * 0.05 : 0,
        max: rawMax + spread * 0.15, // generous headroom for elegance
    };
});

const ticks = computed(() => {
    const { min, max } = domain.value;
    const count = 5;
    return Array.from(
        { length: count },
        (_, i) => min + ((max - min) * i) / (count - 1),
    );
});

const slotCount = computed(() => Math.max(props.labels.length, 1));
const slotWidth = computed(() => innerWidth / slotCount.value);

const slotCenter = (index: number) => {
    return PADDING.left + (index + 0.5) * slotWidth.value;
};

const getY = (val: number) => {
    const { min, max } = domain.value;
    const range = max - min || 1;
    return (
        PADDING.top +
        innerHeight.value -
        ((val - min) / range) * innerHeight.value
    );
};

const baseY = computed(() => {
    return Math.min(getY(0), PADDING.top + innerHeight.value);
});

// Smooth Bézier Spline Curves
function getPoints(data: number[]): { x: number; y: number }[] {
    return data.map((val, idx) => ({
        x: slotCenter(idx),
        y: getY(val || 0),
    }));
}

function getCubicBezierPath(points: { x: number; y: number }[]): string {
    if (!points.length) return '';
    if (points.length === 1)
        return `M ${points[0].x.toFixed(1)},${points[0].y.toFixed(1)}`;
    if (points.length === 2) {
        return (
            'M ' +
            points.map((p) => `${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' L ')
        );
    }

    let d = `M ${points[0].x.toFixed(1)},${points[0].y.toFixed(1)}`;
    const tension = 0.18;

    for (let i = 0; i < points.length - 1; i++) {
        const p0 = points[i === 0 ? 0 : i - 1];
        const p1 = points[i];
        const p2 = points[i + 1];
        const p3 = points[i + 2 < points.length ? i + 2 : i + 1];

        const cp1x = p1.x + (p2.x - p0.x) * tension;
        let cp1y = p1.y + (p2.y - p0.y) * tension;
        const cp2x = p2.x - (p3.x - p1.x) * tension;
        let cp2y = p2.y - (p3.y - p1.y) * tension;

        if (domain.value.min >= 0) {
            cp1y = Math.min(cp1y, baseY.value);
            cp2y = Math.min(cp2y, baseY.value);
        }

        d += ` C ${cp1x.toFixed(1)},${cp1y.toFixed(1)} ${cp2x.toFixed(1)},${cp2y.toFixed(1)} ${p2.x.toFixed(1)},${p2.y.toFixed(1)}`;
    }
    return d;
}

function getAreaPath(points: { x: number; y: number }[]): string {
    if (!points.length) return '';
    const lineD = getCubicBezierPath(points);
    const first = points[0];
    const last = points[points.length - 1];
    return `${lineD} L ${last.x.toFixed(1)},${baseY.value.toFixed(1)} L ${first.x.toFixed(1)},${baseY.value.toFixed(1)} Z`;
}

// Peak Point Indicator on the Curve
const peakPoint = computed(() => {
    if (!activeSeries.value.length || !props.labels.length) return null;
    const primary = activeSeries.value[0];
    let maxIdx = 0;
    let maxVal = primary.data[0] ?? 0;
    for (let i = 1; i < primary.data.length; i++) {
        if ((primary.data[i] ?? 0) > maxVal) {
            maxVal = primary.data[i] ?? 0;
            maxIdx = i;
        }
    }
    if (maxVal <= 0) return null;
    return {
        x: slotCenter(maxIdx),
        y: getY(maxVal),
        amount: maxVal,
        color: getMetricTheme(primary.name).color,
    };
});

// Bar Geometry (when in Bar Mode)
const singleBarWidth = computed(() => {
    const count = activeSeries.value.length;
    if (count === 1) {
        return Math.min(Math.max(slotWidth.value * 0.52, 10), 36);
    }
    const groupW = Math.min(slotWidth.value * 0.78, 52);
    return Math.max(groupW / count, 3.5);
});

function getBarX(slotIdx: number, seriesIdx = 0): number {
    const center = slotCenter(slotIdx);
    const count = activeSeries.value.length;
    const singleW = singleBarWidth.value;
    if (count === 1) {
        return center - singleW / 2;
    }
    const groupW = singleW * count;
    return center - groupW / 2 + seriesIdx * singleW;
}

function getBarHeight(val: number): number {
    const bY = baseY.value;
    const valY = getY(val || 0);
    return Math.max(Math.abs(bY - valY), val > 0 ? 3 : 0);
}

function getBarY(val: number): number {
    const bY = baseY.value;
    const valY = getY(val || 0);
    return Math.min(bY, valY);
}

// X-Axis Stepping
const visibleLabelIndexes = computed(() => {
    const total = props.labels.length;
    if (total <= 8) return props.labels.map((_, i) => i);
    const step = Math.ceil(total / 8);
    const indices: number[] = [];
    for (let i = 0; i < total; i += step) {
        indices.push(i);
    }
    const lastIndex = indices[indices.length - 1];
    if (lastIndex !== undefined && lastIndex !== total - 1) {
        if (total - 1 - lastIndex <= 1) {
            indices[indices.length - 1] = total - 1;
        } else {
            indices.push(total - 1);
        }
    }
    return indices;
});

// Hover & Tooltip Scrubber
function onPointerMove(event: PointerEvent) {
    const el = wrapperRef.value;
    if (!el || props.labels.length === 0) return;
    const rect = el.getBoundingClientRect();
    const ratio = Math.min(
        Math.max((event.clientX - rect.left) / rect.width, 0),
        1,
    );
    hoverIndex.value = Math.min(
        Math.floor(ratio * props.labels.length),
        props.labels.length - 1,
    );
}

function onPointerLeave() {
    hoverIndex.value = null;
}

function formatFullDate(label: string): string {
    const day = label.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (day) {
        const d = new Date(Number(day[1]), Number(day[2]) - 1, Number(day[3]));
        return new Intl.DateTimeFormat('en-GB', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        }).format(d);
    }
    const ym = label.match(/^(\d{4})-(\d{2})$/);
    if (ym) {
        return new Intl.DateTimeFormat('en-GB', {
            month: 'long',
            year: 'numeric',
        }).format(new Date(Number(ym[1]), Number(ym[2]) - 1, 1));
    }
    return label;
}

const tooltipPosition = computed(() => {
    if (hoverIndex.value === null || props.labels.length === 0) {
        return { display: 'none' };
    }
    const ratio = (hoverIndex.value + 0.5) / props.labels.length;
    const percent = ratio * 100;

    let transform = 'translateX(-50%)';
    if (ratio < 0.2) {
        transform = 'translateX(0%)';
    } else if (ratio > 0.8) {
        transform = 'translateX(-100%)';
    }

    return {
        left: `${percent}%`,
        transform,
        display: 'block',
    };
});

const currentHoverDate = computed(() => {
    if (hoverIndex.value === null) return '';
    return formatFullDate(props.labels[hoverIndex.value] ?? '');
});

const hoverDaySales = computed(() => {
    if (hoverIndex.value === null) return 0;
    const s = props.series.find((i) => i.name.toLowerCase() === 'sales');
    return s ? (s.data[hoverIndex.value] ?? 0) : 0;
});

const hoverDayProfit = computed(() => {
    if (hoverIndex.value === null) return 0;
    const p = props.series.find((i) => i.name.toLowerCase() === 'profit');
    return p ? (p.data[hoverIndex.value] ?? 0) : 0;
});

const hoverDayMargin = computed(() => {
    if (hoverDaySales.value <= 0) return null;
    return Math.round((hoverDayProfit.value / hoverDaySales.value) * 100);
});
</script>

<template>
    <div class="relative w-full space-y-6">
        <!-- 1. LUXURY EXECUTIVE HEADER -->
        <div
            class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"
        >
            <!-- Left: Hero Big Metric Display -->
            <div>
                <div class="flex items-center gap-2">
                    <span
                        class="flex h-2 w-2 animate-pulse rounded-full bg-[#003B7D] ring-4 ring-blue-100"
                    />
                    <span
                        class="text-[11px] font-extrabold tracking-[0.16em] text-slate-400 uppercase"
                    >
                        {{ heroDisplay.title }}
                    </span>
                </div>

                <div class="mt-1.5 flex flex-wrap items-baseline gap-3">
                    <span
                        class="tnum text-3xl font-black tracking-tight text-slate-900 sm:text-4xl"
                    >
                        {{ heroDisplay.amount }}
                    </span>

                    <span
                        :class="[
                            'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-bold shadow-2xs backdrop-blur-md',
                            heroDisplay.badgeColor,
                        ]"
                    >
                        <Sparkles class="h-3 w-3" />
                        {{ heroDisplay.badge }}
                    </span>
                </div>

                <p class="mt-1 text-xs font-medium text-slate-500">
                    {{ heroDisplay.subtext }}
                </p>
            </div>

            <!-- Right: Apple-style Segmented Metric Switcher & Chart Style -->
            <div class="flex flex-wrap items-center gap-2.5">
                <!-- Segmented Control Pills -->
                <div
                    class="inline-flex items-center rounded-2xl border border-slate-200/80 bg-slate-100/80 p-1 shadow-inner backdrop-blur-md"
                >
                    <button
                        type="button"
                        @click="activeView = 'sales'"
                        :class="[
                            'inline-flex cursor-pointer items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold transition-all',
                            activeView === 'sales'
                                ? 'bg-white text-[#003B7D] shadow-xs'
                                : 'text-slate-500 hover:text-slate-900',
                        ]"
                    >
                        <span
                            class="h-2 w-2 rounded-full"
                            :class="
                                activeView === 'sales'
                                    ? 'bg-[#003B7D]'
                                    : 'bg-slate-300'
                            "
                        />
                        <span>Sales</span>
                    </button>

                    <button
                        type="button"
                        @click="activeView = 'profit'"
                        :class="[
                            'inline-flex cursor-pointer items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold transition-all',
                            activeView === 'profit'
                                ? 'bg-white text-emerald-700 shadow-xs'
                                : 'text-slate-500 hover:text-slate-900',
                        ]"
                    >
                        <span
                            class="h-2 w-2 rounded-full"
                            :class="
                                activeView === 'profit'
                                    ? 'bg-emerald-600'
                                    : 'bg-slate-300'
                            "
                        />
                        <span>Profit</span>
                    </button>

                    <button
                        type="button"
                        @click="activeView = 'payments'"
                        :class="[
                            'inline-flex cursor-pointer items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold transition-all',
                            activeView === 'payments'
                                ? 'bg-white text-sky-700 shadow-xs'
                                : 'text-slate-500 hover:text-slate-900',
                        ]"
                    >
                        <span
                            class="h-2 w-2 rounded-full"
                            :class="
                                activeView === 'payments'
                                    ? 'bg-sky-600'
                                    : 'bg-slate-300'
                            "
                        />
                        <span>Collections</span>
                    </button>

                    <button
                        type="button"
                        @click="activeView = 'expenses'"
                        :class="[
                            'inline-flex cursor-pointer items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold transition-all',
                            activeView === 'expenses'
                                ? 'bg-white text-rose-700 shadow-xs'
                                : 'text-slate-500 hover:text-slate-900',
                        ]"
                    >
                        <span
                            class="h-2 w-2 rounded-full"
                            :class="
                                activeView === 'expenses'
                                    ? 'bg-rose-600'
                                    : 'bg-slate-300'
                            "
                        />
                        <span>Expenses</span>
                    </button>

                    <button
                        type="button"
                        @click="activeView = 'all'"
                        :class="[
                            'inline-flex cursor-pointer items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold transition-all',
                            activeView === 'all'
                                ? 'bg-[#003B7D] text-white shadow-xs'
                                : 'text-slate-500 hover:text-slate-900',
                        ]"
                    >
                        <Layers class="h-3 w-3" />
                        <span>All</span>
                    </button>
                </div>

                <!-- Chart Type Switcher: Area vs Bars -->
                <div
                    class="inline-flex items-center rounded-2xl border border-slate-200/80 bg-slate-100/80 p-1 shadow-inner backdrop-blur-md"
                >
                    <button
                        type="button"
                        @click="chartStyle = 'area'"
                        :class="[
                            'flex h-7 w-7 cursor-pointer items-center justify-center rounded-xl transition',
                            chartStyle === 'area'
                                ? 'bg-white text-[#003B7D] shadow-xs'
                                : 'text-slate-400 hover:text-slate-800',
                        ]"
                        title="Smooth Wave Chart"
                    >
                        <Activity class="h-4 w-4" />
                    </button>

                    <button
                        type="button"
                        @click="chartStyle = 'bar'"
                        :class="[
                            'flex h-7 w-7 cursor-pointer items-center justify-center rounded-xl transition',
                            chartStyle === 'bar'
                                ? 'bg-white text-[#003B7D] shadow-xs'
                                : 'text-slate-400 hover:text-slate-800',
                        ]"
                        title="Modern Column Bars"
                    >
                        <BarChart2 class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>

        <!-- 2. HIGH-END SVG CHART CANVAS -->
        <div
            ref="wrapperRef"
            class="relative w-full select-none"
            @pointermove="onPointerMove"
            @pointerleave="onPointerLeave"
        >
            <svg
                :viewBox="`0 0 ${WIDTH} ${viewBoxHeight}`"
                class="h-auto w-full overflow-visible"
            >
                <defs>
                    <!-- Line Glow Filter -->
                    <filter
                        id="neon-glow"
                        x="-20%"
                        y="-20%"
                        width="140%"
                        height="140%"
                    >
                        <feGaussianBlur stdDeviation="3.5" result="blur" />
                        <feMerge>
                            <feMergeNode in="blur" />
                            <feMergeNode in="SourceGraphic" />
                        </feMerge>
                    </filter>

                    <!-- Luminous Wave Gradients -->
                    <linearGradient
                        id="wave-grad-sales"
                        x1="0"
                        y1="0"
                        x2="0"
                        y2="1"
                    >
                        <stop
                            offset="0%"
                            stop-color="#003B7D"
                            stop-opacity="0.32"
                        />
                        <stop
                            offset="50%"
                            stop-color="#0284c7"
                            stop-opacity="0.10"
                        />
                        <stop
                            offset="100%"
                            stop-color="#0284c7"
                            stop-opacity="0.00"
                        />
                    </linearGradient>

                    <linearGradient
                        id="wave-grad-profit"
                        x1="0"
                        y1="0"
                        x2="0"
                        y2="1"
                    >
                        <stop
                            offset="0%"
                            stop-color="#059669"
                            stop-opacity="0.35"
                        />
                        <stop
                            offset="50%"
                            stop-color="#10b981"
                            stop-opacity="0.10"
                        />
                        <stop
                            offset="100%"
                            stop-color="#10b981"
                            stop-opacity="0.00"
                        />
                    </linearGradient>

                    <linearGradient
                        id="wave-grad-payments"
                        x1="0"
                        y1="0"
                        x2="0"
                        y2="1"
                    >
                        <stop
                            offset="0%"
                            stop-color="#0284c7"
                            stop-opacity="0.32"
                        />
                        <stop
                            offset="50%"
                            stop-color="#38bdf8"
                            stop-opacity="0.08"
                        />
                        <stop
                            offset="100%"
                            stop-color="#38bdf8"
                            stop-opacity="0.00"
                        />
                    </linearGradient>

                    <linearGradient
                        id="wave-grad-expenses"
                        x1="0"
                        y1="0"
                        x2="0"
                        y2="1"
                    >
                        <stop
                            offset="0%"
                            stop-color="#e11d48"
                            stop-opacity="0.30"
                        />
                        <stop
                            offset="50%"
                            stop-color="#fb7185"
                            stop-opacity="0.08"
                        />
                        <stop
                            offset="100%"
                            stop-color="#fb7185"
                            stop-opacity="0.00"
                        />
                    </linearGradient>

                    <!-- Column Gradients -->
                    <linearGradient
                        id="bar-grad-sales"
                        x1="0"
                        y1="0"
                        x2="0"
                        y2="1"
                    >
                        <stop offset="0%" stop-color="#0056b3" />
                        <stop offset="100%" stop-color="#002752" />
                    </linearGradient>

                    <linearGradient
                        id="bar-grad-profit"
                        x1="0"
                        y1="0"
                        x2="0"
                        y2="1"
                    >
                        <stop offset="0%" stop-color="#10b981" />
                        <stop offset="100%" stop-color="#047857" />
                    </linearGradient>

                    <linearGradient
                        id="bar-grad-payments"
                        x1="0"
                        y1="0"
                        x2="0"
                        y2="1"
                    >
                        <stop offset="0%" stop-color="#38bdf8" />
                        <stop offset="100%" stop-color="#0284c7" />
                    </linearGradient>

                    <linearGradient
                        id="bar-grad-expenses"
                        x1="0"
                        y1="0"
                        x2="0"
                        y2="1"
                    >
                        <stop offset="0%" stop-color="#fb7185" />
                        <stop offset="100%" stop-color="#be123c" />
                    </linearGradient>
                </defs>

                <!-- Soft Ambient Background Panel -->
                <rect
                    :x="PADDING.left"
                    :y="PADDING.top"
                    :width="innerWidth"
                    :height="innerHeight"
                    rx="16"
                    fill="rgba(248, 250, 252, 0.55)"
                    stroke="rgba(226, 232, 240, 0.65)"
                    stroke-width="1"
                />

                <!-- Delicate Horizontal Grid Lines & Tabular Y-Labels -->
                <g v-for="(tick, index) in ticks" :key="index">
                    <line
                        :x1="PADDING.left"
                        :x2="PADDING.left + innerWidth"
                        :y1="getY(tick)"
                        :y2="getY(tick)"
                        stroke="rgba(226, 232, 240, 0.7)"
                        stroke-width="1"
                        stroke-dasharray="3 6"
                    />
                    <text
                        :x="PADDING.left - 12"
                        :y="getY(tick) + 3.5"
                        text-anchor="end"
                        class="fill-slate-400 font-semibold"
                        font-size="10.5"
                    >
                        {{ formatTick(tick) }}
                    </text>
                </g>

                <!-- Baseline -->
                <line
                    :x1="PADDING.left"
                    :x2="PADDING.left + innerWidth"
                    :y1="baseY"
                    :y2="baseY"
                    stroke="rgba(203, 213, 225, 0.9)"
                    stroke-width="1.25"
                />

                <!-- Hover Column Highlight Background -->
                <rect
                    v-if="hoverIndex !== null && labels.length > 0"
                    :x="PADDING.left + hoverIndex * slotWidth"
                    :y="PADDING.top"
                    :width="slotWidth"
                    :height="innerHeight"
                    rx="10"
                    fill="rgba(0, 59, 125, 0.05)"
                />

                <!-- STYLE 1: LUMINOUS SPLINE AREA WAVE -->
                <template v-if="chartStyle === 'area'">
                    <!-- Gradient Fills -->
                    <path
                        v-for="line in activeSeries"
                        :key="`area-${line.name}`"
                        :d="getAreaPath(getPoints(line.data))"
                        :fill="`url(#wave-grad-${line.name.toLowerCase()})`"
                        class="transition-all duration-300"
                    />

                    <!-- Glowing Curves -->
                    <path
                        v-for="line in activeSeries"
                        :key="`curve-${line.name}`"
                        :d="getCubicBezierPath(getPoints(line.data))"
                        fill="none"
                        :stroke="getMetricTheme(line.name).color"
                        stroke-width="3.25"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="drop-shadow-[0_4px_10px_rgba(0,59,125,0.18)] transition-all duration-300"
                    />

                    <!-- Peak Point Badge on the curve -->
                    <g
                        v-if="
                            peakPoint &&
                            activeSeries.length === 1 &&
                            hoverIndex === null
                        "
                    >
                        <circle
                            :cx="peakPoint.x"
                            :cy="peakPoint.y"
                            r="9"
                            :fill="peakPoint.color"
                            fill-opacity="0.22"
                            class="animate-ping"
                            style="
                                transform-box: fill-box;
                                transform-origin: center;
                            "
                        />
                        <circle
                            :cx="peakPoint.x"
                            :cy="peakPoint.y"
                            r="5"
                            :fill="peakPoint.color"
                            stroke="#ffffff"
                            stroke-width="2.5"
                            class="shadow-md"
                        />
                    </g>
                </template>

                <!-- STYLE 2: MODERN ROUNDED COLUMNS -->
                <template v-else>
                    <g
                        v-for="(line, sIdx) in activeSeries"
                        :key="`col-group-${line.name}`"
                    >
                        <rect
                            v-for="(_, slotIdx) in labels"
                            :key="`bar-${line.name}-${slotIdx}`"
                            :x="getBarX(slotIdx, sIdx)"
                            :y="getBarY(line.data[slotIdx] ?? 0)"
                            :width="singleBarWidth"
                            :height="getBarHeight(line.data[slotIdx] ?? 0)"
                            rx="6"
                            :fill="`url(#bar-grad-${line.name.toLowerCase()})`"
                            :class="[
                                'transition-all duration-150',
                                hoverIndex === slotIdx
                                    ? 'opacity-100 drop-shadow-[0_6px_14px_rgba(0,59,125,0.25)] filter'
                                    : hoverIndex !== null
                                      ? 'opacity-65'
                                      : 'opacity-95 hover:opacity-100',
                            ]"
                        />
                    </g>
                </template>

                <!-- Hover Indicator Laser Line & Halo Dots -->
                <g v-if="hoverIndex !== null && labels.length > 0">
                    <line
                        :x1="slotCenter(hoverIndex)"
                        :x2="slotCenter(hoverIndex)"
                        :y1="PADDING.top"
                        :y2="PADDING.top + innerHeight"
                        stroke="rgba(0, 59, 125, 0.35)"
                        stroke-width="1.5"
                        stroke-dasharray="3 3"
                    />

                    <!-- Glowing Circles at Hover Intersection -->
                    <g
                        v-for="line in activeSeries"
                        :key="`hover-halo-${line.name}`"
                    >
                        <circle
                            :cx="slotCenter(hoverIndex)"
                            :cy="getY(line.data[hoverIndex] ?? 0)"
                            r="8"
                            :fill="getMetricTheme(line.name).color"
                            fill-opacity="0.25"
                        />
                        <circle
                            :cx="slotCenter(hoverIndex)"
                            :cy="getY(line.data[hoverIndex] ?? 0)"
                            r="5"
                            :fill="getMetricTheme(line.name).color"
                            stroke="#ffffff"
                            stroke-width="2.5"
                            class="shadow-md"
                        />
                    </g>
                </g>

                <!-- X-Axis Labels -->
                <g v-for="index in visibleLabelIndexes" :key="index">
                    <line
                        :x1="slotCenter(index)"
                        :x2="slotCenter(index)"
                        :y1="PADDING.top + innerHeight"
                        :y2="PADDING.top + innerHeight + 6"
                        stroke="rgba(203, 213, 225, 0.85)"
                        stroke-width="1"
                    />
                    <text
                        :x="slotCenter(index)"
                        :y="viewBoxHeight - 12"
                        text-anchor="middle"
                        class="fill-slate-500 font-medium"
                        font-size="10.5"
                    >
                        {{ formatLabel(labels[index]) }}
                    </text>
                </g>
            </svg>

            <!-- 3. ULTRA-SLEEK FLOATING GLASS TOOLTIP -->
            <div
                v-if="hoverIndex !== null && labels.length > 0"
                class="pointer-events-none absolute top-4 z-30 min-w-[210px] rounded-2xl border border-slate-200/90 bg-white/95 p-3.5 shadow-[0_20px_50px_rgba(0,35,90,0.18),0_4px_12px_rgba(0,0,0,0.05)] backdrop-blur-2xl transition-all duration-75"
                :style="tooltipPosition"
            >
                <div
                    class="mb-2 flex items-center justify-between border-b border-slate-100 pb-1.5"
                >
                    <div class="flex items-center gap-1.5 text-slate-500">
                        <Calendar class="h-3.5 w-3.5 text-[#003B7D]" />
                        <span class="text-xs font-bold text-slate-800">
                            {{ currentHoverDate }}
                        </span>
                    </div>
                    <span
                        v-if="hoverDayMargin !== null"
                        class="rounded-full border border-emerald-200/60 bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700"
                    >
                        {{ hoverDayMargin }}% Margin
                    </span>
                </div>

                <div class="space-y-1.5">
                    <div
                        v-for="line in activeView === 'all'
                            ? series
                            : activeSeries"
                        :key="`tip-${line.name}`"
                        class="flex items-center justify-between gap-3 text-xs"
                    >
                        <div class="flex items-center gap-1.5">
                            <span
                                class="h-2 w-2 rounded-full"
                                :style="{
                                    backgroundColor: getMetricTheme(line.name)
                                        .color,
                                }"
                            />
                            <span class="font-medium text-slate-600">{{
                                line.name
                            }}</span>
                        </div>
                        <span class="tnum font-extrabold text-slate-900">
                            {{ formatValue(line.data[hoverIndex] ?? 0) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. AIRY, MINIMALIST BOTTOM INSIGHTS STRIP -->
        <div
            class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200/70 bg-slate-50/60 px-5 py-3.5 text-xs backdrop-blur-xs"
        >
            <div class="flex items-center gap-2.5">
                <div
                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-500/15 text-amber-600"
                >
                    <Flame class="h-3.5 w-3.5" />
                </div>
                <div>
                    <span
                        class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                    >
                        Peak Day:
                    </span>
                    <span class="ml-1 font-extrabold text-slate-800">
                        {{
                            bestDay
                                ? `${bestDay.label} (${formatValue(bestDay.amount)})`
                                : 'N/A'
                        }}
                    </span>
                </div>
            </div>

            <div class="hidden h-4 w-px bg-slate-200 sm:block" />

            <div class="flex items-center gap-2.5">
                <div
                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-500/15 text-[#003B7D]"
                >
                    <Activity class="h-3.5 w-3.5" />
                </div>
                <div>
                    <span
                        class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                    >
                        Daily Avg:
                    </span>
                    <span class="ml-1 font-extrabold text-slate-800">
                        {{ formatValue(dailyAverageValue) }} / day
                    </span>
                </div>
            </div>

            <div class="hidden h-4 w-px bg-slate-200 sm:block" />

            <div class="flex items-center gap-2.5">
                <div
                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-600"
                >
                    <Award class="h-3.5 w-3.5" />
                </div>
                <div>
                    <span
                        class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                    >
                        Profit Margin:
                    </span>
                    <span class="ml-1 font-extrabold text-emerald-600">
                        {{ profitMargin }}% Net
                    </span>
                </div>
            </div>

            <div class="hidden h-4 w-px bg-slate-200 sm:block" />

            <div class="flex items-center gap-2.5">
                <div
                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-sky-500/15 text-sky-600"
                >
                    <Banknote class="h-3.5 w-3.5" />
                </div>
                <div>
                    <span
                        class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                    >
                        Collections:
                    </span>
                    <span class="ml-1 font-extrabold text-sky-700">
                        {{ collectionRate }}% Realized
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
