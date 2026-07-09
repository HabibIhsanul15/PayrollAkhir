import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { api } from "@/lib/api";
import { saveAuth } from "@/lib/auth";
import { Building2, Users, DollarSign, FolderOpen, BarChart3, Eye, EyeOff, Lock } from "lucide-react";

function getHomePath(user) {
  const role = String(user?.role || "").toLowerCase();

  // staff hanya punya payroll + my profile (tanpa dashboard)
  if (role === "staff" || role === "employee") return "/payrolls";

  // admin roles
  if (role === "fat" || role === "director" || role === "hcga") return "/dashboard";

  return "/my-profile";
}

export default function Login() {
  const nav = useNavigate();

  const [email, setEmail] = useState("staff@test.com");
  const [password, setPassword] = useState("password123");

  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState("");

  const onSubmit = async (e) => {
    e.preventDefault();
    setErr("");

    const eEmail = String(email || "").trim();
    const ePass = String(password || "");

    if (!eEmail || !ePass) {
      setErr("Email dan password wajib diisi.");
      return;
    }

    setLoading(true);
    try {
      const data = await api("/login", {
        method: "POST",
        body: { email: eEmail, password: ePass },
      });

      if (!data?.token) {
        throw new Error("Login berhasil namun token tidak ditemukan.");
      }

      saveAuth(data.token, data.user);

      // ✅ redirect sesuai role
      const home = getHomePath(data.user);
      nav(home, { replace: true });
    } catch (e2) {
      setErr(e2?.message || "Login gagal.");
    } finally {
      setLoading(false);
    }
  };

  const [showPassword, setShowPassword] = useState(false);

  const label = "text-[11px] font-semibold text-slate-700";
  const input =
    "w-full rounded border border-slate-200 bg-white px-4 py-3 text-[13px] text-slate-900 placeholder:text-slate-400 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100 disabled:opacity-60";

  const StatCard = ({ title, value, icon: Icon, color }) => (
    <div className="rounded border border-white/10 bg-white/5 p-4 backdrop-blur flex items-center gap-4">
      <div className={`w-10 h-10 rounded bg-white/10 flex items-center justify-center`} style={{ color }}>
        <Icon size={18} />
      </div>
      <div>
        <div className="text-xl font-bold text-white leading-none">{value}</div>
        <div className="text-[11px] text-white/60 mt-1">{title}</div>
      </div>
    </div>
  );

  return (
    <div className="min-h-screen w-full bg-white flex">
      {/* ===================== LEFT SIDE (Dark Navy) ===================== */}
      <section className="hidden lg:flex w-[45%] relative bg-[#0B1426] flex-col px-10 py-12 border-r border-slate-800">
        {/* Subtle grid background */}
        <div className="absolute inset-0 opacity-20 bg-[radial-gradient(circle,rgba(255,255,255,0.4)_1px,transparent_1px)] bg-[length:24px_24px]" />

        {/* Header Logo */}
        <div className="relative z-10 flex items-center gap-3">
          <div className="w-9 h-9 rounded bg-blue-500 flex items-center justify-center text-white">
            <Building2 size={18} />
          </div>
          <div>
            <div className="text-[13px] font-bold text-white leading-tight">Human Plus Institute</div>
            <div className="text-[10px] text-white/60">Payroll Management System</div>
          </div>
        </div>

        <div className="relative z-10 mt-24 mb-10">
          <h1 className="text-[42px] font-bold text-white leading-[1.1] tracking-tight">
            Payroll
            <br />
            Internal System
          </h1>
        </div>

        <div className="relative z-10 grid grid-cols-2 gap-4 max-w-md">
          <StatCard title="Karyawan Aktif" value="9" icon={Users} color="#60A5FA" />
          <StatCard title="Payroll Records" value="35" icon={DollarSign} color="#34D399" />
          <StatCard title="Proyek Berjalan" value="4" icon={FolderOpen} color="#A78BFA" />
          <StatCard title="Periode Aktif" value="2" icon={BarChart3} color="#FBBF24" />
        </div>

        <div className="relative z-10 mt-auto text-[11px] text-white/40">
          © {new Date().getFullYear()} Human Plus Institute
        </div>
      </section>

      {/* ===================== RIGHT SIDE (Login Form) ===================== */}
      <section className="flex-1 flex items-center justify-center px-6 py-10 bg-white">
        <div className="w-full max-w-[400px]">
          {/* Logo at the top of the form */}
          <div className="mb-8">
            <img src="/logo.png" alt="Human Plus Logo" className="h-10 object-contain" />
          </div>

          <h2 className="text-2xl font-bold text-slate-900">Login Payroll</h2>
          <p className="mt-1.5 text-[13px] text-slate-500">
            Gunakan akun yang telah dibuat oleh HR/GA.
          </p>

          {err && (
            <div className="mt-5 rounded bg-rose-50 px-4 py-3 text-[12px] font-medium text-rose-600 border border-rose-100 flex items-center gap-2">
              <span>{err}</span>
            </div>
          )}

          <form onSubmit={onSubmit} className="mt-8 space-y-5">
            <div className="space-y-1.5">
              <div className={label}>Email</div>
              <input
                className={input}
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="staff@tres.com"
                type="email"
                autoComplete="email"
                disabled={loading}
                required
              />
            </div>

            <div className="space-y-1.5">
              <div className="flex items-center justify-between">
                <div className={label}>Password</div>
                <a href="#" className="text-[11px] text-blue-600 hover:underline font-medium">
                  Lupa password?
                </a>
              </div>
              <div className="relative">
                <input
                  className={input}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="••••••••••"
                  type={showPassword ? "text" : "password"}
                  autoComplete="current-password"
                  disabled={loading}
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                >
                  {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                </button>
              </div>
            </div>

            <button
              disabled={loading}
              className="w-full mt-2 px-4 py-3 bg-blue-600 rounded text-[13px] font-medium text-white hover:bg-blue-700 transition-colors shadow-sm disabled:opacity-70 flex items-center justify-center gap-2"
            >
              {loading ? "Loading..." : "Masuk"}
            </button>
            
            <div className="py-4 flex items-center justify-center gap-3">
              <div className="h-px bg-slate-200 flex-1" />
              <span className="text-[10px] text-slate-400 lowercase tracking-widest">akses terbatas</span>
              <div className="h-px bg-slate-200 flex-1" />
            </div>

            <div className="rounded border border-slate-200 bg-slate-50 p-4 flex gap-3">
              <div className="mt-0.5 text-blue-500 bg-blue-100 p-1 rounded">
                <Lock size={14} />
              </div>
              <p className="text-[11px] text-slate-500 leading-relaxed">
                Sistem ini hanya untuk karyawan internal. Hubungi <strong>HR/GA</strong> jika belum memiliki akun.
              </p>
            </div>
            
            <div className="pt-6 text-center text-[10px] text-slate-400">
              © {new Date().getFullYear()} Human Plus Institute
            </div>
          </form>
        </div>
      </section>
    </div>
  );
}
