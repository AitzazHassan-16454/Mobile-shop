<script setup lang="ts">
import {
    AlertCircle,
    AlertTriangle,
    CheckCircle2,
    Info,
    Trash2,
} from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useConfirm } from '@/composables/useConfirm';

const { isOpen, isAlert, options, handleConfirm, handleCancel } = useConfirm();

const iconComponent = computed(() => {
    switch (options.value.variant) {
        case 'destructive':
            return Trash2;
        case 'warning':
            return AlertTriangle;
        case 'info':
            return Info;
        case 'success':
            return CheckCircle2;
        default:
            return AlertCircle;
    }
});

const iconColorClasses = computed(() => {
    switch (options.value.variant) {
        case 'destructive':
            return 'bg-rose-100 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400';
        case 'warning':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400';
        case 'success':
            return 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400';
        default:
            return 'bg-blue-100 text-[#003B7D] dark:bg-sky-950/60 dark:text-sky-300';
    }
});

const confirmButtonClasses = computed(() => {
    switch (options.value.variant) {
        case 'destructive':
            return 'bg-rose-600 text-white hover:bg-rose-700 dark:bg-rose-600 dark:hover:bg-rose-500';
        case 'warning':
            return 'bg-amber-600 text-white hover:bg-amber-700';
        case 'success':
            return 'bg-emerald-600 text-white hover:bg-emerald-700';
        default:
            return 'bg-[#003B7D] text-white hover:bg-[#002b5c] dark:bg-sky-600 dark:hover:bg-sky-500';
    }
});
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="max-w-md p-5 sm:p-6">
            <div class="flex items-start gap-3.5">
                <div
                    :class="[
                        'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl',
                        iconColorClasses,
                    ]"
                >
                    <component :is="iconComponent" class="h-5 w-5" />
                </div>
                <div class="flex-1">
                    <DialogHeader class="p-0 text-left">
                        <DialogTitle
                            class="text-base font-bold text-gray-900 dark:text-white"
                        >
                            {{ options.title }}
                        </DialogTitle>
                        <DialogDescription
                            class="mt-1.5 text-xs leading-relaxed text-gray-600 dark:text-gray-400"
                        >
                            {{ options.message }}
                        </DialogDescription>
                    </DialogHeader>
                </div>
            </div>

            <DialogFooter class="mt-4 flex flex-row justify-end gap-2 pt-2">
                <Button
                    v-if="!isAlert"
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="handleCancel"
                    class="h-8.5 px-3 text-xs font-medium"
                >
                    {{ options.cancelText || 'Cancel' }}
                </Button>
                <Button
                    type="button"
                    size="sm"
                    @click="handleConfirm"
                    :class="['h-8.5 px-4 text-xs font-bold shadow-sm', confirmButtonClasses]"
                >
                    {{ options.confirmText || 'OK' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
