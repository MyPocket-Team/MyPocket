<script setup>
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import api, { formatStorageUrl } from "../services/api";
import NotificationBell from "../components/NotificationBell.vue";

const router = useRouter();

function goBack() {
  router.back();
}

const profil = ref({
  photo: "",
  nom: "",
  prenom: "",
  email: "",
  telephone: "",
  activite: "",
});

const initiales = ref("");

const formatPhotoUrl = formatStorageUrl;

async function loadProfile() {
  const profileRaw = localStorage.getItem("mypocket_profile");
  if (profileRaw) {
    const profile = JSON.parse(profileRaw);
    profil.value = {
      photo: formatPhotoUrl(profile.photo),
      nom: profile.nom || "",
      prenom: profile.prenom || "",
      email: profile.email || "",
      telephone: profile.telephone || "",
      activite: profile.activite || "",
    };
    initiales.value = `${(profil.value.prenom || "U").charAt(0)}${(profil.value.nom || "P").charAt(0)}`.toUpperCase();
  }

  try {
    const response = await api.get("/profil");
    const user = response.data.data || response.data;
    profil.value = {
      photo: formatPhotoUrl(user.photo),
      nom: user.nom || "",
      prenom: user.prenom || "",
      email: user.email || "",
      telephone: user.telephone || "",
      activite: user.activite || "",
    };
    initiales.value = `${(profil.value.prenom || "U").charAt(0)}${(profil.value.nom || "P").charAt(0)}`.toUpperCase();
    localStorage.setItem("mypocket_profile", JSON.stringify(user));
  } catch (e) {
    console.error("Error loading profile", e);
  }
}

onMounted(() => {
  loadProfile();
});

function editProfile() {
  router.push({ name: "edit-profile" });
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

const infoRows = [
  { key: "email", label: "Email", icon: "mail" },
  { key: "telephone", label: "Téléphone", icon: "phone" },
  { key: "activite", label: "Activité professionnelle", icon: "briefcase" },
];
</script>

<template>
  <main class="profile-container">
    <!-- Topbar -->
    <header class="topbar">
      <button type="button" class="back-btn" @click="goBack" aria-label="Retour">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M15 18l-6-6 6-6" />
        </svg>
      </button>
      <h1 class="topbar-title">Profil</h1>
      <NotificationBell />
    </header>

    <!-- Main Card -->
    <div class="profile-card-wrapper">
      <section class="identity glass-panel">
        <div class="avatar">
          <img v-if="profil.photo" :src="profil.photo" alt="Photo de profil" />
          <span v-else class="avatar-initials">{{ initiales }}</span>
        </div>
        <h2 class="fullname">{{ profil.prenom }} {{ profil.nom }}</h2>
        <span class="status-badge">Membre MyPocket</span>
      </section>

      <!-- Details Info Block -->
      <section class="info-card glass-panel">
        <div v-for="row in infoRows" :key="row.key" class="info-row">
          <span class="info-icon">
            <svg v-if="row.icon === 'mail'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="5" width="18" height="14" rx="2.5" />
              <path d="M3 7l9 6 9-6" />
            </svg>
            <svg v-else-if="row.icon === 'phone'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M6.6 10.8a15.5 15.5 0 0 0 6.6 6.6l2.2-2.2a1.5 1.5 0 0 1 1.5-.4c1.1.4 2.3.6 3.6.6a1.5 1.5 0 0 1 1.5 1.5V21a1.5 1.5 0 0 1-1.5 1.5C10.6 22.5 1.5 13.4 1.5 2.5A1.5 1.5 0 0 1 3 1h3.5A1.5 1.5 0 0 1 8 2.5c0 1.3.2 2.5.6 3.6.1.5 0 1.1-.4 1.5L6.6 10.8z" />
            </svg>
            <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="7" width="18" height="13" rx="2.5" />
              <path d="M8 7V5.5A1.5 1.5 0 0 1 9.5 4h5A1.5 1.5 0 0 1 16 5.5V7M3 12h18" />
            </svg>
          </span>
          <div class="info-text">
            <span class="info-label">{{ row.label }}</span>
            <span class="info-value">{{ profil[row.key] || "Non renseigné" }}</span>
          </div>
        </div>
      </section>

      <!-- Actions Buttons -->
      <div class="actions">
        <button type="button" class="edit-btn" @click="editProfile">Modifier mon profil</button>
        <button type="button" class="logout-btn" @click="handleLogout">Se déconnecter</button>
      </div>
    </div>
  </main>
</template>

<style scoped>
.profile-container {
  padding: 24px 20px 48px;
  max-width: 1200px;
  margin: 0 auto;
}

/* Header */
.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
}

.back-btn {
  background: var(--color-paper-raised);
  border: 1px solid var(--color-border);
  color: var(--color-ink-soft);
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 50%;
}

.topbar-title {
  font-size: 1.4rem;
  font-weight: 700;
  color: var(--color-ink);
}


/* Card centering layout */
.profile-card-wrapper {
  max-width: 480px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* Identity Card */
.identity {
  border-radius: 24px;
  padding: 32px 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.avatar {
  width: 96px;
  height: 96px;
  border-radius: 50%;
  background-color: var(--color-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  box-shadow: 0 10px 24px rgba(16, 185, 129, 0.2);
  border: 4px solid var(--color-paper-raised);
}

.avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-initials {
  font-weight: 800;
  font-size: 2rem;
  color: #fff;
}

.fullname {
  font-size: 1.4rem;
  font-weight: 700;
  color: var(--color-ink);
}

.status-badge {
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--color-primary-dark);
  background-color: var(--color-primary-light);
  padding: 4px 10px;
  border-radius: 99px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

/* Info Card Details */
.info-card {
  border-radius: 24px;
  padding: 8px 24px;
}

.info-row {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px 0;
}

.info-row + .info-row {
  border-top: 1px solid var(--color-border);
}

.info-icon {
  width: 38px;
  height: 38px;
  border-radius: 12px;
  background-color: var(--color-bg-soft);
  color: var(--color-ink-soft);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.info-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.info-label {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: var(--color-ink-soft);
}

.info-value {
  font-size: 0.92rem;
  font-weight: 600;
  color: var(--color-ink);
}

/* Actions Buttons */
.actions {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 10px;
}

.edit-btn {
  width: 100%;
  background-color: var(--color-primary);
  color: #fff;
  font-weight: 700;
  font-size: 0.98rem;
  padding: 15px;
  border-radius: 14px;
  box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);
}

.edit-btn:hover {
  background-color: var(--color-primary-dark);
}

.logout-btn {
  width: 100%;
  background-color: var(--color-bg-soft);
  border: 1px solid var(--color-border);
  color: var(--color-danger);
  font-weight: 700;
  font-size: 0.98rem;
  padding: 15px;
  border-radius: 14px;
  transition: all 0.2s ease;
}

.logout-btn:hover {
  background-color: rgba(239, 68, 68, 0.08);
  border-color: rgba(239, 68, 68, 0.2);
}

@media (min-width: 768px) {
  .profile-container {
    padding: 40px;
  }

  .topbar-title {
    font-size: 1.8rem;
  }
}
</style>