<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { dashboard } from '@/routes';
import { cn } from '@/lib/utils';

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

const from = ref(props.start ?? '');
const to = ref(props.end ?? '');
const showCustom = ref(props.preset === 'custom');
const isCustom = computed(() => showCustom.value);

watch(
    () => [props.preset, props.start, props.end],
    ([preset, start, end]) => {
        showCustom.value = preset === 'custom';
        from.value = start ?? '';
        to.value = end ?? '';
    },
);

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

function applyCustom() {
    const params: Record<string, string> = {};
    if (from.value) params.from = from.value;
    if (to.value) params.to = to.value;
    visit('custom', params);
}

const rangeLabel = computed(() => {
    if (!props.start || !props.end) return null;
    const format = (date: string) =>
        new Intl.DateTimeFormat('en-GB', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        }).format(new Date(`${date}T00:00:00`));
    return `${format(props.start)} – ${format(props.end)}`;
});

const presetLabel = computed(
    () =>
        presets.find((p) => p.value === props.preset)?.label ?? 'Custom Period',
);
</script>

<template>
    <div class="flex flex-col gap-2.5">
        <div class="flex flex-wrap items-center gap-1.5">
            <span
                class="mr-1 hidden text-[11px] font-bold tracking-[0.14em] text-slate-500 uppercase sm:inline"
                >Range</span
            >
            <button
                v-for="preset in presets"
                :key="preset.value"
                type="button"
                @click="select(preset.value)"
                :class="
                    cn(
                        'rounded-lg border px-3 py-1.5 text-xs font-bold transition',
                        props.preset === preset.value
                            ? 'border-[#003b7d] bg-[#003b7d] text-white shadow-sm'
                            : 'border-gray-200 bg-white text-slate-500 hover:border-[#003b7d]/30 hover:text-[#003b7d]',
                    )
                "
            >
                {{ preset.label }}
            </button>
            <button
                type="button"
                @click="showCustom = true"
                :class="
                    cn(
                        'rounded-lg border px-3 py-1.5 text-xs font-bold transition',
                        isCustom
                            ? 'border-[#003b7d] bg-[#003b7d] text-white shadow-sm'
                            : 'border-gray-200 bg-white text-slate-500 hover:border-[#003b7d]/30 hover:text-[#003b7d]',
                    )
                "
            >
                Custom…
            </button>
        </div>

        <div v-if="isCustom" class="flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-1.5 text-xs text-slate-500">
                <input
                    type="date"
                    v-model="from"
                    class="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 outline-none focus:border-[#003b7d]/50"
                />
                <span>to</span>
                <input
                    type="date"
                    v-model="to"
                    class="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 outline-none focus:border-[#003b7d]/50"
                />
            </div>
            <button
                type="button"
                @click="applyCustom"
                class="rounded-lg bg-[#003b7d] px-3 py-1.5 text-xs font-bold text-white transition hover:bg-[#0f4c81]"
            >
                Apply
            </button>
            <span class="text-[11px] text-slate-500"
                >Pick a start and end date.</span
            >
        </div>

        <div
            v-if="rangeLabel"
            class="flex items-center gap-1.5 text-[11px] font-bold tracking-wide text-slate-500"
        >
            <span class="h-1.5 w-1.5 rounded-full bg-[#003b7d]" />
            {{ presetLabel }} · {{ rangeLabel }}
        </div>
    </div>
</template>
