<script setup>
import { ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "../services/api";
import PasswordField from "../components/PasswordField.vue";

const router = useRouter();
const route = useRoute();

const password = ref("");
const confirmPassword = ref("");
const error = ref("");
const loading = ref(false);

function goBack() {
  router.back();
}

async function handleSubmit() {
  error.value = "";

  if (password.value !== confirmPassword.value) {
    error.value = "Les mots de passe ne correspondent pas.";
    return;
  }
  if (password.value.length < 8) {
    error.value = "Le mot de passe doit contenir au moins 8 caractères.";
    return;
  }

  loading.value = true;
  try {
    await api.post("/reset-password", {
      email: route.query.email,
      code: route.query.code,
      password: password.value,
      password_confirmation: confirmPassword.value,
    });
    router.push({ name: "login" });
  } catch (e) {
    error.value = e.response?.data?.message || "Impossible de réinitialiser le mot de passe.";
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <main class="reset-container">
    <div class="reset-card glass-panel">
      <header class="topbar">
        <button type="button" class="back-btn" @click="goBack" aria-label="Retour">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M15 18l-6-6 6-6" />
          </svg>
        </button>
        <h1 class="topbar-title">Nouveau mot de passe</h1>
      </header>

      <form class="form" @submit.prevent="handleSubmit">
        <PasswordField v-model="password" placeholder="Nouveau mot de passe" minlength="8" required />
        <PasswordField v-model="confirmPassword" placeholder="Confirmer le mot de passe" minlength="8" required />

        <p v-if="error" class="error">{{ error }}</p>

        <button type="submit" class="submit" :disabled="loading">
          {{ loading ? "Confirmation..." : "Confirmer le mot de passe" }}
        </button>
      </form>
    </div>
  </main>
</template>

<style scoped>
.reset-container {
  position: relative;
  min-height: 100vh;
  min-height: 100dvh;
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 24px;
  box-sizing: border-box;
}

.reset-container::before {
  content: "";
  position: fixed;
  inset: 0;
  z-index: -1;
  background:
    linear-gradient(180deg, rgba(15, 23, 42, 0.55) 0%, rgba(15, 23, 42, 0.7) 100%),
    url("/fond.jpg") center center / cover no-repeat;
}

.reset-card {
  width: 100%;
  max-width: 440px;
  border-radius: 28px;
  padding: 40px 32px;
  box-sizing: border-box;
}

.topbar {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 24px;
}

.back-btn {
  background: var(--color-bg-soft);
  border-radius: 50%;
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-ink-soft);
}

.topbar-title {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--color-ink);
}

.form {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.field {
  width: 100%;
  background-color: var(--color-bg-soft);
  border: 1.5px solid transparent;
  border-radius: 14px;
  padding: 15px 18px;
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

.submit {
  width: 100%;
  background-color: var(--color-primary);
  color: #fff;
  font-weight: 700;
  font-size: 1rem;
  padding: 15px;
  border-radius: 14px;
  box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);
}

.submit:hover {
  background-color: var(--color-primary-dark);
}

.submit:disabled {
  opacity: 0.6;
}
</style>