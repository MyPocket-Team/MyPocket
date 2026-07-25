<script setup>
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import api from "../services/api";

const router = useRouter();

function goBack() {
  router.back();
}

const nom = ref("");
const prenom = ref("");
const email = ref("");
const telephone = ref("");
const activite = ref("");
const photo = ref("");
const fileInput = ref(null);
let selectedFile = null;

const error = ref("");
const saving = ref(false);

const initiales = ref("");

const formatPhotoUrl = (path) => {
  if (!path) return "";
  return path.startsWith("http") || path.startsWith("blob:") ? path : `http://localhost:8000/storage/${path}`;
};

onMounted(() => {
  const profileRaw = localStorage.getItem("mypocket_profile");
  if (profileRaw) {
    const profile = JSON.parse(profileRaw);
    nom.value = profile.nom || "";
    prenom.value = profile.prenom || "";
    email.value = profile.email || "";
    telephone.value = profile.telephone || "";
    activite.value = profile.activite || "";
    photo.value = formatPhotoUrl(profile.photo);
  }
  initiales.value = `${(prenom.value || "U").charAt(0)}${(nom.value || "P").charAt(0)}`.toUpperCase();
});

function triggerFilePicker() {
  fileInput.value?.click();
}

function handlePhotoChange(event) {
  const file = event.target.files?.[0];
  if (!file) return;
  selectedFile = file;
  photo.value = URL.createObjectURL(file);
}

async function handleSubmit() {
  error.value = "";

  if (!nom.value || !prenom.value || !email.value) {
    error.value = "Nom, prénom et email sont obligatoires.";
    return;
  }

  saving.value = true;
  try {
    const formData = new FormData();
    formData.append("nom", nom.value);
    formData.append("prenom", prenom.value);
    formData.append("telephone", telephone.value || "");
    formData.append("activite", activite.value || "");
    if (selectedFile) {
      formData.append("photo", selectedFile);
    }

    const response = await api.post("/profil", formData, {
      headers: {
        "Content-Type": "multipart/form-data"
      }
    });

    const user = response.data.user;
    localStorage.setItem("mypocket_profile", JSON.stringify(user));
    router.push({ name: "profile" });
  } catch (e) {
    error.value = e.response?.data?.message || "Impossible d'enregistrer les modifications.";
  } finally {
    saving.value = false;
  }
}
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
      <h1 class="topbar-title">Modifier le profil</h1>
      <span class="spacer"></span>
    </header>

    <div class="profile-card-wrapper">
      <section class="avatar-block glass-panel">
        <div class="avatar">
          <img v-if="photo" :src="photo" alt="Photo de profil" />
          <span v-else class="avatar-initials">{{ initiales }}</span>
        </div>
        <input ref="fileInput" type="file" accept="image/*" class="hidden-input" @change="handlePhotoChange" />
        <button type="button" class="change-photo-btn" @click="triggerFilePicker">
          Changer la photo de profil
        </button>
      </section>

      <form class="form glass-panel" @submit.prevent="handleSubmit">
        <div class="input-group">
          <label class="input-label">Prénom</label>
          <input v-model="prenom" type="text" placeholder="Prénom" class="field" required />
        </div>
        
        <div class="input-group">
          <label class="input-label">Nom</label>
          <input v-model="nom" type="text" placeholder="Nom" class="field" required />
        </div>

        <div class="input-group">
          <label class="input-label">Adresse email</label>
          <input v-model="email" type="email" placeholder="Email" class="field" required />
        </div>

        <div class="input-group">
          <label class="input-label">Téléphone</label>
          <input v-model="telephone" type="tel" placeholder="Téléphone" class="field" />
        </div>

        <div class="input-group">
          <label class="input-label">Activité professionnelle</label>
          <input v-model="activite" type="text" placeholder="Activité professionnelle" class="field" />
        </div>

        <p v-if="error" class="error">{{ error }}</p>

        <div class="form-actions">
          <button type="button" class="cancel-btn" @click="goBack">Annuler</button>
          <button type="submit" class="submit-btn" :disabled="saving">
            {{ saving ? "Enregistrement..." : "Enregistrer" }}
          </button>
        </div>
      </form>
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

.spacer {
  width: 40px;
}

.profile-card-wrapper {
  max-width: 480px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.avatar-block {
  border-radius: 24px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.avatar {
  width: 88px;
  height: 88px;
  border-radius: 50%;
  background-color: var(--color-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  box-shadow: 0 8px 20px rgba(16, 185, 129, 0.15);
  border: 3px solid var(--color-paper-raised);
}

.avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-initials {
  font-weight: 800;
  font-size: 1.8rem;
  color: #fff;
}

.hidden-input {
  display: none;
}

.change-photo-btn {
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--color-primary-dark);
  text-decoration: underline;
}

.form {
  border-radius: 24px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.input-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.input-label {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--color-ink-soft);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding-left: 4px;
}

.field {
  width: 100%;
  background-color: var(--color-bg-soft);
  border: 1.5px solid transparent;
  border-radius: 12px;
  padding: 13px 16px;
  font-size: 0.95rem;
  color: var(--color-ink);
  outline: none;
  transition: all 0.2s ease;
}

.field:focus {
  border-color: var(--color-primary);
  background-color: var(--color-paper-raised);
}

.error {
  font-size: 0.85rem;
  color: var(--color-danger);
  text-align: center;
  font-weight: 600;
}

.form-actions {
  display: flex;
  gap: 12px;
  margin-top: 12px;
}

.cancel-btn {
  flex: 1;
  background-color: var(--color-bg-soft);
  color: var(--color-ink-soft);
  padding: 14px;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.92rem;
  text-align: center;
}

.submit-btn {
  flex: 1.5;
  background-color: var(--color-primary);
  color: #fff;
  padding: 14px;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.92rem;
  text-align: center;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
}

.submit-btn:hover {
  background-color: var(--color-primary-dark);
}

.submit-btn:disabled {
  opacity: 0.6;
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