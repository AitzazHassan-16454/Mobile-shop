<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { CalendarClock, CircleDollarSign, Plus, Wallet } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import installments from '@/routes/installments';
import type { Team } from '@/types';

interface Plan {
    id: number;
    customer: { name: string; phone: string };
    total_amount: number | string;
    down_payment: number | string;
    monthly_amount: number | string;
    duration_months: number;
    paid_installments: number;
    next_due_date: string;
    status: string;
}
const props = defineProps<{
    plans: { data: Plan[] };
    customers: Array<{ id: number; name: string; phone: string }>;
    summary: { active_plans: number; outstanding: number; collected: number };
}>();
const page = usePage();
const team = computed(
    () => (page.props.currentTeam as Team | undefined)?.slug || 'default',
);
const showCreate = ref(false);
const paymentPlan = ref<number | null>(null);
const planForm = useForm({
    customer_id: '',
    total_amount: '',
    down_payment: '',
    duration_months: '6',
    next_due_date: new Date().toISOString().slice(0, 10),
    notes: '',
});
const paymentForm = useForm({
    amount: '',
    payment_method: 'cash',
    reference_id: '',
    notes: '',
});
const money = (value: number | string) =>
    `Rs. ${Number(value || 0).toLocaleString('en-PK', { maximumFractionDigits: 0 })}`;
const createPlan = () =>
    planForm.post(installments.store(team.value).url, {
        onSuccess: () => {
            planForm.reset();
            showCreate.value = false;
        },
    });
const collectPayment = (planId: number) =>
    paymentForm.post(
        installments.payments.store({ current_team: team.value, plan: planId })
            .url,
        {
            onSuccess: () => {
                paymentForm.reset();
                paymentPlan.value = null;
            },
        },
    );

defineOptions({
    layout: (layoutProps: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: layoutProps.currentTeam ? '/dashboard' : '/',
            },
            {
                title: 'Installment Plans',
                href: layoutProps.currentTeam
                    ? installments.index(layoutProps.currentTeam.slug).url
                    : '/installments',
            },
        ],
    }),
});
</script>

