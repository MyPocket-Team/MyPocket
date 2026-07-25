<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useRouter } from "vue-router";
import api from "../services/api";

const router = useRouter();

function goBack() {
  router.back();
}

const plannings = ref([]);
const activePlanningId = ref(null);
const planningsPanelOpen = ref(false);
const loading = ref(false);
const error = ref("");

const showPlanningModal = ref(false);
const planningModalMode = ref("create");
const editingPlanningId = ref(null);
const formTitle = ref("");
const formDescription = ref("");
const formDate = ref("");

const activePlanning = computed(() =>
  plannings.value.find((p) => p.id === activePlanningId.value) || null
);

const plannedTransactions = ref([]);
const categories = ref([]);

const showPlanTxModal = ref(false);
const planTxAmount = ref("");
const planTxDescription = ref("");
const planTxType = ref("depense");
const planTxCategoryId = ref("");
const planTxCustomCategory = ref("");
const planTxDueDate = ref("");

function openPlanTxModal() {
  planTxAmount.value = "";
  planTxDescription.value = "";
  planTxType.value = "depense";
  planTxCustomCategory.value = "";
  planTxDueDate.value = new Date().toISOString().slice(0, 10);
  if (categories.value.length) {
    planTxCategoryId.value = categories.value[0].id;
  }
  showPlanTxModal.value = true;
}

function closePlanTxModal() {
  showPlanTxModal.value = false;
}

async function fetchPlannedTransactions(planningId) {
  try {
    const res = await api.get(`/plannings/${planningId}/transactions-planifiees`);
    plannedTransactions.value = res.data.data || res.data || [];
  } catch (e) {
    console.error("Impossible de charger les transactions planifiées", e);
  }
}

async function fetchCategories() {
  try {
    const res = await api.get("/categories");
    const list = res.data.data || res.data || [];
    categories.value = [...list, { id: "Autre", nom: "Autre" }];
    if (categories.value.length) {
      planTxCategoryId.value = categories.value[0].id;
    }
  } catch (e) {
    console.error("Impossible de charger les catégories", e);
  }
}

async function savePlannedTransaction() {
  if (!planTxAmount.value || !planTxDueDate.value) return;

  const payload = {
    montant: parseFloat(planTxAmount.value),
    description: planTxDescription.value.trim(),
    type: planTxType.value,
    date_echeance: planTxDueDate.value,
  };

  if (planTxCategoryId.value === "Autre") {
    payload.nouvelle_categorie = planTxCustomCategory.value.trim();
  } else {
    payload.categorie_id = planTxCategoryId.value;
  }

  try {
    const res = await api.post(`/plannings/${activePlanningId.value}/transactions-planifiees`, payload);
    const newTx = res.data.planned_transaction || res.data;
    plannedTransactions.value.push(newTx);
    showPlanTxModal.value = false;
    fetchCategories();
  } catch (e) {
    alert(e.response?.data?.message || "Impossible d'enregistrer la transaction planifiée.");
  }
}

async function deletePlannedTransaction(id) {
  if (!confirm("Supprimer cette transaction planifiée ? Cette action est irréversible.")) return;

  try {
    await api.delete(`/transactions-planifiees/${id}`);
    plannedTransactions.value = plannedTransactions.value.filter((t) => t.id !== id);
  } catch (e) {
    alert(e.response?.data?.message || "Impossible de supprimer la transaction planifiée.");
  }
}

async function confirmPlannedTransaction(tx, event) {
  if (!confirm("Confirmer la réalisation de cette transaction ? Elle sera enregistrée comme une transaction réelle.")) {
    if (event) event.target.checked = false;
    return;
  }

  try {
    const res = await api.post(`/transactions-planifiees/${tx.id}/confirmer`);
    const updated = res.data.planned_transaction || res.data;
    const index = plannedTransactions.value.findIndex((t) => t.id === updated.id);
    if (index !== -1) {
      plannedTransactions.value.splice(index, 1, updated);
    }
    if (res.data.solde_actuel !== undefined) {
      localStorage.setItem("mypocket_solde", res.data.solde_actuel.toString());
    }

    // Le planning parent peut être passé automatiquement à "réalisé" côté
    // backend si c'était la dernière transaction planifiée en attente :
    // on rafraîchit la liste pour que l'en-tête reflète le nouveau statut.
    await fetchPlannings();
  } catch (e) {
    alert(e.response?.data?.message || "Impossible de réaliser la transaction.");
    if (event) event.target.checked = false;
  }
}

