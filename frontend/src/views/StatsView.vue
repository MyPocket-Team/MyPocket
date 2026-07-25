<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import api from "../services/api";

const router = useRouter();

function goBack() {
  router.back();
}

const overview = ref(null);
const evolution = ref([]);
const repartition = ref([]);
const loading = ref(true);
const error = ref("");

const categoryColors = {
  Alimentation: "#10b981",
  Transport: "#6366f1",
  Logement: "#f59e0b",
  Divertissement: "#ec4899",
  Santé: "#ef4444",
  Autre: "#94a3b8",
};

const fetchStats = async () => {
  loading.value = true;
  error.value = "";

  try {
    const overviewRes = await api.get("/dashboard/overview");
    overview.value = overviewRes.data;
    const evolutionRes = await api.get("/dashboard/evolution-solde");
    evolution.value = evolutionRes.data.evolution || [];
    const repartitionRes = await api.get("/dashboard/repartition-categories");
    repartition.value = repartitionRes.data.repartition || [];
  } catch (e) {
    error.value = e.response?.data?.message || "Impossible de charger les statistiques.";
  } finally {
    loading.value = false;
  }
};

const soldeActuel = computed(() => overview.value?.solde_actuel ?? 0);

const evolutionPoints = computed(() => {
  return evolution.value.map((t) => ({
    ...t,
    label: new Date(t.date).toLocaleDateString("fr-FR", {
      day: "numeric",
      month: "short",
    }),
  }));
});

const categoriesExpenses = computed(() => {
  const total = repartition.value.reduce((sum, item) => sum + item.total, 0);
  if (total === 0) return [];

  return repartition.value
    .map((item) => ({
      name: item.nom || "Autre",
      amount: item.total,
      color: categoryColors[item.nom] || categoryColors.Autre,
      percentage: Math.round((item.total / total) * 100),
    }))
    .sort((a, b) => b.amount - a.amount);
});

const chartWidth = 500;
const chartHeight = 220;
const padding = 15;
const gutterLeft = 56;

const minSolde = computed(() => {
  const vals = evolutionPoints.value.map((e) => e.solde);
  return vals.length > 0 ? Math.min(0, ...vals) : 0;
});
const maxSolde = computed(() => {
  const vals = evolutionPoints.value.map((e) => e.solde);
  return vals.length > 0 ? Math.max(0, ...vals) : 0;
});

const points = computed(() => {
  const min = minSolde.value;
  const max = maxSolde.value;
  const range = max - min || 1;
  const plotWidth = chartWidth - gutterLeft - padding;
  const stepX = evolutionPoints.value.length > 1 ? plotWidth / (evolutionPoints.value.length - 1) : 0;

  return evolutionPoints.value.map((p, i) => {
    const x = gutterLeft + i * stepX;
    const y = padding + (1 - (p.solde - min) / range) * (chartHeight - padding * 2);
    return { x, y, ...p };
  });
});

const yTicks = computed(() => {
  const min = minSolde.value;
  const max = maxSolde.value;
  const range = max - min || 1;
  const steps = 3;
  return Array.from({ length: steps + 1 }, (_, i) => {
    const value = min + (range * i) / steps;
    const y = padding + (1 - i / steps) * (chartHeight - padding * 2);
    return { value: Math.round(value), y };
  });
});

const linePath = computed(() => {
  return points.value.map((p, i) => `${i === 0 ? "M" : "L"}${p.x},${p.y}`).join(" ");
});

const labelsRowStyle = computed(() => {
  const leftPct = (gutterLeft / chartWidth) * 100;
  const rightPct = ((chartWidth - padding) / chartWidth) * 100;
  return { marginLeft: `${leftPct}%`, width: `${rightPct - leftPct}%` };
});

const areaPath = computed(() => {
  if (points.value.length === 0) return "";
  const first = points.value[0];
  const last = points.value[points.value.length - 1];
  return `${linePath.value} L${last.x},${chartHeight - padding} L${first.x},${chartHeight - padding} Z`;
});

const trendPositive = computed(() => {
  if (evolutionPoints.value.length < 2) return true;
  return evolutionPoints.value[evolutionPoints.value.length - 1].solde >= evolutionPoints.value[0].solde;
});

onMounted(() => {
  fetchStats();
});
</script>

