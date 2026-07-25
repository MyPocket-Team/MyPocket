<script setup>
import { useRouter } from "vue-router";

const router = useRouter();

function goToRegister() {
  router.push({ name: "register" });
}

function goToLogin() {
  router.push({ name: "login" });
}
</script>

<template>
  <main class="welcome-container">
    <div class="welcome-layout glass-panel">
      <div class="content">
        <div class="logo-lockup">
          <div class="wordmark">
            <img src="/logo.png" alt="MyPocket" class="logo-img" />
          </div>
          <p class="tagline">Soyez le boss<br />de votre budget</p>
        </div>
      </div>

      <div class="actions">
        <button type="button" class="btn-primary" @click="goToRegister">
          Commencer
        </button>
        <button type="button" class="btn-text" @click="goToLogin">
          J'ai déjà un compte
        </button>
      </div>
    </div>
  </main>
</template>

<style scoped>
.welcome-container {
  min-height: 100vh;
  min-height: 100dvh;
  display: flex;
  justify-content: center;
  align-items: center;
  background: radial-gradient(100% 100% at 50% 0%, rgba(209, 250, 229, 0.45) 0%, #f1f5f9 100%), var(--color-bg);
  padding: 24px;
  box-sizing: border-box;
}

.welcome-layout {
  display: flex;
  flex-direction: column;
  width: 100%;
  max-width: 440px;
  gap: 48px;
  padding: 40px 32px;
  border-radius: 28px;
  box-sizing: border-box;
}

.content {
  display: flex;
  align-items: center;
  justify-content: center;
}

.logo-lockup {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 20px;
}

.wordmark {
  display: flex;
  align-items: center;
  gap: 6px;
}

.logo-img {
  height: 70px;
  width: auto;
}

.tagline {
  font-weight: 700;
  font-size: 1.4rem;
  color: var(--color-ink-soft);
  text-align: center;
  line-height: 1.35;
}

.actions {
  display: flex;
  flex-direction: column;
  width: 100%;
  gap: 14px;
}

.btn-primary, .btn-text {
  width: 100%;
  font-weight: 700;
  font-size: 0.95rem;
  padding: 16px;
  border-radius: 14px;
  text-align: center;
}

.btn-primary {
  background-color: var(--color-primary);
  color: var(--color-white);
  box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);
}

.btn-primary:hover {
  background-color: var(--color-primary-dark);
}

.btn-text {
  background-color: var(--color-bg-soft);
  border: 1px solid var(--color-border);
  color: var(--color-ink);
}

.btn-text:hover {
  background-color: var(--color-border);
}

@media (min-width: 768px) {
  .logo-lockup {
    gap: 28px;
  }

  .badge {
    font-size: 2.2rem;
    padding: 12px 20px;
  }

  .pocket {
    font-size: 2.85rem;
  }

  .tagline {
    font-size: 1.55rem;
  }
}
</style>