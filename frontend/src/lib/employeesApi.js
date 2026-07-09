import { getToken, clearAuth } from "@/lib/auth";

const BASE = import.meta.env.VITE_API_BASE ?? "http://127.0.0.1:8000";

/**
 * @param {string|null} status - "active" | "inactive" | null
 */
export async function fetchEmployees(status = null) {
  const token = getToken();

  const qs = status ? `?status=${status}` : "";

  const res = await fetch(`${BASE}/api/employees${qs}`, {
    headers: {
      Accept: "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });

  // token invalid / expired
  if (res.status === 401) {
    clearAuth();
    throw new Error("Sesi habis. Silakan login ulang.");
  }

  const data = await res.json().catch(() => null);

  if (!res.ok) {
    const msg = data?.message ?? `Gagal load employees (${res.status})`;
    throw new Error(msg);
  }

  return Array.isArray(data) ? data : (data?.value ?? []);
}
