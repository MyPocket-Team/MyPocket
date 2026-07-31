import { ref, computed } from "vue";
import api from "../services/api";

const notifications = ref([]);
const panelOpen = ref(false);

async function refreshNotifications() {
  const token = localStorage.getItem("mypocket_token");
  if (!token) return;

  try {
    const response = await api.get("/notifications");
    const data = response.data.data || response.data;
    notifications.value = data.map((n) => ({
      id: n.id,
      title: n.titre,
      message: n.motif,
      icon: n.type === "solde_bas" ? "alert" : "planning",
      read: !!n.lue,
    }));
  } catch (e) {
    console.error("Error fetching notifications", e);
  }
}

function toggleNotifPanel() {
  panelOpen.value = !panelOpen.value;
  if (panelOpen.value) refreshNotifications();
}

function closeNotifPanel() {
  panelOpen.value = false;
}

async function markNotificationRead(id) {
  const notif = notifications.value.find((n) => n.id === id);
  if (!notif || notif.read) return;

  try {
    await api.post(`/notifications/${id}/lue`);
    notif.read = true;
  } catch (e) {
    console.error(e);
  }
}

const unreadCount = computed(() => notifications.value.filter((n) => !n.read).length);

export function useNotifications() {
  return {
    notifications,
    panelOpen,
    unreadCount,
    refreshNotifications,
    toggleNotifPanel,
    closeNotifPanel,
    markNotificationRead,
  };
}
