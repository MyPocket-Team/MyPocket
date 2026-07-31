<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "./services/api";
import DialogHost from "./components/DialogHost.vue";

const route = useRoute();
const router = useRouter();

const sidebarName = ref("Utilisateur");
const sidebarInitials = ref("U");
const sidebarPhoto = ref("");
const adminName = ref("Admin");

function formatPhotoUrl(path) {
  if (!path) return "";
  return path.startsWith("http") ? path : `http://localhost:8000/storage/${path}`;
}

function loadSidebarProfile() {
  const profileRaw = localStorage.getItem("mypocket_profile");
  if (profileRaw) {
    const profile = JSON.parse(profileRaw);
    const first = profile.prenom || "";
    const last = profile.nom || "";
    sidebarName.value = `${first} ${last}`.trim() || "Utilisateur";
    sidebarInitials.value = `${first.charAt(0)}${last.charAt(0)}`.toUpperCase() || "U";
    sidebarPhoto.value = formatPhotoUrl(profile.photo);

    if (profile.role === "super_admin") {
      adminName.value = `${first} ${last}`.trim() || "Admin";
    }
  } else {
    sidebarName.value = "Utilisateur";
    sidebarInitials.value = "U";
    sidebarPhoto.value = "";
    adminName.value = "Admin";
  }
}

let notifPollId = null;

onMounted(() => {
  loadSidebarProfile();
  refreshNotifications();
  notifPollId = setInterval(refreshNotifications, 60000);
});

onUnmounted(() => {
  if (notifPollId) clearInterval(notifPollId);
});

// Le profil (nom/photo) est une simple relecture du localStorage : pas de coût
// réseau, on peut se permettre de la rafraîchir à chaque navigation. Les
// notifications, elles, ne sont plus refetchées à chaque changement de route
// (ça ajoutait une requête réseau à chaque clic) : un polling périodique et un
// refresh à l'ouverture du panneau suffisent.
watch(() => route.path, () => {
  loadSidebarProfile();
});

// Define routes that do NOT show the navigation shell
const noShellRoutes = [
  "splash",
  "welcome",
  "login",
  "register",
  "forgot-password",
  "verify-code",
  "reset-password",
  "init-balance"
];

const isMemberRoute = computed(() => {
  return route.name && !noShellRoutes.includes(route.name);
});

const isAdminRoute = computed(() => {
  return typeof route.name === "string" && route.name.startsWith("admin-");
});

const isPlanningRoute = computed(() => route.name === "planning");

// ── Sous-menu "Mes plannings" affiché sous le lien Planning Budget (desktop) ──
const sidebarPlannings = ref([]);
const activeSidebarPlanningId = computed(() => {
  const id = route.query.planning ? parseInt(route.query.planning, 10) : null;
  return id || (sidebarPlannings.value[0] && sidebarPlannings.value[0].id) || null;
});

async function fetchSidebarPlannings() {
  try {
    const response = await api.get("/plannings");
    sidebarPlannings.value = response.data.data || response.data || [];
  } catch (e) {
    console.error("Error fetching plannings for sidebar", e);
  }
}

function selectSidebarPlanning(id) {
  router.push({ name: "planning", query: { planning: id } });
}

watch(isPlanningRoute, (isNow) => {
  if (isNow) fetchSidebarPlannings();
}, { immediate: true });

const isChatbotRoute = computed(() => route.name === "chatbot");

// ── Sous-menu "Discussions" affiché sous le lien Assistant (desktop) ──
const sidebarConversations = ref([]);
const activeSidebarConversationId = computed(() => {
  const id = route.query.conversation ? parseInt(route.query.conversation, 10) : null;
  return id || (sidebarConversations.value[0] && sidebarConversations.value[0].id) || null;
});

async function fetchSidebarConversations() {
  try {
    const response = await api.get("/chatbot/conversations");
    const remote = response.data.data || response.data;
    sidebarConversations.value = (remote || []).map((c) => ({ id: c.id, title: c.titre || "Nouvelle discussion" }));
  } catch (e) {
    console.error("Error fetching conversations for sidebar", e);
  }
}

function selectSidebarConversation(id) {
  router.push({ name: "chatbot", query: { conversation: id } });
}

watch(isChatbotRoute, (isNow) => {
  if (isNow) fetchSidebarConversations();
}, { immediate: true });

// Sidebar links (Desktop)
const sidebarLinks = [
  { name: "dashboard", label: "Tableau de bord", icon: "home" },
  { name: "history", label: "Historique", icon: "history" },
  { name: "add-transaction", label: "Nouvelle transaction", icon: "plus" },
  { name: "stats", label: "Statistiques", icon: "stats" },
  { name: "planning", label: "Planning Budget", icon: "planning" },
  { name: "chatbot", label: "Assistant", icon: "chat" },
  { name: "profile", label: "Mon Profil", icon: "profile" }
];

