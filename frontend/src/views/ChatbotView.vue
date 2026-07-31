<script setup>
import { ref, nextTick, computed, onMounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "../services/api";

const router = useRouter();
const route = useRoute();

function goBack() {
  router.back();
}

const conversations = ref([]);
const activeConversationId = ref(null);
const messages = ref([]);
const input = ref("");
const isTyping = ref(false);
const panelOpen = ref(false);
const chatEnd = ref(null);
const textareaRef = ref(null);

function autoResizeInput() {
  const el = textareaRef.value;
  if (!el) return;
  el.style.height = "auto";
  el.style.height = Math.min(el.scrollHeight, 160) + "px";
}

function handleInputKeydown(e) {
  if (e.key === "Enter" && !e.shiftKey) {
    e.preventDefault();
    sendMessage();
  }
}

function mapConversation(conv) {
  return { id: conv.id, title: conv.titre || "Nouvelle discussion" };
}

async function fetchConversations() {
  try {
    const res = await api.get("/chatbot/conversations");
    const remote = res.data.data || res.data;
    conversations.value = (remote || []).map(mapConversation);

    const requestedId = route.query.conversation ? parseInt(route.query.conversation, 10) : null;
    const requested = requestedId && conversations.value.find((c) => c.id === requestedId);

    if (requested) {
      await openConversation(requested);
    } else if (conversations.value.length) {
      await openConversation(conversations.value[0]);
    } else {
      messages.value = [];
    }
  } catch (e) {
    console.error("Unable to load chatbot conversations", e);
  }
}

async function fetchMessages(conversationId) {
  try {
    const res = await api.get(`/chatbot/conversations/${conversationId}/messages`);
    const remote = res.data.data || res.data;
    messages.value = (remote || []).map((message) => ({
      role: message.role === "assistant" ? "bot" : "user",
      text: message.contenu,
    }));
    await scrollToEnd();
  } catch (e) {
    console.error("Unable to load chatbot messages", e);
  }
}

function syncConversationQuery(id) {
  if (String(route.query.conversation || "") !== String(id || "")) {
    router.replace({ query: { ...route.query, conversation: id || undefined } });
  }
}

async function openConversation(conv) {
  activeConversationId.value = conv.id;
  panelOpen.value = false;
  syncConversationQuery(conv.id);
  await fetchMessages(conv.id);
}

async function startNewConversation() {
  try {
    const res = await api.post("/chatbot/conversations");
    const conv = mapConversation(res.data.data || res.data);
    conversations.value.unshift(conv);
    activeConversationId.value = conv.id;
    messages.value = [];
    panelOpen.value = false;
    syncConversationQuery(conv.id);
  } catch (e) {
    console.error("Unable to create a new conversation", e);
  }
}

// Le sous-menu "Discussions" de la sidebar principale (App.vue) navigue vers
// /chatbot?conversation=<id> ; comme Vue Router réutilise cette instance de
// composant (même route), on écoute le changement de query pour refléter la
// sélection sans reload.
watch(
  () => route.query.conversation,
  (newVal) => {
    const requestedId = newVal ? parseInt(newVal, 10) : null;
    const conv = requestedId && conversations.value.find((c) => c.id === requestedId);
    if (conv && conv.id !== activeConversationId.value) {
      openConversation(conv);
    }
  }
);

async function ensureActiveConversation() {
  if (activeConversationId.value) return activeConversationId.value;
  const res = await api.post("/chatbot/conversations");
  const conv = mapConversation(res.data.data || res.data);
  conversations.value.unshift(conv);
  activeConversationId.value = conv.id;
  return conv.id;
}

async function scrollToEnd() {
  await nextTick();
  chatEnd.value?.scrollIntoView({ behavior: "smooth" });
}

async function sendMessage() {
  const text = input.value.trim();
  if (!text) return;

  input.value = "";
  await nextTick();
  autoResizeInput();

  try {
    const conversationId = await ensureActiveConversation();

    messages.value.push({ role: "user", text });
    scrollToEnd();
    isTyping.value = true;

    const res = await api.post("/chatbot/messages", { message: text, conversation_id: conversationId });
    const assistant = res.data.reponse;
    if (assistant) {
      messages.value.push({ role: "bot", text: assistant.contenu });
    }

    const updatedConv = res.data.conversation;
    const currentConv = conversations.value.find((conv) => conv.id === conversationId);
    if (currentConv && updatedConv?.titre) {
      currentConv.title = updatedConv.titre;
    }

    await scrollToEnd();
  } catch (e) {
    messages.value.push({ role: "bot", text: "Une erreur est survenue. Réessaie plus tard." });
  } finally {
    isTyping.value = false;
  }
}

const hasMessages = computed(() => messages.value.length > 0);

onMounted(() => {
  fetchConversations();
});
</script>

<template>
  <main class="chatbot-container">
    <!-- Split Layout Grid for Desktop, overlay drawer for mobile -->
    <div class="chatbot-workspace">
      
      <!-- Left side: Recent conversations list (Desktop Permanent, Mobile Hidden Drawer) -->
      <aside class="recent-sidebar" :class="{ 'recent-sidebar--open': panelOpen }">
        <div class="sidebar-top">
          <span class="sidebar-title">Discussions</span>
          <button type="button" class="close-panel-btn mobile-only" @click="panelOpen = false" aria-label="Fermer">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 6L6 18M6 6l12 12" />
            </svg>
          </button>
        </div>

        <button type="button" class="new-chat-btn" @click="startNewConversation">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19" />
            <line x1="5" y1="12" x2="19" y2="12" />
          </svg>
          Nouvelle discussion
        </button>

        <div class="conv-scrollable-list">
          <button
            v-for="conv in conversations"
            :key="conv.id"
            type="button"
            class="conv-card"
            :class="{ 'conv-card--active': conv.id === activeConversationId }"
            @click="openConversation(conv)"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
            </svg>
            <span class="conv-title">{{ conv.title }}</span>
          </button>
        </div>
      </aside>

      <!-- Sidebar Overlay for mobile -->
      <div v-if="panelOpen" class="panel-overlay mobile-only" @click="panelOpen = false"></div>

      <!-- Right side: Active Chat Stream -->
      <section class="active-chat-section">
        <!-- Topbar -->
        <header class="chat-topbar">
          <button type="button" class="back-btn" @click="goBack" aria-label="Retour">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M15 18l-6-6 6-6" />
            </svg>
          </button>
          
          <div class="chat-header-info">
            <h1 class="chat-title">Assistant Budget</h1>
            <span class="chat-status">
              <span class="status-dot"></span>
              En ligne
            </span>
          </div>

          <!-- History Toggle for Mobile -->
          <button type="button" class="history-toggle mobile-only" @click="panelOpen = true" aria-label="Conversations récentes">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="9" />
              <path d="M12 7v5l3.5 2" />
            </svg>
          </button>

          <!-- New Conversation for Desktop (le tiroir "Discussions" est masqué sur desktop) -->
          <button type="button" class="new-chat-topbar-btn" @click="startNewConversation">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="12" y1="5" x2="12" y2="19" />
              <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Nouvelle discussion
          </button>
        </header>

        <!-- Message List -->
        <div class="chat-messages-area">
          <div v-if="!hasMessages" class="empty-state">
            <span class="empty-icon">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" />
              </svg>
            </span>
            <p class="empty-title">Pose-moi des questions sur tes finances</p>
            <p class="empty-hint">Ex: "Combien puis-je dépenser cette semaine ?" ou "Analyse mon budget"</p>
          </div>

          <div
            v-for="(m, i) in messages"
            :key="i"
            class="message-wrapper"
            :class="m.role === 'user' ? 'message-wrapper--user' : 'message-wrapper--bot'"
          >
            <div class="message-bubble" :class="m.role === 'user' ? 'message-bubble--user' : 'message-bubble--bot'">
              {{ m.text }}
            </div>
          </div>

          <!-- Typing indicator -->
          <div v-if="isTyping" class="message-wrapper message-wrapper--bot">
            <div class="message-bubble message-bubble--bot typing-bubble">
              <span class="dot"></span>
              <span class="dot"></span>
              <span class="dot"></span>
            </div>
          </div>

          <div ref="chatEnd"></div>
        </div>

        <!-- Input Bar -->
        <form class="chat-input-bar" @submit.prevent="sendMessage">
          <div class="input-container">
            <textarea
              ref="textareaRef"
              v-model="input"
              rows="1"
              placeholder="Écris ton message pour le coach..."
              @input="autoResizeInput"
              @keydown="handleInputKeydown"
            ></textarea>
            <button type="submit" class="send-btn" :disabled="!input.trim()" aria-label="Envoyer">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="22" y1="2" x2="11" y2="13" />
                <polygon points="22 2 15 22 11 13 2 9 22 2" />
              </svg>
            </button>
          </div>
        </form>
      </section>

    </div>
  </main>
</template>

<style scoped>
/* Le chat est épinglé directement aux bords du viewport avec position:fixed
   plutôt que de dépendre d'une hauteur calculée à travers .app-content
   (grid) > .chatbot-container (flex) > .active-chat-section (flex) : cette
   chaîne est fragile (vh vs dvh, min-height:auto implicite des items de
   grille) et laissait parfois toute la page défiler au lieu de seulement
   la conversation, faisant disparaître le header et décoller la barre de
   saisie de la nav bar. Fixed = comportement garanti quel que soit l'état
   des parents.
   Décalage à droite de 280px sur desktop pour laisser la place à la
   sidebar principale (.app-sidebar), et 68px en bas sur mobile pour la
   barre de navigation (.app-bottom-bar). */
.chatbot-container {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 68px;
  width: auto;
  display: flex;
  overflow: hidden;
}

@media (min-width: 768px) {
  .chatbot-container {
    left: 280px;
    bottom: 0;
  }
}

.chatbot-workspace {
  display: flex;
  width: 100%;
  height: 100%;
  position: relative;
}

/* Sidebar conversations list */
.recent-sidebar {
  display: flex;
  flex-direction: column;
  background-color: var(--color-paper-raised);
  border-right: 1px solid var(--color-border);
  width: 280px;
  height: 100%;
  padding: 24px 18px;
  gap: 16px;
  flex-shrink: 0;
  transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  z-index: 101;
}

/* Mobile Sidebar Drawer styling */
@media (max-width: 767px) {
  .recent-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    transform: translateX(-100%);
  }

  .recent-sidebar--open {
    transform: translateX(0);
  }
}

