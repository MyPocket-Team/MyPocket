<script setup>
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import api from "../services/api";
import NotificationBell from "../components/NotificationBell.vue";

const router = useRouter();
const solde = ref(0);
const userName = ref("Astride");

const recentTransactions = ref([]);
const monthlyStats = ref({ income: 0, expenses: 0 });

async function loadDashboardData() {
  const profileRaw = localStorage.getItem("mypocket_profile");
  if (profileRaw) {
    const profile = JSON.parse(profileRaw);
    userName.value = profile.prenom || "Astride";
  }

  try {
    const overviewRes = await api.get("/dashboard/overview");
    const overview = overviewRes.data;
    solde.value = parseFloat(overview.solde_actuel || 0);
    monthlyStats.value = {
      income: parseFloat(overview.total_revenus_mois || 0),
      expenses: parseFloat(overview.total_depenses_mois || 0)
    };
    localStorage.setItem("mypocket_solde", solde.value.toString());
    localStorage.setItem("mypocket_initial_solde", overview.solde_initial.toString());

    const txRes = await api.get("/transactions");
    const txList = txRes.data.data || txRes.data;
    recentTransactions.value = txList.slice(0, 5).map((t) => ({
      id: t.id,
      description: t.description,
      montant: parseFloat(t.montant),
      type: t.type,
      categorie: t.categorie?.nom || "Autre",
      date: t.date_transaction,
      dateLabel: new Date(t.date_transaction).toLocaleDateString("fr-FR", {
        day: "numeric",
        month: "short"
      })
    }));
  } catch (e) {
    console.error("Error loading dashboard data", e);
  }
}

onMounted(() => {
  loadDashboardData();
});

const actions = [
  {
    label: "Ajouter transaction",
    description: "Manuel, reçu ou audio",
    routeName: "add-transaction",
    icon: "plus",
    tone: "primary",
  },
  {
    label: "Planning Budget",
    description: "Prévoir tes dépenses",
    routeName: "planning",
    icon: "calendar",
    tone: "accent",
  },
  {
    label: "Historique",
    description: "Toutes tes transactions",
    routeName: "history",
    icon: "clock",
    tone: "accent",
  },
  {
    label: "Statistiques",
    description: "Graphiques et répartition",
    routeName: "stats",
    icon: "chart",
    tone: "primary",
  },
  {
    label: "Chatbot Assistant",
    description: "Discute avec ton assistant",
    routeName: "chatbot",
    icon: "chat",
    tone: "primary",
    wide: true,
  },
];

function goTo(routeName) {
  router.push({ name: routeName });
}
</script>

