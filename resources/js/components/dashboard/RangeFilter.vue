<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CalendarDays, CalendarRange, Check, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

const props = defineProps<{
    teamSlug: string;
    preset: string;
    start?: string | null;
    end?: string | null;
}>();

const presets: { value: string; label: string }[] = [
    { value: 'today', label: 'Today' },
    { value: 'yesterday', label: 'Yesterday' },
    { value: 'this_week', label: 'This Week' },
    { value: 'last_week', label: 'Last Week' },
    { value: 'this_month', label: 'This Month' },
    { value: 'last_month', label: 'Last Month' },
    { value: 'this_year', label: 'This Year' },
    { value: 'all_time', label: 'All Time' },
];

const customShortcuts = [
    { label: 'Last 7 Days', days: 7 },
    { label: 'Last 14 Days', days: 14 },
    { label: 'Last 30 Days', days: 30 },
    { label: 'Last 90 Days', days: 90 },
];

const from = ref(props.start ?? '');
const to = ref(props.end ?? '');
const showCustom = ref(props.preset === 'custom');
const isCustomActive = computed(() => props.preset === 'custom');

watch(
    () => [props.preset, props.start, props.end],
    ([preset, start, end]) => {
        showCustom.value = preset === 'custom';
        from.value = start ?? '';
        to.value = end ?? '';
    },
);

