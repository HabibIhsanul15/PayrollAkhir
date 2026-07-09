const TOKEN_KEY = "payroll_token";
const USER_KEY  = "payroll_user";

function notifyAuthChanged() {
  window.dispatchEvent(new Event("auth:changed"));
}

export function saveAuth(token, user) {
  if (token) localStorage.setItem(TOKEN_KEY, token);
  else localStorage.removeItem(TOKEN_KEY);

  if (user) localStorage.setItem(USER_KEY, JSON.stringify(user));
  else localStorage.removeItem(USER_KEY);

  notifyAuthChanged();
}

export function updateAuthUser(patch) {
  const raw = localStorage.getItem(USER_KEY);
  if (!raw || raw === "undefined" || raw === "null") return;

  let u = null;
  try { u = JSON.parse(raw); } catch { return; }
  if (!u) return;

  const next = { ...u, ...patch };
  localStorage.setItem(USER_KEY, JSON.stringify(next));

  notifyAuthChanged(); // ✅ ganti ini
}

export function getToken() {
  return localStorage.getItem(TOKEN_KEY);
}

export function getUser() {
  const raw = localStorage.getItem(USER_KEY);
  if (!raw || raw === "undefined" || raw === "null") return null;

  try {
    return JSON.parse(raw);
  } catch (e) {
    console.warn("Invalid payroll_user in localStorage:", raw);
    localStorage.removeItem(USER_KEY);
    return null;
  }
}

export function clearAuth() {
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(USER_KEY);

  notifyAuthChanged();
}

export function isAuthed() {
  return !!getToken();
}