.sidebar-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.sidebar-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--color-ink);
}

.close-panel-btn {
  color: var(--color-ink-soft);
  padding: 4px;
}

.new-chat-btn {
  background-color: var(--color-primary-light);
  color: var(--color-primary-dark);
  border-radius: 12px;
  padding: 12px 14px;
  font-weight: 700;
  font-size: 0.88rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
}

.new-chat-btn:hover {
  background-color: rgba(16, 185, 129, 0.2);
}

.conv-scrollable-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  overflow-y: auto;
  flex: 1;
}

.conv-card {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px;
  border-radius: 12px;
  color: var(--color-ink-soft);
  text-align: left;
  transition: all 0.2s ease;
  font-weight: 600;
  font-size: 0.85rem;
}

.conv-card:hover {
  background-color: var(--color-bg-soft);
  color: var(--color-ink);
}

.conv-card--active {
  background-color: var(--color-primary-light);
  color: var(--color-primary-dark);
}

.conv-title {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Sidebar Overlay */
.panel-overlay {
  position: fixed;
  inset: 0;
  background-color: rgba(15, 23, 42, 0.35);
  z-index: 100;
  backdrop-filter: blur(4px);
}

/* Active chat panel */
.active-chat-section {
  flex: 1;
  display: flex;
  flex-direction: column;
  height: 100%;
  background-color: var(--color-bg);
  position: relative;
}

/* Chat Header */
.chat-topbar {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  background-color: var(--color-paper-raised);
  border-bottom: 1px solid var(--color-border);
  height: 68px;
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

.chat-header-info {
  position: absolute;
  left: 50%;
  top: 50%;
  transform: translate(-50%, -50%);
  display: flex;
  flex-direction: column;
  align-items: center;
  pointer-events: none;
}

.chat-title {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--color-ink);
}

.chat-status {
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--color-primary-dark);
  display: flex;
  align-items: center;
  gap: 4px;
}

