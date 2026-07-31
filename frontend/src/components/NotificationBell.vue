<script setup>
import { useNotifications } from "../composables/useNotifications";

const { notifications, panelOpen, unreadCount, toggleNotifPanel, closeNotifPanel, markNotificationRead } =
  useNotifications();
</script>

<template>
  <div class="notif-bell-wrapper">
    <button type="button" class="notif-bell" @click.stop="toggleNotifPanel" aria-label="Notifications">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
        <path d="M13.73 21a2 2 0 0 1-3.46 0" />
      </svg>
      <span v-if="unreadCount" class="notif-badge">{{ unreadCount }}</span>
    </button>

    <div v-if="panelOpen" class="notif-overlay" @click="closeNotifPanel"></div>
    <div v-if="panelOpen" class="notif-panel">
      <div class="notif-panel-header">
        <span>Notifications</span>
        <button type="button" class="notif-close" @click="closeNotifPanel" aria-label="Fermer">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      </div>

      <div v-if="notifications.length === 0" class="notif-empty">Aucune notification pour le moment.</div>

      <button
        v-for="n in notifications"
        :key="n.id"
        type="button"
        class="notif-item"
        :class="[`notif-item--${n.icon}`, { 'notif-item--read': n.read }]"
        @click="markNotificationRead(n.id)"
      >
        <span class="notif-item-icon">
          <svg v-if="n.icon === 'alert'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
            <line x1="12" y1="9" x2="12" y2="13" />
            <line x1="12" y1="17" x2="12.01" y2="17" />
          </svg>
          <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
            <line x1="16" y1="2" x2="16" y2="6" />
            <line x1="8" y1="2" x2="8" y2="6" />
            <line x1="3" y1="10" x2="21" y2="10" />
          </svg>
        </span>
        <div class="notif-item-text">
          <span class="notif-item-title">{{ n.title }}</span>
          <span class="notif-item-message">{{ n.message }}</span>
        </div>
        <span v-if="!n.read" class="notif-unread-dot"></span>
      </button>
    </div>
  </div>
</template>

<style scoped>
/* Épinglé dans le flux normal du header de chaque page (plus de position:fixed
   global qui flottait par-dessus le contenu et se plaçait mal selon la page) :
   le panneau s'ancre directement sous ce bouton via position:absolute. */
.notif-bell-wrapper {
  position: relative;
  flex-shrink: 0;
}

.notif-bell {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background-color: var(--color-paper-raised);
  border: 1px solid var(--color-border);
  color: var(--color-ink-soft);
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  transition: all 0.2s ease;
}

.notif-bell:hover {
  color: var(--color-primary);
  border-color: var(--color-primary-light);
}

.notif-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  min-width: 18px;
  height: 18px;
  padding: 0 4px;
  border-radius: 999px;
  background-color: var(--color-danger);
  color: #fff;
  font-size: 0.68rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}

.notif-overlay {
  position: fixed;
  inset: 0;
  z-index: 45;
}

.notif-panel {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  width: 320px;
  max-width: calc(100vw - 40px);
  max-height: 420px;
  overflow-y: auto;
  background-color: var(--color-paper-raised);
  border: 1px solid var(--color-border);
  border-radius: 18px;
  box-shadow: 0 20px 45px rgba(15, 23, 42, 0.12);
  z-index: 50;
  padding: 8px;
}

.notif-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 12px;
  font-weight: 700;
  font-size: 0.92rem;
  color: var(--color-ink);
}

.notif-close {
  color: var(--color-ink-soft);
  display: flex;
}

.notif-empty {
  padding: 24px 12px;
  text-align: center;
  font-size: 0.85rem;
  color: var(--color-ink-soft);
}

.notif-item {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 30px 10px 12px;
  border-radius: 12px;
  width: 100%;
  text-align: left;
}

.notif-item:hover {
  background-color: var(--color-bg-soft);
}

.notif-item--read {
  opacity: 0.6;
}

.notif-item--read .notif-item-title {
  font-weight: 600;
}

.notif-unread-dot {
  position: absolute;
  top: 14px;
  right: 12px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: var(--color-primary);
  flex-shrink: 0;
}

.notif-item-icon {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.notif-item--alert .notif-item-icon {
  background-color: rgba(239, 68, 68, 0.1);
  color: var(--color-danger);
}

.notif-item--planning .notif-item-icon {
  background-color: var(--color-primary-light);
  color: var(--color-primary-dark);
}

.notif-item-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.notif-item-title {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--color-ink);
}

.notif-item-message {
  font-size: 0.8rem;
  color: var(--color-ink-soft);
  overflow-wrap: break-word;
}
</style>
