<script setup lang="ts">
import { AlertTriangle, CircleHelp } from '@lucide/vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    confirmDialogState,
    resolveConfirmAction,
} from '@/composables/useConfirmDialog';

const handleOpenChange = (open: boolean): void => {
    if (!open) {
        resolveConfirmAction(false);
    }
};
</script>

<template>
    <Dialog :open="confirmDialogState.open" @update:open="handleOpenChange">
        <DialogContent
            class="overflow-hidden border-sidebar-border/70 p-0 sm:max-w-md dark:border-sidebar-border"
            :show-close-button="false"
        >
            <div class="p-5 sm:p-6">
                <DialogHeader class="text-left">
                    <div
                        class="mb-4 flex size-11 items-center justify-center rounded-2xl"
                        :class="
                            confirmDialogState.variant === 'destructive'
                                ? 'bg-red-500/10 text-red-600 dark:text-red-400'
                                : confirmDialogState.variant === 'warning'
                                  ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                  : 'bg-primary/10 text-primary'
                        "
                    >
                        <AlertTriangle
                            v-if="
                                confirmDialogState.variant === 'destructive' ||
                                confirmDialogState.variant === 'warning'
                            "
                            class="size-5"
                        />
                        <CircleHelp v-else class="size-5" />
                    </div>

                    <DialogTitle class="text-xl">
                        {{ confirmDialogState.title }}
                    </DialogTitle>
                    <DialogDescription class="pt-1 leading-6">
                        {{ confirmDialogState.description }}
                    </DialogDescription>
                </DialogHeader>
            </div>

            <DialogFooter
                class="grid grid-cols-2 gap-2 border-t border-sidebar-border/70 bg-muted/30 p-4 sm:grid-cols-2 dark:border-sidebar-border"
            >
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-xl border border-sidebar-border/70 px-4 py-2.5 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                    @click="resolveConfirmAction(false)"
                >
                    {{ confirmDialogState.cancelLabel }}
                </button>
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90"
                    :class="
                        confirmDialogState.variant === 'destructive'
                            ? 'bg-red-600'
                            : confirmDialogState.variant === 'warning'
                              ? 'bg-amber-600'
                              : 'bg-primary text-primary-foreground'
                    "
                    @click="resolveConfirmAction(true)"
                >
                    {{ confirmDialogState.confirmLabel }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
