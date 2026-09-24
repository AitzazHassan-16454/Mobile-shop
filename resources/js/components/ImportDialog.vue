<script setup lang="ts">
import { Download, FileSpreadsheet, RefreshCw, UploadCloud } from '@lucide/vue';
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
        title: string;
        description: string;
        entityLabel: string;
    }>(),
    {
        templateUrl: '',
        actionUrl: '',
        title: 'Bulk Import',
        description: '',
        entityLabel: 'records',
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
            class="max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl"
        >
            <DialogHeader>
                <DialogTitle class="text-lg font-black text-slate-900">{{
                    title
                }}</DialogTitle>
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
                        @click="clearSelection"
                    >
                        Import Another File
                    </Button>
                </DialogFooter>
            </div>

            <!-- Upload Screen -->
            <form v-else @submit.prevent="submitImport" class="space-y-4 py-2">
                <a
                    :href="templateUrl"
                    class="flex items-center justify-between rounded-xl border border-dashed border-indigo-300 bg-indigo-50/60 px-3 py-2 transition hover:bg-indigo-100 dark:border-indigo-500/40 dark:bg-indigo-950/40 dark:hover:bg-indigo-950/60"
                >
                    <span class="text-xs font-bold text-indigo-700 dark:text-indigo-300"
                        >Download Import Template</span
                    >
                    <Download class="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                </a>

                <label
                    class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50/50 px-4 py-8 text-center transition hover:border-indigo-400 hover:bg-indigo-50/40"
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
                        Fill the template below the header row. Rows starting
                        with
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
                    v-for="(error, index) in Object.values(form.errors).flat()"
                    :key="index"
                    class="text-[11px] font-bold text-rose-600 dark:text-rose-400"
                >
                    {{ error }}
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
