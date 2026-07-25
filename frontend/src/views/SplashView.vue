<script setup>
import { onMounted } from "vue";
import { useRouter } from "vue-router";
import api from "../services/api";

const router = useRouter();

onMounted(() => {
  setTimeout(async () => {
    const token = localStorage.getItem("mypocket_token");
    if (!token) {
      router.push({ name: "welcome" });
      return;
    }

    try {
      const response = await api.get("/me");
      const user = response.data.data || response.data;
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
      localStorage.removeItem("mypocket_token");
      localStorage.removeItem("mypocket_profile");
      router.push({ name: "welcome" });
    }
  }, 2200); // 2.2 seconds splash screen
});
</script>

<template>
  <main class="splash-container">
    <div class="splash-content">
      <div class="logo-lockup">
        <div class="wordmark">
          <img src="/logo.png" alt="MyPocket" class="logo-img animate-pop" />
        </div>
        <p class="tagline">Soyez le boss de votre budget</p>
      </div>
      <div class="loader">
        <span class="dot"></span>
        <span class="dot"></span>
        <span class="dot"></span>
      </div>
    </div>
  </main>
</template>

<style scoped>
.splash-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: radial-gradient(100% 100% at 50% 0%, rgba(209, 250, 229, 0.45) 0%, #f1f5f9 100%), var(--color-bg);
  padding: 24px;
  box-sizing: border-box;
}

.splash-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 40px;
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
  height: 80px;
  width: auto;
}

.tagline {
  font-weight: 700;
  font-size: 1.15rem;
  color: var(--color-ink-soft);
  text-align: center;
  opacity: 0.8;
}

/* Loader styling */
.loader {
  display: flex;
  gap: 6px;
  align-items: center;
  margin-top: 10px;
}

.dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: var(--color-primary);
  animation: bounce 1.2s infinite ease-in-out;
}

.dot:nth-child(2) { animation-delay: 0.15s; }
.dot:nth-child(3) { animation-delay: 0.3s; }

@keyframes bounce {
  0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
  30% { transform: translateY(-6px); opacity: 1; }
}

/* Micro-animations */
.animate-pop {
  animation: pop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
}

@keyframes pop {
  0% { transform: scale(0.6); opacity: 0; }
  100% { transform: scale(1); opacity: 1; }
}
</style>