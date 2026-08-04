<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import api from "../services/api";
import { useDialog } from "../composables/useDialog";
import NotificationBell from "../components/NotificationBell.vue";

const router = useRouter();
const { confirmDialog } = useDialog();

function goBack() {
  router.back();
}

const transactions = ref([]);
const loading = ref(true);
const error = ref("");

const categories = ["", "Alimentation", "Transport", "Logement", "Divertissement", "Santé", "Revenu", "Autre"];
const periodes = ["", "7 derniers jours", "Ce mois-ci", "Ce mois dernier"];
const types = ["", "Dépenses", "Revenus"];

const search = ref("");
const filterCategorie = ref("");
const filterPeriode = ref("");
const filterType = ref("");
const openMenuId = ref(null);

function toggleMenu(id) {
  openMenuId.value = openMenuId.value === id ? null : id;
}

function closeMenu() {
  openMenuId.value = null;
}

function viewDetail(id) {
  closeMenu();
  router.push({ name: "transaction-detail", params: { id } });
}

function editTransaction(id) {
  closeMenu();
  router.push({ name: "add-transaction", query: { edit: id } });
}

// Correction Problème 7 : Formatage lisible de la date du jour du groupe
function formatDate(dateStr) {
  if (!dateStr) return "";
  const date = new Date(dateStr);
  return date.toLocaleDateString("fr-FR", { day: "2-digit", month: "long", year: "numeric" });
}

async function fetchTransactions() {
  loading.value = true;
  error.value = "";

  try {
    const res = await api.get("/transactions");
    const list = res.data.data || res.data;
    transactions.value = list.map((t) => ({
      ...t,
      categorie: t.categorie?.nom || "Autre",
      // Correction Problème 7 : Utilisation de date_brute transmise par la Resource Laravel (ou extraction du jour YYYY-MM-DD)
      dateBrute: t.date_brute || (t.date_transaction ? t.date_transaction.split(' ')[0] : ""),
      dateLabel: t.date_transaction, // Affichage avec heure reçu du backend
      sourceSaisie: t.source_saisie || t.source || "manuel",
    }));
  } catch (e) {
    error.value = e.response?.data?.message || "Impossible de charger l'historique des transactions.";
  } finally {
    loading.value = false;
  }
}

async function deleteTransaction(id) {
  closeMenu();
  if (!(await confirmDialog("Supprimer cette transaction ?", { danger: true, confirmLabel: "Supprimer" }))) return;

  try {
    const res = await api.delete(`/transactions/${id}`);
    transactions.value = transactions.value.filter((t) => t.id !== id);
    if (res.data?.solde_actuel !== undefined) {
      localStorage.setItem("mypocket_solde", res.data.solde_actuel.toString());
    }
  } catch (e) {
    error.value = e.response?.data?.message || "Impossible de supprimer la transaction.";
  }
}

const filtered = computed(() => {
  const now = new Date();
  const startOfLastMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
  const endOfLastMonth = new Date(now.getFullYear(), now.getMonth(), 0);

  return transactions.value.filter((t) => {
    const matchSearch = t.description.toLowerCase().includes(search.value.toLowerCase());
    const matchCategorie = filterCategorie.value === "" || t.categorie === filterCategorie.value;
    const matchType =
      filterType.value === "" ||
      (filterType.value === "Dépenses" && t.type === "depense") ||
      (filterType.value === "Revenus" && t.type === "revenu");

    let matchPeriode = true;
    if (filterPeriode.value && t.dateBrute) {
      const date = new Date(t.dateBrute);
      if (filterPeriode.value === "7 derniers jours") {
        const sevenDaysAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
        matchPeriode = date >= sevenDaysAgo && date <= now;
      } else if (filterPeriode.value === "Ce mois-ci") {
        matchPeriode = date.getMonth() === now.getMonth() && date.getFullYear() === now.getFullYear();
      } else if (filterPeriode.value === "Ce mois dernier") {
        matchPeriode = date >= startOfLastMonth && date <= endOfLastMonth;
      }
    }

    return matchSearch && matchCategorie && matchType && matchPeriode;
  });
});