// Bottom bar links (Mobile)
const bottomLinks = [
  { name: "dashboard", label: "Accueil", icon: "home" },
  { name: "history", label: "Historique", icon: "history" },
  { name: "add-transaction", label: "Ajouter", icon: "plus", isFab: true },
  { name: "chatbot", label: "Assistant", icon: "chat" },
  { name: "profile", label: "Profil", icon: "profile" }
];

// Espace Super Admin : navigation dédiée (page distincte de l'espace membre)
const adminSidebarLinks = [
  { name: "admin-overview", label: "Vue d'ensemble", icon: "admin-overview" },
  { name: "admin-users", label: "Utilisateurs", icon: "admin-users" },
];

const adminBottomLinks = [
  { name: "admin-overview", label: "Vue d'ensemble", icon: "admin-overview" },
  { name: "admin-users", label: "Utilisateurs", icon: "admin-users" },
  { name: "admin-logout", label: "Déconnexion", icon: "logout", isLogout: true },
];

const activeSidebarLinks = computed(() => (isAdminRoute.value ? adminSidebarLinks : sidebarLinks));
const activeBottomLinks = computed(() => (isAdminRoute.value ? adminBottomLinks : bottomLinks));

function navigateTo(routeName) {
  router.push({ name: routeName });
}

async function handleLogout() {
  try {
    await api.post("/logout");
  } catch (e) {
    // Ignore error
  }
  localStorage.removeItem("mypocket_token");
  localStorage.removeItem("mypocket_profile");
  localStorage.removeItem("mypocket_solde");
  localStorage.removeItem("mypocket_initial_solde");
  router.push({ name: "welcome" });
}

function handleWordmarkClick() {
  navigateTo(isAdminRoute.value ? "admin-overview" : "dashboard");
}

function handleProfileClick() {
  if (isAdminRoute.value) {
    handleLogout();
  } else {
    navigateTo("profile");
  }
}

// ── Notifications : réelles depuis le backend ──
const notifPanelOpen = ref(false);
const notifications = ref([]);

async function refreshNotifications() {
  const token = localStorage.getItem("mypocket_token");
  if (!token || isAdminRoute.value) return;

  try {
    const response = await api.get("/notifications");
    const data = response.data.data || response.data;
    notifications.value = data.map((n) => ({
      id: n.id,
      title: n.titre,
      message: n.motif,
      icon: n.type === "solde_bas" ? "alert" : "planning",
      read: !!n.lue
    }));
  } catch (e) {
    console.error("Error fetching notifications", e);
  }
}

const unreadNotifCount = computed(() => notifications.value.filter((n) => !n.read).length);

function toggleNotifPanel() {
  notifPanelOpen.value = !notifPanelOpen.value;
  if (notifPanelOpen.value) refreshNotifications();
}

async function markNotificationRead(id) {
  try {
    await api.post(`/notifications/${id}/lue`);
    const notif = notifications.value.find((n) => n.id === id);
    if (notif) notif.read = true;
  } catch (e) {
    console.error(e);
  }
}
</script>