<template>
  <main class="dashboard-container">
    <!-- Topbar for Mobile -->
    <header class="topbar mobile-only">
      <div class="wordmark">
        <img src="/logo.png" alt="MyPocket" class="logo-img" />
      </div>
      <NotificationBell />
    </header>

    <!-- Welcome Message (Desktop only) -->
    <div class="desktop-header">
      <div class="desktop-header-text">
        <h1 class="welcome-title">Bonjour {{ userName }} ! 👋</h1>
        <p class="welcome-subtitle">Voici l'état actuel de tes finances personnelles.</p>
      </div>
      <NotificationBell />
    </div>

    <!-- Responsive Layout Grid -->
    <div class="dashboard-grid">
      <!-- Left side: Balance card + Quick Stats + Recent -->
      <div class="main-column">
        <section class="balance-card">
          <div class="balance-card-bg"></div>
          <div class="balance-card-bg balance-card-bg--2"></div>
          <div class="balance-info">
            <span class="balance-label">Solde disponible</span>
            <span class="balance-amount">
              {{ solde.toLocaleString('fr-FR') }} <span class="balance-currency">FCFA</span>
            </span>
          </div>
          <!-- Monthly mini-stats -->
          <div class="balance-mini-stats">
            <div class="mini-stat">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="19" x2="12" y2="5" /><polyline points="5 12 12 5 19 12" />
              </svg>
              <span>{{ monthlyStats.income.toLocaleString('fr-FR') }} F</span>
            </div>
            <div class="mini-stat mini-stat--expense">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19" /><polyline points="19 12 12 19 5 12" />
              </svg>
              <span>{{ monthlyStats.expenses.toLocaleString('fr-FR') }} F</span>
            </div>
          </div>
        </section>

        <!-- Recent Transactions Card -->
        <section class="recent-card glass-panel">
          <div class="card-header">
            <h3 class="card-title">Dernières transactions</h3>
            <button type="button" class="view-all-link" @click="goTo('history')">Voir tout</button>
          </div>
          
          <div v-if="recentTransactions.length === 0" class="empty-state">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-border);">
              <path d="M9 17H5a2 2 0 0 0-2 2" /><path d="M9 17H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v7a2 2 0 0 1-2 2h-4" /><path d="M17 22v-4M15 20h4" />
            </svg>
            <p>Aucune transaction pour l'instant</p>
            <button type="button" class="empty-cta" @click="goTo('add-transaction')">Ajouter ma première transaction</button>
          </div>

          <div v-else class="transaction-list">
            <div v-for="t in recentTransactions" :key="t.id" class="transaction-item" @click="goTo('history')">
              <div class="t-icon" :class="`t-icon--${t.type}`">
                <svg v-if="t.type === 'revenu'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="12" y1="19" x2="12" y2="5" />
                  <polyline points="5 12 12 5 19 12" />
                </svg>
                <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="12" y1="5" x2="12" y2="19" />
                  <polyline points="19 12 12 19 5 12" />
                </svg>
              </div>
              <div class="t-details">
                <span class="t-desc">{{ t.description }}</span>
                <span class="t-cat">{{ t.categorie }}</span>
              </div>
              <div class="t-meta">
                <span class="t-amount" :class="`t-amount--${t.type}`">
                  {{ t.type === 'revenu' ? '+' : '-' }} {{ t.montant.toLocaleString('fr-FR') }} F
                </span>
                <span class="t-date">{{ t.dateLabel || t.date }}</span>
              </div>
            </div>
          </div>
        </section>
      </div>

      <!-- Right side: Quick Actions Grid -->
      <div class="side-column">
        <h2 class="section-title">Que veux-tu faire ?</h2>

        <div class="actions-grid">
          <button
            v-for="action in actions"
            :key="action.routeName"
            type="button"
            class="action-card glass-panel"
            :class="{ 'action-card--wide': action.wide }"
            @click="goTo(action.routeName)"
          >
            <span class="icon-badge" :class="`icon-badge--${action.tone}`">
              <svg v-if="action.icon === 'plus'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 5v14M5 12h14" />
              </svg>
              <svg v-else-if="action.icon === 'calendar'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="5" width="18" height="16" rx="2.5" />
                <path d="M3 10h18M8 3v4M16 3v4" />
              </svg>
              <svg v-else-if="action.icon === 'clock'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="9" />
                <path d="M12 7v5l3.5 2" />
              </svg>
              <svg v-else-if="action.icon === 'chart'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 20V10M12 20V4M20 20v-7" />
              </svg>
              <svg v-else-if="action.icon === 'chat'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" />
              </svg>
            </span>

            <span class="action-text">
              <span class="action-label">{{ action.label }}</span>
              <span class="action-description">{{ action.description }}</span>
            </span>

            <svg class="chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 18l6-6-6-6" />
            </svg>
          </button>
        </div>
      </div>
    </div>
  </main>
</template>

<style scoped>
.dashboard-container {
  padding: 24px 20px 48px;
  max-width: 1200px;
  margin: 0 auto;
}

/* Header for mobile devices */
.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
}

.wordmark {
  display: flex;
  align-items: center;
  gap: 6px;
}

.logo-img {
  height: 36px;
  width: auto;
}

/* Welcome message header */
.desktop-header {
  display: none;
  margin-bottom: 32px;
}

.welcome-title {
  font-size: 2rem;
  color: var(--color-ink);
  margin-bottom: 4px;
}

.welcome-subtitle {
  color: var(--color-ink-soft);
  font-size: 1rem;
}

/* Grid Layout */
.dashboard-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
}

/* Les items d'une grille CSS ont un min-width:auto implicite (basé sur le
   contenu qui ne peut pas se réduire), donc sans ce reset une transaction
   avec une longue description peut élargir la colonne entière au lieu de
   se faire tronquer par l'ellipsis, et fait déborder la page à l'horizontale. */
.main-column,
.side-column {
  min-width: 0;
}

/* Balance Card */
.balance-card {
  position: relative;
  background: linear-gradient(135deg, #10b981 0%, #059669 60%, #047857 100%);
  border-radius: 24px;
  padding: 32px 28px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  color: #fff;
  box-shadow: 0 14px 40px rgba(16, 185, 129, 0.3);
  overflow: hidden;
  margin-bottom: 24px;
}

.balance-card-bg {
  position: absolute;
  top: -50px;
  right: -50px;
  width: 160px;
  height: 160px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.08);
  pointer-events: none;
}

.balance-card-bg--2 {
  top: auto;
  bottom: -60px;
  left: -40px;
  width: 120px;
  height: 120px;
  background: rgba(255, 255, 255, 0.05);
}

.balance-mini-stats {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
}

