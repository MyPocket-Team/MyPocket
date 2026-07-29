<script setup>
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "../services/api";

const route = useRoute();
const router = useRouter();

const transactionId = route.params.id;
const transaction = ref({
  id: transactionId,
  description: "",
  montant: 0,
  categorie: "",
  type: "depense",
  date: "",
  methode: "",
  note: "",
});
const loading = ref(true);
const error = ref("");
const deleting = ref(false);

function goBack() {
  router.back();
}

function editTransaction() {
  router.push({ name: "add-transaction", query: { edit: transactionId } });
}

async function deleteTransaction() {
  if (!confirm("Supprimer cette transaction ? Cette action est irréversible.")) {
    return;
  }

  deleting.value = true;
  error.value = "";

  try {
    const res = await api.delete(`/transactions/${transactionId}`);
    if (res.data?.solde_actuel !== undefined) {
      localStorage.setItem("mypocket_solde", res.data.solde_actuel.toString());
    }
    router.push({ name: "history" });
  } catch (e) {
    error.value = e.response?.data?.message || "Impossible de supprimer la transaction.";
  } finally {
    deleting.value = false;
  }
}

function formatDate(dateStr) {
  const date = new Date(dateStr);
  return date.toLocaleDateString("fr-FR", { day: "2-digit", month: "long", year: "numeric" });
}

function sourceNote(source) {
  if (source === "ia_recu") {
    return "Cette transaction a été enregistrée automatiquement par reconnaissance d'image du reçu de caisse.";
  }
  if (source === "ia_audio") {
    return "Cette transaction a été enregistrée automatiquement par reconnaissance vocale.";
  }
  return "Transaction enregistrée manuellement.";
}

function methodLabel(source) {
  if (source === "ia_recu") {
    return "Scan de reçu (IA)";
  }
  if (source === "ia_audio") {
    return "Reconnaissance vocale (IA)";
  }
  return "Saisie manuelle";
}

async function fetchTransaction() {
  loading.value = true;
  error.value = "";

  try {
    const res = await api.get(`/transactions/${transactionId}`);
    const t = res.data.data || res.data;
    transaction.value = {
      id: t.id,
      description: t.description,
      montant: t.montant,
      categorie: t.categorie?.nom || "Autre",
      type: t.type,
      date: t.date_transaction,
      methode: methodLabel(t.source),
      note: sourceNote(t.source),
    };
  } catch (e) {
    error.value = e.response?.data?.message || "Impossible de charger la transaction.";
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  fetchTransaction();
});
</script>

<template>
  <main class="page-container">
    <div class="page-layout">
      <!-- Topbar -->
      <header class="topbar">
        <button type="button" class="back-btn" @click="goBack" aria-label="Retour">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M15 18l-6-6 6-6" />
          </svg>
        </button>
        <h1 class="topbar-title">Détail de la transaction</h1>
      </header>

      <!-- Receipt card display -->
      <div class="detail-wrapper">
        <article class="receipt-card glass-panel">
          <!-- Receipt header -->
          <div class="receipt-header">
            <div class="receipt-header-actions">
              <button type="button" class="action-secondary" @click="editTransaction">Modifier</button>
              <button type="button" class="action-danger" @click="deleteTransaction" :disabled="deleting">
                {{ deleting ? 'Suppression...' : 'Supprimer' }}
              </button>
            </div>
            <div class="badge-icon" :class="`badge-icon--${transaction.type}`">
              <svg v-if="transaction.type === 'depense'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <polyline points="19 12 12 19 5 12"></polyline>
              </svg>
              <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="19" x2="12" y2="5"></line>
                <polyline points="5 12 12 5 19 12"></polyline>
              </svg>
            </div>
            <span class="type-label">{{ transaction.type === 'depense' ? 'Dépense effectuée' : 'Revenu reçu' }}</span>
          </div>

          <!-- Main amount -->
          <div class="amount-display" :class="`amount-display--${transaction.type}`">
            <span class="math-sign">{{ transaction.type === 'depense' ? '-' : '+' }}</span>
            {{ transaction.montant.toLocaleString('fr-FR') }}
            <span class="currency">FCFA</span>
          </div>

          <h2 class="transaction-desc">{{ transaction.description }}</h2>

          <!-- Dashed divider -->
          <div class="ticket-divider"></div>

          <!-- Transaction details -->
          <div class="details-list">
            <div class="detail-row">
              <span class="detail-label">Catégorie</span>
              <span class="detail-value font-highlight">{{ transaction.categorie }}</span>
            </div>

            <div class="detail-row">
              <span class="detail-label">Date & heure</span>
              <span class="detail-value">{{ transaction.date }}</span>
            </div>

            <div class="detail-row">
              <span class="detail-label">Saisie via</span>
              <span class="detail-value">{{ transaction.methode }}</span>
            </div>

            <div class="detail-row">
              <span class="detail-label">Numéro d'ID</span>
              <span class="detail-value reference-code">#{{ transaction.id }}</span>
            </div>
          </div>

          <div class="ticket-divider"></div>

          <p v-if="error" class="detail-error">{{ error }}</p>

          <!-- Optional notes -->
          <div class="note-box">
            <span class="note-title">Notes / Commentaires</span>
            <p class="note-content">{{ transaction.note }}</p>
          </div>
        </article>
      </div>
    </div>
  </main>