<template>
  <main class="stats-container">
    <!-- Topbar -->
    <header class="topbar">
      <button type="button" class="back-btn" @click="goBack" aria-label="Retour">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M15 18l-6-6 6-6" />
        </svg>
      </button>
      <h1 class="topbar-title">Statistiques</h1>
      <span class="spacer"></span>
    </header>

    <div v-if="loading" class="empty-stats-card glass-panel">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-ink-soft); margin-bottom: 16px;">
        <circle cx="12" cy="12" r="8" />
        <path d="M12 6v6l4 2" />
      </svg>
      <h2 class="empty-title">Chargement des statistiques...</h2>
    </div>
    <div v-else-if="error" class="empty-stats-card glass-panel">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-danger); margin-bottom: 16px;">
        <circle cx="12" cy="12" r="10" />
        <path d="M12 8v4" />
        <path d="M12 16h.01" />
      </svg>
      <h2 class="empty-title">Erreur</h2>
      <p class="empty-desc">{{ error }}</p>
      <button type="button" class="empty-cta" @click="fetchStats()">
        Réessayer
      </button>
    </div>
    <div v-else-if="evolution.length === 0" class="empty-stats-card glass-panel">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-ink-soft); margin-bottom: 16px;">
        <line x1="18" y1="20" x2="18" y2="10" />
        <line x1="12" y1="20" x2="12" y2="4" />
        <line x1="6" y1="20" x2="6" y2="14" />
      </svg>
      <h2 class="empty-title">Aucune statistique disponible</h2>
      <p class="empty-desc">Saisissez des transactions pour commencer à analyser vos finances.</p>
      <button type="button" class="empty-cta" @click="router.push({ name: 'add-transaction' })">
        Ajouter une transaction
      </button>
    </div>
    <div v-else class="stats-grid">
      <!-- Left side: Chart Card -->
      <div class="chart-section">
        <!-- Balance Display -->
        <div class="balance-card">
          <div class="balance-card-bg"></div>
          <span class="balance-label">Solde actuel</span>
          <span class="balance-amount">{{ soldeActuel.toLocaleString('fr-FR') }} <span class="balance-currency">FCFA</span></span>
        </div>

        <!-- Line Chart Card -->
        <div class="chart-card glass-panel">
          <div class="chart-header">
            <span class="chart-title">Évolution du solde</span>
            <span class="chart-trend" :class="trendPositive ? 'trend-positive' : 'trend-negative'">
              <svg v-if="trendPositive" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 16l6-6 4 4 6-8" />
              </svg>
              <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 8l6 6 4-4 6 8" />
              </svg>
              30 jours
            </span>
          </div>

          <p class="chart-caption">Historique en FCFA</p>

          <div class="svg-container">
            <svg :viewBox="`0 0 ${chartWidth} ${chartHeight}`" class="chart-svg" preserveAspectRatio="none">
              <defs>
                <linearGradient id="areaFill" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="var(--color-primary)" stop-opacity="0.2" />
                  <stop offset="100%" stop-color="var(--color-primary)" stop-opacity="0" />
                </linearGradient>
              </defs>

              <!-- Gridlines & Y-Axis Labels -->
              <g v-for="(tick, i) in yTicks" :key="i">
                <line
                  :x1="gutterLeft"
                  :y1="tick.y"
                  :x2="chartWidth - padding"
                  :y2="tick.y"
                  stroke="var(--color-border)"
                  stroke-width="1"
                  :stroke-dasharray="i === 0 ? '0' : '3 3'"
                />
                <text :x="gutterLeft - 8" :y="tick.y" text-anchor="end" dominant-baseline="middle" class="axis-value">
                  {{ tick.value.toLocaleString('fr-FR') }}
                </text>
              </g>

              <!-- Line Chart Paths -->
              <path :d="areaPath" fill="url(#areaFill)" />
              <path :d="linePath" fill="none" stroke="var(--color-primary)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
              
              <!-- Plot Dots -->
              <circle
                v-for="(p, i) in points"
                :key="i"
                :cx="p.x"
                :cy="p.y"
                r="4.5"
                fill="var(--color-paper-raised)"
                stroke="var(--color-primary)"
                stroke-width="2.5"
              />
            </svg>
          </div>

          <div class="chart-labels" :style="labelsRowStyle">
            <span v-for="(e, i) in evolution" :key="i" class="chart-label">{{ e.label }}</span>
          </div>
        </div>
      </div>

      <!-- Right side: Category Breakdown -->
      <div class="breakdown-section">
        <section class="breakdown-card glass-panel">
          <h2 class="section-title">Répartition des dépenses</h2>
          <p class="section-subtitle">Ce mois-ci par catégorie</p>

          <div class="category-list">
            <div v-for="cat in categoriesExpenses" :key="cat.name" class="category-item">
              <div class="category-info">
                <span class="category-name-wrapper">
                  <span class="category-dot" :style="{ backgroundColor: cat.color }"></span>
                  <span class="category-name">{{ cat.name }}</span>
                </span>
                <span class="category-amounts">
                  <strong>{{ cat.amount.toLocaleString('fr-FR') }} F</strong>
                  <span class="category-pct">{{ cat.percentage }}%</span>
                </span>
              </div>
              <div class="progress-bar-bg">
                <div class="progress-bar-fill" :style="{ width: `${cat.percentage}%`, backgroundColor: cat.color }"></div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </main>
