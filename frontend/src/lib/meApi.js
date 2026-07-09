// src/lib/meApi.js
import { getToken, clearAuth } from "./auth";

const API_BASE = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000";

/**
 * Header auth + optional JSON content-type
 */
function authHeaders(json = false) {
  const token = getToken();

  if (!token || token === "undefined" || token === "null") {
    const err = new Error("Token login tidak ditemukan. Silakan login ulang.");
    err.code = 401;
    throw err;
  }

  const h = {
    Authorization: `Bearer ${token}`,
    Accept: "application/json",
  };

  if (json) h["Content-Type"] = "application/json";
  return h;
}

async function readJson(res) {
  try {
    return await res.json();
  } catch {
    return null;
  }
}

function handle401() {
  clearAuth?.();
  const err = new Error("Sesi habis. Silakan login ulang.");
  err.code = 401;
  throw err;
}

function makeErr(message, code, payload) {
  const err = new Error(message);
  err.code = code;
  err.payload = payload;
  return err;
}

/**
 * Laravel biasanya ngirim:
 * { message: "...", errors: { field: ["..."] } }
 * Ini ambil message terbaik untuk user.
 */
function pickLaravelMessage(data, fallback) {
  if (!data) return fallback;

  if (typeof data?.message === "string" && data.message.trim()) return data.message;

  const errors = data?.errors;
  if (errors && typeof errors === "object") {
    const keys = Object.keys(errors);
    if (keys.length) {
      const firstVal = errors[keys[0]];
      if (Array.isArray(firstVal) && firstVal[0]) return firstVal[0];
      if (typeof firstVal === "string") return firstVal;
    }
  }

  return fallback;
}

/**
 * helper request agar konsisten
 * - support JSON body
 * - support FormData body (kalau nanti perlu upload)
 * - tidak mengirim body untuk GET
 */
async function request(
  path,
  { method = "GET", json = false, body } = {}
) {
  const upper = String(method || "GET").toUpperCase();
  const isGetLike = upper === "GET" || upper === "HEAD";

  // detect FormData (jangan JSON.stringify & jangan set Content-Type manual)
  const isFormData =
    typeof FormData !== "undefined" && body instanceof FormData;

  const headers = isFormData ? authHeaders(false) : authHeaders(json);

  const opts = {
    method: upper,
    headers,
  };

  if (!isGetLike) {
    if (isFormData) {
      opts.body = body;
    } else if (json) {
      opts.body = JSON.stringify(body ?? {});
    } else if (body != null) {
      // kalau suatu saat mau kirim raw text/Blob
      opts.body = body;
    }
  }

  const res = await fetch(`${API_BASE}${path}`, opts);

  if (res.status === 401) handle401();

  const data = await readJson(res);

  if (!res.ok) {
    const fallback = `Request gagal (${res.status})`;
    throw makeErr(pickLaravelMessage(data, fallback), res.status, data);
  }

  return data;
}

/**
 * =========================
 * ME
 * =========================
 */

/** GET /api/me */
export async function fetchMe() {
  return request("/api/me", { method: "GET" });
}

/**
 * PUT /api/me
 * Backend kamu: butuh { name } (required)
 * (kalau nanti kamu tambah email, tinggal kirim email juga)
 */
export async function updateMe(payload) {
  return request("/api/me", { method: "PUT", json: true, body: payload });
}

/**
 * PUT /api/me/password
 * Backend kamu validasi:
 * - current_password (required)
 * - password (required, min 8, confirmed)
 * Jadi FE harus kirim:
 * { current_password, password, password_confirmation }
 */
export async function updatePassword(payload) {
  return request("/api/me/password", { method: "PUT", json: true, body: payload });
}

/**
 * =========================
 * EMPLOYEE (ME) - STAFF ONLY
 * =========================
 */

/** GET /api/me/employee */
export async function fetchMeEmployee() {
  return request("/api/me/employee", { method: "GET" });
}

/** PUT /api/me/employee */
export async function updateMeEmployee(payload) {
  return request("/api/me/employee", { method: "PUT", json: true, body: payload });
}