</template>

<style scoped>
.page-container {
  padding: 24px 20px 48px;
  max-width: 1200px;
  margin: 0 auto;
}

.page-layout {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* Topbar */
.topbar {
  display: flex;
  align-items: center;
  gap: 16px;
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

.detail-wrapper {
  margin-top: 16px;
  display: flex;
  justify-content: center;
}

/* Receipt Card Design */
.receipt-card {
  border-radius: 24px;
  padding: 32px 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 100%;
  max-width: 500px;
}

.receipt-header {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.badge-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.badge-icon--depense {
  background-color: rgba(239, 68, 68, 0.08);
  color: var(--color-danger);
}

.badge-icon--revenu {
  background-color: var(--color-primary-light);
  color: var(--color-primary-dark);
}

.type-label {
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--color-ink-soft);
}

/* Amount display */
.amount-display {
  font-size: 2.2rem;
  font-weight: 800;
  margin-top: 16px;
  display: flex;
  align-items: baseline;
  gap: 4px;
}

.amount-display--depense {
  color: var(--color-danger);
}

.amount-display--revenu {
  color: var(--color-primary-dark);
}

.math-sign {
  font-size: 1.8rem;
  font-weight: 500;
}

.currency {
  font-size: 1.1rem;
  font-weight: 600;
}

.transaction-desc {
  font-weight: 700;
  font-size: 1.15rem;
  color: var(--color-ink);
  text-align: center;
  margin-top: 8px;
}

/* Dotted ticket line */
.ticket-divider {
  width: 100%;
  height: 1px;
  border-bottom: 2px dashed var(--color-border);
  margin: 24px 0;
}

/* Details list styling */
.details-list {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.9rem;
}

.detail-label {
  color: var(--color-ink-soft);
  font-weight: 500;
}

.detail-value {
  color: var(--color-ink);
  font-weight: 600;
  text-align: right;
}

.font-highlight {
  color: var(--color-primary-dark);
}

.reference-code {
  font-family: monospace;
  font-size: 0.95rem;
  color: var(--color-ink-soft);
}

/* Notes comments */
.note-box {
  width: 100%;
  text-align: left;
}

.note-title {
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--color-ink-soft);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  display: block;
  margin-bottom: 8px;
}

.note-content {
  font-size: 0.88rem;
  line-height: 1.5;
  color: var(--color-ink);
  background-color: var(--color-bg-soft);
  padding: 12px 16px;
  border-radius: 14px;
  margin: 0;
}

/* Narrow phones */
@media (max-width: 380px) {
  .amount-display {
    font-size: 1.7rem;
  }

  .math-sign {
    font-size: 1.4rem;
  }

  .currency {
    font-size: 0.95rem;
  }
}

/* Responsive adjustments */
@media (min-width: 768px) {
  .page-container {
    padding: 40px;
  }

  .topbar-title {
    font-size: 1.8rem;
  }

  .detail-wrapper {
    margin-top: 32px;
  }

  .receipt-card {
    padding: 40px;
    max-width: 520px;
  }
}
</style>