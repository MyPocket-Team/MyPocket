<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import api from "../services/api";
import NotificationBell from "../components/NotificationBell.vue";

const router = useRouter();
const route = useRoute();

// ── Mode Selection ──
const mode = ref("manual"); // "manual" | "text" | "audio" | "scan"

// ── Form Fields ──
const description = ref("");
const montant = ref("");
const selectedCategoryId = ref("");
const customCategorie = ref("");
const type = ref("depense");
const dateTransaction = ref(new Date().toISOString().slice(0, 10));

const isEditMode = ref(false);
const editTxId = ref(null);
const pageTitle = computed(() => (isEditMode.value ? "Modifier la transaction" : "Nouvelle transaction"));

// ── AI Assist State ──
const isProcessing = ref(false);
const missingFields = ref([]);
const freeText = ref("");
const audioState = ref("idle");
const fileInput = ref(null);

// ── Save State ──
const error = ref("");
const saving = ref(false);

const backendCategories = ref([]);

// ── Review multi-transactions ──
const reviewMode = ref(false);
const reviewSource = ref(null); // 'text' | 'recu' | 'audio'
const reviewTraitementId = ref(null); // uuid pour recu/audio, null pour texte
const reviewItems = ref([]);
const reviewSubmitting = ref(false);

function buildReviewItems(transactions, source) {
  return transactions.map((t) => {
    let categorie_id = "";
    let nouvelle_categorie = "";
    const catName = t.categorie_suggeree;
    if (catName) {
      const found = backendCategories.value.find(
        (c) => c.nom.toLowerCase() === catName.toLowerCase()
      );
      if (found) {
        categorie_id = found.id;
      } else {
        nouvelle_categorie = catName;
      }
    }
    return {
      montant: t.montant ?? "",
      description: t.description ?? "",
      type: t.type === "revenu" ? "revenu" : "depense",
      date_transaction: t.date || new Date().toISOString().slice(0, 10),
      categorie_id,
      nouvelle_categorie,
      statut: "realise",
      source,
    };
  });
}

function removeReviewItem(index) {
  reviewItems.value.splice(index, 1);
}

function cancelReview() {
  reviewMode.value = false;
  reviewItems.value = [];
  mode.value = "manual";
}

async function submitReview() {
  error.value = "";
  if (!reviewItems.value.length) return;

  for (const item of reviewItems.value) {
    if (!item.montant || isNaN(parseFloat(item.montant)) || parseFloat(item.montant) <= 0) {
      error.value = "Chaque transaction doit avoir un montant valide.";
      return;
    }
    if (!item.categorie_id && !item.nouvelle_categorie.trim()) {
      error.value = "Choisis une catégorie pour chaque transaction.";
      return;
    }
  }

  reviewSubmitting.value = true;
  try {
    const entries = reviewItems.value.map((item) => ({
      statut: item.statut,
      montant: parseFloat(item.montant),
      type: item.type,
      description: item.description?.trim() || null,
      date_transaction: item.date_transaction,
      ...(item.categorie_id
        ? { categorie_id: item.categorie_id }
        : { nouvelle_categorie: item.nouvelle_categorie.trim() }),
      source: item.source,
    }));

    const url =
      reviewSource.value === "recu"
        ? `/recus/${reviewTraitementId.value}/valider`
        : reviewSource.value === "audio"
        ? `/audios/${reviewTraitementId.value}/valider`
        : "/transactions/valider-extraction";

    await api.post(url, { entries });

    if (reviewTraitementId.value) {
      await api.patch(`/traitements/${reviewTraitementId.value}/finaliser`);
    }

    router.push({ name: "dashboard" });
  } catch (e) {
    error.value = e.response?.data?.message || "Impossible d'enregistrer les transactions.";
  } finally {
    reviewSubmitting.value = false;
  }
}

async function fetchCategories() {
  try {
    const res = await api.get("/categories");
    backendCategories.value = res.data.data || res.data;
  } catch (e) {
    console.error("Error fetching categories", e);
  }
}