<template>
  <div id="app-shell">
    <!-- Non-member / onboarding screens -->
    <router-view v-if="!isMemberRoute" />

    <!-- Authenticated / member app workspace -->
    <div v-else class="member-layout">
      <!-- Desktop Sidebar -->
      <aside class="app-sidebar">
        <div class="sidebar-header">
          <!-- Logo -->
          <div class="sidebar-brand">
            <div class="sidebar-wordmark" @click="handleWordmarkClick">
              <img src="/logo.png" alt="MyPocket" class="logo-img" />
            </div>
            <span v-if="isAdminRoute" class="sidebar-admin-tag">Admin</span>
          </div>

          <!-- Navigation Links -->
          <div>
            <div v-if="isAdminRoute" class="sidebar-section-label">Pilotage</div>
            <nav class="sidebar-nav">
              <template v-for="link in activeSidebarLinks" :key="link.name">
              <button
                type="button"
                class="sidebar-nav-link"
                :class="{ 'sidebar-nav-link--active': route.name === link.name }"
                @click="navigateTo(link.name)"
              >
                <!-- Icons -->
                <svg v-if="link.icon === 'home'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                  <polyline points="9 22 9 12 15 12 15 22" />
                </svg>
                <svg v-else-if="link.icon === 'history'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="10" />
                  <polyline points="12 6 12 12 16 14" />
                </svg>
                <svg v-else-if="link.icon === 'plus'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="12" y1="5" x2="12" y2="19" />
                  <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
                <svg v-else-if="link.icon === 'stats'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="18" y1="20" x2="18" y2="10" />
                  <line x1="12" y1="20" x2="12" y2="4" />
                  <line x1="6" y1="20" x2="6" y2="14" />
                </svg>
                <svg v-else-if="link.icon === 'planning'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                  <line x1="16" y1="2" x2="16" y2="6" />
                  <line x1="8" y1="2" x2="8" y2="6" />
                  <line x1="3" y1="10" x2="21" y2="10" />
                </svg>
                <svg v-else-if="link.icon === 'chat'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                </svg>
                <svg v-else-if="link.icon === 'profile'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                  <circle cx="12" cy="7" r="4" />
                </svg>
                <svg v-else-if="link.icon === 'admin-overview'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                </svg>
                <svg v-else-if="link.icon === 'admin-users'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                  <circle cx="9" cy="7" r="4" />
                  <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                  <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                </svg>

                <span>{{ link.label }}</span>
              </button>

              <div v-if="link.name === 'planning' && isPlanningRoute" class="sidebar-submenu">
                <button
                  v-for="p in sidebarPlannings"
                  :key="p.id"
                  type="button"
                  class="sidebar-submenu-item"
                  :class="{ 'sidebar-submenu-item--active': p.id === activeSidebarPlanningId }"
                  @click="selectSidebarPlanning(p.id)"
                >
                  {{ p.titre }}
                </button>
                <p v-if="sidebarPlannings.length === 0" class="sidebar-submenu-empty">Aucun planning pour l'instant.</p>
              </div>

              <div v-if="link.name === 'chatbot' && isChatbotRoute" class="sidebar-submenu">
                <button
                  v-for="c in sidebarConversations"
                  :key="c.id"
                  type="button"
                  class="sidebar-submenu-item"
                  :class="{ 'sidebar-submenu-item--active': c.id === activeSidebarConversationId }"
                  @click="selectSidebarConversation(c.id)"
                >
                  {{ c.title }}
                </button>
                <p v-if="sidebarConversations.length === 0" class="sidebar-submenu-empty">Aucune discussion pour l'instant.</p>
              </div>
              </template>
            </nav>
          </div>
        </div>

        <!-- Profile badge / Logout -->
        <div class="sidebar-profile" @click="handleProfileClick">
          <div class="sidebar-avatar">
            <img v-if="sidebarPhoto" :src="sidebarPhoto" alt="Photo de profil" class="sidebar-avatar-img" />
            <template v-else>{{ isAdminRoute ? adminName.slice(0, 2).toUpperCase() : sidebarInitials }}</template>
          </div>
          <div class="sidebar-profile-info">
            <span class="sidebar-profile-name">{{ isAdminRoute ? adminName : sidebarName }}</span>
            <span class="sidebar-profile-sub">{{ isAdminRoute ? "Admin — se déconnecter" : "Mon compte" }}</span>
          </div>
        </div>
      </aside>

      <!-- Mobile/Tablet Bottom Navigation Bar -->
      <nav class="app-bottom-bar">
        <button
          v-for="link in activeBottomLinks"
          :key="link.name"
          type="button"
          class="nav-link"
          :class="{
            'nav-link--active': route.name === link.name,
            'nav-fab': link.isFab
          }"
          @click="link.isLogout ? handleLogout() : navigateTo(link.name)"
        >
          <div :class="{ 'nav-link-icon-container': !link.isFab }">
            <svg v-if="link.icon === 'home'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
              <polyline points="9 22 9 12 15 12 15 22" />
            </svg>
            <svg v-else-if="link.icon === 'history'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10" />
              <polyline points="12 6 12 12 16 14" />
            </svg>
            <svg v-else-if="link.icon === 'plus'" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <line x1="12" y1="5" x2="12" y2="19" />
              <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            <svg v-else-if="link.icon === 'chat'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
            </svg>
            <svg v-else-if="link.icon === 'profile'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
              <circle cx="12" cy="7" r="4" />
            </svg>
            <svg v-else-if="link.icon === 'admin-overview'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
            </svg>
            <svg v-else-if="link.icon === 'admin-users'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
              <path d="M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
            <svg v-else-if="link.icon === 'logout'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
              <polyline points="16 17 21 12 16 7" />
              <line x1="21" y1="12" x2="9" y2="12" />
            </svg>
          </div>
          <span v-if="!link.isFab">{{ link.label }}</span>
        </button>
      </nav>

      <!-- Main Workspace -->
      <main class="app-content">
        <template v-if="!isAdminRoute">
          <button type="button" class="notif-bell" @click="toggleNotifPanel" aria-label="Notifications">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
              <path d="M13.73 21a2 2 0 0 1-3.46 0" />
            </svg>
            <span v-if="unreadNotifCount" class="notif-badge">{{ unreadNotifCount }}</span>
          </button>

          <div v-if="notifPanelOpen" class="notif-overlay" @click="notifPanelOpen = false"></div>
          <div v-if="notifPanelOpen" class="notif-panel">
            <div class="notif-panel-header">
              <span>Notifications</span>
              <button type="button" class="notif-close" @click="notifPanelOpen = false" aria-label="Fermer">
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
        </template>

        <router-view />
      </main>
    </div>

    <DialogHost />
  </div>
</template>