// Vrai si la date d'échéance de la transaction planifiée tombe aujourd'hui,
// pour mettre en évidence les transactions "à traiter maintenant".
function isDueToday(tx) {
  if (!tx.date_echeance) return false;
  const echeance = new Date(tx.date_echeance);
  const today = new Date();
  return (
    echeance.getFullYear() === today.getFullYear() &&
    echeance.getMonth() === today.getMonth() &&
    echeance.getDate() === today.getDate()
  );
}

watch(activePlanningId, (newId) => {
  if (newId) {
    fetchPlannedTransactions(newId);
  } else {
    plannedTransactions.value = [];
  }
});


const statutLabels = {
  en_attente: "En attente",
  realise: "Réalisé",
  annule: "Annulé",
};

function mapPlanning(planning) {
  return planning;
}

async function fetchPlannings() {
  loading.value = true;
  error.value = "";

  try {
    const res = await api.get("/plannings");
    const list = res.data.data || res.data;
    plannings.value = (list || []).map(mapPlanning);

    if (plannings.value.length && !plannings.value.some((p) => p.id === activePlanningId.value)) {
      activePlanningId.value = plannings.value[0].id;
    }
  } catch (e) {
    error.value = e.response?.data?.message || "Impossible de charger les plannings.";
  } finally {
    loading.value = false;
  }
}

function selectPlanning(id) {
  activePlanningId.value = id;
  planningsPanelOpen.value = false;
}

function openCreatePlanningModal() {
  planningModalMode.value = "create";
  editingPlanningId.value = null;
  formTitle.value = "";
  formDescription.value = "";
  formDate.value = "";
  showPlanningModal.value = true;
}

function openEditPlanningModal() {
  if (!activePlanning.value) return;
  planningModalMode.value = "edit";
  editingPlanningId.value = activePlanning.value.id;
  formTitle.value = activePlanning.value.titre;
  formDescription.value = activePlanning.value.description || "";
  formDate.value = activePlanning.value.date_prevue ? activePlanning.value.date_prevue.slice(0, 10) : "";
  showPlanningModal.value = true;
}

function closePlanningModal() {
  showPlanningModal.value = false;
}

async function savePlanningDetails() {
  if (!formTitle.value.trim()) return;

  const payload = {
    titre: formTitle.value.trim(),
    description: formDescription.value.trim(),
  };

  try {
    if (planningModalMode.value === "create") {
      const res = await api.post("/plannings", payload);
      const planning = mapPlanning(res.data.planning || res.data);
      plannings.value.unshift(planning);
      activePlanningId.value = planning.id;
    } else {
      const res = await api.put(`/plannings/${editingPlanningId.value}`, payload);
      const updated = mapPlanning(res.data.planning || res.data);
      const index = plannings.value.findIndex((p) => p.id === updated.id);
      if (index !== -1) {
        plannings.value.splice(index, 1, updated);
      }
      activePlanningId.value = updated.id;
    }
    showPlanningModal.value = false;
  } catch (e) {
    alert(e.response?.data?.message || "Impossible d'enregistrer le planning.");
  }
}

async function deletePlanning(id) {
  const planning = plannings.value.find((p) => p.id === id);
  if (!planning) return;
  if (!confirm(`Supprimer le planning "${planning.titre}" ? Cette action est irréversible.`)) return;

  try {
    await api.delete(`/plannings/${id}`);
    plannings.value = plannings.value.filter((p) => p.id !== id);
    if (activePlanningId.value === id) {
      activePlanningId.value = plannings.value.length ? plannings.value[0].id : null;
    }
  } catch (e) {
    alert(e.response?.data?.message || "Impossible de supprimer le planning.");
  }
}

async function cancelPlanning(planning) {
  if (!planning) return;
  if (!confirm("Annuler ce planning ?")) return;

  try {
    await api.post(`/plannings/${planning.id}/annuler`);
    const index = plannings.value.findIndex((p) => p.id === planning.id);
    if (index !== -1) {
      plannings.value[index].statut = "annule";
    }
  } catch (e) {
    alert(e.response?.data?.message || "Impossible d'annuler le planning.");
  }
}

function formatDateFr(dateStr) {
  if (!dateStr) return "";
  const d = new Date(dateStr);
  return d.toLocaleDateString("fr-FR", { day: "numeric", month: "long", year: "numeric" });
}