async function fetchTransactionForEdit(id) {
  try {
    const res = await api.get(`/transactions/${id}`);
    const t = res.data.data || res.data;
    description.value = t.description || "";
    montant.value = t.montant ? t.montant.toString() : "";
    type.value = t.type || "depense";
    dateTransaction.value = t.date_transaction ? t.date_transaction.slice(0, 10) : new Date().toISOString().slice(0, 10);
    if (t.categorie?.id) {
      selectedCategoryId.value = t.categorie.id;
      customCategorie.value = "";
    } else if (t.categorie?.nom) {
      selectedCategoryId.value = "Autre";
      customCategorie.value = t.categorie.nom;
    }
  } catch (e) {
    console.error("Unable to load transaction for edit", e);
    error.value = e.response?.data?.message || "Impossible de charger la transaction pour modification.";
  }
}

async function initializeEditMode() {
  const editId = route.query.edit;
  if (editId) {
    isEditMode.value = true;
    editTxId.value = editId;
    await fetchTransactionForEdit(editId);
  }
}

onMounted(() => {
  fetchCategories();
  initializeEditMode();
});

const categories = computed(() => {
  return [...backendCategories.value, { id: "Autre", nom: "Autre" }];
});

function goBack() {
  router.back();
}

function selectMode(newMode) {
  mode.value = newMode;
  error.value = "";
}

// ── Polling status helper ──
// Le traitement (reçu/audio) tourne désormais de façon synchrone côté serveur, donc le
// premier poll obtient généralement déjà le résultat final. On garde un polling court
// avec un nombre d'essais limité (au lieu d'un setInterval infini) pour éviter que
// l'utilisateur reste bloqué sur "Analyse en cours..." sans aucun message en cas de
// souci réseau ou serveur.
const POLL_MAX_ATTEMPTS = 30; // 30 x 2s = 60s max

async function pollStatus(traitementId, isAudio = false) {
  const url = isAudio ? `/audios/${traitementId}/statut` : `/recus/${traitementId}/statut`;
  let attempts = 0;

  const interval = setInterval(async () => {
    attempts += 1;
    try {
      const res = await api.get(url);
      if (res.data.status === "termine") {
        clearInterval(interval);
        isProcessing.value = false;

        const extracted = res.data.data;
        const transactions = Array.isArray(extracted?.transactions) ? extracted.transactions : [];

        if (!transactions.length) {
          error.value = "Aucune transaction n'a pu être détectée. Réessaie ou saisis manuellement.";
          mode.value = "manual";
          return;
        }

        reviewItems.value = buildReviewItems(transactions, isAudio ? "ia_audio" : "ia_recu");
        reviewSource.value = isAudio ? "audio" : "recu";
        reviewTraitementId.value = traitementId;
        reviewMode.value = true;
      } else if (res.data.status === "echec") {
        clearInterval(interval);
        isProcessing.value = false;
        error.value = res.data.message || "L'extraction a échoué.";
      } else if (attempts >= POLL_MAX_ATTEMPTS) {
        clearInterval(interval);
        isProcessing.value = false;
        error.value = "L'analyse prend trop de temps. Réessaie ou saisis manuellement.";
      }
    } catch (e) {
      clearInterval(interval);
      isProcessing.value = false;
      error.value = "Erreur lors du suivi du traitement.";
    }
  }, 2000);
}

// ── AI Extraction ──
let mediaRecorder = null;
let audioChunks = [];

async function startAudio() {
  audioState.value = "recording";
  audioChunks = [];
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    mediaRecorder = new MediaRecorder(stream);
    mediaRecorder.ondataavailable = (event) => {
      audioChunks.push(event.data);
    };
    mediaRecorder.onstop = async () => {
      const audioBlob = new Blob(audioChunks, { type: "audio/webm" });
      isProcessing.value = true;
      error.value = "";
      try {
        const formData = new FormData();
        formData.append("audio", audioBlob, "recording.webm");
        const res = await api.post("/audios/upload", formData, {
          headers: { "Content-Type": "multipart/form-data" }
        });
        pollStatus(res.data.traitement_id, true);
      } catch (e) {
        isProcessing.value = false;
        error.value = e.response?.data?.message || "Erreur lors de l'envoi de l'audio.";
      }
    };
    mediaRecorder.start();

    // Stop recording automatically after 4 seconds
    setTimeout(() => {
      if (mediaRecorder && mediaRecorder.state === "recording") {
        mediaRecorder.stop();
        stream.getTracks().forEach((track) => track.stop());
        audioState.value = "idle";
      }
    }, 4000);
  } catch (e) {
    audioState.value = "idle";
    error.value = "Impossible d'accéder au microphone.";
  }
}

