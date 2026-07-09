const API_BASE = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000";

async function readJson(res) {
  try {
    return await res.json();
  } catch {
    return null;
  }
}

export async function registerStaff(payload) {
  const res = await fetch(`${API_BASE}/api/register`, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: JSON.stringify(payload),
  });

  const data = await readJson(res);

  if (!res.ok) {
    const msg =
      data?.message ||
      (data?.errors ? "Validasi gagal. Cek input." : `Register gagal (${res.status}).`);

    const err = new Error(msg);
    err.status = res.status;
    err.payload = data; // ✅ penting untuk field errors di UI
    throw err;
  }

  return data;
}