.status-dot {
  width: 6px;
  height: 6px;
  background-color: var(--color-primary);
  border-radius: 50%;
  display: inline-block;
}

.history-toggle {
  background: var(--color-bg-soft);
  color: var(--color-ink-soft);
  border-radius: 50%;
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Le tiroir "Discussions" (avec son bouton Nouvelle discussion) est masqué
   sur desktop (cf. .recent-sidebar), donc ce bouton prend le relais dans le
   topbar pour garder l'action accessible sur tous les écrans. */
.new-chat-topbar-btn {
  display: none;
}

/* Message area */
.chat-messages-area {
  flex: 1;
  overflow-y: auto;
  padding: 24px 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.empty-state {
  margin: auto;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 12px;
  max-width: 340px;
}

.empty-icon {
  width: 52px;
  height: 52px;
  border-radius: 16px;
  background-color: var(--color-primary-light);
  color: var(--color-primary-dark);
  display: flex;
  align-items: center;
  justify-content: center;
}

.empty-title {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--color-ink);
}

.empty-hint {
  font-size: 0.8rem;
  color: var(--color-ink-soft);
  line-height: 1.5;
}

.message-wrapper {
  display: flex;
  width: 100%;
}

.message-wrapper--user {
  justify-content: flex-end;
}

.message-wrapper--bot {
  justify-content: flex-start;
}

.message-bubble {
  max-width: 78%;
  padding: 12px 18px;
  font-size: 0.92rem;
  line-height: 1.5;
}

.message-bubble--user {
  background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
  color: #fff;
  border-radius: 20px 20px 4px 20px;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
}

.message-bubble--bot {
  background-color: var(--color-paper-raised);
  color: var(--color-ink);
  border: 1px solid var(--color-border);
  border-radius: 20px 20px 20px 4px;
}

/* Typing bubble styling */
.typing-bubble {
  display: flex;
  gap: 4px;
  align-items: center;
  padding: 16px 20px;
}

.dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: var(--color-ink-soft);
  animation: bounce 1.2s infinite ease-in-out;
}