// Correction Problème 7 : Regroupement strict par 'dateBrute' (YYYY-MM-DD) pour éliminer les doublons d'en-tête de date
const grouped = computed(() => {
  const groups = {};
  filtered.value.forEach((t) => {
    const key = t.dateBrute;
    if (!groups[key]) groups[key] = [];
    groups[key].push(t);
  });
  return Object.entries(groups).sort((a, b) => (a[0] < b[0] ? 1 : -1));
});

onMounted(() => {
  fetchTransactions();
});
</script>

<template>
  <main class="history-container" @click="closeMenu">
    <!-- Topbar -->
    <header class="topbar">
      <button type="button" class="back-btn" @click="goBack" aria-label="Retour">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M15 18l-6-6 6-6" />
        </svg>
      </button>
      <h1 class="topbar-title">Historique des transactions</h1>
      <NotificationBell />
    </header>

    <!-- Search & Filters Container -->
    <div class="controls-panel glass-panel">
      <div class="search-bar">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="7" />
          <path d="M21 21l-4.3-4.3" />
        </svg>
        <input v-model="search" type="text" placeholder="Rechercher une transaction..." />
      </div>

      <div class="filters">
        <div class="select-wrapper">
          <select v-model="filterPeriode" class="filter-select">
            <option value="">Toutes périodes</option>
            <option v-for="p in periodes.slice(1)" :key="p" :value="p">{{ p }}</option>
          </select>
        </div>
        <div class="select-wrapper">
          <select v-model="filterCategorie" class="filter-select">
            <option value="">Toutes catégories</option>
            <option v-for="c in categories.slice(1)" :key="c" :value="c">{{ c }}</option>
          </select>
        </div>
        <div class="select-wrapper">
          <select v-model="filterType" class="filter-select">
            <option value="">Tous types</option>
            <option v-for="t in types.slice(1)" :key="t" :value="t">{{ t }}</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-if="transactions.length === 0" class="empty-state glass-panel">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-ink-soft); margin-bottom: 8px;">
        <circle cx="12" cy="12" r="10" />
        <line x1="12" y1="8" x2="12" y2="12" />
        <line x1="12" y1="16" x2="12.01" y2="16" />
      </svg>
      <p>Aucune transaction enregistrée. Vos transactions s'afficheront ici.</p>
    </div>
    <div v-else-if="grouped.length === 0" class="empty-state glass-panel">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10" />
        <line x1="12" y1="8" x2="12" y2="12" />
        <line x1="12" y1="16" x2="12.01" y2="16" />
      </svg>
      <p>Aucune transaction ne correspond à tes critères.</p>
    </div>

    <!-- Transactions List -->
    <div v-else class="transactions-list">
      <div v-for="[date, items] in grouped" :key="date" class="day-group">
        <p class="day-label">{{ formatDate(date) }}</p>

        <div class="rows-container">
          <div v-for="t in items" :key="t.id" class="transaction-row">
            <!-- Icon by category type -->
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

            <!-- Details -->
            <div class="transaction-main">
              <span class="transaction-description">{{ t.description }}</span>
              <span class="transaction-categorie">
                {{ t.categorie }}
                <!-- Correction Problème 7 : Badge de la source de saisie -->
                <small class="source-tag">({{ t.sourceSaisie }})</small>
              </span>
            </div>

            <!-- Side (Amount & Dropdown Menu) -->
            <div class="transaction-side">
              <span class="transaction-amount" :class="`amount-${t.type}`">
                {{ t.type === "revenu" ? "+" : "-" }} {{ t.montant.toLocaleString("fr-FR") }} FCFA
              </span>

              <div class="menu-wrapper" @click.stop>
                <button type="button" class="menu-trigger" @click="toggleMenu(t.id)" aria-label="Actions">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                    <circle cx="12" cy="5" r="1.6" />
                    <circle cx="12" cy="12" r="1.6" />
                    <circle cx="12" cy="19" r="1.6" />
                  </svg>
                </button>

                <div v-if="openMenuId === t.id" class="menu-dropdown glass-panel">
                  <button type="button" class="menu-item" @click="viewDetail(t.id)">Voir détail</button>
                  <button type="button" class="menu-item" @click="editTransaction(t.id)">Modifier</button>
                  <button type="button" class="menu-item menu-item--danger" @click="deleteTransaction(t.id)">Supprimer</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</template>