function triggerFilePicker() {
  fileInput.value?.click();
}

async function handleFileChange(event) {
  const file = event.target.files?.[0];
  // Permet de re-sélectionner la même photo (ex: nouvelle tentative après échec) :
  // sans ça, le navigateur ne redéclenche pas l'évènement "change" pour un fichier identique.
  event.target.value = "";
  if (!file) return;

  isProcessing.value = true;
  error.value = "";
  try {
    const formData = new FormData();
    formData.append("photo", file);
    const res = await api.post("/recus/upload", formData, {
      headers: { "Content-Type": "multipart/form-data" }
    });
    pollStatus(res.data.traitement_id, false);
  } catch (e) {
    isProcessing.value = false;
    error.value = e.response?.data?.message || "Erreur lors de l'envoi du reçu.";
  }
}

async function analyzeText() {
  if (!freeText.value.trim()) return;
  isProcessing.value = true;
  error.value = "";
  try {
    const res = await api.post("/transactions/parse-text", { texte: freeText.value });
    const transactions = Array.isArray(res.data.transactions) ? res.data.transactions : [];

    if (!transactions.length) {
      error.value = "Aucune transaction détectée dans ce texte.";
      return;
    }

    reviewItems.value = buildReviewItems(transactions, "texte");
    reviewSource.value = "text";
    reviewTraitementId.value = null;
    reviewMode.value = true;
  } catch (e) {
    error.value = "Erreur lors de l'analyse du texte.";
  } finally {
    isProcessing.value = false;
  }
}

// ── Save Transaction ──
async function handleSave() {
  error.value = "";

  if (!description.value.trim()) {
    error.value = "La description est requise.";
    return;
  }
  if (!montant.value || isNaN(parseFloat(montant.value)) || parseFloat(montant.value) <= 0) {
    error.value = "Saisis un montant valide supérieur à 0.";
    return;
  }
  if (!selectedCategoryId.value) {
    error.value = "Sélectionne une catégorie.";
    return;
  }

  if (selectedCategoryId.value === "Autre" && !customCategorie.value.trim()) {
    error.value = "Entre une catégorie personnalisée.";
    return;
  }

  saving.value = true;
  try {
    const payload = {
      description: description.value.trim(),
      montant: parseFloat(montant.value),
      type: type.value,
      date_transaction: dateTransaction.value,
      source: mode.value === "scan" ? "ia_recu" : (mode.value === "audio" ? "ia_audio" : "manuel")
    };

    if (selectedCategoryId.value === "Autre") {
      payload.nouvelle_categorie = customCategorie.value.trim();
    } else {
      payload.categorie_id = selectedCategoryId.value;
    }

    const request = isEditMode.value
      ? api.put(`/transactions/${editTxId.value}`, payload)
      : api.post("/transactions", payload);

    const res = await request;
    if (res.data?.solde_actuel !== undefined) {
      localStorage.setItem("mypocket_solde", res.data.solde_actuel.toString());
    }

    if (isEditMode.value) {
      router.push({ name: "transaction-detail", params: { id: editTxId.value } });
    } else {
      router.push({ name: "dashboard" });
    }
  } catch (e) {
    error.value = e.response?.data?.message || "Impossible d'enregistrer la transaction.";
  } finally {
    saving.value = false;
  }
}

const isFormValid = computed(() => {
  return (
    description.value.trim() &&
    montant.value &&
    parseFloat(montant.value) > 0 &&
    selectedCategoryId.value &&
    (selectedCategoryId.value !== "Autre" || customCategorie.value.trim())
  );
});
</script>

