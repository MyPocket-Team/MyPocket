<script setup>
import { ref, computed, onMounted, watch } from "vue";
import api from "../services/api";

const users = ref([]);
const searchQuery = ref("");
const statusFilter = ref("Tous"); // 'Tous' | 'Actif' | 'Suspendu'
const currentPage = ref(1);
const itemsPerPage = ref(6);
const totalUsersCount = ref(0);
const loading = ref(false);
const error = ref("");

function getInitials(name) {
  if (!name) return "";
  const parts = name.split(" ");
  return parts.map((p) => p[0]).join("").toUpperCase().slice(0, 2);
}

async function fetchUsers(page = 1) {
  loading.value = true;
  error.value = "";
  try {
    const response = await api.get("/admin/users", {
      params: {
        recherche: searchQuery.value || undefined,
        statut: statusFilter.value === "Tous" ? undefined : statusFilter.value.toLowerCase(),
        per_page: itemsPerPage.value,
        page,
      },
    });

    const rawUsers = response.data.data || response.data;
    users.value = rawUsers.map((user) => ({
      id: user.id,
      name: `${user.prenom || ""} ${user.nom || ""}`.trim() || user.email,
      email: user.email,
      dateInscrit: new Date(user.created_at).toLocaleDateString("fr-FR", {
        day: "2-digit",
        month: "long",
        year: "numeric",
      }),
      status: user.actif ? "Actif" : "Suspendu",
      actif: !!user.actif,
    }));

    totalUsersCount.value = response.data.meta?.total ?? users.value.length;
    currentPage.value = response.data.meta?.current_page ?? page;
  } catch (e) {
    error.value = e.response?.data?.message || "Impossible de charger la liste des utilisateurs.";
  } finally {
    loading.value = false;
  }
}

async function toggleUserStatus(user) {
  const actif = user.actif === false || user.actif === 0 ? true : !user.actif;
  try {
    const response = await api.patch(`/admin/users/${user.id}/statut`, { actif });
    const updated = response.data.user;
    user.actif = updated?.actif ?? actif;
    user.status = user.actif ? "Actif" : "Suspendu";
  } catch (e) {
    error.value = e.response?.data?.message || "Impossible de mettre à jour le statut de l'utilisateur.";
  }
}

async function deleteUser(id) {
  if (!confirm("Êtes-vous sûr de vouloir supprimer cet utilisateur ? Cette action est irréversible.")) {
    return;
  }

  try {
    await api.delete(`/admin/users/${id}`);
    await fetchUsers(currentPage.value);
  } catch (e) {
    error.value = e.response?.data?.message || "Impossible de supprimer l'utilisateur.";
  }
}

const totalPages = computed(() => Math.ceil(totalUsersCount.value / itemsPerPage.value) || 1);

const startItemIndex = computed(() => {
  if (totalUsersCount.value === 0) return 0;
  return (currentPage.value - 1) * itemsPerPage.value + 1;
});

const endItemIndex = computed(() => Math.min(currentPage.value * itemsPerPage.value, totalUsersCount.value));

const paginatedUsers = computed(() => users.value);

function setPage(page) {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page;
    fetchUsers(page);
  }
}

function handleFilterClick(filter) {
  statusFilter.value = filter;
  currentPage.value = 1;
  fetchUsers(1);
}

watch([searchQuery, statusFilter], () => {
  currentPage.value = 1;
  fetchUsers(1);
});

onMounted(() => {
  fetchUsers();
});
</script>

