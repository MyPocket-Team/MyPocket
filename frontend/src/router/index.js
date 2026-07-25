import { createRouter, createWebHistory } from "vue-router";

const routes = [
  {
    path: "/",
    name: "splash",
    component: () => import("../views/SplashView.vue"),
  },
  {
    path: "/bienvenue",
    name: "welcome",
    component: () => import("../views/WelcomeView.vue"),
  },
  {
    path: "/connexion",
    name: "login",
    component: () => import("../views/LoginView.vue"),
  },
  {
    path: "/inscription",
    name: "register",
    component: () => import("../views/RegisterView.vue"),
  },
  {
    path: "/mot-de-passe-oublie",
    name: "forgot-password",
    component: () => import("../views/ForgotPasswordView.vue"),
  },
  {
    path: "/verification-code",
    name: "verify-code",
    component: () => import("../views/VerifyCodeView.vue"),
  },
  {
    path: "/reinitialiser-mot-de-passe",
    name: "reset-password",
    component: () => import("../views/ResetPasswordView.vue"),
  },
  {
  path: "/initialiser-solde",
  name: "init-balance",
  component: () => import("../views/InitBalanceView.vue"),
},
{
  path: "/dashboard",
  name: "dashboard",
  component: () => import("../views/DashboardView.vue"),
},
{
  path: "/transactions/nouvelle",
  name: "add-transaction",
  component: () => import("../views/AddTransactionView.vue"),
},
{
  path: "/planning",
  name: "planning",
  component: () => import("../views/PlanningView.vue"),
},
{
  path: "/historique",
  name: "history",
  component: () => import("../views/HistoryView.vue"),
},
{
  path: "/transactions/:id",
  name: "transaction-detail",
  component: () => import("../views/TransactionDetailView.vue"),
},
{
  path: "/statistiques",
  name: "stats",
  component: () => import("../views/StatsView.vue"),
},
{
  path: "/chatbot",
  name: "chatbot",
  component: () => import("../views/ChatbotView.vue"),
},
{
  path: "/profil",
  name: "profile",
  component: () => import("../views/ProfileView.vue"),
},
{
  path: "/profil/modifier",
  name: "edit-profile",
  component: () => import("../views/EditProfileView.vue"),
},
{
  path: "/admin",
  name: "admin-overview",
  component: () => import("../views/AdminOverviewView.vue"),
},
{
  path: "/admin/utilisateurs",
  name: "admin-users",
  component: () => import("../views/AdminUsersView.vue"),
},
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

export default router;