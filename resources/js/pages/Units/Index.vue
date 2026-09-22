<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Edit2,
    Hash,
    Plus,
    Ruler,
    Search,
    SlidersHorizontal,
    Trash2,
    X,
    XCircle,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useConfirm } from '@/composables/useConfirm';
import type { Team } from '@/types';

const { confirm } = useConfirm();
const page = usePage();

// Interactive Table Column Customizer State
const defaultVisibleColumns = {
    name: true,
    short_name: true,
    allow_decimal: true,
    is_active: true,
    description: true,
    actions: true,
};

const visibleColumns = ref({ ...defaultVisibleColumns });

const unitColumnLabels: Record<keyof typeof defaultVisibleColumns, string> = {
    name: 'Unit Name',
    short_name: 'Short Code',
    allow_decimal: 'Allow Decimal',
    is_active: 'Status',
    description: 'Description',
    actions: 'Actions',
};

const STORAGE_KEY = 'faizan_mobile_units_table_columns_v1';
const PER_PAGE_STORAGE_KEY = 'faizan_mobile_units_per_page_v1';

onMounted(() => {
    try {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved) {
            visibleColumns.value = { ...defaultVisibleColumns, ...JSON.parse(saved) };
        }
        const savedPerPage = localStorage.getItem(PER_PAGE_STORAGE_KEY);
        if (savedPerPage && Number(savedPerPage) !== perPage.value) {
            perPage.value = Number(savedPerPage);
        }
    } catch (e) {
        console.error(e);
    }
});

const toggleUnitColumn = (key: string) => {
    const k = key as keyof typeof defaultVisibleColumns;
    visibleColumns.value[k] = !visibleColumns.value[k];
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(visibleColumns.value));
    } catch (e) {
        console.error(e);
    }
};

const resetColumns = () => {
    visibleColumns.value = { ...defaultVisibleColumns };
    try {
        localStorage.removeItem(STORAGE_KEY);
    } catch (e) {
        console.error(e);
    }
};

const activeColumnCount = computed(() => {
    return Object.values(visibleColumns.value).filter(Boolean).length;
});

interface UnitItem {
    id: number;
    name: string;
    short_name: string;
    allow_decimal: boolean;
    is_active: boolean;
    description?: string | null;
    created_at?: string;
    updated_at?: string;
}

interface PaginatedUnits {
    data: UnitItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
    units: PaginatedUnits;
    filters: {
        search?: string;
        status_filter?: string;
        per_page?: number;
    };
    stats: {
        total_units: number;
        active_units: number;
        decimal_units: number;
    };
}

const props = defineProps<Props>();

defineOptions({
    layout: (layoutProps: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: layoutProps.currentTeam ? '/dashboard' : '/',
            },
            {
                title: 'Units',
                href: layoutProps.currentTeam
                    ? `/${layoutProps.currentTeam.slug}/units`
                    : '/units',
            },
        ],
    }),
});

const currentTeamSlug = computed(() => (page.props.currentTeam as Team)?.slug ?? '');

function getTeamUrl(path: string) {
    return currentTeamSlug.value ? `/${currentTeamSlug.value}${path}` : path;
}

// Search & Filter state
const searchQuery = ref(props.filters.search ?? '');
const activeStatusFilter = ref(props.filters.status_filter ?? 'all');
const perPage = ref(props.filters.per_page ?? 15);

watch([searchQuery, activeStatusFilter, perPage], ([newSearch, newFilter, newPerPage]) => {
    try {
        localStorage.setItem(PER_PAGE_STORAGE_KEY, String(newPerPage));
    } catch (e) {
        console.error(e);
    }
    router.get(
        getTeamUrl('/units'),
        {
            search: newSearch,
            status_filter: newFilter,
            per_page: newPerPage,
        },
        {
            preserveState: true,
            replace: true,
        }
    );
});

// Create Modal state
const isCreateDialogOpen = ref(false);
const createForm = useForm({
    name: '',
    short_name: '',
    allow_decimal: false,
    is_active: true,
    description: '',
});