<template>
  <main class="admin-users-container">
    <!-- Header -->
    <header class="admin-header-panel">
      <div class="header-meta">GESTION DES COMPTES</div>
      <h1 class="admin-title">Utilisateurs</h1>
      <p class="admin-subtitle">Suspension, réactivation ou suppression — aucun accès au détail des transactions.</p>
    </header>

    <!-- Controls Bar -->
    <section class="controls-card glass-panel">
      <div class="search-box">
        <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8" />
          <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
        <input 
          v-model="searchQuery" 
          type="text" 
          placeholder="Rechercher un nom ou un email..." 
          class="search-input" 
        />
      </div>

      <div class="filter-tabs">
        <button 
          type="button" 
          class="tab-btn" 
          :class="{ 'tab-btn--active': statusFilter === 'Tous' }"
          @click="handleFilterClick('Tous')"
        >
          Tous
        </button>
        <button 
          type="button" 
          class="tab-btn" 
          :class="{ 'tab-btn--active': statusFilter === 'Actif' }"
          @click="handleFilterClick('Actif')"
        >
          Actifs
        </button>
        <button 
          type="button" 
          class="tab-btn" 
          :class="{ 'tab-btn--active': statusFilter === 'Suspendu' }"
          @click="handleFilterClick('Suspendu')"
        >
          Suspendus
        </button>
      </div>
    </section>

    <!-- Users Table Container -->
    <section class="table-card glass-panel">
      <div class="table-responsive">
        <table class="users-table">
          <thead>
            <tr>
              <th>UTILISATEUR</th>
              <th>INSCRIT LE</th>
              <th>STATUT</th>
              <th class="actions-header">ACTIONS</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="paginatedUsers.length === 0">
              <td colspan="4" class="empty-row">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-ink-soft); margin-bottom: 8px;">
                  <circle cx="12" cy="12" r="10" />
                  <line x1="8" y1="12" x2="16" y2="12" />
                </svg>
                <p>Aucun utilisateur trouvé.</p>
              </td>
            </tr>
            <tr v-for="user in paginatedUsers" :key="user.id" class="user-row">
              <!-- Avatar + Info -->
              <td>
                <div class="user-profile-cell">
                  <div class="avatar-circle">
                    {{ getInitials(user.name) }}
                  </div>
                  <div class="user-details">
                    <span class="user-name">{{ user.name }}</span>
                    <span class="user-email">{{ user.email }}</span>
                  </div>
                </div>
              </td>

              <!-- Date registered -->
              <td class="date-cell">{{ user.dateInscrit }}</td>

              <!-- Status badge -->
              <td>
                <span class="status-badge" :class="`status-badge--${user.status.toLowerCase()}`">
                  <span class="badge-dot"></span>
                  {{ user.status }}
                </span>
              </td>

              <!-- Action buttons -->
              <td>
                <div class="actions-cell">
                  <!-- Suspend / Activate toggle button -->
                  <button 
                    type="button" 
                    class="action-btn" 
                    :title="user.status === 'Actif' ? 'Suspendre' : 'Réactiver'"
                    @click="toggleUserStatus(user)"
                  >
                    <!-- Play icon if suspended, otherwise Pause icon -->
                    <svg v-if="user.status === 'Suspendu'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                      <polygon points="5 3 19 12 5 21 5 3" />
                    </svg>
                    <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                      <rect x="6" y="4" width="4" height="16" />
                      <rect x="14" y="4" width="4" height="16" />
                    </svg>
                  </button>

                  <!-- Delete button -->
                  <button 
                    type="button" 
                    class="action-btn action-btn--danger" 
                    title="Supprimer le compte"
                    @click="deleteUser(user.id)"
                  >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                      <polyline points="3 6 5 6 21 6" />
                      <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination and stats footer -->
      <footer class="table-footer">
        <span class="footer-stats">
          {{ startItemIndex }}-{{ endItemIndex }} sur {{ totalUsersCount }} comptes
        </span>

        <div class="pagination-controls">
          <button 
            type="button" 
            class="page-btn" 
            :disabled="currentPage === 1"
            @click="setPage(currentPage - 1)"
          >
            &lt;
          </button>
          
          <button 
            v-for="page in totalPages" 
            :key="page" 
            type="button" 
            class="page-btn"
            :class="{ 'page-btn--active': currentPage === page }"
            @click="setPage(page)"
          >
            {{ page }}
          </button>

          <button 
            type="button" 
            class="page-btn" 
            :disabled="currentPage === totalPages"
            @click="setPage(currentPage + 1)"
          >
            &gt;
          </button>
        </div>
      </footer>
    </section>
  </main>
</template>

<style scoped>
.admin-users-container {
  padding: 24px 20px 48px;
  max-width: 1200px;
  margin: 0 auto;
}

.admin-header-panel {
  margin-bottom: 32px;
}

.header-meta {
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--color-ink-soft);
  letter-spacing: 0.1em;
  margin-bottom: 8px;
}

.admin-title {
  font-size: 1.8rem;
  font-weight: 800;
  color: var(--color-ink);
  margin: 0;
}

.admin-subtitle {
  font-size: 0.95rem;
  color: var(--color-ink-soft);
  margin-top: 4px;
}