function formatShortDate(dateStr) {
  if (!dateStr) return "";
  const d = new Date(dateStr);
  return d.toLocaleDateString("fr-FR", { day: "2-digit", month: "short", year: "numeric" });
}

onMounted(() => {
  fetchPlannings();
  fetchCategories();
});
</script>

<template>
  <main class="planning-container">
    <!-- Topbar -->
    <header class="topbar">
      <button type="button" class="back-btn" @click="goBack" aria-label="Retour">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M15 18l-6-6 6-6" />
        </svg>
      </button>
      <h1 class="topbar-title">Planning Budget</h1>
      <button type="button" class="plannings-toggle mobile-only" @click="planningsPanelOpen = true" aria-label="Mes plannings">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="8" y1="6" x2="21" y2="6" />
          <line x1="8" y1="12" x2="21" y2="12" />
          <line x1="8" y1="18" x2="21" y2="18" />
          <line x1="3" y1="6" x2="3.01" y2="6" />
          <line x1="3" y1="12" x2="3.01" y2="12" />
          <line x1="3" y1="18" x2="3.01" y2="18" />
        </svg>
      </button>
    </header>

    <div class="planning-workspace">
      <!-- Plannings sidebar (liste des plannings) -->
      <aside class="plannings-sidebar" :class="{ 'plannings-sidebar--open': planningsPanelOpen }">
        <div class="plannings-sidebar-top">
          <span class="plannings-sidebar-title">Mes plannings</span>
          <button type="button" class="close-panel-btn mobile-only" @click="planningsPanelOpen = false" aria-label="Fermer">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 6L6 18M6 6l12 12" />
            </svg>
          </button>
        </div>

        <button type="button" class="new-planning-btn" @click="openCreatePlanningModal">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19" />
            <line x1="5" y1="12" x2="19" y2="12" />
          </svg>
          Nouveau planning
        </button>

        <div class="plannings-scrollable-list">
          <p v-if="plannings.length === 0" class="plannings-empty">Aucun planning pour l'instant.</p>

          <button
            v-for="p in plannings"
            :key="p.id"
            type="button"
            class="planning-card"
            :class="{ 'planning-card--active': p.id === activePlanningId }"
            @click="selectPlanning(p.id)"
          >
            <span class="planning-card-text">
              <span class="planning-card-title">{{ p.titre }}</span>
              <span class="planning-card-meta">{{ p.description || 'Aucune description' }}</span>
            </span>
            <span class="planning-delete-btn" @click.stop="deletePlanning(p.id)" role="button" aria-label="Supprimer le planning">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="3 6 5 6 21 6" />
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
              </svg>
            </span>
          </button>
        </div>
      </aside>

      <div v-if="planningsPanelOpen" class="plannings-overlay mobile-only" @click="planningsPanelOpen = false"></div>

      <!-- Détail du planning actif -->
      <section class="planning-detail">
        <template v-if="activePlanning">
          <!-- Planning Header Details Card -->
          <section class="planning-header-card glass-panel">
            <div class="header-display">
              <div class="header-main-info">
                <h2 class="planning-main-title">{{ activePlanning.titre }}</h2>
                <p class="planning-main-description">{{ activePlanning.description || "Aucune description." }}</p>
              </div>
              <button type="button" class="edit-header-btn" @click="openEditPlanningModal">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px; vertical-align: middle;">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                  <path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                </svg>
                Modifier les détails
              </button>
            </div>
          </section>

          <!-- Planning Details Section -->
          <section class="planning-detail-card glass-panel">
            <div class="planning-detail-content">
              <h2 class="section-title">Détails du planning</h2>
              <p class="section-subtitle">Gère ton objectif financier et suis son évolution.</p>

              <div v-if="activePlanning.date_prevue" class="planning-detail-row">
                <span class="planning-detail-label">Échéance</span>
                <strong>{{ formatDateFr(activePlanning.date_prevue) }}</strong>
              </div>
              <div class="planning-detail-row">
                <span class="planning-detail-label">Statut</span>
                <span class="status-badge" :class="`status-badge--${activePlanning.statut}`">
                  {{ statutLabels[activePlanning.statut] || activePlanning.statut }}
                </span>
              </div>

              <div v-if="activePlanning.transaction" class="planning-detail-row planning-linked-transaction">
                <span class="planning-detail-label">Transaction liée</span>
                <strong>{{ activePlanning.transaction.description || 'Transaction liée' }}</strong>
              </div>

              <div class="planning-actions">
                <button type="button" class="edit-header-btn" @click="openEditPlanningModal">
                  Modifier
                </button>
                <button
                  v-if="activePlanning.statut === 'en_attente'"
                  type="button"
                  class="cancel-btn"
                  @click="cancelPlanning(activePlanning)"
                >
                  Annuler
                </button>
                <button type="button" class="delete-btn" @click="deletePlanning(activePlanning.id)">
                  Supprimer
                </button>
              </div>
            </div>
          </section>

          <!-- Transactions Planifiées Section -->
          <section class="planned-transactions-card glass-panel" style="margin-top: 24px;">
            <div class="planned-header">
              <div>
                <h2 class="section-title">Transactions Planifiées</h2>
                <p class="section-subtitle">Planifie tes dépenses et revenus liés à ce budget.</p>
              </div>
              <button type="button" class="plan-btn" @click="openPlanTxModal">
                + Planifier une transaction
              </button>
            </div>

            <div v-if="plannedTransactions.length === 0" class="empty-state">
              <p>Aucune transaction planifiée pour ce planning.</p>
              <button type="button" class="empty-cta" @click="openPlanTxModal">
                Planifier ma première transaction
              </button>
            </div>

            <div v-else class="planned-list">
              <div v-for="tx in plannedTransactions" :key="tx.id" class="planned-item">
                <div class="planned-type-badge" :class="`planned-type-badge--${tx.type}`">
                  <svg v-if="tx.type === 'revenu'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="19" x2="12" y2="5" /><polyline points="5 12 12 5 19 12" />
                  </svg>
                  <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19" /><polyline points="19 12 12 19 5 12" />
                  </svg>
                </div>

                <div class="planned-details">
                  <div class="planned-desc-row">
                    <span class="planned-desc">{{ tx.description || 'Sans description' }}</span>
                    <span class="planned-cat-badge">{{ tx.categorie?.nom || 'Sans catégorie' }}</span>
                  </div>
                  <div class="planned-date-row">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px;">
                      <circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" />
                    </svg>
                    Échéance : {{ formatDateFr(tx.date_echeance) }}
                  </div>
                </div>

                <div class="planned-amount-block">
                  <span class="planned-amount" :class="`planned-amount--${tx.type}`">
                    {{ tx.type === 'revenu' ? '+' : '-' }}{{ parseFloat(tx.montant).toLocaleString('fr-FR') }} FCFA
                  </span>
                </div>

                <div class="planned-status-actions">
                  <span v-if="tx.statut === 'realise'" class="status-badge-display status-badge-display--réalisée">
                    Réalisé
                  </span>
                  <template v-else>
                    <span
                      class="status-badge-display"
                      :class="isDueToday(tx) ? 'status-badge-display--due' : 'status-badge-display--en-attente'"
                    >
                      {{ isDueToday(tx) ? "Échéance aujourd'hui" : "En attente" }}
                    </span>
                    <label class="realized-checkbox-label" :class="{ 'realized-checkbox-label--due': isDueToday(tx) }">
                      <input
                        type="checkbox"
                        class="realized-checkbox"
                        :checked="false"
                        @change="confirmPlannedTransaction(tx, $event)"
                      />
                      <span>Fait ?</span>
                    </label>
                  </template>

                  <div class="action-buttons">
                    <button 
                      type="button" 
                      class="btn-icon btn-icon--danger" 
                      @click="deletePlannedTransaction(tx.id)"
                      aria-label="Supprimer la transaction planifiée"
                    >
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6" />
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </section>
        </template>

        <div v-else class="no-planning-state glass-panel">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-border);">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
            <line x1="16" y1="2" x2="16" y2="6" />
            <line x1="8" y1="2" x2="8" y2="6" />
            <line x1="3" y1="10" x2="21" y2="10" />
          </svg>
          <p>Tu n'as pas encore de planning.</p>
          <button type="button" class="empty-cta" @click="openCreatePlanningModal">Créer mon premier planning</button>
        </div>
      </section>
    </div>

    <!-- Modal : créer / modifier un planning -->
    <div v-if="showPlanningModal" class="modal-overlay" @click.self="closePlanningModal">
      <div class="modal-card glass-panel">
        <div class="modal-header">
          <h3 class="modal-title">{{ planningModalMode === 'create' ? 'Nouveau planning' : 'Modifier le planning' }}</h3>
          <button type="button" class="close-btn" @click="closePlanningModal" aria-label="Fermer">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </button>
        </div>

        <form class="modal-form" @submit.prevent="savePlanningDetails">
          <div class="form-group">
            <label for="pl-title" class="form-label">Titre du planning</label>
            <input
              id="pl-title"
              v-model="formTitle"
              type="text"
              class="form-input"
              placeholder="Ex: Vacances, Rentrée scolaire..."
              required
            />
          </div>

          <div class="form-group">
            <label for="pl-desc" class="form-label">Description (optionnelle)</label>
            <textarea
              id="pl-desc"
              v-model="formDescription"
              class="form-input form-textarea"
              rows="2"
              placeholder="Ex: Objectifs et prévisions..."
            ></textarea>
          </div>



          <div class="modal-form-actions">
            <button type="button" class="cancel-btn" @click="closePlanningModal">Annuler</button>
            <button type="submit" class="save-btn-modal">
              {{ planningModalMode === 'create' ? 'Créer' : 'Enregistrer' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal : planifier une transaction -->
    <div v-if="showPlanTxModal" class="modal-overlay" @click.self="closePlanTxModal">
      <div class="modal-card glass-panel">
        <div class="modal-header">
          <h3 class="modal-title">Planifier une transaction</h3>
          <button type="button" class="close-btn" @click="closePlanTxModal" aria-label="Fermer">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </button>
        </div>

        <form class="modal-form" @submit.prevent="savePlannedTransaction">
          <div class="form-group">
            <label class="form-label">Type</label>
            <div class="type-toggle">
              <button type="button" class="type-btn type-btn--depense" :class="{ 'type-btn--active': planTxType === 'depense' }" @click="planTxType = 'depense'">Dépense</button>
              <button type="button" class="type-btn type-btn--revenu" :class="{ 'type-btn--active': planTxType === 'revenu' }" @click="planTxType = 'revenu'">Revenu</button>
            </div>
          </div>

          <div class="form-group">
            <label for="ptx-amount" class="form-label">Montant (FCFA)</label>
            <input id="ptx-amount" v-model="planTxAmount" type="number" step="0.01" min="0.01" class="form-input" placeholder="0" required />
          </div>

          <div class="form-group">
            <label for="ptx-desc" class="form-label">Description</label>
            <input id="ptx-desc" v-model="planTxDescription" type="text" class="form-input" placeholder="Ex: Loyer, Facture SBEE..." />
          </div>

          <div class="form-group">
            <label for="ptx-cat" class="form-label">Catégorie</label>
            <select id="ptx-cat" v-model="planTxCategoryId" class="form-input">
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.nom }}</option>
            </select>
            <input
              v-if="planTxCategoryId === 'Autre'"
              v-model="planTxCustomCategory"
              type="text"
              class="form-input"
              placeholder="Nom de la catégorie personnalisée"
              style="margin-top: 8px;"
              required
            />
          </div>

          <div class="form-group">
            <label for="ptx-date" class="form-label">Date d'échéance</label>
            <input id="ptx-date" v-model="planTxDueDate" type="date" class="form-input" required />
          </div>

          <div class="modal-form-actions">
            <button type="button" class="cancel-btn" @click="closePlanTxModal">Annuler</button>
            <button type="submit" class="save-btn-modal">Planifier</button>
          </div>
        </form>
      </div>
    </div>

  </main>
