import { reactive } from 'vue';

// Module-level singleton dialog state. ConfirmHost renders it once globally.
const state = reactive({
    open: false,
    title: 'Подтвердите действие',
    message: '',
    confirmText: 'Подтвердить',
    cancelText: 'Отмена',
    variant: 'danger', // danger | primary
    _resolve: null,
});

function answer(value) {
    state.open = false;
    if (state._resolve) {
        state._resolve(value);
        state._resolve = null;
    }
}

export function useConfirm() {
    /**
     * Returns a promise resolving to true (confirmed) or false (cancelled).
     * Usage: if (await confirm({ message: 'Удалить?' })) { ... }
     */
    function confirm(options = {}) {
        return new Promise((resolve) => {
            Object.assign(state, {
                open: true,
                title: options.title ?? 'Подтвердите действие',
                message: options.message ?? '',
                confirmText: options.confirmText ?? 'Подтвердить',
                cancelText: options.cancelText ?? 'Отмена',
                variant: options.variant ?? 'danger',
                _resolve: resolve,
            });
        });
    }

    return { state, confirm, answer };
}