<template>
  <main class="page-container">
    <!-- Topbar -->
    <header class="topbar">
      <button type="button" class="back-btn" @click="goBack" aria-label="Retour">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M15 18l-6-6 6-6" />
        </svg>
      </button>
      <h1 class="topbar-title">{{ pageTitle }}</h1>
      <NotificationBell />
    </header>

    <div class="page-body">
      <!-- Input mode selector -->
      <section class="mode-section glass-panel">
        <p class="assist-label">Comment veux-tu saisir ta transaction ?</p>

        <div class="mode-row">
          <button type="button" class="mode-btn" :class="{ 'mode-btn--active': mode === 'manual' }" @click="selectMode('manual')">
            <span class="mode-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
              </svg>
            </span>
            Manuel
          </button>

          <button type="button" class="mode-btn" :class="{ 'mode-btn--active': mode === 'text' }" @click="selectMode('text')">
            <span class="mode-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 6h16M4 12h10M4 18h13" />
              </svg>
            </span>
            Texte
          </button>

          <button type="button" class="mode-btn" :class="{ 'mode-btn--active': mode === 'audio' }" @click="selectMode('audio')">
            <span class="mode-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="9" y="2" width="6" height="12" rx="3" />
                <path d="M5 10v1a7 7 0 0 0 14 0v-1M12 18v4M8 22h8" />
              </svg>
            </span>
            Audio
          </button>

          <button type="button" class="mode-btn" :class="{ 'mode-btn--active': mode === 'scan' }" @click="selectMode('scan')">
            <span class="mode-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 8V5a1 1 0 0 1 1-1h3M20 8V5a1 1 0 0 0-1-1h-3M4 16v3a1 1 0 0 0 1 1h3M20 16v3a1 1 0 0 1-1 1h-3" />
                <circle cx="12" cy="12" r="3.5" />
              </svg>
            </span>
            Scanner
          </button>
        </div>
      </section>

      <!-- AI Input Panels -->
      <section v-if="mode === 'text' && !reviewMode" class="assist-section glass-panel">
        <div class="input-with-button">
          <input
            v-model="freeText"
            type="text"
            placeholder="Ex : J'ai dépensé 5000 FCFA en transport hier"
            class="text-input"
            :disabled="isProcessing"
            @keyup.enter="analyzeText"
          />
          <button type="button" class="analyze-btn" @click="analyzeText" :disabled="isProcessing || !freeText.trim()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" />
            </svg>
            Analyser
          </button>
        </div>
        <p class="assist-hint">L'analyse extraira la description, le montant et tentera de détecter la catégorie.</p>
      </section>

      <section v-else-if="mode === 'audio' && !reviewMode" class="assist-section glass-panel">
        <template v-if="audioState === 'idle' && !isProcessing">
          <button type="button" class="record-btn" @click="startAudio">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="9" y="2" width="6" height="12" rx="3" />
              <path d="M5 10v1a7 7 0 0 0 14 0v-1M12 18v4M8 22h8" />
            </svg>
          </button>
          <p class="assist-hint">Appuie pour décrire ta dépense à l'oral</p>
        </template>
        <template v-else-if="audioState === 'recording'">
          <div class="record-btn record-btn--active">
            <span class="pulse"></span>
          </div>
          <p class="assist-hint text-recording">Écoute en cours...</p>
        </template>
      </section>

      <section v-else-if="mode === 'scan' && !reviewMode" class="assist-section glass-panel">
        <input ref="fileInput" type="file" accept="image/*,.heic,.heif" capture="environment" class="hidden-input" @change="handleFileChange" />
        <button type="button" class="scan-btn" @click="triggerFilePicker" :disabled="isProcessing">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 8V5a1 1 0 0 1 1-1h3M20 8V5a1 1 0 0 0-1-1h-3M4 16v3a1 1 0 0 0 1 1h3M20 16v3a1 1 0 0 1-1 1h-3" />
            <circle cx="12" cy="12" r="3.5" />
          </svg>
          Prendre une photo/Téléverser
        </button>
        <p class="assist-hint">Scannez ou Téléversez votre reçu papier pour extraire les données automatiquement</p>
      </section>

      <!-- Processing spinner -->
      <div v-if="isProcessing" class="processing-container glass-panel">
        <span class="spinner"></span>
        <p class="processing">Analyse en cours...</p>
      </div>

      <!-- Review multi-transactions -->
      <section v-if="reviewMode" class="form-section glass-panel">
        <h2 class="section-title" style="margin-bottom: 4px;">
          {{ reviewItems.length }} transaction(s) détectée(s)
        </h2>
        <p class="assist-hint" style="text-align: left; margin-bottom: 16px;">
          Vérifie, corrige si besoin, puis valide pour tout enregistrer.
        </p>

        <div v-for="(item, i) in reviewItems" :key="i" class="form-section" style="margin-bottom: 16px; border: 1.5px solid var(--color-border);">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <div class="type-toggle" style="flex: 1; margin: 0 12px 0 0;">
              <button type="button" class="type-btn type-btn--depense" :class="{ 'type-btn--active': item.type === 'depense' }" @click="item.type = 'depense'">Dépense</button>
              <button type="button" class="type-btn type-btn--revenu" :class="{ 'type-btn--active': item.type === 'revenu' }" @click="item.type = 'revenu'">Revenu</button>
            </div>
            <button type="button" class="btn-icon btn-icon--danger" @click="removeReviewItem(i)" aria-label="Retirer cette transaction">✕</button>
          </div>

          <div class="input-group">
            <label class="input-label">Montant</label>
            <input v-model="item.montant" type="number" step="0.01" min="0.01" class="field" />
          </div>
          <div class="input-group">
            <label class="input-label">Description</label>
            <input v-model="item.description" type="text" class="field" />
          </div>
          <div class="input-group">
            <label class="input-label">Catégorie</label>
            <select v-model="item.categorie_id" class="field">
              <option value="">— Autre / nouvelle catégorie —</option>
              <option v-for="cat in backendCategories" :key="cat.id" :value="cat.id">{{ cat.nom }}</option>
            </select>
            <input
              v-if="!item.categorie_id"
              v-model="item.nouvelle_categorie"
              type="text"
              placeholder="Nom de la catégorie"
              class="field"
              style="margin-top: 8px;"
            />
          </div>
          <div class="input-group">
            <label class="input-label">Date</label>
            <input v-model="item.date_transaction" type="date" class="field" />
          </div>
        </div>

        <p v-if="error" class="error">{{ error }}</p>

        <div style="display: flex; gap: 12px;">
          <button type="button" class="submit" style="background: var(--color-bg-soft); color: var(--color-ink); flex: 1;" @click="cancelReview">
            Annuler
          </button>
          <button type="button" class="submit" style="flex: 2;" :disabled="reviewSubmitting || !reviewItems.length" @click="submitReview">
            {{ reviewSubmitting ? "Enregistrement..." : `Valider (${reviewItems.length})` }}
          </button>
        </div>
      </section>

      <!-- Main Form (always visible in manual mode) -->
      <section v-if="mode === 'manual' && !reviewMode" class="form-section glass-panel">
        <!-- Transaction Type Toggle -->
        <div class="type-toggle">
          <button
            type="button"
            class="type-btn type-btn--depense"
            :class="{ 'type-btn--active': type === 'depense' }"
            @click="type = 'depense'"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <line x1="12" y1="5" x2="12" y2="19" />
              <polyline points="19 12 12 19 5 12" />
            </svg>
            Dépense
          </button>
          <button
            type="button"
            class="type-btn type-btn--revenu"
            :class="{ 'type-btn--active': type === 'revenu' }"
            @click="type = 'revenu'"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <line x1="12" y1="19" x2="12" y2="5" />
              <polyline points="5 12 12 5 19 12" />
            </svg>
            Revenu
          </button>
        </div>

        <form class="form" @submit.prevent="handleSave">
          <!-- Amount Field (prominent) -->
          <div class="amount-field">
            <div class="amount-wrapper" :class="type === 'revenu' ? 'amount-wrapper--income' : 'amount-wrapper--expense'">
              <span class="amount-sign">{{ type === 'revenu' ? '+' : '-' }}</span>
              <input
                v-model="montant"
                type="number"
                step="0.01"
                min="0.01"
                inputmode="decimal"
                placeholder="0"
                class="amount-input"
              />
              <span class="amount-currency">FCFA</span>
            </div>
          </div>

          <!-- Description -->
          <div class="input-group">
            <label class="input-label">Description</label>
            <input
              v-model="description"
              type="text"
              placeholder="Ex: Courses alimentaires, Salaire…"
              class="field"
              :class="{ 'field--missing': missingFields.includes('description') }"
            />
          </div>

          <!-- Category -->
          <div class="input-group">
            <label class="input-label">Catégorie</label>
            <div class="category-chips">
              <button
                v-for="cat in categories"
                :key="cat.id"
                type="button"
                class="chip"
                :class="{ 'chip--active': selectedCategoryId === cat.id }"
                @click="selectedCategoryId = cat.id"
              >
                {{ cat.nom }}
              </button>
            </div>
            <!-- Custom category input field shown if 'Autre' is selected -->
            <div v-if="selectedCategoryId === 'Autre'" class="custom-category-wrapper" style="margin-top: 12px;">
              <input
                v-model="customCategorie"
                type="text"
                placeholder="Indiquez le nom de la catégorie personnalisée"
                class="field"
                required
              />
            </div>
          </div>

          <!-- Date -->
          <div class="input-group">
            <label class="input-label">Date</label>
            <input
              v-model="dateTransaction"
              type="date"
              class="field"
            />
          </div>

          <p v-if="error" class="error">{{ error }}</p>

          <button
            type="submit"
            class="submit"
            :disabled="saving"
          >
            {{ saving ? "Enregistrement..." : "Enregistrer la transaction" }}
          </button>
        </form>
      </section>
    </div>
  </main>