</template>

<style scoped>
.planning-container {
  padding: 24px 20px 48px;
  max-width: 1200px;
  margin: 0 auto;
}

/* Header */
.topbar {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
}

.back-btn,
.plannings-toggle {
  background: var(--color-paper-raised);
  border: 1px solid var(--color-border);
  color: var(--color-ink-soft);
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  flex-shrink: 0;
}

.topbar-title {
  position: absolute;
  left: 50%;
  top: 50%;
  transform: translate(-50%, -50%);
  font-size: 1.4rem;
  font-weight: 700;
  color: var(--color-ink);
  white-space: nowrap;
  pointer-events: none;
}

@media (min-width: 768px) {
  .plannings-toggle {
    display: none;
  }
}

/* Workspace layout: plannings sidebar + detail */
.planning-workspace {
  display: flex;
  align-items: flex-start;
  gap: 24px;
  position: relative;
}

/* Plannings sidebar */
.plannings-sidebar {
  display: flex;
  flex-direction: column;
  gap: 14px;
  background-color: var(--color-paper-raised);
  border: 1px solid var(--color-border);
  border-radius: 20px;
  width: 260px;
  flex-shrink: 0;
  padding: 18px;
}

@media (max-width: 767px) {
  .plannings-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100%;
    width: 280px;
    border-radius: 0;
    transform: translateX(-100%);
    transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 101;
    overflow-y: auto;
  }

  .plannings-sidebar--open {
    transform: translateX(0);
  }
}