</template>

<style scoped>
.stats-container {
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

/* Grid layout */
.stats-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
}

/* Balance display card */
.balance-card {
  position: relative;
  background: linear-gradient(135deg, #10b981, #059669);
  border-radius: 24px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  color: #fff;
  box-shadow: 0 10px 30px rgba(16, 185, 129, 0.2);
  margin-bottom: 24px;
  overflow: hidden;
}

.balance-card-bg {
  position: absolute;
  top: -40px;
  right: -40px;
  width: 120px;
  height: 120px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.08);
}

.balance-label {
  font-size: 0.85rem;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.85);
  text-transform: uppercase;
}

.balance-amount {
  font-size: 1.8rem;
  font-weight: 800;
}

.balance-currency {
  font-size: 1.1rem;
  font-weight: 600;
}

/* Chart card styling */
.chart-card {
  border-radius: 24px;
  padding: 24px;
}

.chart-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.chart-title {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--color-ink);
}

.chart-trend {
  display: flex;
  align-items: center;
  gap: 4px;
  font-weight: 700;
  font-size: 0.8rem;
  padding: 4px 10px;
  border-radius: 99px;
}

.trend-positive {
  color: var(--color-primary-dark);
  background-color: rgba(16, 185, 129, 0.1);
}

.trend-negative {
  color: var(--color-danger);
  background-color: rgba(239, 68, 68, 0.1);
}

.chart-caption {
  font-size: 0.8rem;
  color: var(--color-ink-soft);
  margin-top: 4px;
}

.svg-container {
  position: relative;
  width: 100%;
  height: 220px;
  margin-top: 16px;
}

.chart-svg {
  width: 100%;
  height: 100%;
}

.axis-value {
  font-size: 9px;
  font-weight: 600;
  fill: var(--color-ink-soft);
}

.chart-labels {
  display: flex;
  justify-content: space-between;
  margin-top: 12px;
}

.chart-label {
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--color-ink-soft);
}

/* Category breakdown styling */
.breakdown-card {
  border-radius: 24px;
  padding: 24px;
}

.section-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--color-ink);
}

.section-subtitle {
  font-size: 0.82rem;
  color: var(--color-ink-soft);
  margin-bottom: 24px;
}

.category-list {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.category-item {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.category-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.category-name-wrapper {
  display: flex;
  align-items: center;
  gap: 8px;
}

.category-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

.category-name {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--color-ink);
}

.category-amounts {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.9rem;
}

.category-pct {
  font-size: 0.78rem;
  color: var(--color-ink-soft);
  background: var(--color-bg-soft);
  padding: 2px 6px;
  border-radius: 6px;
}

.progress-bar-bg {
  width: 100%;
  height: 8px;
  background-color: var(--color-bg-soft);
  border-radius: 99px;
  overflow: hidden;
}

.progress-bar-fill {
  height: 100%;
  border-radius: 99px;
  transition: width 0.8s ease-out;
}

/* Desktop Styles Override */
@media (min-width: 768px) {
  .stats-container {
    padding: 40px;
  }

  .topbar {
    margin-bottom: 32px;
  }

  .topbar-title {
    font-size: 1.8rem;
  }

  .stats-grid {
    grid-template-columns: 1.3fr 1fr;
    gap: 36px;
    align-items: start;
  }
}

/* Empty stats view styling */
.empty-stats-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 60px 24px;
  border-radius: 28px;
  margin-top: 24px;
}

.empty-title {
  font-size: 1.25rem;
  font-weight: 800;
  color: var(--color-ink);
  margin: 0 0 8px 0;
}

.empty-desc {
  font-size: 0.95rem;
  color: var(--color-ink-soft);
  margin: 0 0 24px 0;
  max-width: 320px;
  line-height: 1.5;
}

.empty-cta {
  background-color: var(--color-primary);
  color: #fff;
  font-weight: 700;
  font-size: 0.92rem;
  padding: 12px 24px;
  border-radius: 14px;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
  transition: all 0.2s ease;
}

.empty-cta:hover {
  background-color: var(--color-primary-dark);
}
</style>