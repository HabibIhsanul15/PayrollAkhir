import { getToken } from "./auth";

const API_BASE = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000";

function authHeaders() {
  const token = getToken();
  if (!token) throw new Error("Token login tidak ditemukan. Silakan login ulang.");
  return {
    Authorization: `Bearer ${token}`,
    Accept: "application/json",
    "Content-Type": "application/json",
  };
}

// ✅ NEW: create admin user (FAT/Direktur) tanpa employee
export async function createAdminUser(payload) {
  const res = await fetch(`${API_BASE}/api/admin/users`, {
    method: "POST",
    headers: authHeaders(),
    body: JSON.stringify(payload),
  });

  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data?.message || "Gagal membuat akun.");
  return data;
}