function openCreateModal() {
    createForm.reset();
    createForm.clearErrors();
    isCreateDialogOpen.value = true;
}

function handleCreateUnit() {
    createForm.post(getTeamUrl('/units'), {
        onSuccess: () => {
            isCreateDialogOpen.value = false;
            createForm.reset();
        },
    });
}

// Edit Modal state
const isEditDialogOpen = ref(false);
const editingUnitId = ref<number | null>(null);
const editForm = useForm({
    name: '',
    short_name: '',
    allow_decimal: false,
    is_active: true,
    description: '',
});

function openEditModal(unit: UnitItem) {
    editingUnitId.value = unit.id;
    editForm.name = unit.name;
    editForm.short_name = unit.short_name;
    editForm.allow_decimal = unit.allow_decimal;
    editForm.is_active = unit.is_active;
    editForm.description = unit.description ?? '';
    editForm.clearErrors();
    isEditDialogOpen.value = true;
}

function handleUpdateUnit() {
    if (!editingUnitId.value) return;

    editForm.put(getTeamUrl(`/units/${editingUnitId.value}`), {
        onSuccess: () => {
            isEditDialogOpen.value = false;
            editingUnitId.value = null;
            editForm.reset();
        },
    });
}

// Delete Unit
async function handleDeleteUnit(unit: UnitItem) {
    const isConfirmed = await confirm({
        title: 'Delete Unit',
        message: `Are you sure you want to delete unit "${unit.name} (${unit.short_name})"? This action cannot be undone.`,
        confirmText: 'Delete Unit',
        cancelText: 'Cancel',
        variant: 'destructive',
    });

    if (isConfirmed) {
        router.delete(getTeamUrl(`/units/${unit.id}`));
    }
}
</script>