.dot:nth-child(2) { animation-delay: 0.15s; }
.dot:nth-child(3) { animation-delay: 0.3s; }

@keyframes bounce {
  0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
  30% { transform: translateY(-4px); opacity: 1; }
}

/* Input Bar */
.chat-input-bar {
  padding: 16px 20px 24px;
  background-color: var(--color-paper-raised);
  border-top: 1px solid var(--color-border);
}

.input-container {
  display: flex;
  align-items: flex-end;
  background-color: var(--color-bg);
  border: 1.5px solid var(--color-border);
  border-radius: 18px;
  padding: 6px 6px 6px 16px;
  gap: 8px;
  transition: all 0.2s ease;
}

.input-container:focus-within {
  border-color: var(--color-primary);
  background-color: var(--color-paper-raised);
}

.input-container textarea {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  resize: none;
  font-family: inherit;
  font-size: 0.95rem;
  line-height: 1.4;
  color: var(--color-ink);
  padding: 8px 0;
  max-height: 160px;
  overflow-y: auto;
}

.send-btn {
  width: 38px;
  height: 38px;
  border-radius: 12px;
  background-color: var(--color-primary);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-bottom: 1px;
}

.send-btn:hover {
  background-color: var(--color-primary-dark);
}

.send-btn:disabled {
  opacity: 0.5;
  background-color: var(--color-ink-soft);
}

/* Large Screens */
@media (min-width: 768px) {
  /* La sélection d'une conversation se fait depuis le sous-menu de la
     sidebar principale (App.vue) — cette liste interne ne sert qu'au
     tiroir mobile. */
  .recent-sidebar {
    display: none;
  }

  .new-chat-topbar-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background-color: var(--color-primary-light);
    color: var(--color-primary-dark);
    border-radius: 10px;
    padding: 8px 14px;
    font-weight: 700;
    font-size: 0.82rem;
  }

  .new-chat-topbar-btn:hover {
    background-color: rgba(16, 185, 129, 0.2);
  }

  .chat-messages-area {
    padding: 32px 40px;
  }

  .chat-input-bar {
    padding: 24px 40px;
  }
}
</style>