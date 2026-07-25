<script setup>
import { ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "../services/api";

const router = useRouter();
const route = useRoute();

const digits = ref(["", "", "", "", "", ""]);
const inputRefs = ref([]);
const error = ref("");
const loading = ref(false);

function setInputRef(el, index) {
  if (el) inputRefs.value[index] = el;
}

function handleInput(index, event) {
  const value = event.target.value.replace(/\D/g, "").slice(-1);
  digits.value[index] = value;

  if (value && index < 5) {
    inputRefs.value[index + 1]?.focus();
  }
}

function handleKeydown(index, event) {
  if (event.key === "Backspace" && !digits.value[index] && index > 0) {
    inputRefs.value[index - 1]?.focus();
  }
}

function handlePaste(event) {
  const pasted = event.clipboardData.getData("text").replace(/\D/g, "").slice(0, 6);
  if (!pasted) return;
  event.preventDefault();
  pasted.split("").forEach((char, i) => {
    digits.value[i] = char;
  });
  inputRefs.value[Math.min(pasted.length, 5)]?.focus();
}

function goBack() {
  router.back();
}

async function handleSubmit() {
  error.value = "";
  const code = digits.value.join("");
  if (code.length < 6) {
    error.value = "Entre les 6 chiffres du code.";
    return;
  }

  loading.value = true;
  try {
    await api.post("/forgot-password/verify-code", {
      email: route.query.email,
      code,
    });
    router.push({ name: "reset-password", query: { email: route.query.email, code } });
  } catch (e) {
    error.value = e.response?.data?.message || "Code de vérification incorrect.";
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <main class="verify-container">
    <div class="verify-card glass-panel">
      <header class="topbar">
        <button type="button" class="back-btn" @click="goBack" aria-label="Retour">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M15 18l-6-6 6-6" />
          </svg>
        </button>
        <h1 class="topbar-title">Code de vérification</h1>
      </header>

      <p class="instructions">Saisis le code à 6 chiffres qui t'a été envoyé par e-mail.</p>

      <form class="form" @submit.prevent="handleSubmit">
        <div class="code-row" @paste="handlePaste">
          <input
            v-for="(digit, index) in digits"
            :key="index"
            :ref="(el) => setInputRef(el, index)"
            v-model="digits[index]"
            type="text"
            inputmode="numeric"
            maxlength="1"
            class="code-input"
            @input="handleInput(index, $event)"
            @keydown="handleKeydown(index, $event)"
          />
        </div>

        <p v-if="error" class="error">{{ error }}</p>

        <button type="submit" class="submit" :disabled="loading">
          {{ loading ? "Vérification..." : "Vérifier le code" }}
        </button>
      </form>
    </div>
  </main>
</template>

<style scoped>
.verify-container {
  min-height: 100vh;
  display: flex;
  justify-content: center;
  align-items: center;
  background: radial-gradient(100% 100% at 50% 0%, rgba(209, 250, 229, 0.4) 0%, #f1f5f9 100%), var(--color-bg);
  padding: 24px;
  box-sizing: border-box;
}

.verify-card {
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
  gap: 24px;
}

.code-row {
  display: flex;
  justify-content: space-between;
  gap: 8px;
}

.code-input {
  width: 46px;
  height: 56px;
  text-align: center;
  background-color: var(--color-bg-soft);
  border: 1.5px solid transparent;
  border-radius: 12px;
  font-weight: 800;
  font-size: 1.4rem;
  color: var(--color-ink);
  outline: none;
  transition: all 0.2s ease;
}

.code-input:focus {
  border-color: var(--color-primary);
  background-color: var(--color-paper-raised);
  box-shadow: 0 4px 10px rgba(16, 185, 129, 0.1);
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