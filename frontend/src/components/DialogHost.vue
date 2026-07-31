<script setup>
import { useDialog } from "../composables/useDialog";

const { state, resolveConfirm, resolveAlert } = useDialog();

function onCancel() {
  resolveConfirm(false);
}

function onConfirm() {
  resolveConfirm(true);
}
</script>

<template>
  <Teleport to="body">
    <!-- Confirm dialog: le clic sur le fond équivaut à Annuler -->
    <div v-if="state.confirm.visible" class="dialog-overlay" @click.self="onCancel">
      <div class="dialog-card" role="alertdialog" aria-modal="true">
        <h3 class="dialog-title">{{ state.confirm.title }}</h3>
        <p class="dialog-message">{{ state.confirm.message }}</p>
        <div class="dialog-actions">
          <button type="button" class="dialog-btn dialog-btn--secondary" @click="onCancel">
            {{ state.confirm.cancelLabel }}
          </button>
          <button
            type="button"
            class="dialog-btn"
            :class="state.confirm.danger ? 'dialog-btn--danger' : 'dialog-btn--primary'"
            @click="onConfirm"
          >
            {{ state.confirm.confirmLabel }}
          </button>
        </div>
      </div>
    </div>

    <!-- Alert dialog: pas de fermeture au clic sur le fond, uniquement via le bouton -->
    <div v-if="state.alert.visible" class="dialog-overlay">
      <div class="dialog-card" role="alertdialog" aria-modal="true">
        <span class="dialog-icon" :class="state.alert.danger ? 'dialog-icon--danger' : 'dialog-icon--info'">
          <svg v-if="state.alert.danger" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="8" x2="12" y2="12" />
            <line x1="12" y1="16" x2="12.01" y2="16" />
          </svg>
          <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="16" x2="12" y2="12" />
            <line x1="12" y1="8" x2="12.01" y2="8" />
          </svg>
        </span>
        <h3 class="dialog-title">{{ state.alert.title }}</h3>
        <p class="dialog-message">{{ state.alert.message }}</p>
        <div class="dialog-actions dialog-actions--single">
          <button type="button" class="dialog-btn dialog-btn--primary" @click="resolveAlert">
            {{ state.alert.closeLabel }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.dialog-overlay {
  position: fixed;
  inset: 0;
  background-color: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  z-index: 2000;
  animation: dialog-fade-in 0.2s ease;
}

.dialog-card {
  width: 100%;
  max-width: 400px;
  background-color: var(--color-paper-raised);
  border-radius: 24px;
  padding: 28px;
  box-shadow: 0 20px 50px rgba(15, 23, 42, 0.18);
  text-align: center;
  animation: dialog-slide-up 0.25s cubic-bezier(0.16, 1, 0.3, 1);
  box-sizing: border-box;
}

.dialog-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 14px;
}

.dialog-icon--danger {
  background-color: rgba(239, 68, 68, 0.1);
  color: var(--color-danger);
}

.dialog-icon--info {
  background-color: var(--color-primary-light);
  color: var(--color-primary-dark);
}

.dialog-title {
  font-size: 1.1rem;
  font-weight: 800;
  color: var(--color-ink);
  margin: 0 0 8px;
}

.dialog-message {
  font-size: 0.9rem;
  color: var(--color-ink-soft);
  line-height: 1.5;
  margin: 0 0 22px;
}

.dialog-actions {
  display: flex;
  gap: 12px;
}

.dialog-actions--single {
  justify-content: center;
}

.dialog-btn {
  flex: 1;
  padding: 13px;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.92rem;
  text-align: center;
  transition: all 0.2s ease;
}

.dialog-actions--single .dialog-btn {
  flex: none;
  min-width: 140px;
}

.dialog-btn--secondary {
  background-color: var(--color-bg-soft);
  color: var(--color-ink-soft);
}

.dialog-btn--secondary:hover {
  background-color: var(--color-border);
}

.dialog-btn--primary {
  background-color: var(--color-primary);
  color: #fff;
}

.dialog-btn--primary:hover {
  background-color: var(--color-primary-dark);
}

.dialog-btn--danger {
  background-color: var(--color-danger);
  color: #fff;
}

.dialog-btn--danger:hover {
  background-color: #dc2626;
}

@keyframes dialog-fade-in {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

@keyframes dialog-slide-up {
  from {
    transform: translateY(15px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}
</style>
