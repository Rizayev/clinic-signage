import { reactive } from 'vue';

// Module-level singleton: every component that calls useToast() shares this state.
const state = reactive({ toasts: [] });
let seq = 0;

function dismiss(id) {
    const i = state.toasts.findIndex((t) => t.id === id);
    if (i !== -1) state.toasts.splice(i, 1);
}

function push(message, type = 'success', timeout = 3500) {
    const id = ++seq;
    state.toasts.push({ id, message, type });
    if (timeout) setTimeout(() => dismiss(id), timeout);
    return id;
}

export function useToast() {
    return {
        state,
        dismiss,
        success: (m) => push(m, 'success'),
        error: (m) => push(m, 'error', 5000),
        info: (m) => push(m, 'info'),
        warning: (m) => push(m, 'warning', 4500),
    };
}
