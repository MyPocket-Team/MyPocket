import axios from "axios";

const API_BASE_URL = import.meta.env.VITE_API_URL || "http://localhost:8000/api";

// Base à utiliser pour les fichiers publics servis par Laravel (ex: /storage/...),
// dérivée de l'URL de l'API plutôt que codée en dur, pour fonctionner aussi bien en
// local qu'une fois déployé (Render, etc.).
export const storageBaseUrl = API_BASE_URL.replace(/\/api\/?$/, "");

export function formatStorageUrl(path) {
  if (!path) return "";
  if (path.startsWith("http://") || path.startsWith("https://") || path.startsWith("blob:")) {
    return path;
  }
  return `${storageBaseUrl}/storage/${path}`;
}

const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 30000,
  headers: {
    Accept: "application/json",
  },
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem("mypocket_token");
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem("mypocket_token");
      window.location.href = "/connexion";
    }
    return Promise.reject(error);
  }
);

export default api;