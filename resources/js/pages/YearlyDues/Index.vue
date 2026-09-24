<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CalendarClock,
    CheckCircle2,
    Save,
    Target,
    UserX,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useForm } from '@inertiajs/vue3';
import type { Team } from '@/types';

const page = usePage();
const currentTeamSlug = computed(
    () => (page.props.currentTeam as Team | undefined)?.slug || 'default',
);

interface Debtor {
    id: number;
    name: string;
    phone?: string | null;
    current_balance: number;
}

interface Reminder {
    id: number;
    customer: { id: number; name: string; phone?: string | null } | null;
    monthly_amount: number;
    remaining: number;
    next_due_date?: string | null;
    overdue_days: number;
}

interface MonthlyPoint {
    month: string;
    number: number;
    total: number;
}

const props = defineProps<{
    year: number;
    year_options: number[];
    dues: {
        total: number;
        count: number;
        top_debtors: Debtor[];
    };
    installments: {
        outstanding: number;
        reminders: Reminder[];
        reminders_count: number;
    };
    collections: {
        target: string;
        target_numeric: number | null;
        collected: number;
        monthly: MonthlyPoint[];
    };
}>();

const currency = (val: number | string) =>
    `Rs ${Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;

const switchYear = (year: number) => {
    router.get(
        `/${currentTeamSlug.value}/yearly-dues`,
        { year },
        { preserveState: true, replace: true },
    );
};

const progressPct = computed(() => {
    if (!props.collections.target_numeric) return 0;
    return Math.min(
        100,
        (props.collections.collected / props.collections.target_numeric) * 100,
    );
});

const maxMonthly = computed(() =>
    Math.max(1, ...props.collections.monthly.map((m) => m.total)),
);

// Target editor
const isTargetOpen = ref(false);
const targetForm = useForm({
    target: props.collections.target_numeric?.toString() ?? '',
});

const openTarget = () => {
    targetForm.clearErrors();
    targetForm.target = props.collections.target_numeric?.toString() ?? '';
    isTargetOpen.value = true;
};

const saveTarget = () => {
    targetForm.patch(`/${currentTeamSlug.value}/yearly-dues/target`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Target Updated', {
                description: 'Collection target saved.',
            });
            isTargetOpen.value = false;
        },
        onError: (errors) => {
            toast.error('Could Not Save', {
                description: Object.values(errors).flat().join(' '),
            });
        },
    });
};
</script>

<template>
    <Head title="Yearly Dues" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4 sm:p-6">
        <section
            class="glass-card flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p class="eyebrow mb-1.5">Collections & Recovery</p>
                <h1
                    class="flex items-center gap-2.5 text-2xl font-black text-slate-900"
                >
                    <CalendarClock class="text-primary h-7 w-7" /> Yearly Dues
                </h1>
                <p class="mt-1 text-xs font-medium text-slate-500">
                    Track outstanding balances, installment maturation and
                    collection targets.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <select
                    :value="year"
                    @change="
                        switchYear(
                            Number(($event.target as HTMLSelectElement).value),
                        )
                    "
                    class="focus:border-primary h-10 rounded-xl border border-slate-200 bg-white/70 px-3 text-xs font-black text-slate-700 focus:outline-none"
                >
                    <option
                        v-for="option in year_options"
                        :key="option"
                        :value="option"
                    >
                        {{ option }}
                    </option>
                </select>
                <Button
                    type="button"
                    variant="outline"
                    class="gap-2 text-xs font-bold"
                    @click="openTarget"
                >
                    <Target class="text-primary h-4 w-4" /> Collection Target
                </Button>
            </div>
        </section>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="glass-card p-4">
                <p class="eyebrow text-amber-600">Outstanding Dues</p>
                <p class="mt-1 text-2xl font-black text-slate-900">
                    {{ currency(dues.total) }}
                </p>
                <p class="mt-0.5 text-[10px] font-semibold text-slate-400">
                    {{ dues.count }} customer(s) with balances
                </p>
            </div>
            <div class="glass-card p-4">
                <p class="eyebrow text-sky-600 dark:text-sky-400">Installment Outstanding</p>
                <p class="mt-1 text-2xl font-black text-sky-600 dark:text-sky-400">
                    {{ currency(installments.outstanding) }}
                </p>
                <p class="mt-0.5 text-[10px] font-semibold text-slate-400">
                    active plans remaining
                </p>
            </div>
            <div class="glass-card p-4">
                <p class="eyebrow text-emerald-600">Collected in {{ year }}</p>
                <p class="mt-1 text-2xl font-black text-emerald-600">
                    {{ currency(collections.collected) }}
                </p>
                <p class="mt-0.5 text-[10px] font-semibold text-slate-400">
                    target:
                    {{
                        collections.target_numeric
                            ? currency(collections.target_numeric)
                            : '—'
                    }}
                </p>
            </div>
            <div class="glass-card p-4">
                <p class="eyebrow text-rose-500">Maturation Reminders</p>
                <p class="mt-1 text-2xl font-black text-rose-500">
                    {{ installments.reminders_count }}
                </p>
                <p class="mt-0.5 text-[10px] font-semibold text-slate-400">
                    due within 30 days
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
            <section class="glass-card p-5">
                <div class="mb-4 flex items-center justify-between">
                    <p class="eyebrow">Collection Progress — {{ year }}</p>
                    <p
                        v-if="collections.target_numeric"
                        class="text-primary text-xs font-black"
                    >
                        {{ progressPct.toFixed(0) }}% of target
                    </p>
                </div>

                <div v-if="collections.target_numeric" class="mb-5">
                    <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                        <div
                            class="from-primary h-full rounded-full bg-gradient-to-r to-emerald-500 transition-all duration-500"
                            :style="{ width: `${progressPct}%` }"
                        ></div>
                    </div>
                </div>

                <div
                    class="flex items-end justify-between gap-1.5"
                    style="height: 160px"
                >
                    <div
                        v-for="point in collections.monthly"
                        :key="point.number"
                        class="group flex flex-1 flex-col items-center justify-end gap-1"
                    >
                        <span
                            class="text-[9px] font-black text-slate-400 opacity-0 transition group-hover:opacity-100"
                        >
                            {{ currency(point.total) }}
                        </span>
                        <div
                            :class="
                                point.total > 0
                                    ? 'from-primary bg-gradient-to-t to-sky-400'
                                    : 'bg-slate-200'
                            "
                            class="group-hover:from-primary w-full max-w-[22px] rounded-t-lg transition-all duration-300 group-hover:to-emerald-400"
                            :style="{
                                height: `${(point.total / maxMonthly) * 100}%`,
                                minHeight: point.total > 0 ? '6px' : '3px',
                            }"
                        ></div>
                        <span
                            class="text-[9px] font-black text-slate-400 uppercase"
                            >{{ point.month }}</span
                        >
                    </div>
                </div>
            </section>

            <section class="glass-card flex flex-col p-5">
                <div class="mb-3 flex items-center justify-between">
                    <p class="eyebrow">Top Debtors</p>
                    <span class="text-[10px] font-black text-slate-400">{{
                        dues.top_debtors.length
                    }}</span>
                </div>
                <div class="flex-1 space-y-2 overflow-y-auto">
                    <div
                        v-for="debtor in dues.top_debtors"
                        :key="debtor.id"
                        class="flex items-center justify-between rounded-xl border border-slate-100 bg-white/60 px-3 py-2.5"
                    >
                        <div class="min-w-0">
                            <p
                                class="truncate text-xs font-black text-slate-800"
                            >
                                {{ debtor.name }}
                            </p>
                            <p class="text-[10px] font-semibold text-slate-400">
                                {{ debtor.phone || '—' }}
                            </p>
                        </div>
                        <p class="text-xs font-black text-amber-600">
                            {{ currency(debtor.current_balance) }}
                        </p>
                    </div>
                    <div
                        v-if="dues.top_debtors.length === 0"
                        class="py-6 text-center"
                    >
                        <UserX class="mx-auto mb-2 h-8 w-8 text-slate-200" />
                        <p class="text-xs font-bold text-slate-400">
                            No outstanding balances!
                        </p>
                    </div>
                </div>
            </section>
        </div>

        <section class="glass-card overflow-hidden rounded-2xl">
            <div
                class="flex items-center justify-between border-b border-slate-100 bg-white/60 px-5 py-3.5"
            >
                <p class="eyebrow">Maturation & Installment Reminders</p>
                <span
                    :class="
                        installments.reminders_count
                            ? 'bg-rose-100 text-rose-600 dark:bg-rose-950/60 dark:text-rose-300'
                            : 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-300'
                    "
                    class="rounded-full px-2 py-0.5 text-[10px] font-black"
                >
                    {{ installments.reminders_count }} due soon
                </span>
            </div>
            <div class="divide-y divide-slate-100">
                <div
                    v-for="reminder in installments.reminders"
                    :key="reminder.id"
                    class="flex flex-col gap-2 px-5 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-center gap-3">
                        <span
                            :class="
                                reminder.overdue_days > 0
                                    ? 'bg-rose-100 text-rose-600 dark:bg-rose-950/60 dark:text-rose-300'
                                    : 'bg-amber-100 text-amber-600 dark:bg-amber-950/60 dark:text-amber-300'
                            "
                            class="rounded-full p-1.5"
                        >
                            <AlertTriangle
                                v-if="reminder.overdue_days > 0"
                                class="h-4 w-4"
                            />
                            <CheckCircle2 v-else class="h-4 w-4" />
                        </span>
                        <div>
                            <p class="text-xs font-black text-slate-800">
                                {{
                                    reminder.customer?.name ||
                                    'Deleted customer'
                                }}
                            </p>
                            <p class="text-[10px] font-semibold text-slate-400">
                                Monthly
                                {{ currency(reminder.monthly_amount) }} •
                                Remaining {{ currency(reminder.remaining) }}
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p
                            :class="
                                reminder.overdue_days > 0
                                    ? 'text-rose-500'
                                    : 'text-amber-600'
                            "
                            class="text-xs font-black"
                        >
                            {{
                                reminder.overdue_days > 0
                                    ? `${reminder.overdue_days} day(s) overdue`
                                    : 'Due'
                            }}
                        </p>
                        <p class="text-[10px] font-semibold text-slate-400">
                            {{ reminder.next_due_date }}
                        </p>
                    </div>
                </div>
                <div
                    v-if="installments.reminders.length === 0"
                    class="px-5 py-8 text-center"
                >
                    <CheckCircle2
                        class="mx-auto mb-2 h-8 w-8 text-emerald-200"
                    />
                    <p class="text-xs font-bold text-slate-400">
                        No installments due in the next 30 days.
                    </p>
                </div>
            </div>
        </section>
    </div>

    <Dialog
        :open="isTargetOpen"
        @update:open="(value: boolean) => !value && (isTargetOpen = false)"
    >
        <DialogContent
            class="max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl"
        >
            <DialogHeader>
                <DialogTitle class="text-lg font-black text-slate-900"
                    >Collection Target — {{ year }}</DialogTitle
                >
                <DialogDescription class="text-xs text-slate-500">
                    How much do you want to collect from customers &
                    installments this year?
                </DialogDescription>
            </DialogHeader>

            <div class="py-2">
                <label
                    class="mb-1 block text-[11px] font-bold text-slate-700 uppercase"
                    >Target (PKR)</label
                >
                <input
                    v-model="targetForm.target"
                    type="number"
                    min="0"
                    step="0.01"
                    :disabled="targetForm.processing"
                    class="focus:border-primary h-11 w-full rounded-xl border border-slate-300 px-3 text-base font-black focus:outline-none"
                />
                <p
                    v-if="targetForm.errors.target"
                    class="mt-1 text-[10px] font-bold text-rose-500"
                >
                    {{ targetForm.errors.target }}
                </p>
            </div>

            <DialogFooter class="pt-3">
                <Button
                    type="button"
                    variant="outline"
                    @click="isTargetOpen = false"
                    >Cancel</Button
                >
                <Button
                    type="button"
                    :disabled="targetForm.processing"
                    class="bg-primary hover:bg-primary/90 gap-2 font-bold text-white"
                    @click="saveTarget"
                >
                    <Save class="h-4 w-4" /> Save Target
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
