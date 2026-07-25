<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import api from "../services/api";

const router = useRouter();
const solde = ref("");
const error = ref("");
const loading = ref(false);

async function handleSubmit() {
  error.value = "";

  const montant = parseFloat(solde.value);
  if (isNaN(montant) || montant <= 0) {
    error.value = "Le solde doit être supérieur à 0."; 
    return;
  }

  loading.value = true;
  try {
    const response = await api.post("/profil", {
      solde_initial: montant
    });
    const user = response.data.user;
    localStorage.setItem("mypocket_profile", JSON.stringify(user));
    localStorage.setItem("mypocket_solde", user.solde_actuel);
    localStorage.setItem("mypocket_initial_solde", user.solde_initial);
    router.push({ name: "dashboard" });
  } catch (e) {
    error.value = e.response?.data?.message || "Impossible d'enregistrer ton solde. Réessaie.";
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <main class="page">
    <!-- Visual background blobs -->
    <div class="backdrop">
      <div class="blob blob-1"></div>
      <div class="blob blob-2"></div>
      <div class="blob blob-3"></div>
    </div>

    <!-- Onboarding Card -->
    <div class="card glass-panel">
      <div class="wordmark">
        <img src="/logo.png" alt="MyPocket" class="logo-img" />
      </div>

      <h1 class="welcome">Bienvenue sur MyPocket !</h1>
      <p class="instructions">
        Pour démarrer, indique le solde actuel de ton compte — c'est ce point de départ
        qui nous permettra de suivre tes finances avec toi.
      </p>

      <form class="form" @submit.prevent="handleSubmit">
        <div class="amount-field">
          <input
            v-model="solde"
            type="number"
            step="1"
            min="0"
            placeholder="0"
            class="amount-input"
            required
          />
          <span class="currency">FCFA</span>
        </div>

        <p v-if="error" class="error">{{ error }}</p>

        <button type="submit" class="submit" :disabled="loading">
          {{ loading ? "Enregistrement..." : "Continuer" }}
        </button>
      </form>
    </div>
  </main>
</template>

<style scoped>
.page {
  position: relative;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: var(--color-bg);
  overflow: hidden;
  padding: 24px;
  box-sizing: border-box;
}

.backdrop {
  position: absolute;
  inset: 0;
  z-index: 0;
  pointer-events: none;
}

.blob {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.3;
}

.blob-1 {
  width: 340px;
  height: 340px;
  background: var(--color-primary);
  top: -80px;
  left: -100px;
}

.blob-2 {
  width: 280px;
  height: 280px;
  background: var(--color-accent);
  bottom: -60px;
  right: -80px;
}

.blob-3 {
  width: 200px;
  height: 200px;
  background: var(--color-primary-dark);
  bottom: 20%;
  left: 8%;
  opacity: 0.15;
}

.card {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 440px;
  border-radius: 28px;
  padding: 44px 32px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  box-sizing: border-box;
}

.wordmark {
  display: flex;
  align-items: center;
  gap: 6px;
}

.logo-img {
  height: 52px;
  width: auto;
}

.welcome {
  margin-top: 24px;
  font-size: 1.8rem;
  color: var(--color-ink);
}

.instructions {
  margin-top: 12px;
  font-size: 0.95rem;
  line-height: 1.55;
  color: var(--color-ink-soft);
}

.form {
  width: 100%;
  margin-top: 32px;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.amount-field {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  border: 1.5px solid var(--color-border);
  border-radius: 16px;
  padding: 14px 20px;
  background-color: var(--color-bg-soft);
  transition: all 0.2s ease;
}

.amount-field:focus-within {
  border-color: var(--color-primary);
  background-color: var(--color-paper-raised);
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.1);
}

.amount-input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  text-align: right;
  font-weight: 800;
  font-size: 1.5rem;
  color: var(--color-ink);
  width: 100%;
}

.amount-input::-webkit-outer-spin-button,
.amount-input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

.currency {
  font-weight: 800;
  font-size: 1rem;
  color: var(--color-primary-dark);
}

.error {
  font-size: 0.85rem;
  color: var(--color-danger);
  font-weight: 600;
}

.submit {
  width: 100%;
  background-color: var(--color-primary);
  color: #fff;
  font-weight: 700;
  font-size: 1.05rem;
  padding: 16px;
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