import { useMemo, useState } from "react";
import { useNavigate, Link } from "react-router-dom";
import { registerStaff } from "@/lib/authApi";
import AlertMessage from "@/components/AlertMessage";

export default function Register() {
  const nav = useNavigate();

  // Step 1 (wajib)
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");

  // Step 2 (wajib)
  const [department, setDepartment] = useState("");
  const [position, setPosition] = useState("");

  // Step 3 (opsional)
  const [address, setAddress] = useState("");

  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState("");

  // untuk nampilin error validasi per-field (kalau backend kirim errors)
  const [fieldErrs, setFieldErrs] = useState({});

  const isRequiredFilled = useMemo(() => {
    return (
      name.trim() &&
      email.trim() &&
      password.trim() &&
      passwordConfirmation.trim() &&
      department.trim() &&
      position.trim()
    );
  }, [name, email, password, passwordConfirmation, department, position]);

  const onSubmit = async (e) => {
    e.preventDefault();
    setErr("");
    setFieldErrs({});
    setLoading(true);

    try {
      await registerStaff({
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
        department,
        position,
        address: address.trim() ? address : null, // opsional
      });

      alert("Register berhasil. Silakan login.");
      nav("/login", { replace: true });
    } catch (e2) {
      setErr(e2?.message || "Register gagal");

      // kalau backend kirim errors laravel, simpan untuk ditampilkan
      const errors = e2?.payload?.errors;
      if (errors && typeof errors === "object") {
        const mapped = {};
        Object.keys(errors).forEach((k) => {
          mapped[k] = Array.isArray(errors[k]) ? errors[k].join(" ") : String(errors[k]);
        });
        setFieldErrs(mapped);
      }
    } finally {
      setLoading(false);
    }
  };

  // UI classes (nuansa sama seperti login: sky ↔ indigo)
  const label = "text-[11px] font-semibold text-slate-700";
  const input =
    "w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-[13px] text-slate-900 placeholder:text-slate-400 outline-none transition focus:border-sky-400 focus:ring-4 focus:ring-sky-200/60 disabled:opacity-60";
  const errText = "mt-1 text-[11px] text-rose-600";

  const StepCard = ({ n, title, desc }) => (
    <div className="flex items-start gap-4 rounded bg-white/12 p-4 backdrop-blur">
      <div className="flex h-9 w-9 items-center justify-center rounded-full bg-white/25 font-extrabold">
        {n}
      </div>
      <div>
        <div className="font-semibold leading-tight">{title}</div>
        <div className="mt-1 text-sm text-white/80 leading-relaxed">{desc}</div>
      </div>
    </div>
  );

  return (
    <div className="min-h-screen w-full bg-slate-50">
      <div className="min-h-screen grid grid-cols-1 lg:grid-cols-2">
        {/* ===================== LEFT PANEL (tata cara lucu) ===================== */}
        <section className="relative overflow-hidden px-8 py-10 lg:px-12 lg:py-12 text-white">
          {/* background gradient */}
          <div className="absolute inset-0 bg-gradient-to-br from-sky-700 via-sky-600 to-indigo-700" />
          <div className="absolute -top-24 -left-24 h-72 w-72 rounded-full bg-white/15 blur-3xl" />
          <div className="absolute -bottom-28 -right-28 h-80 w-80 rounded-full bg-white/10 blur-3xl" />
          <div className="absolute inset-0 opacity-25 bg-[radial-gradient(circle_at_20%_20%,rgba(255,255,255,0.35),transparent_45%),radial-gradient(circle_at_80%_30%,rgba(255,255,255,0.25),transparent_45%)]" />

          <div className="relative h-full flex flex-col justify-between">
            <div>
              <div className="inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-2 backdrop-blur">
                <span className="h-2 w-2 rounded-full bg-white" />
                <span className="text-xs font-semibold">Human Plus Institute</span>
              </div>

              <h1 className="mt-10 text-4xl font-black leading-tight">
                Let’s setup your
                <br />
                <span className="text-white/90">Akun Pegawai</span>
              </h1>

              <p className="mt-5 max-w-md text-white/85 leading-relaxed">
                Tenang… ini cuma sebentar kok 😄 <br />
                Ikuti langkah singkat di bawah ini, lalu login dan mulai akses sistem payroll.
              </p>

              <div className="mt-8 space-y-4 max-w-md">
                <StepCard
                  n="1"
                  title="Isi Data Akun"
                  desc="Masukkan nama, email, dan password (min. 8 karakter). Jangan typo yaa 😆"
                />
                <StepCard
                  n="2"
                  title="Lengkapi Pekerjaan"
                  desc="Isi department & position biar datanya rapi dan gampang dicari."
                />
                <StepCard
                  n="3"
                  title="Klik Register → Login"
                  desc="Kalau sudah sukses, balik ke login. Kalau gagal, cek pesan errornya (kita beresin bareng)."
                />
              </div>
            </div>

            <div className="text-xs text-white/70">
              *Sistem ini khusus internal HR & Payroll Human Plus Institute.
            </div>
          </div>
        </section>

        {/* ===================== RIGHT PANEL (form flat, rapi) ===================== */}
        <section className="flex items-center justify-center px-6 py-10">
          <div className="w-full max-w-lg">
            {/* top nav */}
            <div className="flex items-center justify-between">
              <div className="text-xs text-slate-500">Daftar / Pegawai</div>
              <Link to="/login" className="text-xs font-semibold text-sky-700 hover:underline">
                Back to Login →
              </Link>
            </div>

            <h2 className="mt-3 text-3xl font-black text-slate-900">Daftar Pegawai</h2>
            <p className="mt-1 text-sm text-slate-600">
              Lengkapi data berikut untuk membuat akun pegawai.
            </p>

            <AlertMessage type="error" message={err} className="mt-4" />

            <form onSubmit={onSubmit} className="mt-6 space-y-5">
              {/* step line */}
              <div className="flex items-center gap-2">
                <div className="h-2 w-2 rounded-full bg-sky-600" />
                <div className="h-px flex-1 bg-slate-200" />
                <div className="h-2 w-2 rounded-full bg-slate-300" />
                <div className="h-px flex-1 bg-slate-200" />
                <div className="h-2 w-2 rounded-full bg-slate-300" />
              </div>

              {/* AKUN */}
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <div className="text-sm font-extrabold text-slate-800">Akun</div>
                  <div className="text-xs text-slate-500">Step 1</div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <div>
                    <div className={label}>Nama Lengkap</div>
                    <input
                      className={input}
                      value={name}
                      onChange={(e) => setName(e.target.value)}
                      placeholder="Nama lengkap"
                      disabled={loading}
                    />
                    {fieldErrs.name && <div className={errText}>{fieldErrs.name}</div>}
                  </div>

                  <div>
                    <div className={label}>Email</div>
                    <input
                      className={input}
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      placeholder="email@humanplus.id"
                      type="email"
                      disabled={loading}
                    />
                    {fieldErrs.email && <div className={errText}>{fieldErrs.email}</div>}
                  </div>

                  <div>
                    <div className={label}>Password</div>
                    <input
                      className={input}
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      placeholder="Minimal 8 karakter"
                      type="password"
                      disabled={loading}
                    />
                    {fieldErrs.password && <div className={errText}>{fieldErrs.password}</div>}
                  </div>

                  <div>
                    <div className={label}>Konfirmasi Password</div>
                    <input
                      className={input}
                      value={passwordConfirmation}
                      onChange={(e) => setPasswordConfirmation(e.target.value)}
                      placeholder="Ulangi password"
                      type="password"
                      disabled={loading}
                    />
                    {fieldErrs.password_confirmation && (
                      <div className={errText}>{fieldErrs.password_confirmation}</div>
                    )}
                  </div>
                </div>
              </div>

              <div className="h-px w-full bg-slate-200/80" />

              {/* PEKERJAAN */}
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <div className="text-sm font-extrabold text-slate-800">Pekerjaan</div>
                  <div className="text-xs text-slate-500">Step 2</div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <div className="space-y-1">
                    <div className={label}>Department</div>
                    <input
                      className={input}
                      value={department}
                      onChange={(e) => setDepartment(e.target.value)}
                      placeholder="Finance / HR / IT"
                      disabled={loading}
                    />
                    {fieldErrs.department && <div className={errText}>{fieldErrs.department}</div>}
                  </div>

                  <div className="space-y-1">
                    <div className={label}>Position</div>
                    <input
                      className={input}
                      value={position}
                      onChange={(e) => setPosition(e.target.value)}
                      placeholder="Pegawai / Supervisor"
                      disabled={loading}
                    />
                    {fieldErrs.position && <div className={errText}>{fieldErrs.position}</div>}
                  </div>
                </div>
              </div>

              <div className="h-px w-full bg-slate-200/80" />

              {/* OPSIONAL */}
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <div className="text-sm font-extrabold text-slate-800">Opsional</div>
                  <div className="text-xs text-slate-500">Step 3</div>
                </div>

                <div className="space-y-1">
                  <div className={label}>Alamat</div>
                  <input
                    className={input}
                    value={address}
                    onChange={(e) => setAddress(e.target.value)}
                    placeholder="Alamat lengkap (boleh dikosongkan)"
                    disabled={loading}
                  />
                  {fieldErrs.address && <div className={errText}>{fieldErrs.address}</div>}
                </div>
              </div>

              {/* Footer */}
              <button
                disabled={loading || !isRequiredFilled}
                className="px-4 py-1.5 bg-blue-600 rounded text-xs font-medium text-white hover:bg-blue-700 transition-colors"
                title={!isRequiredFilled ? "Lengkapi field wajib (Step 1 & 2)" : ""}
              >
                {loading ? "Loading..." : "Daftar Pegawai"}
              </button>

              <div className="text-xs text-slate-500">
                Catatan: peran otomatis dibuat sebagai <b>Pegawai</b>. FAT/Direktur tidak bisa mendaftar dari halaman ini.
              </div>

              <div className="text-center text-xs text-slate-500">
                Sudah punya akun?{" "}
                <Link to="/login" className="font-semibold text-sky-700 hover:underline">
                  Login
                </Link>
                .
              </div>

              <div className="pt-2 text-center text-[11px] text-slate-400">
                © {new Date().getFullYear()} Human Plus Institute — Internal System
              </div>
            </form>
          </div>
        </section>
      </div>
    </div>
  );
}
