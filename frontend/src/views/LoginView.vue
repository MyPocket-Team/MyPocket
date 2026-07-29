<script setup>
import { ref } from "vue";
import { RouterLink, useRouter } from "vue-router";
import api from "../services/api";
import PasswordField from "../components/PasswordField.vue";

const router = useRouter();

const email = ref("");
const password = ref("");
const error = ref("");
const loading = ref(false);

async function handleSubmit() {
  error.value = "";
  loading.value = true;
  try {
    const response = await api.post("/login", {
      email: email.value.trim(),
      password: password.value,
    });

    const { token, user } = response.data;
    localStorage.setItem("mypocket_token", token);
    localStorage.setItem("mypocket_profile", JSON.stringify(user));
    localStorage.setItem("mypocket_solde", user.solde_actuel);
    localStorage.setItem("mypocket_initial_solde", user.solde_initial);

    if (user.role === "super_admin") {
      router.push({ name: "admin-overview" });
    } else if (parseFloat(user.solde_initial) <= 0) {
      router.push({ name: "init-balance" });
    } else {
      router.push({ name: "dashboard" });
    }
  } catch (e) {
    error.value = e.response?.data?.message || "Email ou mot de passe incorrect.";
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <main class="login-container">
    <div class="login-card glass-panel">
      <div class="logo-lockup">
        <div class="wordmark" @click="router.push({ name: 'welcome' })">
          <img src="/logo.png" alt="MyPocket" class="logo-img" />
        </div>
      </div>

      <h1 class="title">Connexion</h1>

      <form class="form" @submit.prevent="handleSubmit">
        <div class="input-group">
          <input v-model="email" type="email" placeholder="Adresse email" class="field" required />
        </div>
        
        <div class="input-group">
          <PasswordField v-model="password" placeholder="Mot de passe" required />
        </div>

        <RouterLink to="/mot-de-passe-oublie" class="forgot-link">
          Mot de passe oublié ?
        </RouterLink>

        <p v-if="error" class="error">{{ error }}</p>

        <button type="submit" class="submit" :disabled="loading">
          {{ loading ? "Connexion..." : "Se connecter" }}
        </button>
      </form>

      <p class="switch">
        Nouveau sur MyPocket ?
        <RouterLink to="/inscription" class="switch-link">Créer un compte</RouterLink>
      </p>
    </div>
  </main>
</template>

<style scoped>
.login-container {
  position: relative;
  min-height: 100vh;
  min-height: 100dvh;
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 24px;
  box-sizing: border-box;
}

.login-container::before {
  content: "";
  position: fixed;
  inset: 0;
  z-index: -1;
  background:
    linear-gradient(180deg, rgba(15, 23, 42, 0.55) 0%, rgba(15, 23, 42, 0.7) 100%),
    url("/fond.jpg") center center / cover no-repeat;
}

.login-card {
  width: 100%;
  max-width: 440px;
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

.forgot-link {
  align-self: flex-end;
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--color-primary-dark);
}

.forgot-link:hover {
  text-decoration: underline;
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