</template>

<style scoped>
.page-container {
  padding: 24px 20px 80px;
  max-width: 680px;
  margin: 0 auto;
}

/* Topbar */
.topbar {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 28px;
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
  flex-shrink: 0;
}

.topbar-title {
  flex: 1;
  font-size: 1.4rem;
  font-weight: 700;
  color: var(--color-ink);
}

/* Page body */
.page-body {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

/* Mode Selector */
.mode-section {
  border-radius: 20px;
  padding: 20px;
}

.assist-label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--color-ink-soft);
  text-align: center;
  margin-bottom: 16px;
}

.mode-row {
  display: flex;
  gap: 10px;
}

.mode-btn {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 7px;
  background-color: var(--color-bg-soft);
  border: 1.5px solid transparent;
  border-radius: 14px;
  padding: 14px 6px;
  font-weight: 600;
  font-size: 0.78rem;
  color: var(--color-ink-soft);
  transition: all 0.2s ease;
}

.mode-btn:hover {
  color: var(--color-primary);
  border-color: var(--color-primary-light);
}

.mode-btn--active {
  border-color: var(--color-primary);
  color: var(--color-primary-dark);
  background-color: var(--color-primary-light);
}

.mode-icon {
  display: flex;
  align-items: center;
  justify-content: center;
}