.plannings-sidebar-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.plannings-sidebar-title {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--color-ink);
}

.close-panel-btn {
  color: var(--color-ink-soft);
  padding: 4px;
}

.new-planning-btn {
  background-color: var(--color-primary-light);
  color: var(--color-primary-dark);
  border-radius: 12px;
  padding: 12px 14px;
  font-weight: 700;
  font-size: 0.85rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.new-planning-btn:hover {
  background-color: rgba(16, 185, 129, 0.2);
}

.plannings-scrollable-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  overflow-y: auto;
}

.plannings-empty {
  font-size: 0.82rem;
  color: var(--color-ink-soft);
  text-align: center;
  padding: 16px 8px;
}

.planning-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 12px;
  border-radius: 12px;
  color: var(--color-ink-soft);
  text-align: left;
  transition: all 0.2s ease;
}

.planning-card:hover {
  background-color: var(--color-bg-soft);
  color: var(--color-ink);
}

.planning-card--active {
  background-color: var(--color-primary-light);
  color: var(--color-primary-dark);
}

.planning-card-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.planning-card-title {
  font-weight: 700;
  font-size: 0.9rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.planning-card-meta {
  font-size: 0.72rem;
  opacity: 0.8;
}

.planning-delete-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-ink-soft);
  padding: 6px;
  border-radius: 8px;
  flex-shrink: 0;
  transition: all 0.15s ease;
}

