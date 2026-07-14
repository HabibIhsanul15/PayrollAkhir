import { getToken, clearAuth } from "./auth";

const API_BASE = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000";

/** Build auth headers. Optionally include JSON content type. */
function authHeaders({ json = false } = {}) {
  const token = getToken();
  if (!token) {
    const err = new Error("Token login tidak ditemukan. Silakan login ulang.");
    err.code = "NO_TOKEN";
    throw err;
  }

  const headers = {
    Authorization: `Bearer ${token}`,
    Accept: "application/json",
  };

  if (json) headers["Content-Type"] = "application/json";
  return headers;
}

async function readJson(res) {
  try {
    return await res.json();
  } catch {
    return null;
  }
}

function handle401() {
  try {
    clearAuth?.();
  } catch {
    // Clearing an already empty session is harmless.
  }
  const err = new Error("Sesi habis. Silakan login ulang.");
  err.code = "UNAUTHORIZED";
  throw err;
}

/** Normalisasi error Laravel biar enak dipakai UI */
function buildApiError(res, data, fallbackMsg) {
  const err = new Error(
    data?.message ||
      (data?.errors ? "Validasi gagal. Cek isian." : fallbackMsg || `Request gagal (${res.status}).`)
  );
  err.status = res.status;
  err.payload = data;

  // mapping errors: {field: [msg1, msg2]}
  if (data?.errors && typeof data.errors === "object") {
    err.fieldErrors = Object.fromEntries(
      Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v.join(" ") : String(v)])
    );
  }
  return err;
}

/**
 * Ambil list employee (lite) dengan filter status opsional
 * @param {string|null} status "active" | "inactive" | null
 */
export async function fetchEmployeesLite(status = null) {
  const qs = status ? `?status=${encodeURIComponent(status)}` : "";
  const res = await fetch(`${API_BASE}/api/employees${qs}`, {
    headers: authHeaders(),
  });

  if (res.status === 401) handle401();

  const data = await readJson(res);

  if (!res.ok) {
    throw buildApiError(res, data, `Gagal mengambil data employees (${res.status}).`);
  }

  if (Array.isArray(data)) return data;
  return data?.data ?? data?.value ?? [];
}

export async function fetchCurrentSalaryProfile(employeeId, dateStr) {
  const url = new URL(`${API_BASE}/api/employees/${employeeId}/salary-profile`);
  if (dateStr) url.searchParams.set("date", dateStr);

  const res = await fetch(url.toString(), {
    headers: authHeaders(),
  });

  if (res.status === 401) handle401();

  const data = await readJson(res);

  if (!res.ok) {
    throw buildApiError(res, data, `Gagal mengambil salary profile (${res.status}).`);
  }

  return data;
}

/** Create payroll */
export async function createPayroll(payload) {
  const res = await fetch(`${API_BASE}/api/payrolls`, {
    method: "POST",
    headers: authHeaders({ json: true }),
    body: JSON.stringify(payload),
  });

  if (res.status === 401) handle401();

  const data = await readJson(res);

  if (!res.ok) {
    throw buildApiError(res, data, `Gagal membuat payroll (${res.status}).`);
  }

  return data;
}

/**
 * List payrolls with optional filters (periode, employee_id, status, date_from, date_to, etc)
 * @param {object} filters
 */
export async function fetchPayrolls(filters = {}) {
  const url = new URL(`${API_BASE}/api/payrolls`);
  Object.entries(filters || {}).forEach(([k, v]) => {
    if (v === null || v === undefined || v === "") return;
    url.searchParams.set(k, String(v));
  });

  const res = await fetch(url.toString(), {
    headers: authHeaders(),
  });

  if (res.status === 401) handle401();

  const data = await readJson(res);

  if (!res.ok) {
    throw buildApiError(res, data, `Gagal mengambil data payroll (${res.status}).`);
  }

  // bisa array langsung, atau dibungkus paginate
  if (Array.isArray(data)) return data;
  return data?.data ?? data?.value ?? [];
}

/** Payroll detail (Slip per payroll) */
export async function fetchPayrollDetail(payrollId) {
  const res = await fetch(`${API_BASE}/api/payrolls/${payrollId}`, {
    headers: authHeaders(),
  });

  if (res.status === 401) handle401();

  const data = await readJson(res);

  if (!res.ok) {
    throw buildApiError(res, data, `Gagal mengambil detail payroll (${res.status}).`);
  }

  return data;
}

/** Update payroll (optional, kalau backend kamu support PUT/PATCH) */
export async function updatePayroll(payrollId, payload, method = "PUT") {
  const res = await fetch(`${API_BASE}/api/payrolls/${payrollId}`, {
    method, // "PUT" atau "PATCH"
    headers: authHeaders({ json: true }),
    body: JSON.stringify(payload),
  });

  if (res.status === 401) handle401();

  const data = await readJson(res);

  if (!res.ok) {
    throw buildApiError(res, data, `Gagal update payroll (${res.status}).`);
  }

  return data;
}

/** Delete payroll */
export async function deletePayroll(payrollId) {
  const res = await fetch(`${API_BASE}/api/payrolls/${payrollId}`, {
    method: "DELETE",
    headers: authHeaders(),
  });

  if (res.status === 401) handle401();

  const data = await readJson(res);

  if (!res.ok) {
    throw buildApiError(res, data, `Gagal menghapus payroll (${res.status}).`);
  }

  return data ?? { ok: true };
}

export async function previewCalculation(payload) {
  const res = await fetch(`${API_BASE}/api/payrolls/preview-calculation`, {
    method: "POST",
    headers: authHeaders({ json: true }),
    body: JSON.stringify(payload),
  });
  if (res.status === 401) handle401();
  const data = await readJson(res);
  if (!res.ok) throw buildApiError(res, data, `Gagal memuat preview calculation (${res.status}).`);
  return data ?? null;
}

export async function autoCalculate(payload) {
  const res = await fetch(`${API_BASE}/api/payrolls/auto`, {
    method: "POST",
    headers: authHeaders({ json: true }),
    body: JSON.stringify(payload),
  });
  if (res.status === 401) handle401();
  const data = await readJson(res);
  if (!res.ok) throw buildApiError(res, data, `Gagal kalkulasi otomatis (${res.status}).`);
  return data ?? null;
}

export async function batchGenerate(payload) {
  const res = await fetch(`${API_BASE}/api/payrolls/batch-generate`, {
    method: "POST",
    headers: authHeaders({ json: true }),
    body: JSON.stringify(payload),
  });
  if (res.status === 401) handle401();
  const data = await readJson(res);
  if (!res.ok) throw buildApiError(res, data, `Gagal batch generate (${res.status}).`);
  return data ?? null;
}

export async function recalculatePayroll(id, payload = {}) {
  const res = await fetch(`${API_BASE}/api/payrolls/${id}/recalculate`, {
    method: "POST",
    headers: authHeaders({ json: true }),
    body: JSON.stringify(payload),
  });
  if (res.status === 401) handle401();
  const data = await readJson(res);
  if (!res.ok) throw buildApiError(res, data, `Gagal recalculate (${res.status}).`);
  return data ?? null;
}

export async function overrideAllowance(payrollId, allowanceId, payload) {
  const res = await fetch(`${API_BASE}/api/payrolls/${payrollId}/allowances/${allowanceId}`, {
    method: "PATCH",
    headers: authHeaders({ json: true }),
    body: JSON.stringify(payload),
  });
  if (res.status === 401) handle401();
  const data = await readJson(res);
  if (!res.ok) throw buildApiError(res, data, `Gagal override allowance (${res.status}).`);
  return data ?? null;
}