.mini-stat {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 0.82rem;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.9);
  background: rgba(255, 255, 255, 0.12);
  padding: 5px 10px;
  border-radius: 99px;
}

.mini-stat--expense {
  color: rgba(254, 226, 226, 0.9);
}

.balance-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.balance-label {
  font-size: 0.9rem;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.85);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.balance-amount {
  font-size: 2.2rem;
  font-weight: 800;
}

.balance-currency {
  font-size: 1.2rem;
  font-weight: 600;
}

.reset-balance {
  align-self: flex-start;
  font-size: 0.8rem;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.9);
  text-decoration: underline;
}

.reset-balance:hover {
  color: #fff;
}

/* Recent Transactions Card */
.recent-card {
  border-radius: 24px;
  padding: 24px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 18px;
}

.card-title {
  font-size: 1rem;
  font-weight: 700;
  color: var(--color-ink);
  min-width: 0;
}

.view-all-link {
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--color-primary);
  white-space: nowrap;
  flex-shrink: 0;
}

.view-all-link:hover {
  text-decoration: underline;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 24px;
  text-align: center;
  color: var(--color-ink-soft);
  font-size: 0.88rem;
}

.empty-cta {
  margin-top: 4px;
  background-color: var(--color-primary-light);
  color: var(--color-primary-dark);
  font-weight: 700;
  font-size: 0.85rem;
  padding: 10px 18px;
  border-radius: 10px;
}

.transaction-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.transaction-item {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding: 12px;
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.5);
  border: 1px solid var(--color-bg-soft);
  transition: all 0.2s ease;
  cursor: pointer;
}

.transaction-item:hover {
  background: var(--color-white);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
}

.t-icon {
  width: 38px;
  height: 38px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.t-icon--revenu {
  background-color: rgba(16, 185, 129, 0.12);
  color: var(--color-primary);
}

.t-icon--depense {
  background-color: rgba(239, 68, 68, 0.1);
  color: var(--color-danger);
}

.t-details {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 55%;
}

.t-desc {
  font-weight: 700;
  font-size: 0.85rem;
  color: var(--color-ink);
  line-height: 1.35;
  overflow-wrap: break-word;
}

.t-cat {
  font-size: 0.7rem;
  color: var(--color-ink-soft);
}

.t-meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
  flex-shrink: 1;
  min-width: 0;
}

.t-amount {
  font-weight: 700;
  font-size: 0.82rem;
  white-space: nowrap;
}

.t-amount--revenu {
  color: var(--color-primary-dark);
}

.t-amount--depense {
  color: var(--color-danger);
}

.t-date {
  font-size: 0.68rem;
  color: var(--color-ink-soft);
  white-space: nowrap;
}

/* Quick Actions Section */
.section-title {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--color-ink);
  margin-bottom: 18px;
}

.actions-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 14px;
}

.action-card {
  position: relative;
  border-radius: 20px;
  padding: 20px 18px 22px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 14px;
  text-align: left;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.action-card:hover {
  border-color: var(--color-primary);
  transform: translateY(-3px);
  box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
}

.action-card--wide {
  grid-column: 1 / -1;
  flex-direction: row;
  align-items: center;
}

.action-card--wide .action-text {
  flex: 1;
}

.icon-badge {
  width: 44px;
  height: 44px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.icon-badge--primary {
  background-color: var(--color-primary-light);
  color: var(--color-primary-dark);
}

.icon-badge--accent {
  background-color: rgba(99, 102, 241, 0.12);
  color: var(--color-accent);
}

.action-text {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.action-label {
  font-weight: 700;
  font-size: 0.95rem;
  color: var(--color-ink);
  line-height: 1.25;
}

.action-description {
  font-size: 0.8rem;
  color: var(--color-ink-soft);
}

.chevron {
  position: absolute;
  top: 18px;
  right: 16px;
  color: var(--color-ink-soft);
  opacity: 0.5;
}

.action-card--wide .chevron {
  position: static;
  margin-left: auto;
  opacity: 0.6;
}

/* Narrow phones */
@media (max-width: 380px) {
  .balance-amount {
    font-size: 1.7rem;
  }

  .balance-currency {
    font-size: 1rem;
  }
}

/* Responsive Overrides */
@media (min-width: 768px) {
  .dashboard-container {
    padding: 40px;
  }

  .mobile-only {
    display: none !important;
  }

  .desktop-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
  }

  .dashboard-grid {
    grid-template-columns: 1.2fr 1fr;
    gap: 36px;
    align-items: start;
  }

  .actions-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 1024px) {
  .dashboard-grid {
    grid-template-columns: 1.4fr 1fr;
  }
}
</style>