.planning-delete-btn:hover {
  background-color: rgba(239, 68, 68, 0.1);
  color: var(--color-danger);
}

.plannings-overlay {
  position: fixed;
  inset: 0;
  background-color: rgba(15, 23, 42, 0.35);
  z-index: 100;
  backdrop-filter: blur(4px);
}

@media (min-width: 768px) {
  .plannings-overlay {
    display: none;
  }
}

.planning-detail {
  flex: 1;
  min-width: 0;
}

.no-planning-state {
  border-radius: 24px;
  padding: 48px 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 12px;
  color: var(--color-ink-soft);
}

/* --- Planning Header Card --- */
.planning-header-card {
  border-radius: 20px;
  padding: 20px 24px;
  margin-bottom: 24px;
}

.header-display {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
}

.header-main-info {
  flex: 1;
  min-width: 250px;
}

.planning-main-title {
  font-size: 1.4rem;
  font-weight: 800;
  color: var(--color-ink);
  margin: 0 0 6px 0;
}

.planning-main-description {
  font-size: 0.9rem;
  color: var(--color-ink-soft);
  margin: 0;
  line-height: 1.45;
}

.edit-header-btn {
  background-color: var(--color-bg-soft);
  border: 1px solid var(--color-border);
  color: var(--color-ink);
  font-weight: 700;
  font-size: 0.82rem;
  padding: 8px 14px;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  transition: all 0.2s ease;
}

.edit-header-btn:hover {
  background-color: var(--color-border);
}

/* --- Planning Detail Rows (Échéance / Statut / Transaction liée) --- */
.planning-detail-content {
  display: flex;
  flex-direction: column;
}

.planning-detail-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 0;
  border-top: 1px solid var(--color-border);
}

.planning-detail-row:first-of-type {
  border-top: none;
}

.planning-detail-label {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--color-ink-soft);
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.planning-detail-row strong {
  font-size: 0.92rem;
  font-weight: 700;
  color: var(--color-ink);
  text-align: right;
}

.planning-linked-transaction strong {
  color: var(--color-primary-dark);
}

.status-badge {
  display: inline-block;
  font-size: 0.72rem;
  font-weight: 700;
  padding: 4px 12px;
  border-radius: 20px;
  text-transform: capitalize;
  border: 1px solid transparent;
}

.status-badge--en_attente {
  background-color: rgba(245, 158, 11, 0.1);
  color: var(--color-warning);
  border-color: rgba(245, 158, 11, 0.2);
}

.status-badge--realise {
  background-color: rgba(16, 185, 129, 0.1);
  color: var(--color-primary);
  border-color: rgba(16, 185, 129, 0.2);
}

.status-badge--annule {
  background-color: rgba(239, 68, 68, 0.1);
  color: var(--color-danger);
  border-color: rgba(239, 68, 68, 0.2);
}

