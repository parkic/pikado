import { reactive } from 'vue';

export type ConfirmDialogVariant = 'default' | 'warning' | 'destructive';

export type ConfirmDialogOptions = {
    title: string;
    description: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: ConfirmDialogVariant;
};

export const confirmDialogState = reactive({
    open: false,
    title: '',
    description: '',
    confirmLabel: 'Potvrdi',
    cancelLabel: 'Odustani',
    variant: 'default' as ConfirmDialogVariant,
});

let resolveConfirmation: ((confirmed: boolean) => void) | null = null;

export const confirmAction = (
    options: ConfirmDialogOptions,
): Promise<boolean> => {
    resolveConfirmation?.(false);

    Object.assign(confirmDialogState, {
        open: true,
        title: options.title,
        description: options.description,
        confirmLabel: options.confirmLabel ?? 'Potvrdi',
        cancelLabel: options.cancelLabel ?? 'Odustani',
        variant: options.variant ?? 'default',
    });

    return new Promise<boolean>((resolve) => {
        resolveConfirmation = resolve;
    });
};

export const resolveConfirmAction = (confirmed: boolean): void => {
    if (!confirmDialogState.open && !resolveConfirmation) {
        return;
    }

    confirmDialogState.open = false;
    resolveConfirmation?.(confirmed);
    resolveConfirmation = null;
};