/* AI Assist Panels */
.assist-section {
  border-radius: 20px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
}

.input-with-button {
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: 100%;
}

.text-input {
  width: 100%;
  border: 1.5px solid var(--color-border);
  border-radius: 14px;
  padding: 14px 18px;
  font-size: 0.95rem;
  color: var(--color-ink);
  outline: none;
  background-color: var(--color-paper-raised);
  transition: border-color 0.15s ease;
}

.text-input:focus {
  border-color: var(--color-primary);
}

.analyze-btn, .scan-btn {
  width: 100%;
  background-color: var(--color-primary);
  color: #fff;
  font-weight: 700;
  font-size: 0.92rem;
  padding: 14px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
}

.analyze-btn:hover, .scan-btn:hover {
  background-color: var(--color-primary-dark);
}

.analyze-btn:disabled, .scan-btn:disabled {
  opacity: 0.6;
}

.assist-hint {
  font-size: 0.82rem;
  color: var(--color-ink-soft);
  font-weight: 500;
  text-align: center;
}

.text-recording {
  color: var(--color-danger);
  font-weight: 600;
}

/* Audio recording */
.record-btn {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background-color: var(--color-danger);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
}

.record-btn--active {
  background-color: #dc2626;
  box-shadow: 0 0 0 10px rgba(239, 68, 68, 0.15);
}

.pulse {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background-color: #fff;
  animation: pulse 1.2s infinite ease-in-out;
}

@keyframes pulse {
  0%, 100% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.3); opacity: 0.5; }
}

.hidden-input {
  display: none;
}

/* Processing spinner */
.processing-container {
  border-radius: 20px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid var(--color-border);
  border-top-color: var(--color-primary);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.processing {
  font-size: 0.88rem;
  color: var(--color-ink-soft);
  font-style: italic;
}

/* AI Prefilled Banner */
.prefilled-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  background-color: var(--color-primary-light);
  color: var(--color-primary-dark);
  border-radius: 12px;
  padding: 12px 16px;
  font-size: 0.85rem;
  font-weight: 600;
}

