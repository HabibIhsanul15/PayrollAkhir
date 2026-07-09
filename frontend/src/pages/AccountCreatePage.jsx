import { useEffect, useMemo, useState } from "react";
import { Navigate, useNavigate } from "react-router-dom";
import { getUser, isAuthed } from "@/lib/auth";
import { api } from "@/lib/api";
import { fetchEmployees } from "@/lib/employeesApi";

import { Button } from "@/components/ui/button";
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";

const DEFAULT_FORM = {
  employee_id: "",
  name: "", // auto dari employee (read-only)
  email: "",
  role: "staff", // staff/hcga/fat/director
  password: "",
  password_confirmation: "",
};

function randomPassword(len = 12) {
  const chars =
    "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%^&*_-+=";
  let out = "";
  const arr = new Uint32Array(len);
  crypto.getRandomValues(arr);
  for (let i = 0; i < len; i++) out += chars[arr[i] % chars.length];
  return out;
}

export default function AccountCreatePage() {
  const nav = useNavigate();

  const [user, setUser] = useState(() => getUser());
  const [form, setForm] = useState(DEFAULT_FORM);

  const [submitting, setSubmitting] = useState(false);
  const [loadingEmp, setLoadingEmp] = useState(true);
  const [employees, setEmployees] = useState([]);

  const [err, setErr] = useState("");
  const [ok, setOk] = useState("");
  const [createdUser, setCreatedUser] = useState(null);

  const [showPw, setShowPw] = useState(false);
  const [lastGeneratedPw, setLastGeneratedPw] = useState("");

  // ===== sync session user =====
  useEffect(() => {
    const sync = () => setUser(getUser());
    window.addEventListener("auth:changed", sync);
    window.addEventListener("storage", sync);
    return () => {
      window.removeEventListener("auth:changed", sync);
      window.removeEventListener("storage", sync);
    };
  }, []);

  const authed = isAuthed();
  const role = String(user?.role || "").toLowerCase();

  // ✅ FINAL RULE: hanya HCGA boleh create account
  const allowed = useMemo(() => role === "hcga", [role]);

  const loadingSession = authed && !user;

  function setField(k, v) {
    setForm((p) => ({ ...p, [k]: v }));
    setErr("");
    setOk("");
  }

  function reset() {
    setForm(DEFAULT_FORM);
    setErr("");
    setOk("");
    setCreatedUser(null);
    setLastGeneratedPw("");
    setShowPw(false);
  }

  function genPassword() {
    const pw = randomPassword(12);
    setLastGeneratedPw(pw);
    setForm((p) => ({ ...p, password: pw, password_confirmation: pw }));
    setErr("");
    setOk("Password otomatis dibuat. Jangan lupa copy sebelum submit.");
  }

  async function copyGenerated() {
    if (!lastGeneratedPw) return;
    try {
      await navigator.clipboard.writeText(lastGeneratedPw);
      setOk("Password berhasil di-copy ke clipboard.");
      setErr("");
    } catch {
      setErr("Gagal copy. Copy manual dari field password ya.");
    }
  }

  // ===== Load employees (yang belum punya akun) =====
  async function loadEmployeesWithoutUser() {
    setLoadingEmp(true);
    setErr("");

    try {
      // Paling aman: ambil semua dulu, lalu filter di FE
      // (kalau backend belum support ?without_user=1)
      const data = await fetchEmployees();

      const rows = Array.isArray(data) ? data : data?.data ?? [];
      setEmployees(rows);

      // reset pilihan (biar HCGA sadar memilih)
      setForm((p) => ({
        ...p,
        employee_id: "",
        name: "",
      }));
    } catch (e) {
      setErr(e?.message || "Gagal memuat daftar employee.");
    } finally {
      setLoadingEmp(false);
    }
  }

  useEffect(() => {
    if (!authed || !allowed) return;
    loadEmployeesWithoutUser();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [authed, allowed]);

  // ✅ FILTER: hanya employee yang belum punya akun
  const selectableEmployees = useMemo(() => {
    return (employees || []).filter((e) => !e.user_id);
  }, [employees]);

  // ✅ auto isi nama dari employee (read-only)
  useEffect(() => {
    const emp = selectableEmployees.find(
      (e) => String(e.id) === String(form.employee_id)
    );
    if (!emp) {
      setForm((p) => ({ ...p, name: "" }));
      return;
    }
    setForm((p) => ({ ...p, name: emp?.name ?? "" }));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [form.employee_id, selectableEmployees]);

  async function onSubmit(e) {
    e.preventDefault();
    setErr("");
    setOk("");
    setCreatedUser(null);

    const empId = String(form.employee_id || "").trim();
    const email = String(form.email || "").trim();

    if (!empId) return setErr("Pilih employee terlebih dahulu.");
    if (!email) return setErr("Email wajib diisi.");
    if (form.password.length < 8) return setErr("Password minimal 8 karakter.");
    if (form.password !== form.password_confirmation)
      return setErr("Konfirmasi password tidak sama.");

    setSubmitting(true);
    try {
      const res = await api(`/employees/${empId}/create-user`, {
        method: "POST",
        body: {
          // name auto dari employee (konsisten)
          name: form.name.trim(),
          email,
          role: form.role, // staff/hcga/fat/director (sesuaikan backend kamu)
          password: form.password,
          password_confirmation: form.password_confirmation,
        },
      });

      setCreatedUser(res?.user || null);
      setOk(res?.message || "Akun berhasil dibuat.");

      // clear password (security)
      setForm((p) => ({
        ...p,
        email: "",
        password: "",
        password_confirmation: "",
        employee_id: "",
        name: "",
      }));
      setShowPw(false);
      setLastGeneratedPw("");

      // refresh list → employee yang barusan dibuat akun akan hilang dari dropdown
      await loadEmployeesWithoutUser();
    } catch (e2) {
      setErr(e2?.message || "Gagal membuat akun.");
    } finally {
      setSubmitting(false);
    }
  }

  // ✅ akses: kalau bukan HCGA, lempar ke payrolls (aman buat staff yg gak punya dashboard)
  if (!loadingSession && (!authed || !allowed)) {
    return <Navigate to="/payrolls" replace />;
  }

  return (
    <div>
      <div className="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
        <div className="absolute -top-40 -left-40 h-[520px] w-[520px] rounded-full bg-sky-200/45 blur-3xl" />
        <div className="absolute -bottom-44 -right-44 h-[620px] w-[620px] rounded-full bg-indigo-200/35 blur-3xl" />
      </div>

      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <div className="hidden">
              <span className="h-2 w-2 rounded-full bg-sky-500" />
              <span className="text-[10px] font-semibold text-muted-foreground">
                Human Plus Institute
              </span>
            </div>

            <h1 className="mt-4 text-lg font-semibold text-foreground">
              Create Account
            </h1>
            <p className="mt-1 text-sm text-slate-600">
              Onboarding akun dilakukan oleh <b>HCGA</b> (internal).
            </p>
          </div>

          <div className="flex gap-2">
            <Button
              variant="outline"
              className="rounded bg-white/70 border-slate-200 hover:bg-white"
              onClick={() => nav(-1)}
              disabled={submitting}
            >
              Back
            </Button>

            <Button
              variant="outline"
              className="rounded border-slate-200 bg-white hover:bg-slate-50"
              onClick={reset}
              disabled={submitting}
            >
              Reset
            </Button>
          </div>
        </div>

        {/* Loading session */}
        {loadingSession && (
          <div className="bg-white border border-border rounded shadow-sm p-4">
            <div className="text-xs font-semibold text-slate-800">Loading...</div>
            <div className="text-xs text-[10px] text-muted-foreground mt-0.5">
              Menyiapkan sesi akun
            </div>
          </div>
        )}

        {!loadingSession && (
          <Card className="bg-white border border-border rounded shadow-sm">
            <CardHeader>
              <div className="flex items-start justify-between gap-4 flex-wrap">
                <div>
                  <CardTitle className="text-base">Account Form</CardTitle>
                  <div className="mt-1 text-sm text-slate-600">
                    Pilih employee yang belum punya akun, lalu buat akun login-nya.
                  </div>
                </div>

                {!createdUser && (
                  <div className="flex gap-2">
                    <Button
                      type="button"
                      variant="outline"
                      className="rounded border-slate-200 bg-white hover:bg-slate-50"
                      onClick={genPassword}
                      disabled={submitting}
                    >
                      Generate Password
                    </Button>

                    <Button
                      type="button"
                      variant="outline"
                      className="rounded border-slate-200 bg-white hover:bg-slate-50"
                      onClick={copyGenerated}
                      disabled={submitting || !lastGeneratedPw}
                    >
                      Copy Password
                    </Button>
                  </div>
                )}
              </div>
            </CardHeader>

            <CardContent>
              {err && (
                <div className="mb-4 rounded border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm">
                  {err}
                </div>
              )}
              {ok && (
                <div className="mb-4 rounded border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">
                  {ok}
                </div>
              )}

              {/* kalau sudah dibuat, tampilkan summary */}
              {createdUser ? (
                <div className="max-w-2xl space-y-4">
                  <div className="text-lg font-black text-slate-900">
                    Akun dibuat ✅
                  </div>

                  <div className="bg-white border border-border rounded shadow-sm p-4">
                    <div className="text-sm">
                      Nama: <b>{createdUser.name}</b>
                    </div>
                    <div className="text-sm mt-1">
                      Email: <b>{createdUser.email}</b>
                    </div>
                    <div className="text-sm mt-1">
                      Role: <b>{createdUser.role}</b>
                    </div>

                    <div className="text-xs text-slate-500 mt-3">
                      Password tidak ditampilkan demi keamanan.
                    </div>
                  </div>

                  <div className="flex justify-end gap-2">
                    <Button
                      className="bg-blue-600 rounded text-xs font-medium text-white hover:bg-blue-700 transition-colors"
                      onClick={reset}
                    >
                      Buat Akun Lagi
                    </Button>
                  </div>
                </div>
              ) : (
                <form onSubmit={onSubmit} className="space-y-6">
                  <section className="space-y-4">
                    <div className="text-sm font-bold text-slate-900">
                      Target Employee
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <Select
                        label="Employee *"
                        value={form.employee_id}
                        onChange={(v) => setField("employee_id", v)}
                        options={[
                          {
                            value: "",
                            label: loadingEmp
                              ? "Loading..."
                              : selectableEmployees.length
                              ? "-- pilih employee --"
                              : "-- tidak ada employee --",
                          },
                          ...selectableEmployees.map((e) => ({
                            value: String(e.id),
                            label: `${e.name} — ${e.employee_code}`,
                          })),
                        ]}
                        disabled={loadingEmp || submitting || selectableEmployees.length === 0}
                      />

                      <Select
                        label="Role *"
                        value={form.role}
                        onChange={(v) => setField("role", v)}
                        options={[
                          { value: "staff", label: "Staff" },
                          { value: "hcga", label: "HCGA" },
                          { value: "fat", label: "Finance Admin (FAT)" },
                          { value: "director", label: "Director" },
                        ]}
                        disabled={submitting}
                      />
                    </div>

                    {/* Nama auto dari employee (read-only) */}
                    <Input
                      label="Nama (auto dari employee)"
                      value={form.name}
                      disabled
                      placeholder="Pilih employee dulu"
                      full
                    />
                  </section>

                  <section className="space-y-4">
                    <div className="text-sm font-bold text-slate-900">
                      Account Information
                    </div>

                    <Input
                      label="Email *"
                      value={form.email}
                      onChange={(v) => setField("email", v)}
                      placeholder="email@company.com"
                      required
                      full
                      disabled={submitting}
                    />
                  </section>

                  <section className="space-y-4">
                    <div className="flex items-center justify-between gap-3 flex-wrap">
                      <div className="text-sm font-bold text-slate-900">Password</div>

                      <button
                        type="button"
                        className="text-xs font-bold text-slate-700 underline underline-offset-4"
                        onClick={() => setShowPw((p) => !p)}
                      >
                        {showPw ? "Hide Password" : "Show Password"}
                      </button>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <Input
                        label="Password *"
                        type={showPw ? "text" : "password"}
                        value={form.password}
                        onChange={(v) => setField("password", v)}
                        placeholder="Minimal 8 karakter"
                        required
                        disabled={submitting}
                      />
                      <Input
                        label="Konfirmasi Password *"
                        type={showPw ? "text" : "password"}
                        value={form.password_confirmation}
                        onChange={(v) => setField("password_confirmation", v)}
                        placeholder="Ulangi password"
                        required
                        disabled={submitting}
                      />
                    </div>

                    {!!lastGeneratedPw && (
                      <div className="rounded border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                        Password otomatis sudah di-generate. Pastikan kamu simpan/copy
                        sebelum submit.
                      </div>
                    )}
                  </section>

                  <div className="flex justify-end gap-2 pt-2">
                    <Button
                      type="submit"
                      disabled={
                        submitting ||
                        loadingEmp ||
                        !form.employee_id ||
                        selectableEmployees.length === 0
                      }
                      className="bg-blue-600 rounded text-xs font-medium text-white hover:bg-blue-700 transition-colors"
                    >
                      {submitting ? "Creating..." : "Create Account"}
                    </Button>
                  </div>

                  {/* hint kalau list kosong */}
                  {!loadingEmp && selectableEmployees.length === 0 && (
                    <div className="rounded border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">
                      Semua employee sudah punya akun, atau belum ada employee yang bisa
                      dipilih.
                    </div>
                  )}
                </form>
              )}
            </CardContent>
          </Card>
        )}
      </div>
    </div>
  );
}

/* ===== small inputs ===== */
function Input({
  label,
  value,
  onChange,
  placeholder,
  required,
  full,
  type = "text",
  disabled,
}) {
  return (
    <div className={full ? "md:col-span-2 space-y-1" : "space-y-1"}>
      <label className="text-xs font-medium text-slate-600">{label}</label>
      <input
        type={type}
        className={[
          "w-full rounded-xl border px-3 py-2.5 text-sm bg-white",
          "border-slate-200 focus:border-sky-300 focus:ring-4 focus:ring-sky-200/40",
          disabled ? "opacity-60 cursor-not-allowed bg-slate-50" : "",
        ].join(" ")}
        value={value}
        required={required}
        placeholder={placeholder}
        disabled={disabled}
        onChange={(e) => onChange?.(e.target.value)}
      />
    </div>
  );
}

function Select({ label, value, onChange, options, disabled }) {
  return (
    <div className="space-y-1">
      <label className="text-xs font-medium text-slate-600">{label}</label>
      <select
        disabled={disabled}
        className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-indigo-300 focus:ring-4 focus:ring-indigo-200/40 bg-white disabled:opacity-60"
        value={value}
        onChange={(e) => onChange(e.target.value)}
      >
        {options.map((o) => (
          <option key={o.value} value={o.value}>
            {o.label}
          </option>
        ))}
      </select>
    </div>
  );
}