/* Controls Card */
.controls-card {
  padding: 16px 20px;
  border-radius: 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  margin-bottom: 24px;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
}

.search-box {
  position: relative;
  flex: 1;
  min-width: 260px;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 16px;
  color: var(--color-ink-soft);
  pointer-events: none;
}

.search-input {
  width: 100%;
  padding: 12px 16px 12px 48px;
  border-radius: 14px;
  background-color: var(--color-bg-soft);
  border: 1.5px solid transparent;
  outline: none;
  font-size: 0.92rem;
  font-weight: 600;
  color: var(--color-ink);
  transition: all 0.2s ease;
}

.search-input:focus {
  border-color: var(--color-primary);
  background-color: var(--color-white);
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.08);
}

.filter-tabs {
  display: flex;
  background-color: var(--color-bg-soft);
  padding: 4px;
  border-radius: 12px;
  gap: 2px;
}

.tab-btn {
  padding: 8px 18px;
  font-size: 0.88rem;
  font-weight: 700;
  border-radius: 10px;
  color: var(--color-ink-soft);
  transition: all 0.2s ease;
}

.tab-btn--active {
  background-color: var(--color-ink);
  color: var(--color-white);
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.15);
}

/* Table Card */
.table-card {
  border-radius: 24px;
  overflow: hidden;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
  padding: 8px;
}

.table-responsive {
  width: 100%;
  overflow-x: auto;
}

.users-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}

.users-table th {
  padding: 18px 24px;
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--color-ink-soft);
  letter-spacing: 0.05em;
  border-bottom: 1.5px solid var(--color-bg-soft);
  background-color: transparent;
}

.actions-header {
  text-align: right;
}

.user-row {
  border-bottom: 1px solid var(--color-bg-soft);
  transition: background-color 0.2s ease;
}

.user-row:hover {
  background-color: rgba(241, 245, 249, 0.4);
}

.user-row:last-child {
  border-bottom: none;
}

.user-profile-cell {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px 24px;
}

.avatar-circle {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background-color: var(--color-bg-soft);
  color: var(--color-ink-soft);
  font-weight: 700;
  font-size: 0.95rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--color-border);
}

.user-details {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.user-name {
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--color-ink);
}

.user-email {
  font-size: 0.82rem;
  color: var(--color-ink-soft);
}

.date-cell {
  padding: 16px 24px;
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--color-ink-soft);
}

/* Status Badges */
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 99px;
  font-size: 0.8rem;
  font-weight: 700;
}

.status-badge--actif {
  background-color: rgba(16, 185, 129, 0.08);
  color: var(--color-primary-dark);
}

.status-badge--actif .badge-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: var(--color-primary);
}

.status-badge--suspendu {
  background-color: rgba(245, 158, 11, 0.08);
  color: #b45309;
}

.status-badge--suspendu .badge-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: var(--color-warning);
}

/* Actions Cell */
.actions-cell {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding: 16px 24px;
}

.action-btn {
  background: var(--color-bg-soft);
  border: 1px solid var(--color-border);
  color: var(--color-ink-soft);
  width: 34px;
  height: 34px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.action-btn:hover {
  background-color: var(--color-ink);
  color: var(--color-white);
  border-color: var(--color-ink);
}

.action-btn--danger:hover {
  background-color: var(--color-danger);
  color: var(--color-white);
  border-color: var(--color-danger);
}

.empty-row {
  text-align: center;
  padding: 48px;
  color: var(--color-ink-soft);
  font-size: 0.95rem;
}

/* Table Footer & Pagination */
.table-footer {
  padding: 16px 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  border-top: 1.5px solid var(--color-bg-soft);
}

.footer-stats {
  font-size: 0.88rem;
  font-weight: 600;
  color: var(--color-ink-soft);
}

.pagination-controls {
  display: flex;
  gap: 6px;
  align-items: center;
}

.page-btn {
  min-width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
  font-weight: 700;
  background-color: var(--color-bg-soft);
  border: 1px solid var(--color-border);
  color: var(--color-ink-soft);
  transition: all 0.2s ease;
  padding: 0 6px;
}

.page-btn:hover:not(:disabled) {
  background-color: var(--color-ink);
  color: var(--color-white);
  border-color: var(--color-ink);
}

.page-btn--active {
  background-color: var(--color-ink) !important;
  color: var(--color-white) !important;
  border-color: var(--color-ink) !important;
}

.page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
</style>
