<script setup>
import { ref, onMounted } from "vue";
import api from "../services/api";

const totalUsers = ref(0);
const totalTransactionsCount = ref(0);
const geminiRequests = ref(0);
const geminiLimit = ref(0);
const geminiPercentage = ref(0);
const lastUpdated = ref("à l'instant");
const loading = ref(true);
const error = ref("");

async function loadOverview() {
  loading.value = true;
  error.value = "";
  try {
    const res = await api.get("/admin/dashboard/overview");
    const data = res.data;
    totalUsers.value = data.utilisateurs?.total ?? 0;
    totalTransactionsCount.value = data.transactions?.total ?? 0;
    geminiRequests.value = data.gemini?.total_estime ?? 0;
    geminiLimit.value = data.gemini?.ce_mois?.quota ?? 0;
    geminiPercentage.value = data.gemini?.ce_mois?.pourcentage_utilise ?? 0;
    lastUpdated.value = "à l'instant";
  } catch (e) {
    error.value = e.response?.data?.message || "Impossible de charger les données d'administration.";
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  loadOverview();
});
</script>

<template>
  <main class="admin-overview-container">
    <!-- Header -->
    <header class="admin-header-panel">
      <div class="header-title-row">
        <h1 class="admin-title">Vue d'ensemble</h1>
        <div class="live-indicator">
          <span class="pulse-dot"></span>
          <span>mis à jour {{ lastUpdated }}</span>
        </div>
      </div>
      <p class="admin-subtitle">État global de la plateforme, en temps réel.</p>
    </header>

    <!-- Stats Grid -->
    <section class="stats-cards-grid">
      <!-- Card 1: Users -->
      <div class="stat-card glass-panel card-users">
        <div class="stat-content">
          <span class="stat-label">UTILISATEURS INSCRITS</span>
          <span class="stat-value">{{ totalUsers.toLocaleString('fr-FR') }}</span>
        </div>
        <div class="stat-icon-wrapper">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
          </svg>
        </div>
      </div>

      <!-- Card 2: Transactions -->
      <div class="stat-card glass-panel card-transactions">
        <div class="stat-content">
          <span class="stat-label">VOLUME DE TRANSACTIONS</span>
          <span class="stat-value">{{ totalTransactionsCount.toLocaleString('fr-FR') }} <span class="unit">trans.</span></span>
        </div>
        <div class="stat-icon-wrapper">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="5" width="20" height="14" rx="2" />
            <line x1="2" y1="10" x2="22" y2="10" />
          </svg>
        </div>
      </div>

      <!-- Card 3: Gemini Requests -->
      <div class="stat-card glass-panel card-gemini">
        <div class="stat-content">
          <span class="stat-label">REQUÊTES API GEMINI</span>
          <span class="stat-value gold-value">{{ geminiRequests.toLocaleString('fr-FR') }} <span class="limit">/ {{ geminiLimit.toLocaleString('fr-FR') }}</span></span>
          
          <!-- Progress bar -->
          <div class="progress-bar-container">
            <div class="progress-bar-fill" :style="{ width: `${(geminiRequests / geminiLimit) * 100}%` }"></div>
          </div>

          <span class="stat-subtext">{{ Math.round((geminiRequests / geminiLimit) * 100) }}% du quota mensuel utilisé</span>
        </div>
        <div class="stat-icon-wrapper">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
          </svg>
        </div>
      </div>
    </section>

    <!-- Disclaimer Info Banner -->
    <footer class="disclaimer-banner">
      <div class="disclaimer-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10" />
          <line x1="12" y1="16" x2="12" y2="12" />
          <line x1="12" y1="8" x2="12.01" y2="8" />
        </svg>
      </div>
      <p class="disclaimer-text">
        <strong>Confidentialité :</strong> cette vue n'expose aucun détail de transaction individuelle — uniquement des compteurs agrégés, conformément au périmètre défini pour l'Admin.
      </p>
    </footer>
  </main>
</template>

<style scoped>
.admin-overview-container {
  padding: 24px 20px 48px;
  max-width: 1200px;
  margin: 0 auto;
}

.admin-header-panel {
  margin-bottom: 32px;
}

.header-title-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.admin-title {
  font-size: 1.8rem;
  font-weight: 800;
  color: var(--color-ink);
  margin: 0;
}

.live-indicator {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--color-ink-soft);
}

.pulse-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: var(--color-primary);
  display: inline-block;
  position: relative;
}

.pulse-dot::after {
  content: "";
  position: absolute;
  inset: -4px;
  border-radius: 50%;
  border: 2px solid var(--color-primary);
  opacity: 0.4;
  animation: pulse-ring 1.8s infinite ease-out;
}

@keyframes pulse-ring {
  0% { transform: scale(0.6); opacity: 0.8; }
  100% { transform: scale(1.8); opacity: 0; }
}

.admin-subtitle {
  font-size: 0.95rem;
  color: var(--color-ink-soft);
  margin-top: 4px;
}

/* Stats Cards Grid */
.stats-cards-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
  margin-bottom: 36px;
}

@media (min-width: 768px) {
  .stats-cards-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

.stat-card {
  border-radius: 20px;
  padding: 24px;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  transition: all 0.25s ease;
  position: relative;
  overflow: hidden;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
}

.stat-card::before {
  content: "";
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
}

.card-users::before {
  background-color: var(--color-primary);
}

.card-transactions::before {
  background-color: var(--color-accent);
}

.card-gemini::before {
  background-color: #b45309; /* Bronze/gold left accent indicator */
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.04);
}

.stat-content {
  display: flex;
  flex-direction: column;
  flex: 1;
}

.stat-label {
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--color-ink-soft);
  letter-spacing: 0.05em;
  margin-bottom: 12px;
}

.stat-value {
  font-size: 1.8rem;
  font-weight: 800;
  color: var(--color-ink);
  line-height: 1.1;
  margin-bottom: 8px;
  display: flex;
  align-items: baseline;
  gap: 4px;
}

.stat-value .unit {
  font-size: 1rem;
  font-weight: 600;
  color: var(--color-ink-soft);
}

.stat-value .limit {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--color-ink-soft);
}

.gold-value {
  color: #b45309 !important;
}

.stat-subtext {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--color-ink-soft);
  margin-top: 4px;
}

.stat-icon-wrapper {
  background-color: var(--color-bg-soft);
  color: var(--color-ink-soft);
  width: 40px;
  height: 40px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-left: 12px;
}

/* Gemini quota progress bar */
.progress-bar-container {
  width: 100%;
  height: 6px;
  background-color: var(--color-bg-soft);
  border-radius: 99px;
  overflow: hidden;
  margin: 6px 0 10px 0;
}

.progress-bar-fill {
  height: 100%;
  background-color: #b45309;
  border-radius: 99px;
}

/* Disclaimer Banner */
.disclaimer-banner {
  display: flex;
  gap: 14px;
  background-color: rgba(16, 185, 129, 0.05);
  border: 1px solid rgba(16, 185, 129, 0.15);
  border-radius: 16px;
  padding: 16px 20px;
  align-items: flex-start;
}

.disclaimer-icon {
  color: var(--color-primary-dark);
  flex-shrink: 0;
  display: flex;
  margin-top: 2px;
}

.disclaimer-text {
  font-size: 0.85rem;
  line-height: 1.5;
  color: var(--color-ink-soft);
  margin: 0;
}

.disclaimer-text strong {
  color: var(--color-ink);
}
</style>