function toISODate(d: Date): string {
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function visit(preset: string, extra: Record<string, string> = {}) {
    router.get(
        dashboard.url(props.teamSlug),
        { range: preset, ...extra },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function select(preset: string) {
    if (preset === 'custom') return;
    showCustom.value = false;
    visit(preset);
}

function toggleCustom() {
    showCustom.value = !showCustom.value;
    if (showCustom.value && (!from.value || !to.value)) {
        const now = new Date();
        to.value = toISODate(now);
        const past = new Date();
        past.setDate(now.getDate() - 29);
        from.value = toISODate(past);
    }
}

function cancelCustom() {
    showCustom.value = false;
}

function applyCustom() {
    const params: Record<string, string> = {};
    if (from.value) params.from = from.value;
    if (to.value) params.to = to.value;
    visit('custom', params);
}

function applyShortcut(days: number) {
    const now = new Date();
    const past = new Date();
    past.setDate(now.getDate() - (days - 1));
    from.value = toISODate(past);
    to.value = toISODate(now);
    applyCustom();
}

const rangeLabel = computed(() => {
    if (!props.start || !props.end) {
        if (props.preset === 'all_time') return 'All Time Records';
        return null;
    }
    const format = (date: string) =>
        new Intl.DateTimeFormat('en-GB', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        }).format(new Date(`${date}T00:00:00`));

    if (props.start === props.end) {
        return format(props.start);
    }
    return `${format(props.start)} – ${format(props.end)}`;
});

const presetLabel = computed(
    () =>
        presets.find((p) => p.value === props.preset)?.label ?? 'Custom Range',
);
</script>

<template>
    <section class="glass-card rounded-3xl p-4 sm:p-5">
        <!-- Header row -->
        <div
            class="flex flex-col gap-3 border-b border-black/[0.05] pb-3.5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-[#003B7D]/18 to-[#003B7D]/5 text-[#003B7D] shadow-[inset_0_1px_1.5px_rgba(255,255,255,0.7),0_4px_12px_rgba(0,0,0,0.04)] ring-1 ring-[#003B7D]/20 ring-inset"
                >
                    <CalendarDays class="h-5 w-5" />
                </div>
                <div>
                    <h2
                        class="text-lg font-black tracking-tight text-slate-900"
                    >
                        Reporting Period
                    </h2>
                    <p class="text-xs text-slate-500">
                        Choose the window for the earnings and graph below
                    </p>
                </div>
            </div>

            <!-- Active range badge -->
            <div class="flex items-center gap-2 self-start sm:self-auto">
                <div
                    class="glass-pill inline-flex items-center gap-2 rounded-xl px-3.5 py-1.5 text-xs font-semibold text-[#003B7D]"
                >
                    <span
                        class="flex h-2 w-2 animate-pulse rounded-full bg-[#003B7D]"
                    />
                    <span class="font-black text-[#003B7D]">{{
                        presetLabel
                    }}</span>
                    <span v-if="rangeLabel" class="text-slate-300">|</span>
                    <span
                        v-if="rangeLabel"
                        class="tnum font-medium text-slate-700"
                    >
                        {{ rangeLabel }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Presets bar -->
        <div class="pt-3.5">
            <div
                class="glass-segmented-track flex flex-wrap items-center gap-1.5 rounded-2xl p-1.5"
            >
                <button
                    v-for="presetItem in presets"
                    :key="presetItem.value"
                    type="button"
                    @click="select(presetItem.value)"
                    :class="
                        cn(
                            'rounded-xl px-3.5 py-1.5 text-xs font-bold transition-all duration-150',
                            props.preset === presetItem.value && !isCustomActive
                                ? 'bg-[#003B7D] text-white shadow-[0_4px_14px_rgba(0,59,125,0.35),inset_0_1px_1px_rgba(255,255,255,0.35)]'
                                : 'text-slate-600 hover:bg-white/80 hover:text-[#003B7D] hover:shadow-xs',
                        )
                    "
                >
                    {{ presetItem.label }}
                </button>

                <button
                    type="button"
                    @click="toggleCustom"
                    :class="
                        cn(
                            'inline-flex items-center gap-1.5 rounded-xl px-3.5 py-1.5 text-xs font-bold transition-all duration-150',
                            isCustomActive || showCustom
                                ? 'bg-[#003B7D] text-white shadow-[0_4px_14px_rgba(0,59,125,0.35),inset_0_1px_1px_rgba(255,255,255,0.35)]'
                                : 'text-slate-600 hover:bg-white/80 hover:text-[#003B7D] hover:shadow-xs',
                        )
                    "
                >
                    <CalendarRange class="h-3.5 w-3.5" />
                    <span>Custom…</span>
                </button>
            </div>

            <!-- Custom date range drawer -->
            <div
                v-if="showCustom"
                class="mt-3.5 rounded-2xl border border-white/85 bg-white/70 p-4 shadow-[inset_0_1px_1.5px_rgba(255,255,255,1),0_10px_30px_rgba(0,25,70,0.06)] backdrop-blur-xl"
            >
                <div
                    class="flex flex-col gap-3 border-b border-black/[0.05] pb-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-center gap-2">
                        <CalendarRange class="h-4 w-4 text-[#003B7D]" />
                        <span class="text-xs font-bold text-slate-800"
                            >Custom Date Range</span
                        >
                    </div>

                    <!-- Quick shortcut pills -->
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="text-[11px] font-semibold text-slate-400"
                            >Quick pick:</span
                        >
                        <button
                            v-for="shortcut in customShortcuts"
                            :key="shortcut.label"
                            type="button"
                            @click="applyShortcut(shortcut.days)"
                            class="rounded-lg border border-white/80 bg-white/80 px-2.5 py-1 text-[11px] font-bold text-slate-600 shadow-xs transition hover:border-[#003B7D]/40 hover:bg-white hover:text-[#003B7D]"
                        >
                            {{ shortcut.label }}
                        </button>
                    </div>
                </div>

                <!-- Date Inputs and Apply button -->
                <div class="flex flex-wrap items-center gap-3 pt-3">
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-bold text-slate-600"
                            >From:</label
                        >
                        <input
                            type="date"
                            v-model="from"
                            class="rounded-xl border border-white/90 bg-white/85 px-3 py-1.5 text-xs font-semibold text-slate-800 shadow-[inset_0_1px_2px_rgba(0,0,0,0.04)] backdrop-blur-md transition outline-none focus:border-[#003B7D] focus:ring-2 focus:ring-[#003B7D]/15"
                        />
                    </div>
                    <span class="text-xs font-bold text-slate-400">to</span>
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-bold text-slate-600"
                            >To:</label
                        >
                        <input
                            type="date"
                            v-model="to"
                            class="rounded-xl border border-white/90 bg-white/85 px-3 py-1.5 text-xs font-semibold text-slate-800 shadow-[inset_0_1px_2px_rgba(0,0,0,0.04)] backdrop-blur-md transition outline-none focus:border-[#003B7D] focus:ring-2 focus:ring-[#003B7D]/15"
                        />
                    </div>

                    <div class="flex items-center gap-2 sm:ml-auto">
                        <button
                            type="button"
                            @click="applyCustom"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-[#003B7D] px-4 py-2 text-xs font-bold text-white shadow-[0_4px_16px_rgba(0,59,125,0.3),inset_0_1px_1px_rgba(255,255,255,0.3)] transition hover:bg-[#002b5c]"
                        >
                            <Check class="h-3.5 w-3.5" />
                            Apply Filter
                        </button>
                        <button
                            type="button"
                            @click="cancelCustom"
                            class="inline-flex items-center gap-1 rounded-xl border border-white/80 bg-white/80 px-3 py-2 text-xs font-semibold text-slate-600 shadow-xs transition hover:bg-white hover:text-slate-900"
                        >
                            <X class="h-3.5 w-3.5" />
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
