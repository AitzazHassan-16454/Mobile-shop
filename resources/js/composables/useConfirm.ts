import { ref } from 'vue';

export interface ConfirmOptions {
    title?: string;
    message: string;
    confirmText?: string;
    cancelText?: string;
    variant?: 'destructive' | 'default' | 'warning' | 'info' | 'success';
}

export interface AlertOptions {
    title?: string;
    message: string;
    confirmText?: string;
    variant?: 'destructive' | 'warning' | 'info' | 'success';
}

const isOpen = ref(false);
const isAlert = ref(false);
const options = ref<ConfirmOptions>({
    title: 'Are you sure?',
    message: '',
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    variant: 'default',
});

let resolvePromise: ((value: boolean) => void) | null = null;

export function useConfirm() {
    const confirm = (opts: ConfirmOptions | string): Promise<boolean> => {
        if (typeof opts === 'string') {
            options.value = {
                title: 'Confirmation',
                message: opts,
                confirmText: 'Confirm',
                cancelText: 'Cancel',
                variant: 'default',
            };
        } else {
            options.value = {
                title: opts.title || 'Confirmation',
                message: opts.message,
                confirmText: opts.confirmText || 'Confirm',
                cancelText: opts.cancelText || 'Cancel',
                variant: opts.variant || 'default',
            };
        }

        isAlert.value = false;
        isOpen.value = true;

        return new Promise((resolve) => {
            resolvePromise = resolve;
        });
    };

    const alert = (opts: AlertOptions | string): Promise<boolean> => {
        if (typeof opts === 'string') {
            options.value = {
                title: 'Notice',
                message: opts,
                confirmText: 'OK',
                cancelText: '',
                variant: 'info',
            };
        } else {
            options.value = {
                title: opts.title || 'Notice',
                message: opts.message,
                confirmText: opts.confirmText || 'OK',
                cancelText: '',
                variant: opts.variant || 'info',
            };
        }

        isAlert.value = true;
        isOpen.value = true;

        return new Promise((resolve) => {
            resolvePromise = resolve;
        });
    };

    const handleConfirm = () => {
        isOpen.value = false;
        if (resolvePromise) {
            resolvePromise(true);
            resolvePromise = null;
        }
    };

    const handleCancel = () => {
        isOpen.value = false;
        if (resolvePromise) {
            resolvePromise(false);
            resolvePromise = null;
        }
    };

    return {
        isOpen,
        isAlert,
        options,
        confirm,
        alert,
        handleConfirm,
        handleCancel,
    };
}