<style scoped>
.history-container {
  padding: 24px 20px 48px;
  max-width: 1200px;
  margin: 0 auto;
}

/* Header */
.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
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

/* Controls Panel */
.controls-panel {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border-radius: 20px;
  margin-bottom: 32px;
}

.search-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  background-color: var(--color-bg-soft);
  border-radius: 14px;
  padding: 12px 16px;
  color: var(--color-ink-soft);
  width: 100%;
}

.search-bar input {
  flex: 1;
  border: none;
  outline: none;
  background: transparent;
  font-size: 0.95rem;
  color: var(--color-ink);
}

.filters {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.select-wrapper {
  position: relative;
  flex: 1;
  min-width: 150px;
}

.filter-select {
  width: 100%;
  background-color: var(--color-bg-soft);
  border: 1px solid transparent;
  border-radius: 12px;
  padding: 10px 34px 10px 14px;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--color-ink-soft);
  outline: none;
  appearance: none;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23475569' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
  transition: all 0.2s ease;
}

.filter-select:focus {
  border-color: var(--color-primary);
  background-color: var(--color-paper-raised);
}

/* Empty State */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 48px 24px;
  border-radius: 20px;
  color: var(--color-ink-soft);
  text-align: center;
}

/* Transactions Grouping */
.day-group {
  margin-bottom: 28px;
}

.day-label {
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--color-ink-soft);
  margin-bottom: 12px;
  padding-left: 8px;
}

.rows-container {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.transaction-row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 8px 16px;
  background-color: var(--color-paper-raised);
  border: 1px solid var(--color-border);
  border-radius: 16px;
  padding: 14px 18px;
  transition: all 0.2s ease;
}

.transaction-row:hover {
  transform: translateY(-1px);
  box-shadow: 0 8px 16px rgba(15, 23, 42, 0.04);
  border-color: var(--color-primary-light);
}

.t-icon {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.t-icon--revenu {
  background-color: rgba(16, 185, 129, 0.1);
  color: var(--color-primary);
}

.t-icon--depense {
  background-color: rgba(239, 68, 68, 0.08);
  color: var(--color-danger);
}

.transaction-main {
  flex: 1 1 180px;
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 180px;
}

.transaction-description {
  font-weight: 700;
  font-size: 0.88rem;
  color: var(--color-ink);
  line-height: 1.35;
  overflow-wrap: break-word;
}

.transaction-categorie {
  font-size: 0.72rem;
  color: var(--color-ink-soft);
}

.source-tag {
  font-size: 0.68rem;
  opacity: 0.75;
  text-transform: lowercase;
  margin-left: 4px;
}

.transaction-side {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
  margin-left: auto;
}

.transaction-amount {
  font-weight: 700;
  font-size: 0.85rem;
  white-space: nowrap;
}

.amount-depense {
  color: var(--color-danger);
}

.amount-revenu {
  color: var(--color-primary-dark);
}

.menu-wrapper {
  position: relative;
}

.menu-trigger {
  color: var(--color-ink-soft);
  padding: 6px;
  border-radius: 50%;
  display: flex;
}

.menu-trigger:hover {
  background-color: var(--color-bg-soft);
}

.menu-dropdown {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  border-radius: 14px;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  z-index: 10;
  min-width: 140px;
  padding: 4px;
}

.menu-item {
  background: none;
  border: none;
  text-align: left;
  padding: 10px 14px;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--color-ink);
  border-radius: 8px;
}

.menu-item:hover {
  background-color: var(--color-bg-soft);
}

.menu-item--danger {
  color: var(--color-danger);
}

.menu-item--danger:hover {
  background-color: rgba(239, 68, 68, 0.08);
}

/* Desktop styles override */
@media (min-width: 768px) {
  .history-container {
    padding: 40px;
  }

  .topbar-title {
    font-size: 1.8rem;
  }

  .controls-panel {
    flex-direction: column;
    align-items: stretch;
    padding: 20px 24px;
  }

  .search-bar {
    max-width: 400px;
  }

  .filters {
    flex-wrap: nowrap;
  }
}
</style>
