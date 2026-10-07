<script setup lang="ts">
import {
    Download,
    FileSpreadsheet,
    FileUp,
    RefreshCw,
    Table2,
    UploadCloud,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
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
import { usePage, useForm } from '@inertiajs/vue3';

interface ImportResult {
    label?: string;
    created?: number;
    skipped?: number;
    errors?: string[];
}

const props = withDefaults(
    defineProps<{
        open: boolean;
        templateUrl: string;
        actionUrl: string;
        exportUrl?: string;
        title: string;
        description: string;
        entityLabel: string;
    }>(),
    {
        templateUrl: '',
        actionUrl: '',
        exportUrl: '',
        title: 'Import / Export',
        description: '',
        entityLabel: 'products',
    },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const page = usePage();
const fileInputRef = ref<HTMLInputElement | null>(null);
const result = ref<ImportResult | null>(null);

const form = useForm<{ file: File | null }>({ file: null });

const isOpen = computed(() => props.open || result.value !== null);

const selectFile = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] || null;
    form.file = file;
    if (file) {
        form.clearErrors();
    }
};

const clearSelection = () => {
    form.file = null;
    form.reset();
    form.clearErrors();
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
};

const submitImport = () => {
    if (!form.file) {
        toast.error('No File Selected', {
            description: 'Choose an Excel (.xlsx) file first.',
        });
        return;
    }

    form.clearErrors();
    form.post(props.actionUrl, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            if (fileInputRef.value) {
                fileInputRef.value.value = '';
            }
            toast.success('Import Complete', {
                description: `Your ${props.entityLabel} were processed.`,
            });
        },
        onError: (errors) => {
            const errorMsg =
                Object.values(errors).flat().join(' ') ||
                'Could not process the file.';
            toast.error('Import Failed', { description: errorMsg });
        },
    });
};

watch(
    () =>
        (page.props.flash as { importResult?: ImportResult } | undefined)
            ?.importResult,
    (value) => {
        if (value) {
            result.value = value;
        }
    },
);

const closeDialog = () => {
    emit('update:open', false);
    result.value = null;
    clearSelection();
};
</script>