<template>
    <div class="space-y-6">
        <Head title="Measurement Units" />

        <!-- Header Banner & Action Button -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                    Measurement Units
                </h1>
                <p class="text-sm text-muted-foreground">
                    Manage stock measurement units for products, inventory tracking, and sales billing.
                </p>
            </div>
            <Button
                @click="openCreateModal"
                class="inline-flex items-center gap-2 bg-primary text-primary-foreground hover:bg-primary/90"
            >
                <Plus class="h-4 w-4" />
                Add Unit
            </Button>
        </div>

        <!-- Summary Stat Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <!-- Total Units -->
            <div class="relative overflow-hidden rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                            Total Units
                        </p>
                        <h3 class="mt-1 text-2xl font-bold tracking-tight text-foreground">
                            {{ stats.total_units }}
                        </h3>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Ruler class="h-6 w-6" />
                    </div>
                </div>
            </div>

            <!-- Active Units -->
            <div class="relative overflow-hidden rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                            Active Units
                        </p>
                        <h3 class="mt-1 text-2xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
                            {{ stats.active_units }}
                        </h3>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                        <CheckCircle2 class="h-6 w-6" />
                    </div>
                </div>
            </div>

            <!-- Decimal Allowed -->
            <div class="relative overflow-hidden rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                            Decimal Quantities
                        </p>
                        <h3 class="mt-1 text-2xl font-bold tracking-tight text-blue-600 dark:text-blue-400">
                            {{ stats.decimal_units }}
                        </h3>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
                        <Hash class="h-6 w-6" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Controls -->
        <div class="flex flex-col gap-4 rounded-xl border border-border/60 bg-card p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <!-- Search Bar -->
            <div class="relative flex-1">
                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search by unit name, short code, or description..."
                    class="pl-9"
                />
            </div>

            <!-- Status Filter Tabs & Table Columns Customizer -->
            <div class="flex flex-wrap items-center gap-2 border-t border-border/40 pt-3 sm:border-t-0 sm:pt-0">
                <button
                    type="button"
                    @click="activeStatusFilter = 'all'"
                    :class="[
                        'rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors',
                        activeStatusFilter === 'all'
                            ? 'bg-primary text-primary-foreground'
                            : 'bg-muted/50 text-muted-foreground hover:bg-muted hover:text-foreground',
                    ]"
                >
                    All Units
                </button>
                <button
                    type="button"
                    @click="activeStatusFilter = 'active'"
                    :class="[
                        'rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors',
                        activeStatusFilter === 'active'
                            ? 'bg-emerald-600 text-white dark:bg-emerald-500'
                            : 'bg-muted/50 text-muted-foreground hover:bg-muted hover:text-foreground',
                    ]"
                >
                    Active
                </button>
                <button
                    type="button"
                    @click="activeStatusFilter = 'inactive'"
                    :class="[
                        'rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors',
                        activeStatusFilter === 'inactive'
                            ? 'bg-rose-600 text-white dark:bg-rose-500'
                            : 'bg-muted/50 text-muted-foreground hover:bg-muted hover:text-foreground',
                    ]"
                >
                    Inactive
                </button>
                <button
                    type="button"
                    @click="activeStatusFilter = 'decimal'"
                    :class="[
                        'rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors',
                        activeStatusFilter === 'decimal'
                            ? 'bg-blue-600 text-white dark:bg-blue-500'
                            : 'bg-muted/50 text-muted-foreground hover:bg-muted hover:text-foreground',
                    ]"
                >
                    Decimal Allowed
                </button>

                <!-- Table Columns Dropdown -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-8 gap-1.5 text-xs"
                        >
                            <SlidersHorizontal class="h-3.5 w-3.5 text-primary" />
                            <span>Columns</span>
                            <span class="ml-1 rounded-full bg-primary/10 px-1.5 py-0.5 text-[10px] font-bold text-primary">
                                {{ activeColumnCount }}/6
                            </span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56 p-2 space-y-1">
                        <DropdownMenuLabel class="flex items-center justify-between text-xs font-bold px-1 py-1">
                            <span>Table Columns</span>
                            <button
                                type="button"
                                @click="resetColumns"
                                class="text-[11px] font-semibold text-primary hover:underline cursor-pointer"
                            >
                                Reset All
                            </button>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator class="my-1" />
                        <div
                            v-for="(label, key) in unitColumnLabels"
                            :key="key"
                            @click.stop="toggleUnitColumn(key)"
                            class="flex items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-xs font-medium hover:bg-muted cursor-pointer select-none transition-colors"
                        >
                            <span>{{ label }}</span>
                            <input
                                type="checkbox"
                                :checked="visibleColumns[key as keyof typeof visibleColumns]"
                                @change="toggleUnitColumn(key)"
                                @click.stop
                                class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary cursor-pointer"
                            />
                        </div>
                    </DropdownMenuContent>
                </DropdownMenu>

                <!-- Per-Page Selection -->
                <div class="flex items-center gap-2">
                    <span class="text-xs font-medium text-muted-foreground">Show:</span>
                    <select
                        v-model="perPage"
                        class="h-8 rounded-lg border border-input bg-background px-2 text-xs font-medium shadow-2xs focus:border-primary focus:outline-none"
                    >
                        <option :value="10">10</option>
                        <option :value="15">15</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                        <option :value="250">250</option>
                        <option :value="500">500 / All</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Units Table Card -->
        <div class="overflow-hidden rounded-xl border border-border/60 bg-card shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-border/60 bg-muted/40 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        <tr>
                            <th v-if="visibleColumns.name" class="px-6 py-3.5">Unit Name</th>
                            <th v-if="visibleColumns.short_name" class="px-6 py-3.5">Short Code</th>
                            <th v-if="visibleColumns.allow_decimal" class="px-6 py-3.5">Allow Decimal</th>
                            <th v-if="visibleColumns.is_active" class="px-6 py-3.5">Status</th>
                            <th v-if="visibleColumns.description" class="px-6 py-3.5">Description</th>
                            <th v-if="visibleColumns.actions" class="px-6 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr
                            v-for="unit in units.data"
                            :key="unit.id"
                            class="transition-colors hover:bg-muted/30"
                        >
                            <!-- Unit Name -->
                            <td v-if="visibleColumns.name" class="px-6 py-4 font-semibold text-foreground">
                                {{ unit.name }}
                            </td>

                            <!-- Short Code -->
                            <td v-if="visibleColumns.short_name" class="px-6 py-4">
                                <Badge variant="outline" class="font-mono text-xs font-semibold">
                                    {{ unit.short_name }}
                                </Badge>
                            </td>

                            <!-- Allow Decimal -->
                            <td v-if="visibleColumns.allow_decimal" class="px-6 py-4">
                                <Badge
                                    v-if="unit.allow_decimal"
                                    class="bg-blue-500/15 text-blue-700 dark:bg-blue-500/25 dark:text-blue-300"
                                >
                                    Decimal Allowed (1.5)
                                </Badge>
                                <Badge
                                    v-else
                                    variant="secondary"
                                    class="text-muted-foreground"
                                >
                                    Integer Only (1, 2)
                                </Badge>
                            </td>

                            <!-- Status -->
                            <td v-if="visibleColumns.is_active" class="px-6 py-4">
                                <span
                                    v-if="unit.is_active"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs font-medium text-emerald-600 dark:text-emerald-400"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    Active
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 rounded-full bg-rose-500/10 px-2.5 py-1 text-xs font-medium text-rose-600 dark:text-rose-400"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                    Inactive
                                </span>
                            </td>

                            <!-- Description -->
                            <td v-if="visibleColumns.description" class="max-w-xs px-6 py-4 truncate text-muted-foreground">
                                {{ unit.description || '—' }}
                            </td>

                            <!-- Actions -->
                            <td v-if="visibleColumns.actions" class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openEditModal(unit)"
                                        class="h-8 w-8 p-0"
                                    >
                                        <Edit2 class="h-3.5 w-3.5" />
                                        <span class="sr-only">Edit Unit</span>
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="handleDeleteUnit(unit)"
                                        class="h-8 w-8 p-0 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                        <span class="sr-only">Delete Unit</span>
                                    </Button>
                                </div>
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-if="units.data.length === 0">
                            <td colspan="6" class="px-6 py-12 text-center text-muted-foreground">
                                <div class="flex flex-col items-center justify-center space-y-3">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                                        <Ruler class="h-6 w-6 text-muted-foreground" />
                                    </div>
                                    <p class="text-base font-medium">No measurement units found</p>
                                    <p class="text-sm">Try adjusting your search query or add a new unit.</p>
                                    <Button @click="openCreateModal" size="sm" class="mt-2">
                                        <Plus class="mr-1.5 h-4 w-4" />
                                        Add New Unit
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Create Unit Modal -->
        <Dialog :open="isCreateDialogOpen" @update:open="isCreateDialogOpen = $event">
            <DialogContent class="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle>Add New Unit</DialogTitle>
                    <DialogDescription>
                        Create a measurement unit to standardize product inventory.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="handleCreateUnit" class="space-y-4 py-2">
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Unit Name -->
                        <div class="space-y-1.5">
                            <Label for="create_name">Unit Name <span class="text-destructive">*</span></Label>
                            <Input
                                id="create_name"
                                v-model="createForm.name"
                                placeholder="e.g. Piece, Box, Pack"
                                :class="{ 'border-destructive': createForm.errors.name }"
                            />
                            <p v-if="createForm.errors.name" class="text-xs text-destructive">
                                {{ createForm.errors.name }}
                            </p>
                        </div>

                        <!-- Short Code -->
                        <div class="space-y-1.5">
                            <Label for="create_short_name">Short Code <span class="text-destructive">*</span></Label>
                            <Input
                                id="create_short_name"
                                v-model="createForm.short_name"
                                placeholder="e.g. Pcs, Box, Pkt"
                                :class="{ 'border-destructive': createForm.errors.short_name }"
                            />
                            <p v-if="createForm.errors.short_name" class="text-xs text-destructive">
                                {{ createForm.errors.short_name }}
                            </p>
                        </div>
                    </div>

                    <!-- Allow Decimal Option -->
                    <div class="flex items-center justify-between rounded-lg border border-border/60 p-3 bg-muted/20">
                        <div class="space-y-0.5">
                            <Label class="text-sm font-medium">Allow Decimal Quantities</Label>
                            <p class="text-xs text-muted-foreground">
                                Enable if items can be sold in fractions (e.g. 1.5 Kg or 2.5 Mtr).
                            </p>
                        </div>
                        <input
                            type="checkbox"
                            v-model="createForm.allow_decimal"
                            class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                        />
                    </div>

                    <!-- Is Active Option -->
                    <div class="flex items-center justify-between rounded-lg border border-border/60 p-3 bg-muted/20">
                        <div class="space-y-0.5">
                            <Label class="text-sm font-medium">Active Unit</Label>
                            <p class="text-xs text-muted-foreground">
                                Active units appear in product dropdowns and inventory creation.
                            </p>
                        </div>
                        <input
                            type="checkbox"
                            v-model="createForm.is_active"
                            class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                        />
                    </div>

                    <!-- Description -->
                    <div class="space-y-1.5">
                        <Label for="create_description">Description (Optional)</Label>
                        <Input
                            id="create_description"
                            v-model="createForm.description"
                            placeholder="Optional note about this unit"
                        />
                    </div>

                    <DialogFooter class="pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isCreateDialogOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="createForm.processing"
                        >
                            {{ createForm.processing ? 'Saving...' : 'Save Unit' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Edit Unit Modal -->
        <Dialog :open="isEditDialogOpen" @update:open="isEditDialogOpen = $event">
            <DialogContent class="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle>Edit Unit</DialogTitle>
                    <DialogDescription>
                        Update measurement unit parameters.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="handleUpdateUnit" class="space-y-4 py-2">
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Unit Name -->
                        <div class="space-y-1.5">
                            <Label for="edit_name">Unit Name <span class="text-destructive">*</span></Label>
                            <Input
                                id="edit_name"
                                v-model="editForm.name"
                                placeholder="e.g. Piece, Box"
                                :class="{ 'border-destructive': editForm.errors.name }"
                            />
                            <p v-if="editForm.errors.name" class="text-xs text-destructive">
                                {{ editForm.errors.name }}
                            </p>
                        </div>

                        <!-- Short Code -->
                        <div class="space-y-1.5">
                            <Label for="edit_short_name">Short Code <span class="text-destructive">*</span></Label>
                            <Input
                                id="edit_short_name"
                                v-model="editForm.short_name"
                                placeholder="e.g. Pcs, Box"
                                :class="{ 'border-destructive': editForm.errors.short_name }"
                            />
                            <p v-if="editForm.errors.short_name" class="text-xs text-destructive">
                                {{ editForm.errors.short_name }}
                            </p>
                        </div>
                    </div>

                    <!-- Allow Decimal Option -->
                    <div class="flex items-center justify-between rounded-lg border border-border/60 p-3 bg-muted/20">
                        <div class="space-y-0.5">
                            <Label class="text-sm font-medium">Allow Decimal Quantities</Label>
                            <p class="text-xs text-muted-foreground">
                                Enable if items can be sold in fractions.
                            </p>
                        </div>
                        <input
                            type="checkbox"
                            v-model="editForm.allow_decimal"
                            class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                        />
                    </div>

                    <!-- Is Active Option -->
                    <div class="flex items-center justify-between rounded-lg border border-border/60 p-3 bg-muted/20">
                        <div class="space-y-0.5">
                            <Label class="text-sm font-medium">Active Unit</Label>
                            <p class="text-xs text-muted-foreground">
                                Active units appear in product dropdowns.
                            </p>
                        </div>
                        <input
                            type="checkbox"
                            v-model="editForm.is_active"
                            class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                        />
                    </div>

                    <!-- Description -->
                    <div class="space-y-1.5">
                        <Label for="edit_description">Description (Optional)</Label>
                        <Input
                            id="edit_description"
                            v-model="editForm.description"
                            placeholder="Optional note about this unit"
                        />
                    </div>

                    <DialogFooter class="pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isEditDialogOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="editForm.processing"
                        >
                            {{ editForm.processing ? 'Updating...' : 'Update Unit' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