<template>
    <Head title="Installment Plans" />
    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <section
            class="bg-card/60 flex flex-col gap-4 rounded-2xl border border-gray-200 p-5 shadow-[0_1px_2px_rgba(2,43,90,0.06)] backdrop-blur-xl sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p class="eyebrow mb-2">Customer financing</p>
                <h1
                    class="flex items-center gap-2 text-2xl font-bold text-gray-900"
                >
                    <CalendarClock class="h-7 w-7 text-violet-600" />
                    Installment Plans
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Track mobile kist schedules, due dates and collections.
                </p>
            </div>
            <Button
                class="gap-2 bg-[#003b7d] font-bold text-white shadow-sm hover:bg-[#0f4c81]"
                @click="showCreate = !showCreate"
                ><Plus class="h-4 w-4" /> New Plan</Button
            >
        </section>
        <section
            v-if="showCreate"
            class="rounded-2xl border border-violet-200 bg-violet-500/[0.04] p-5 backdrop-blur-xl"
        >
            <form
                class="grid gap-4 md:grid-cols-2 lg:grid-cols-5"
                @submit.prevent="createPlan"
            >
                <div class="grid gap-2 lg:col-span-2">
                    <Label for="plan-customer">Customer</Label
                    ><select
                        id="plan-customer"
                        v-model="planForm.customer_id"
                        required
                        class="h-10 rounded-md border border-gray-200 bg-gray-50 px-3 text-sm text-gray-900"
                    >
                        <option value="" disabled>Select customer</option>
                        <option
                            v-for="customer in customers"
                            :key="customer.id"
                            :value="customer.id"
                        >
                            {{ customer.name }} - {{ customer.phone }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="plan-total">Total price</Label
                    ><Input
                        id="plan-total"
                        v-model="planForm.total_amount"
                        required
                        type="number"
                        min="1"
                        class="border-gray-200 bg-gray-50 text-gray-900"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="plan-down">Down payment</Label
                    ><Input
                        id="plan-down"
                        v-model="planForm.down_payment"
                        required
                        type="number"
                        min="0"
                        class="border-gray-200 bg-gray-50 text-gray-900"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="plan-duration">Months</Label
                    ><Input
                        id="plan-duration"
                        v-model="planForm.duration_months"
                        required
                        type="number"
                        min="1"
                        max="60"
                        class="border-gray-200 bg-gray-50 text-gray-900"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="plan-due">First due date</Label
                    ><Input
                        id="plan-due"
                        v-model="planForm.next_due_date"
                        required
                        type="date"
                        class="border-gray-200 bg-gray-50 text-gray-900"
                    />
                </div>
                <div class="flex items-end">
                    <Button
                        class="w-full bg-[#003b7d] font-semibold text-white shadow-sm hover:bg-[#0f4c81]"
                        :disabled="planForm.processing"
                        >Create plan</Button
                    >
                </div>
            </form>
        </section>
        <section class="grid gap-4 sm:grid-cols-3">
            <div
                class="bg-card/60 rounded-2xl border border-gray-200 p-4 backdrop-blur-xl"
            >
                <p class="text-xs tracking-wider text-slate-500 uppercase">
                    Active plans
                </p>
                <p class="tnum mt-2 text-2xl font-bold text-gray-900">
                    {{ summary.active_plans }}
                </p>
            </div>
            <div
                class="bg-card/60 rounded-2xl border border-gray-200 p-4 backdrop-blur-xl"
            >
                <p class="text-xs tracking-wider text-slate-500 uppercase">
                    Outstanding
                </p>
                <p class="tnum mt-2 text-2xl font-bold text-amber-600">
                    {{ money(summary.outstanding) }}
                </p>
            </div>
            <div
                class="bg-card/60 rounded-2xl border border-gray-200 p-4 backdrop-blur-xl"
            >
                <p class="text-xs tracking-wider text-slate-500 uppercase">
                    Collected
                </p>
                <p class="tnum mt-2 text-2xl font-bold text-violet-600">
                    {{ money(summary.collected) }}
                </p>
            </div>
        </section>
        <section
            class="bg-card/60 overflow-hidden rounded-2xl border border-gray-200 backdrop-blur-xl"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-gray-200 text-xs tracking-wider text-slate-500 uppercase"
                    >
                        <tr>
                            <th class="px-5 py-4">Customer</th>
                            <th class="px-5 py-4">Plan</th>
                            <th class="px-5 py-4">Next due</th>
                            <th class="px-5 py-4">Status</th>
                            <th class="px-5 py-4 text-right">Collection</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr
                            v-for="plan in plans.data"
                            :key="plan.id"
                            class="text-slate-600 hover:bg-gray-50"
                        >
                            <td class="px-5 py-4">
                                <div class="font-semibold text-gray-900">
                                    {{ plan.customer.name }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ plan.customer.phone }}
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="tnum text-gray-900">
                                    {{ money(plan.monthly_amount) }} / month
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ plan.paid_installments }} of
                                    {{ plan.duration_months }} paid
                                </div>
                            </td>
                            <td class="tnum px-5 py-4 text-slate-500">
                                {{ plan.next_due_date }}
                            </td>
                            <td class="px-5 py-4">
                                <span
                                    :class="
                                        plan.status === 'active'
                                            ? 'text-amber-600'
                                            : 'text-violet-600'
                                    "
                                    >{{ plan.status }}</span
                                >
                            </td>
                            <td class="px-5 py-4 text-right">
                                <Button
                                    size="sm"
                                    class="border-violet-200 bg-violet-50 text-violet-600 hover:bg-violet-100"
                                    :disabled="plan.status !== 'active'"
                                    @click="paymentPlan = plan.id"
                                    ><Wallet class="mr-1 h-4 w-4" />
                                    Collect</Button
                                >
                                <form
                                    v-if="paymentPlan === plan.id"
                                    class="mt-3 flex justify-end gap-2"
                                    @submit.prevent="collectPayment(plan.id)"
                                >
                                    <Input
                                        v-model="paymentForm.amount"
                                        required
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        class="w-28 border-gray-200 bg-gray-50 text-gray-900"
                                        placeholder="Amount"
                                    /><Button
                                        size="sm"
                                        class="bg-[#003b7d] text-white hover:bg-[#0f4c81]"
                                        :disabled="paymentForm.processing"
                                        ><CircleDollarSign
                                            class="mr-1 h-4 w-4"
                                        />
                                        Save</Button
                                    >
                                </form>
                            </td>
                        </tr>
                        <tr v-if="plans.data.length === 0">
                            <td
                                colspan="5"
                                class="px-5 py-12 text-center text-slate-500"
                            >
                                No installment plans found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