.planning-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid var(--color-border);
}

.planning-actions .cancel-btn {
  flex: none;
  padding: 8px 14px;
  font-size: 0.82rem;
}

.delete-btn {
  background-color: var(--color-bg-soft);
  border: 1px solid var(--color-border);
  color: var(--color-danger);
  font-weight: 700;
  font-size: 0.82rem;
  padding: 8px 14px;
  border-radius: 10px;
  transition: all 0.2s ease;
}

.delete-btn:hover {
  background-color: rgba(239, 68, 68, 0.08);
  border-color: rgba(239, 68, 68, 0.2);
}

/* --- Planned Transactions Section --- */
.planned-transactions-card {
  border-radius: 24px;
  padding: 24px;
}

.planned-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  margin-bottom: 20px;
}

.section-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--color-ink);
  margin: 0;
}

.section-subtitle {
  font-size: 0.82rem;
  color: var(--color-ink-soft);
  margin: 4px 0 0;
}

.plan-btn {
  background-color: var(--color-primary);
  color: #fff;
  font-weight: 700;
  font-size: 0.88rem;
  padding: 10px 16px;
  border-radius: 12px;
  box-shadow: 0 4px 10px rgba(16, 185, 129, 0.15);
  display: inline-flex;
  align-items: center;
  transition: all 0.2s ease;
}

.plan-btn:hover {
  background-color: var(--color-primary-dark);
  transform: translateY(-1px);
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  text-align: center;
  color: var(--color-ink-soft);
}

.empty-state p {
  margin: 0 0 16px 0;
  font-size: 0.95rem;
}

.empty-cta {
  background-color: var(--color-bg-soft);
  border: 1px solid var(--color-border);
  color: var(--color-primary-dark);
  font-weight: 700;
  font-size: 0.85rem;
  padding: 10px 16px;
  border-radius: 12px;
  transition: all 0.2s ease;
}

.empty-cta:hover {
  background-color: var(--color-border);
}

/* Planned List & Items */
.planned-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.planned-item {
  display: flex;
  align-items: center;
  gap: 14px;
  background-color: rgba(255, 255, 255, 0.4);
  border: 1px solid var(--color-bg-soft);
  padding: 14px 18px;
  border-radius: 18px;
  transition: all 0.2s ease;
}

.planned-item:hover {
  background-color: rgba(255, 255, 255, 0.65);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
}

.planned-type-badge {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.planned-type-badge--revenu {
  background-color: rgba(16, 185, 129, 0.1);
  color: var(--color-primary);
}

.planned-type-badge--depense {
  background-color: rgba(239, 68, 68, 0.1);
  color: var(--color-danger);
}

.planned-details {
  flex: 1;
  min-width: 150px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.planned-desc-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.planned-desc {
  font-weight: 700;
  font-size: 0.95rem;
  color: var(--color-ink);
}

.planned-cat-badge {
  font-size: 0.7rem;
  font-weight: 700;
  background-color: var(--color-bg-soft);
  color: var(--color-ink-soft);
  padding: 2px 8px;
  border-radius: 6px;
  border: 1px solid var(--color-border);
}

.planned-date-row {
  font-size: 0.78rem;
  color: var(--color-ink-soft);
  display: flex;
  align-items: center;
}

.planned-amount-block {
  text-align: right;
  flex-shrink: 0;
}

.planned-amount {
  font-size: 1rem;
  font-weight: 800;
}

.planned-amount--revenu {
  color: var(--color-primary);
}

.planned-amount--depense {
  color: var(--color-danger);
}

.planned-status-actions {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-shrink: 0;
  flex-wrap: wrap;
}

.status-badge-display {
  display: inline-block;
  font-size: 0.72rem;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 20px;
  text-transform: capitalize;
  text-align: center;
  min-width: 76px;
  border: 1px solid transparent;
}

.status-badge-display--en-attente {
  background-color: rgba(245, 158, 11, 0.1);
  color: var(--color-warning);
  border-color: rgba(245, 158, 11, 0.2);
}

.status-badge-display--réalisée {
  background-color: rgba(16, 185, 129, 0.1);
  color: var(--color-primary);
  border-color: rgba(16, 185, 129, 0.2);
}

.status-badge-display--due {
  background-color: rgba(239, 68, 68, 0.1);
  color: var(--color-danger);
  border-color: rgba(239, 68, 68, 0.25);
  animation: due-pulse 1.8s infinite ease-in-out;
}

@keyframes due-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.6; }
}

