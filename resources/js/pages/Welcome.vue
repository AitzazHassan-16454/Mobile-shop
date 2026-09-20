<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { dashboard, login } from '@/routes';

const page = usePage();
const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);
</script>

<template>
    <Head title="Faizan Mobile" />

    <div
        class="relative min-h-screen overflow-hidden bg-gray-100 px-4 py-8 sm:px-6 lg:px-8"
    >
        <div class="bg-grid-brand pointer-events-none absolute inset-0"></div>
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(900px_500px_at_12%_-8%,rgba(0,59,125,0.08),transparent_60%),radial-gradient(820px_620px_at_50%_118%,rgba(0,59,125,0.05),transparent_62%)]"
        ></div>

        <div class="relative mx-auto max-w-6xl">
            <header class="mb-10 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#003b7d] text-white shadow-sm"
                    >
                        <AppLogoIcon class="h-6 w-6 fill-current" />
                    </div>
                    <div>
                        <div
                            class="text-lg font-black tracking-tight text-gray-900"
                        >
                            Faizan Mobile
                        </div>
                        <div class="eyebrow">Mobile shop POS</div>
                    </div>
                </div>

                <nav class="flex items-center gap-3">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboardUrl"
                        class="inline-flex items-center rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-bold text-gray-700 transition hover:border-[#003b7d]/40 hover:text-[#003b7d]"
                    >
                        Dashboard
                    </Link>
                    <Link
                        v-else
                        :href="login()"
                        class="inline-flex items-center rounded-xl bg-[#003b7d] px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-[#0f4c81]"
                    >
                        Log in
                    </Link>
                </nav>
            </header>

            <main
                class="overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-xl shadow-[#003b7d]/5"
            >
                <div
                    class="grid items-center gap-6 p-6 md:p-10 lg:grid-cols-[1.1fr_0.9fr]"
                >
                    <div>
                        <div
                            class="mb-5 inline-flex items-center gap-2 rounded-full border border-[#003b7d]/20 bg-[#003b7d]/5 px-3 py-1 text-[10px] font-black tracking-[0.18em] text-[#003b7d] uppercase"
                        >
                            Smart retail operations
                        </div>
                        <h1
                            class="max-w-xl text-4xl font-black tracking-tight text-gray-900 md:text-5xl"
                        >
                            Your mobile shop, powered by a streamlined POS
                            system.
                        </h1>
                        <p
                            class="mt-5 max-w-lg text-base leading-7 text-slate-500"
                        >
                            Manage sales, customer khata, repairs, inventory,
                            and cash drawer performance from one clean command
                            center built for growth.
                        </p>

                        <div class="mt-8 flex flex-wrap gap-3">
                            <Link
                                v-if="$page.props.auth.user"
                                :href="dashboardUrl"
                                class="inline-flex items-center rounded-xl bg-[#003b7d] px-5 py-3 text-sm font-black text-white shadow-sm transition hover:bg-[#0f4c81]"
                            >
                                Open dashboard
                            </Link>
                            <Link
                                v-else
                                :href="login()"
                                class="inline-flex items-center rounded-xl bg-[#003b7d] px-5 py-3 text-sm font-black text-white shadow-sm transition hover:bg-[#0f4c81]"
                            >
                                Get started
                            </Link>
                            <div
                                class="inline-flex items-center rounded-xl border border-gray-200 bg-gray-50 px-5 py-3 text-sm font-bold text-gray-600"
                            >
                                24/7 live monitoring
                            </div>
                        </div>

                        <div class="mt-10 grid gap-4 sm:grid-cols-3">
                            <div
                                class="rounded-xl border border-gray-200 bg-gray-50 p-4 shadow-sm"
                            >
                                <div
                                    class="tnum text-2xl font-black text-[#003b7d]"
                                >
                                    2.4x
                                </div>
                                <div
                                    class="mt-1 text-xs tracking-[0.16em] text-slate-500 uppercase"
                                >
                                    Faster sales
                                </div>
                            </div>
                            <div
                                class="rounded-xl border border-gray-200 bg-gray-50 p-4 shadow-sm"
                            >
                                <div
                                    class="tnum text-2xl font-black text-[#003b7d]"
                                >
                                    99.2%
                                </div>
                                <div
                                    class="mt-1 text-xs tracking-[0.16em] text-slate-500 uppercase"
                                >
                                    Accuracy
                                </div>
                            </div>
                            <div
                                class="rounded-xl border border-gray-200 bg-gray-50 p-4 shadow-sm"
                            >
                                <div
                                    class="tnum text-2xl font-black text-[#003b7d]"
                                >
                                    24/7
                                </div>
                                <div
                                    class="mt-1 text-xs tracking-[0.16em] text-slate-500 uppercase"
                                >
                                    Operations
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="relative">
                        <div
                            class="rounded-3xl border border-gray-200 bg-gradient-to-br from-white to-gray-100 p-5 shadow-lg shadow-[#003b7d]/5"
                        >
                            <div class="rounded-2xl bg-white p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div
                                            class="text-[10px] font-bold tracking-[0.18em] text-slate-500 uppercase"
                                        >
                                            Today
                                        </div>
                                        <div
                                            class="tnum mt-1 text-2xl font-black text-gray-900"
                                        >
                                            Rs 184,250
                                        </div>
                                    </div>
                                    <div
                                        class="rounded-xl bg-emerald-100 px-2 py-1 text-xs font-bold text-emerald-600"
                                    >
                                        +12.8%
                                    </div>
                                </div>

                                <div class="mt-6 space-y-3">
                                    <div
                                        class="flex items-center justify-between rounded-xl bg-gray-50 p-3 text-xs text-gray-600"
                                    >
                                        <span>Cash sales</span>
                                        <span
                                            class="tnum font-black text-gray-900"
                                            >Rs 72,400</span
                                        >
                                    </div>
                                    <div
                                        class="flex items-center justify-between rounded-xl bg-gray-50 p-3 text-xs text-gray-600"
                                    >
                                        <span>Used phone buys</span>
                                        <span
                                            class="tnum font-black text-gray-900"
                                            >Rs 41,820</span
                                        >
                                    </div>
                                    <div
                                        class="flex items-center justify-between rounded-xl bg-gray-50 p-3 text-xs text-gray-600"
                                    >
                                        <span>Repair jobs</span>
                                        <span class="font-black text-gray-900"
                                            >14 pending</span
                                        >
                                    </div>
                                </div>

                                <div
                                    class="mt-6 flex items-end gap-2 rounded-xl bg-gray-50 p-3"
                                >
                                    <div
                                        class="h-10 w-8 rounded-t-xl bg-[#003b7d]/40"
                                    ></div>
                                    <div
                                        class="h-16 w-8 rounded-t-xl bg-sky-400/50"
                                    ></div>
                                    <div
                                        class="h-12 w-8 rounded-t-xl bg-[#5b8def]/60"
                                    ></div>
                                    <div
                                        class="h-20 w-8 rounded-t-xl bg-sky-300/60"
                                    ></div>
                                    <div
                                        class="h-14 w-8 rounded-t-xl bg-[#003b7d]/50"
                                    ></div>
                                    <div
                                        class="h-24 w-8 rounded-t-xl bg-[#00458f]/70"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>
