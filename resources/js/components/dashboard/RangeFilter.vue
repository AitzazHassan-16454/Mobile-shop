<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ChevronDown, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { dashboard } from '@/routes';

const props = defineProps<{
    teamSlug: string;
    preset: string;
    start?: string | null;
    end?: string | null;
}>();

const presets = [
    { value: 'today', label: 'Today' },
    { value: 'yesterday', label: 'Yesterday' },
    { value: 'this_week', label: 'This Week' },
    { value: 'last_week', label: 'Last Week' },
    { value: 'this_month', label: 'This Month' },
    { value: 'last_month', label: 'Last Month' },
    { value: 'this_year', label: 'This Year' },
    { value: 'last_year', label: 'Last Year' },
    { value: 'all_time', label: 'All Time' },
    { value: 'custom', label: 'Custom' },
];

const selectedPreset = ref(props.preset ?? 'this_month');
const from = ref(props.start ?? '');
const to = ref(props.end ?? '');
const showCustomBox = ref(props.preset === 'custom');
const showDatePickerModal = ref(false);

watch(
    () => props.preset,
    (newPreset) => {
        selectedPreset.value = newPreset;
        showCustomBox.value = newPreset === 'custom';
    },
);

watch(
    () => [props.start, props.end],
    ([newStart, newEnd]) => {
        from.value = newStart ?? '';
        to.value = newEnd ?? '';
    },
);

function toISODate(d: Date): string {
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function formatDDMMYYYY(isoDateStr: string): string {
    if (!isoDateStr) return '';
    const parts = isoDateStr.split('-');
    if (parts.length === 3) {
        return `${parts[2]}-${parts[1]}-${parts[0]}`;
    }
    return isoDateStr;
}

function navigate(rangeVal: string, extra: Record<string, string> = {}) {
    router.get(
        dashboard.url(props.teamSlug),
        { range: rangeVal, ...extra },
        {
            preserveState: false,
            preserveScroll: true,
            replace: true,
        },
    );
}

function onPresetChange(e: Event) {
    const val = (e.target as HTMLSelectElement).value;
    selectedPreset.value = val;

    if (val === 'custom') {
        showCustomBox.value = true;
        showDatePickerModal.value = true;
        if (!from.value || !to.value) {
            const now = new Date();
            to.value = toISODate(now);
            const past = new Date();
            past.setDate(now.getDate() - 6);
            from.value = toISODate(past);
        }
    } else {
        showCustomBox.value = false;
        showDatePickerModal.value = false;
        navigate(val);
    }
}

function applyCustomDates() {
    if (!from.value || !to.value) return;
    showDatePickerModal.value = false;
    navigate('custom', { from: from.value, to: to.value });
}

function clearCustom() {
    showCustomBox.value = false;
    showDatePickerModal.value = false;
    selectedPreset.value = 'this_month';
    navigate('this_month');
}

const customDisplayString = computed(() => {
    if (!from.value || !to.value) return 'Select Date Range';
    return `${formatDDMMYYYY(from.value)} to ${formatDDMMYYYY(to.value)}`;
});
</script>

<template>
    <div class="relative flex items-center gap-2">
        <!-- Custom Date Range Display Box (Image 3: 20-09-2026 to 26-09-2026 ✕) -->
        <div
            v-if="showCustomBox"
            class="flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 shadow-2xs transition hover:border-[#003B7D]"
        >
            <span
                @click="showDatePickerModal = !showDatePickerModal"
                class="cursor-pointer font-mono tracking-tight hover:text-[#003B7D]"
            >
                {{ customDisplayString }}
            </span>
            <button
                type="button"
                @click="clearCustom"
                class="text-slate-400 hover:text-rose-600 focus:outline-none"
                title="Clear custom range"
            >
                <X class="h-3.5 w-3.5" />
            </button>
        </div>

        <!-- Single Dropdown Select Box (Matching Image 2 & Screenshot) -->
        <div class="relative">
            <select
                :value="selectedPreset"
                @change="onPresetChange"
                class="min-w-[110px] cursor-pointer appearance-none rounded-lg border border-slate-300 bg-white px-3 py-1.5 pr-8 text-xs font-bold text-slate-800 shadow-2xs hover:border-[#003B7D] focus:border-[#003B7D] focus:ring-1 focus:ring-[#003B7D] focus:outline-none"
            >
                <option
                    v-for="opt in presets"
                    :key="opt.value"
                    :value="opt.value"
                >
                    {{ opt.label }}
                </option>
            </select>
            <ChevronDown
                class="pointer-events-none absolute top-1/2 right-2.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-500"
            />
        </div>

        <!-- Custom Date Range Picker Dropdown Modal -->
        <div
            v-if="showCustomBox && showDatePickerModal"
            class="absolute top-full right-0 z-40 mt-2 w-72 rounded-xl border border-slate-200 bg-white p-3.5 shadow-2xl"
        >
            <div
                class="mb-2.5 flex items-center justify-between border-b border-slate-100 pb-2"
            >
                <span class="text-xs font-bold text-slate-800"
                    >Select Custom Dates</span
                >
                <button
                    @click="showDatePickerModal = false"
                    class="text-slate-400 hover:text-slate-600"
                >
                    <X class="h-3.5 w-3.5" />
                </button>
            </div>
            <div class="space-y-3">
                <div>
                    <label
                        class="mb-1 block text-[11px] font-bold text-slate-600"
                        >From Date:</label
                    >
                    <input
                        type="date"
                        v-model="from"
                        class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs text-slate-800 outline-none focus:border-[#003B7D]"
                    />
                </div>
                <div>
                    <label
                        class="mb-1 block text-[11px] font-bold text-slate-600"
                        >To Date:</label
                    >
                    <input
                        type="date"
                        v-model="to"
                        class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs text-slate-800 outline-none focus:border-[#003B7D]"
                    />
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <button
                        type="button"
                        @click="applyCustomDates"
                        class="rounded-lg bg-[#003B7D] px-3.5 py-1.5 text-xs font-bold text-white shadow-xs hover:bg-[#002752]"
                    >
                        Apply Filter
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
