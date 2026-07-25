<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import api from "../services/api";

const router = useRouter();
const email = ref("");
const error = ref("");
const loading = ref(false);

function goBack() {
  router.back();
}

async function handleSubmit() {
  error.value = "";
  loading.value = true;
  try {
    await api.post("/forgot-password", { email: email.value.trim() });
    router.push({ name: "verify-code", query: { email: email.value.trim() } });
  } catch (e) {
    error.value = e.response?.data?.message || "Impossible d'envoyer le code. Vérifie l'adresse email.";
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <main class="forgot-container">
    <div class="forgot-card glass-panel">
      <header class="topbar">
        <button type="button" class="back-btn" @click="goBack" aria-label="Retour">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M15 18l-6-6 6-6" />
          </svg>
        </button>
        <h1 class="topbar-title">Mot de passe oublié</h1>
      </header>

      <p class="instructions">
        Saisis ton adresse email. Nous t'enverrons un code de vérification pour réinitialiser ton mot de passe.
      </p>

      <form class="form" @submit.prevent="handleSubmit">
        <input v-model="email" type="email" placeholder="Adresse email" class="field" required />

        <p v-if="error" class="error">{{ error }}</p>

        <button type="submit" class="submit" :disabled="loading">
          {{ loading ? "Envoi..." : "Envoyer le code" }}
        </button>
      </form>
    </div>
  </main>
</template>

<style scoped>
.forgot-container {
  min-height: 100vh;
  display: flex;
  justify-content: center;
  align-items: center;
  background: radial-gradient(100% 100% at 50% 0%, rgba(209, 250, 229, 0.4) 0%, #f1f5f9 100%), var(--color-bg);
  padding: 24px;
  box-sizing: border-box;
}

.forgot-card {
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

.instructions {
  font-size: 0.9rem;
  line-height: 1.5;
  color: var(--color-ink-soft);
  text-align: center;
  margin-bottom: 24px;
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