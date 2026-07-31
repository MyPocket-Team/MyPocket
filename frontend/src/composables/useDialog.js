import { reactive } from "vue";

const state = reactive({
  confirm: {
    visible: false,
    title: "",
    message: "",
    confirmLabel: "Confirmer",
    cancelLabel: "Annuler",
    danger: false,
    resolve: null,
  },
  alert: {
    visible: false,
    title: "",
    message: "",
    closeLabel: "OK",
    danger: true,
    resolve: null,
  },
});

function confirmDialog(message, options = {}) {
  return new Promise((resolve) => {
    state.confirm.title = options.title || "Confirmation";
    state.confirm.message = message;
    state.confirm.confirmLabel = options.confirmLabel || "Confirmer";
    state.confirm.cancelLabel = options.cancelLabel || "Annuler";
    state.confirm.danger = options.danger ?? false;
    state.confirm.resolve = resolve;
    state.confirm.visible = true;
  });
}

function resolveConfirm(value) {
  state.confirm.visible = false;
  state.confirm.resolve?.(value);
  state.confirm.resolve = null;
}

function alertDialog(message, options = {}) {
  return new Promise((resolve) => {
    state.alert.title = options.title || (options.danger === false ? "Information" : "Erreur");
    state.alert.message = message;
    state.alert.closeLabel = options.closeLabel || "OK";
    state.alert.danger = options.danger ?? true;
    state.alert.resolve = resolve;
    state.alert.visible = true;
  });
}

function resolveAlert() {
  state.alert.visible = false;
  state.alert.resolve?.();
  state.alert.resolve = null;
}

export function useDialog() {
  return { state, confirmDialog, resolveConfirm, alertDialog, resolveAlert };
}
