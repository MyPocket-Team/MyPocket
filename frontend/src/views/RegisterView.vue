<script setup>
import { ref } from "vue";
import { RouterLink, useRouter } from "vue-router";
import api from "../services/api";

const router = useRouter();

const nom = ref("");
const prenom = ref("");
const email = ref("");
const password = ref("");
const confirmPassword = ref("");
const error = ref("");
const loading = ref(false);

async function handleSubmit() {
  error.value = "";

  if (password.value !== confirmPassword.value) {
    error.value = "Les mots de passe ne correspondent pas.";
    return;
  }

  loading.value = true;
  try {
    const response = await api.post("/register", {
      nom: nom.value,
      prenom: prenom.value,
      email: email.value,
      password: password.value,
      password_confirmation: confirmPassword.value,
    });

    const { token, user } = response.data;
    localStorage.setItem("mypocket_token", token);
    localStorage.setItem("mypocket_profile", JSON.stringify(user));
    localStorage.setItem("mypocket_solde", user.solde_actuel);
    localStorage.setItem("mypocket_initial_solde", user.solde_initial);
    router.push({ name: "init-balance" });
  } catch (e) {
    error.value = e.response?.data?.message || "Impossible de créer le compte. Vérifie les champs.";
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <main class="register-container">
    <div class="register-card glass-panel">
      <div class="logo-lockup" @click="router.push({ name: 'welcome' })">
        <div class="wordmark">
          <img src="/logo.png" alt="MyPocket" class="logo-img" />
        </div>
      </div>

      <h1 class="title">Création de compte</h1>

      <form class="form" @submit.prevent="handleSubmit">
        <div class="form-grid">
          <input v-model="prenom" type="text" placeholder="Prénom" class="field" required />
          <input v-model="nom" type="text" placeholder="Nom" class="field" required />
        </div>
        
        <input v-model="email" type="email" placeholder="Adresse email" class="field" required />

        <div class="form-grid">
          <input v-model="password" type="password" placeholder="Mot de passe" class="field" minlength="8" required />
          <input v-model="confirmPassword" type="password" placeholder="Confirmer" class="field" minlength="8" required />
        </div>

        <p v-if="error" class="error">{{ error }}</p>

        <button type="submit" class="submit" :disabled="loading">
          {{ loading ? "Création..." : "Créer mon compte" }}
        </button>
      </form>

      <p class="switch">
        Déjà membre ?
        <RouterLink to="/connexion" class="switch-link">Se connecter</RouterLink>
      </p>
    </div>
  </main>
</template>

<style scoped>
.register-container {
  min-height: 100vh;
  display: flex;
  justify-content: center;
  align-items: center;
  background: radial-gradient(100% 100% at 50% 0%, rgba(209, 250, 229, 0.4) 0%, #f1f5f9 100%), var(--color-bg);
  padding: 24px;
  box-sizing: border-box;
}

.register-card {
  width: 100%;
  max-width: 520px;
  border-radius: 28px;
  padding: 40px 32px;
  box-sizing: border-box;
}

.logo-lockup {
  display: flex;
  justify-content: center;
  cursor: pointer;
}

.wordmark {
  display: flex;
  align-items: center;
  gap: 6px;
}

.logo-img {
  height: 56px;
  width: auto;
}

.title {
  margin-top: 24px;
  font-size: 1.8rem;
  color: var(--color-ink);
  text-align: center;
}

.form {
  width: 100%;
  margin-top: 28px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
}

@media (min-width: 480px) {
  .form-grid {
    grid-template-columns: 1fr 1fr;
  }
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
  margin-top: 8px;
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

.switch {
  margin-top: 24px;
  font-size: 0.9rem;
  color: var(--color-ink-soft);
  text-align: center;
  font-weight: 500;
}

.switch-link {
  color: var(--color-primary-dark);
  font-weight: 700;
}

.switch-link:hover {
  text-decoration: underline;
}
</style>