.realized-checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  user-select: none;
}

.realized-checkbox-label--due {
  font-weight: 700;
  color: var(--color-danger);
}

.realized-checkbox-label--due .realized-checkbox {
  accent-color: var(--color-danger);
}

.realized-checkbox {
  width: 16px;
  height: 16px;
  accent-color: var(--color-primary);
  cursor: pointer;
}

.action-buttons {
  display: flex;
  gap: 6px;
}

.btn-icon {
  background-color: var(--color-bg-soft);
  border: 1px solid var(--color-border);
  color: var(--color-ink-soft);
  width: 32px;
  height: 32px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s ease;
}

.btn-icon:hover {
  background-color: var(--color-border);
  color: var(--color-ink);
}

.btn-icon--danger:hover {
  background-color: rgba(239, 68, 68, 0.1);
  border-color: rgba(239, 68, 68, 0.2);
  color: var(--color-danger);
}

/* --- Modal Overlay & Form --- */
.modal-overlay {
  position: fixed;
  inset: 0;
  background-color: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  z-index: 1000;
  animation: fadeIn 0.25s ease;
}

.modal-card {
  width: 100%;
  max-width: 460px;
  border-radius: 28px;
  padding: 28px;
  box-shadow: 0 20px 50px rgba(15, 23, 42, 0.15);
  position: relative;
  display: flex;
  flex-direction: column;
  animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  box-sizing: border-box;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.modal-title {
  font-size: 1.2rem;
  font-weight: 800;
  color: var(--color-ink);
  margin: 0;
}

.close-btn {
  background: transparent;
  border: none;
  color: var(--color-ink-soft);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px;
  cursor: pointer;
  transition: color 0.15s ease;
}

.close-btn:hover {
  color: var(--color-ink);
}

.modal-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group-row {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-label {
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--color-ink-soft);
  text-transform: uppercase;
}

.form-input,
.form-select {
  width: 100%;
  background-color: var(--color-bg-soft);
  border: 1.5px solid transparent;
  border-radius: 12px;
  padding: 12px 16px;
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--color-ink);
  outline: none;
  transition: all 0.2s ease;
  box-sizing: border-box;
}

.form-textarea {
  font-family: inherit;
  font-weight: 500;
  resize: vertical;
}

.form-input:focus,
.form-select:focus {
  border-color: var(--color-primary);
  background-color: var(--color-paper-raised);
}

.type-toggle-group {
  display: grid;
  grid-template-columns: 1fr 1fr;
  background-color: var(--color-bg-soft);
  padding: 4px;
  border-radius: 14px;
}

.type-btn {
  padding: 10px;
  font-size: 0.9rem;
  font-weight: 700;
  border-radius: 10px;
  color: var(--color-ink-soft);
  transition: all 0.2s ease;
}

.type-btn--depense.active {
  background-color: var(--color-danger);
  color: #fff;
  box-shadow: 0 4px 10px rgba(239, 68, 68, 0.2);
}

.type-btn--revenu.active {
  background-color: var(--color-primary);
  color: #fff;
  box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2);
}

.modal-form-actions {
  display: flex;
  gap: 12px;
  margin-top: 14px;
}

.cancel-btn {
  flex: 1;
  background-color: var(--color-bg-soft);
  color: var(--color-ink-soft);
  padding: 14px;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.92rem;
  text-align: center;
}

.save-btn-modal {
  flex: 1.3;
  background-color: var(--color-primary);
  color: #fff;
  padding: 14px;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.95rem;
  text-align: center;
}

.save-btn-modal:hover {
  background-color: var(--color-primary-dark);
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes slideUp {
  from { transform: translateY(15px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

/* Desktop layout */
@media (min-width: 768px) {
  .planning-container {
    padding: 40px;
  }

  .topbar-title {
    font-size: 1.8rem;
  }
}

/* Responsive adjustments for small screens */
@media (max-width: 480px) {
  .planned-item {
    flex-wrap: wrap;
    gap: 10px;
    padding: 12px 14px;
  }

  .planned-amount-block {
    order: 3;
    flex-grow: 1;
    text-align: left;
    margin-left: 46px;
  }

  .planned-status-actions {
    order: 4;
    width: 100%;
    justify-content: space-between;
    margin-top: 4px;
    border-top: 1px solid var(--color-bg-soft);
    padding-top: 8px;
  }
}
</style>