/* Form Section */
.form-section {
  border-radius: 24px;
  padding: 24px;
}

/* Type Toggle */
.type-toggle {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  background-color: var(--color-bg-soft);
  border-radius: 16px;
  padding: 5px;
  margin-bottom: 24px;
}

.type-btn {
  border-radius: 12px;
  padding: 12px;
  font-weight: 700;
  font-size: 0.9rem;
  color: var(--color-ink-soft);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  transition: all 0.2s ease;
}

.type-btn--active.type-btn--depense {
  background-color: #ef4444;
  color: #fff;
  box-shadow: 0 3px 10px rgba(239, 68, 68, 0.2);
}

.type-btn--active.type-btn--revenu {
  background-color: var(--color-primary);
  color: #fff;
  box-shadow: 0 3px 10px rgba(16, 185, 129, 0.2);
}

/* Amount Field */
.amount-field {
  margin-bottom: 24px;
}

.amount-wrapper {
  display: flex;
  align-items: center;
  gap: 10px;
  border: 2px solid var(--color-border);
  border-radius: 18px;
  padding: 16px 20px;
  background-color: var(--color-bg-soft);
  transition: all 0.2s ease;
}

.amount-wrapper--expense {
  border-color: rgba(239, 68, 68, 0.2);
  background-color: rgba(239, 68, 68, 0.04);
}

.amount-wrapper--income {
  border-color: rgba(16, 185, 129, 0.2);
  background-color: rgba(16, 185, 129, 0.04);
}

.amount-wrapper:focus-within {
  border-color: var(--color-primary);
  background-color: var(--color-paper-raised);
}

.amount-sign {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--color-ink-soft);
}

.amount-input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  font-size: 1.8rem;
  font-weight: 800;
  color: var(--color-ink);
  text-align: right;
  width: 100%;
}

.amount-input::-webkit-outer-spin-button,
.amount-input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

.amount-currency {
  font-weight: 700;
  font-size: 0.95rem;
  color: var(--color-ink-soft);
  white-space: nowrap;
}

/* Form fields */
.form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.input-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.input-label {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--color-ink-soft);
  text-transform: uppercase;
  letter-spacing: 0.07em;
  padding-left: 2px;
}

.field {
  width: 100%;
  background-color: var(--color-bg-soft);
  border: 1.5px solid transparent;
  border-radius: 14px;
  padding: 14px 18px;
  font-size: 0.95rem;
  color: var(--color-ink);
  outline: none;
  transition: all 0.2s ease;
}

.field:focus {
  border-color: var(--color-primary);
  background-color: var(--color-paper-raised);
}

.field--missing {
  border-color: var(--color-warning);
}

/* Category Chips */
.category-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.chip {
  background-color: var(--color-bg-soft);
  border: 1.5px solid transparent;
  border-radius: 99px;
  padding: 8px 16px;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--color-ink-soft);
  transition: all 0.2s ease;
}

.chip:hover {
  border-color: var(--color-primary-light);
  color: var(--color-primary-dark);
}

.chip--active {
  background-color: var(--color-primary-light);
  border-color: var(--color-primary);
  color: var(--color-primary-dark);
}

/* Error and Submit */
.error {
  font-size: 0.85rem;
  color: var(--color-danger);
  text-align: center;
  font-weight: 600;
  padding: 10px;
  background-color: rgba(239, 68, 68, 0.07);
  border-radius: 10px;
}

.submit {
  background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
  color: #fff;
  font-weight: 700;
  font-size: 1rem;
  padding: 16px;
  border-radius: 14px;
  margin-top: 8px;
  box-shadow: 0 4px 15px rgba(16, 185, 129, 0.25);
  transition: all 0.2s ease;
}

.submit:hover {
  box-shadow: 0 6px 20px rgba(16, 185, 129, 0.35);
  transform: translateY(-1px);
}

.submit:disabled {
  opacity: 0.6;
  transform: none;
}

@media (min-width: 768px) {
  .page-container {
    padding: 40px 40px 60px;
  }

  .topbar-title {
    font-size: 1.8rem;
  }

  .input-with-button {
    flex-direction: row;
    align-items: center;
  }

  .analyze-btn {
    width: auto;
    white-space: nowrap;
    padding-left: 24px;
    padding-right: 24px;
    flex-shrink: 0;
  }
}
</style>
