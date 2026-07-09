import { getToken, clearAuth } from "./auth";

const BASE_URL = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000";
const API_PREFIX = "/api";

function normalizePath(path) {
  let cleanPath = path.startsWith("/") ? path : `/${path}`;
  if (!cleanPath.startsWith(API_PREFIX)) cleanPath = `${API_PREFIX}${cleanPath}`;
  return cleanPath;
}

/**
 * api(path, options)
 * - body bisa berupa object (akan dijadikan JSON)
 * - body bisa berupa FormData (untuk upload file)
 * - responseType: "json" (default) | "text" | "blob"
 */
export async function api(
  path,
  {
    method = "GET",
    body,
    responseType = "json", // "json" | "text" | "blob"
    headers: extraHeaders,
  } = {}
) {
  const url = `${BASE_URL}${normalizePath(path)}`;

  const token = getToken();
  const headers = {
    Accept: "application/json",
    ...(extraHeaders || {}),
  };

  if (token && token !== "undefined" && token !== "null") {
    headers.Authorization = `Bearer ${token}`;
  }

  const isFormData =
    typeof FormData !== "undefined" && body instanceof FormData;

  // ✅ kalau body FormData: JANGAN set Content-Type (biar boundary otomatis)
  // ✅ kalau body object biasa: set JSON
  let finalBody = undefined;
  if (body !== undefined && body !== null) {
    if (isFormData) {
      finalBody = body;
    } else {
      headers["Content-Type"] = "application/json";
      finalBody = JSON.stringify(body);
    }
  }

  const res = await fetch(url, {
    method,
    headers,
    body: finalBody,
  });

  // 401 => logout
  if (res.status === 401) {
    clearAuth();
  }

  // handle response
  let data = null;

  if (responseType === "blob") {
    data = await res.blob();
  } else if (responseType === "text") {
    data = await res.text();
  } else {
    // default json
    const text = await res.text();
    try {
      data = text ? JSON.parse(text) : null;
    } catch {
      data = text; // fallback
    }
  }

  if (!res.ok) {
    const msg =
      data && typeof data === "object" && data.message
        ? data.message
        : `HTTP ${res.status}`;

    const err = new Error(msg);
    err.status = res.status;
    err.data = data;
    err.url = url;
    throw err;
  }

  return data;
}

/**
 * helper kalau nanti mau download file bukti transfer pakai token
 * contoh: const blob = await apiBlob(`/payrolls/${id}/proof`);
 */
export async function apiBlob(path, opts = {}) {
  return api(path, { ...opts, responseType: "blob" });
}