<template>
    <Dialog
        :open="isOpen"
        @update:open="(value: boolean) => !value && closeDialog()"
    >
        <DialogContent
            class="max-w-2xl gap-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl"
        >
            <DialogHeader>
                <DialogTitle
                    class="flex items-center gap-2 text-lg font-black text-slate-900"
                >
                    <span
                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#003B7D]/10"
                    >
                        <Table2 class="h-4.5 w-4.5 text-[#003B7D]" />
                    </span>
                    {{ title }}
                </DialogTitle>
                <DialogDescription class="text-xs text-slate-500">{{
                    description
                }}</DialogDescription>
            </DialogHeader>

            <!-- Result Screen -->
            <div v-if="result" class="space-y-3 py-2">
                <div
                    class="flex items-center gap-3 rounded-xl border p-3"
                    :class="
                        (result.errors?.length || 0) > 0
                            ? 'border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-950/40'
                            : 'border-emerald-300 bg-emerald-50 dark:border-emerald-500/40 dark:bg-emerald-950/40'
                    "
                >
                    <FileSpreadsheet
                        class="h-6 w-6 shrink-0"
                        :class="
                            (result.errors?.length || 0) > 0
                                ? 'text-amber-600 dark:text-amber-400'
                                : 'text-emerald-600 dark:text-emerald-400'
                        "
                    />
                    <div class="text-xs font-bold text-slate-800">
                        <span v-if="(result.errors?.length || 0) > 0">
                            {{ result.label || entityLabel }} imported with some
                            skipped rows.
                        </span>
                        <span v-else>
                            {{ result.label || entityLabel }} imported
                            successfully.
                        </span>
                        <div class="mt-0.5 font-semibold text-slate-600">
                            {{ result.created ?? 0 }} created •
                            {{ result.skipped ?? 0 }} skipped
                        </div>
                    </div>
                </div>

                <div
                    v-if="result.errors?.length"
                    class="max-h-40 space-y-1 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-3"
                >
                    <div
                        class="mb-1 text-[10px] font-extrabold tracking-wide text-slate-500 uppercase"
                    >
                        Skipped rows ({{
                            (result.skipped ?? 0) > (result.errors?.length || 0)
                                ? result.errors?.length
                                : (result.skipped ?? 0)
                        }}
                        shown)
                    </div>
                    <div
                        v-for="(error, index) in result.errors"
                        :key="index"
                        class="text-[11px] font-semibold text-rose-600 dark:text-rose-400"
                    >
                        • {{ error }}
                    </div>
                </div>

                <DialogFooter class="pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="closeDialog"
                    >
                        Close
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        @click="result = null"
                    >
                        <RefreshCw class="mr-1 h-3.5 w-3.5" />
                        Import Another File
                    </Button>
                </DialogFooter>
            </div>

            <!-- Export & Import Screen -->
            <form v-else @submit.prevent="submitImport" class="space-y-4 py-1">
                <div class="grid gap-4 sm:grid-cols-2">
                    <!-- Export -->
                    <div
                        class="flex flex-col gap-3 rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4 dark:border-emerald-500/30 dark:bg-emerald-950/30"
                    >
                        <div>
                            <div
                                class="flex items-center gap-1.5 text-xs font-black tracking-wide text-emerald-700 uppercase dark:text-emerald-300"
                            >
                                <Download class="h-3.5 w-3.5" />
                                Export
                            </div>
                            <p
                                class="mt-1 text-[11px] leading-snug font-medium text-slate-500 dark:text-slate-400"
                            >
                                Download the full {{ entityLabel }} list in the
                                same columns as the import template.
                            </p>
                        </div>

                        <div class="mt-auto grid gap-2">
                            <a
                                v-if="exportUrl"
                                :href="`${exportUrl}?format=xlsx`"
                                class="flex items-center justify-between rounded-xl border border-emerald-300 bg-white px-3 py-2.5 transition hover:border-emerald-400 hover:bg-emerald-50 dark:border-emerald-500/40 dark:bg-slate-900 dark:hover:bg-emerald-950/60"
                            >
                                <span
                                    class="text-xs font-bold text-slate-700 dark:text-slate-200"
                                    >Excel (.xlsx)</span
                                >
                                <span
                                    class="rounded-md bg-emerald-100 px-1.5 py-0.5 text-[9px] font-black tracking-wide text-emerald-700 uppercase dark:bg-emerald-500/20 dark:text-emerald-300"
                                    >Download</span
                                >
                            </a>
                            <a
                                v-if="exportUrl"
                                :href="`${exportUrl}?format=csv`"
                                class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2.5 transition hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800"
                            >
                                <span
                                    class="text-xs font-bold text-slate-700 dark:text-slate-200"
                                    >CSV</span
                                >
                                <span
                                    class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[9px] font-black tracking-wide text-slate-600 uppercase dark:bg-slate-800 dark:text-slate-300"
                                    >Download</span
                                >
                            </a>
                        </div>
                    </div>

                    <!-- Template -->
                    <div
                        class="flex flex-col gap-3 rounded-2xl border border-indigo-200 bg-indigo-50/40 p-4 dark:border-indigo-500/30 dark:bg-indigo-950/30"
                    >
                        <div>
                            <div
                                class="flex items-center gap-1.5 text-xs font-black tracking-wide text-indigo-700 uppercase dark:text-indigo-300"
                            >
                                <FileUp class="h-3.5 w-3.5" />
                                Template
                            </div>
                            <p
                                class="mt-1 text-[11px] leading-snug font-medium text-slate-500 dark:text-slate-400"
                            >
                                Download the blank {{ entityLabel }} template,
                                fill it in, then upload it below.
                            </p>
                        </div>

                        <a
                            :href="templateUrl"
                            class="mt-auto flex items-center justify-between rounded-xl border border-indigo-300 bg-white px-3 py-2.5 transition hover:border-indigo-400 hover:bg-indigo-50 dark:border-indigo-500/40 dark:bg-slate-900 dark:hover:bg-indigo-950/60"
                        >
                            <span
                                class="text-xs font-bold text-slate-700 dark:text-slate-200"
                                >Download Template</span
                            >
                            <Download
                                class="h-4 w-4 text-indigo-600 dark:text-indigo-400"
                            />
                        </a>
                    </div>
                </div>

                <!-- Import -->
                <div class="space-y-3">
                    <div
                        class="flex items-center gap-1.5 text-xs font-black tracking-wide text-slate-700 uppercase dark:text-slate-300"
                    >
                        <UploadCloud class="h-3.5 w-3.5" />
                        Import
                    </div>

                    <label
                        class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50/50 px-4 py-7 text-center transition hover:border-[#003B7D]/50 hover:bg-[#003B7D]/5"
                    >
                        <input
                            ref="fileInputRef"
                            type="file"
                            accept=".xlsx"
                            class="hidden"
                            @change="selectFile"
                        />
                        <UploadCloud class="h-8 w-8 text-slate-400" />
                        <span class="text-xs font-extrabold text-slate-700">
                            {{
                                form.file
                                    ? form.file.name
                                    : 'Click to select an .xlsx file'
                            }}
                        </span>
                        <span
                            v-if="!form.file"
                            class="text-[10px] font-semibold text-slate-400"
                        >
                            Keep the header row as-is. Rows starting with
                            <span class="font-black">#</span> are ignored.
                        </span>
                        <span
                            v-else
                            class="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400"
                        >
                            Ready to import — click "Import Now".
                        </span>
                    </label>

                    <div
                        v-for="(error, index) in Object.values(
                            form.errors,
                        ).flat()"
                        :key="index"
                        class="text-[11px] font-bold text-rose-600 dark:text-rose-400"
                    >
                        {{ error }}
                    </div>
                </div>

                <DialogFooter class="pt-1">
                    <Button type="button" variant="outline" @click="closeDialog"
                        >Cancel</Button
                    >
                    <Button
                        type="button"
                        variant="outline"
                        v-if="form.file"
                        @click="clearSelection"
                    >
                        <RefreshCw class="mr-1 h-3.5 w-3.5" />
                        Clear
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing || !form.file"
                        class="bg-[#003B7D] font-bold text-white hover:bg-[#002b5c] dark:bg-sky-600 dark:hover:bg-sky-500"
                    >
                        <span
                            v-if="form.processing"
                            class="mr-1.5 inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-white/40 border-t-white"
                        ></span>
                        Import Now
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
