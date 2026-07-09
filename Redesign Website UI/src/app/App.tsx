import { useState } from "react";
import {
  LayoutDashboard,
  DollarSign,
  Users,
  FolderOpen,
  ClipboardList,
  UserCheck,
  ChevronRight,
  RefreshCw,
  Plus,
  Search,
  ChevronDown,
  LogOut,
  Building2,
  Eye,
  EyeOff,
  Pencil,
  Trash2,
  ChevronLeft,
  BarChart3,
  BookOpen,
  TrendingUp,
} from "lucide-react";

type Page = "login" | "dashboard" | "payroll";

const NAV_ITEMS = [
  { id: "dashboard", label: "Dashboard", icon: LayoutDashboard },
  { id: "payroll", label: "Payroll", icon: DollarSign },
  { id: "batch-payroll", label: "Batch Payroll", icon: ClipboardList },
  { id: "payroll-report", label: "Payroll Report", icon: BarChart3 },
  { id: "employees", label: "Employees", icon: Users },
  { id: "projects", label: "Projects", icon: FolderOpen },
  { id: "project-assignments", label: "Project Assignments", icon: UserCheck },
  { id: "attendance", label: "Attendance", icon: BookOpen },
];

const PAYROLL_DATA = [
  { id: 1, name: "Test Staff", code: "TST-STAFF-01", avatar: "TS", period: "Okt 2026", status: "PAID", nominalOk: true },
  { id: 2, name: "Ahmad Fauzi", code: "HPI-EMP-02", avatar: "AF", period: "Okt 2026", status: "PAID", nominalOk: true },
  { id: 3, name: "Budi Santoso", code: "HPI-EMP-03", avatar: "BS", period: "Okt 2026", status: "PENDING", nominalOk: false },
  { id: 4, name: "Citra Dewi", code: "HPI-EMP-04", avatar: "CD", period: "Okt 2026", status: "PAID", nominalOk: true },
  { id: 5, name: "Dani Prasetyo", code: "HPI-EMP-05", avatar: "DP", period: "Sep 2026", status: "PENDING", nominalOk: false },
  { id: 6, name: "Eka Wulandari", code: "HPI-EMP-06", avatar: "EW", period: "Okt 2026", status: "PAID", nominalOk: true },
  { id: 7, name: "Fajar Hidayat", code: "HPI-EMP-07", avatar: "FH", period: "Sep 2026", status: "PAID", nominalOk: true },
  { id: 8, name: "Gita Rahayu", code: "HPI-EMP-08", avatar: "GR", period: "Okt 2026", status: "PENDING", nominalOk: false },
];

function AvatarInitial({ letters }: { letters: string }) {
  return (
    <div className="w-7 h-7 rounded flex items-center justify-center text-[10px] font-semibold bg-blue-50 text-blue-600 flex-shrink-0 border border-blue-100">
      {letters}
    </div>
  );
}

function Sidebar({ activePage, onNavigate }: { activePage: Page; onNavigate: (p: Page) => void }) {
  return (
    <aside className="w-48 flex-shrink-0 flex flex-col border-r border-border bg-white">
      {/* Brand */}
      <div className="px-4 py-4 border-b border-border">
        <div className="flex items-center gap-2.5">
          <div className="w-6 h-6 rounded flex items-center justify-center bg-blue-600">
            <Building2 size={12} className="text-white" />
          </div>
          <div>
            <div className="text-foreground text-xs font-semibold leading-none">Human Plus</div>
            <div className="text-muted-foreground text-[10px] mt-0.5">Institute</div>
          </div>
        </div>
      </div>

      {/* Section label */}
      <div className="px-4 pt-5 pb-1.5">
        <span className="text-[9px] font-semibold uppercase tracking-widest text-muted-foreground opacity-60">
          Menu
        </span>
      </div>

      {/* Nav items */}
      <nav className="flex-1 px-2 pb-4 flex flex-col gap-px">
        {NAV_ITEMS.map((item) => {
          const Icon = item.icon;
          const isActive =
            (item.id === "dashboard" && activePage === "dashboard") ||
            (item.id === "payroll" && activePage === "payroll");
          return (
            <button
              key={item.id}
              onClick={() => {
                if (item.id === "dashboard") onNavigate("dashboard");
                else if (item.id === "payroll") onNavigate("payroll");
              }}
              className={`w-full flex items-center gap-2.5 px-3 py-2 rounded text-left transition-colors ${
                isActive
                  ? "bg-blue-50 text-blue-600 font-medium"
                  : "text-muted-foreground hover:bg-slate-50 hover:text-foreground"
              }`}
            >
              <Icon size={13} className={`flex-shrink-0 ${isActive ? "text-blue-500" : ""}`} />
              <span className="text-xs truncate">{item.label}</span>
              {isActive && <div className="ml-auto w-1 h-4 rounded-full bg-blue-500" />}
            </button>
          );
        })}
      </nav>

      {/* Footer note */}
      <div className="mx-3 mb-4 p-3 rounded bg-slate-50 border border-border">
        <div className="text-foreground text-[10px] font-semibold mb-1">Payroll Internal</div>
        <div className="text-muted-foreground text-[10px] leading-relaxed">
          Modul untuk proses penggajian karyawan dan bersifat internal.
        </div>
      </div>
    </aside>
  );
}

function Topbar({ onLogout }: { onLogout: () => void }) {
  return (
    <header className="h-10 bg-white border-b border-border flex items-center justify-between px-5 flex-shrink-0">
      <div className="flex items-center gap-2">
        <div className="w-1.5 h-1.5 rounded-full bg-emerald-500"></div>
        <span className="text-xs text-muted-foreground">Payroll App</span>
      </div>
      <div className="flex items-center gap-4">
        <span className="text-xs text-muted-foreground">
          Test FAT · <span className="text-foreground font-medium">Finance Admin</span>
        </span>
        <button
          onClick={onLogout}
          className="flex items-center gap-1.5 px-2.5 py-1 text-xs text-muted-foreground border border-border rounded hover:bg-muted hover:text-foreground transition-colors"
        >
          <LogOut size={11} />
          Logout
        </button>
      </div>
    </header>
  );
}

function PageBreadcrumb({ children }: { children: React.ReactNode }) {
  return (
    <div className="bg-white border-b border-border px-6 py-2.5 flex items-center justify-between">
      <div className="flex items-center gap-2 text-xs text-muted-foreground">
        <div className="w-1.5 h-1.5 rounded-full bg-blue-500"></div>
        <span>Human Plus Institute</span>
        <ChevronRight size={11} className="opacity-40" />
        <span className="text-foreground font-medium">{children}</span>
      </div>
    </div>
  );
}

function DashboardPage() {
  return (
    <main className="flex-1 overflow-auto bg-background">
      <PageBreadcrumb>Dashboard</PageBreadcrumb>

      <div className="p-6">
        {/* Title + period */}
        <div className="flex items-start justify-between mb-6">
          <div>
            <h1 className="text-lg font-semibold text-foreground">Dashboard</h1>
            <p className="text-xs text-muted-foreground mt-0.5">
              Ringkasan payroll per bulan (tanpa membocarkan nominal gaji).
            </p>
          </div>
          <div className="flex items-center gap-2">
            <div className="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-border rounded text-xs text-foreground">
              <span>Juli 2026</span>
              <ChevronDown size={11} className="text-muted-foreground" />
            </div>
            <button className="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-border rounded text-xs text-muted-foreground hover:text-foreground transition-colors">
              <RefreshCw size={11} />
              Refresh
            </button>
          </div>
        </div>

        {/* Stat cards */}
        <div className="grid grid-cols-3 gap-4 mb-5">
          {[
            {
              label: "Payroll Bulan Ini",
              value: "0",
              sub: "Jumlah payroll terbuat",
              icon: DollarSign,
              accent: "#2563EB",
              accentLight: "#EFF6FF",
              trend: "Juli 2026",
            },
            {
              label: "Karyawan Aktif",
              value: "9",
              sub: "Status aktif saat ini",
              icon: Users,
              accent: "#059669",
              accentLight: "#ECFDF5",
              trend: "+0 bulan ini",
            },
            {
              label: "Karyawan Nonaktif",
              value: "0",
              sub: "Tidak dapat mengakses sistem",
              icon: UserCheck,
              accent: "#94A3B8",
              accentLight: "#F8FAFC",
              trend: "Stabil",
            },
          ].map((card) => {
            const Icon = card.icon;
            return (
              <div
                key={card.label}
                className="bg-white rounded border border-border overflow-hidden"
                style={{ boxShadow: "0 1px 4px 0 rgba(0,0,0,0.04)" }}
              >
                {/* Top accent bar */}
                <div className="h-0.5 w-full" style={{ background: card.accent }} />
                <div className="p-5">
                  <div className="flex items-start justify-between mb-4">
                    <p className="text-[11px] font-medium text-muted-foreground leading-tight">{card.label}</p>
                    <div
                      className="w-8 h-8 rounded flex items-center justify-center flex-shrink-0"
                      style={{ background: card.accentLight }}
                    >
                      <Icon size={14} style={{ color: card.accent }} />
                    </div>
                  </div>
                  <div className="text-3xl font-semibold text-foreground tracking-tight mb-1" style={{ letterSpacing: "-0.02em" }}>
                    {card.value}
                  </div>
                  <div className="flex items-center justify-between mt-3 pt-3 border-t border-border">
                    <span className="text-[10px] text-muted-foreground">{card.sub}</span>
                    <span className="text-[10px] font-medium" style={{ color: card.accent }}>{card.trend}</span>
                  </div>
                </div>
              </div>
            );
          })}
        </div>

        {/* Bottom row */}
        <div className="grid grid-cols-3 gap-4">
          {/* Status payroll — spans 2 cols */}
          <div
            className="col-span-2 bg-white rounded border border-border overflow-hidden"
            style={{ boxShadow: "0 1px 4px 0 rgba(0,0,0,0.04)" }}
          >
            <div className="px-5 py-3.5 border-b border-border flex items-center justify-between">
              <div>
                <div className="text-sm font-semibold text-foreground">Status Payroll</div>
                <div className="text-[10px] text-muted-foreground mt-0.5">Bulan Juli 2026</div>
              </div>
              <span className="text-[10px] px-2.5 py-1 rounded border border-border text-muted-foreground">
                Juli 2026
              </span>
            </div>
            <div className="p-5">
              <div className="grid grid-cols-4 gap-2 pb-2.5 mb-1 border-b border-border text-[9px] font-semibold text-muted-foreground uppercase tracking-widest">
                <div>Karyawan</div>
                <div>Periode</div>
                <div>Status</div>
                <div>Nominal</div>
              </div>
              <div className="py-8 flex flex-col items-center justify-center gap-2">
                <div className="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center">
                  <ClipboardList size={14} className="text-slate-300" />
                </div>
                <p className="text-xs text-muted-foreground">Belum ada data status untuk periode ini.</p>
              </div>
            </div>
          </div>

          {/* Summary */}
          <div
            className="bg-white rounded border border-border overflow-hidden"
            style={{ boxShadow: "0 1px 4px 0 rgba(0,0,0,0.04)" }}
          >
            <div className="px-5 py-3.5 border-b border-border">
              <div className="text-sm font-semibold text-foreground">Ringkasan</div>
              <div className="text-[10px] text-muted-foreground mt-0.5">Distribusi status penggajian</div>
            </div>
            <div className="p-5 space-y-4">
              {[
                { label: "Sudah digaji", val: 6, total: 9, color: "#2563EB" },
                { label: "Belum digaji", val: 3, total: 9, color: "#E2E8F0" },
                { label: "Karyawan aktif", val: 9, total: 9, color: "#059669" },
              ].map((r) => (
                <div key={r.label}>
                  <div className="flex items-center justify-between mb-1.5">
                    <span className="text-[11px] text-muted-foreground">{r.label}</span>
                    <span className="text-[11px] font-semibold text-foreground">{r.val} <span className="text-muted-foreground font-normal">/ {r.total}</span></span>
                  </div>
                  <div className="h-1 bg-slate-100 rounded-full overflow-hidden">
                    <div
                      className="h-full rounded-full transition-all"
                      style={{ width: `${(r.val / r.total) * 100}%`, background: r.color }}
                    />
                  </div>
                </div>
              ))}

              <div className="pt-3 mt-1 border-t border-border flex items-center gap-1.5">
                <TrendingUp size={11} className="text-emerald-500" />
                <span className="text-[10px] text-muted-foreground">Periode aktif: <span className="text-foreground font-medium">Juli 2026</span></span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  );
}

function PayrollPage() {
  const [search, setSearch] = useState("");
  const [period, setPeriod] = useState("all");

  const filtered = PAYROLL_DATA.filter((r) => {
    const matchSearch =
      r.name.toLowerCase().includes(search.toLowerCase()) ||
      r.code.toLowerCase().includes(search.toLowerCase());
    const matchPeriod = period === "all" || r.period === period;
    return matchSearch && matchPeriod;
  });

  return (
    <main className="flex-1 overflow-auto bg-background">
      <PageBreadcrumb>Payroll</PageBreadcrumb>

      <div className="p-6">
        {/* Title + actions */}
        <div className="flex items-start justify-between mb-5">
          <div>
            <h1 className="text-lg font-semibold text-foreground">Payroll</h1>
            <p className="text-xs text-muted-foreground mt-0.5">
              Kelola dan lihat slip gaji per periode. Gunakan pencarian &amp; filter agar lebih cepat.
            </p>
            <div className="flex items-center gap-3 mt-2 text-[10px] text-muted-foreground">
              <span>Total: <strong className="text-foreground">{PAYROLL_DATA.length}</strong></span>
              <span className="text-border">·</span>
              <span>Masked: <strong className="text-foreground">0</strong></span>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <button className="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-border rounded text-xs text-muted-foreground hover:text-foreground transition-colors">
              <RefreshCw size={11} />
              Refresh
            </button>
            <button className="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 rounded text-xs font-medium text-white hover:bg-blue-700 transition-colors">
              <Plus size={11} />
              Create Payroll
            </button>
          </div>
        </div>

        {/* Filters */}
        <div className="bg-white border border-border rounded p-4 mb-4">
          <div className="flex items-end gap-3">
            <div className="flex-1">
              <label className="block text-[10px] font-medium text-muted-foreground mb-1.5">Cari Karyawan</label>
              <div className="relative">
                <Search size={12} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground" />
                <input
                  type="text"
                  placeholder="Nama / kode karyawan..."
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  className="w-full pl-8 pr-3 py-2 text-xs border border-border rounded bg-white focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all placeholder:text-muted-foreground"
                />
              </div>
            </div>
            <div className="w-52">
              <label className="block text-[10px] font-medium text-muted-foreground mb-1.5">Filter Periode</label>
              <div className="relative">
                <select
                  value={period}
                  onChange={(e) => setPeriod(e.target.value)}
                  className="w-full appearance-none pl-3 pr-7 py-2 text-xs border border-border rounded bg-white focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all"
                >
                  <option value="all">Semua periode</option>
                  <option value="Okt 2026">Okt 2026</option>
                  <option value="Sep 2026">Sep 2026</option>
                </select>
                <ChevronDown size={11} className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" />
              </div>
            </div>
            <button
              onClick={() => { setSearch(""); setPeriod("all"); }}
              className="px-3 py-2 text-xs text-muted-foreground border border-border rounded bg-white hover:bg-muted transition-colors"
            >
              Reset
            </button>
          </div>
        </div>

        {/* Table */}
        <div className="bg-white border border-border rounded overflow-hidden">
          <div className="px-5 py-3 border-b border-border flex items-center justify-between">
            <span className="text-sm font-medium text-foreground">Payroll Records</span>
            <span className="text-[10px] text-muted-foreground">
              Menampilkan {filtered.length} dari {PAYROLL_DATA.length} record
            </span>
          </div>

          <table className="w-full border-collapse">
            <thead>
              <tr className="border-b border-border bg-slate-50">
                <th className="text-left px-5 py-2.5 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Karyawan</th>
                <th className="text-left px-4 py-2.5 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Periode</th>
                <th className="text-left px-4 py-2.5 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Status</th>
                <th className="text-left px-4 py-2.5 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Akses Nominal</th>
                <th className="text-right px-5 py-2.5 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((row) => (
                <tr
                  key={row.id}
                  className="border-b border-border last:border-0 hover:bg-slate-50/70 transition-colors cursor-pointer"
                >
                  <td className="px-5 py-3">
                    <div className="flex items-center gap-2.5">
                      <AvatarInitial letters={row.avatar} />
                      <div>
                        <div className="text-xs font-medium text-foreground">{row.name}</div>
                        <div className="text-[10px] text-muted-foreground">{row.code}</div>
                      </div>
                    </div>
                  </td>
                  <td className="px-4 py-3 text-xs text-muted-foreground">{row.period}</td>
                  <td className="px-4 py-3">
                    <span className={`text-[10px] font-semibold ${row.status === "PAID" ? "text-emerald-600" : "text-amber-600"}`}>
                      {row.status}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-[10px] text-muted-foreground">
                    {row.nominalOk ? <span className="text-blue-600 font-medium">Nominal OK</span> : <span className="text-slate-400">Masked</span>}
                  </td>
                  <td className="px-5 py-3">
                    <div className="flex items-center justify-end gap-1.5">
                      <button className="flex items-center gap-1 px-2.5 py-1 text-[10px] font-medium text-muted-foreground border border-border rounded hover:bg-muted hover:text-foreground transition-colors">
                        <Pencil size={9} />
                        Edit
                      </button>
                      <button className="flex items-center gap-1 px-2.5 py-1 text-[10px] font-medium text-red-600 border border-red-200 rounded hover:bg-red-50 transition-colors">
                        <Trash2 size={9} />
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>

          {/* Pagination */}
          <div className="px-5 py-3 border-t border-border flex items-center justify-between bg-slate-50/50">
            <span className="text-[10px] text-muted-foreground">Halaman 1 dari 5 · Total 35 record</span>
            <div className="flex items-center gap-1">
              <button className="w-7 h-7 flex items-center justify-center rounded border border-border text-muted-foreground hover:bg-white transition-colors">
                <ChevronLeft size={12} />
              </button>
              {[1, 2, 3, 4, 5].map((p) => (
                <button
                  key={p}
                  className={`w-7 h-7 flex items-center justify-center rounded text-xs transition-colors ${
                    p === 1
                      ? "bg-blue-600 text-white border border-blue-600"
                      : "border border-border text-muted-foreground hover:bg-white"
                  }`}
                >
                  {p}
                </button>
              ))}
              <button className="w-7 h-7 flex items-center justify-center rounded border border-border text-muted-foreground hover:bg-white transition-colors">
                <ChevronRight size={12} />
              </button>
            </div>
          </div>
        </div>
      </div>
    </main>
  );
}

function LoginPage({ onLogin }: { onLogin: () => void }) {
  const [email, setEmail] = useState("staff@tres.com");
  const [password, setPassword] = useState("rahasia123");
  const [showPass, setShowPass] = useState(false);

  return (
    <div className="min-h-screen flex" style={{ fontFamily: "Inter, sans-serif" }}>
      {/* Left panel */}
      <div
        className="hidden lg:flex flex-col w-[46%] relative overflow-hidden"
        style={{ background: "linear-gradient(160deg, #0B1526 0%, #102040 45%, #1a3a6b 100%)" }}
      >
        {/* Dot grid texture */}
        <div
          className="absolute inset-0 opacity-[0.05]"
          style={{
            backgroundImage: "radial-gradient(circle, rgba(255,255,255,1) 1px, transparent 1px)",
            backgroundSize: "24px 24px",
          }}
        />

        <div className="relative flex flex-col h-full px-10 py-9">
          {/* Brand */}
          <div className="flex items-center gap-2.5 mb-10">
            <div className="w-7 h-7 rounded flex items-center justify-center bg-blue-500">
              <Building2 size={13} className="text-white" />
            </div>
            <div>
              <div className="text-white text-sm font-semibold">Human Plus Institute</div>
              <div className="text-blue-400 text-[10px] tracking-wide">Payroll Management System</div>
            </div>
          </div>

          {/* Headline */}
          <div className="mb-8">
            <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-500/15 border border-emerald-500/20 mb-4">
              <div className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
              <span className="text-emerald-300 text-[10px] font-medium">Sistem Aktif</span>
            </div>
            <h2 className="text-[26px] font-semibold text-white leading-snug">
              Payroll<br />Internal System
            </h2>
          </div>

          {/* Visual stats grid */}
          <div className="grid grid-cols-2 gap-2.5 mb-6">
            {[
              { icon: Users, value: "9", label: "Karyawan Aktif", color: "#3B82F6" },
              { icon: DollarSign, value: "35", label: "Payroll Records", color: "#10B981" },
              { icon: FolderOpen, value: "4", label: "Proyek Berjalan", color: "#8B5CF6" },
              { icon: BarChart3, value: "2", label: "Periode Aktif", color: "#F59E0B" },
            ].map((s) => {
              const Icon = s.icon;
              return (
                <div
                  key={s.label}
                  className="p-3.5 rounded border border-white/8 flex items-center gap-3"
                  style={{ background: "rgba(255,255,255,0.04)" }}
                >
                  <div
                    className="w-8 h-8 rounded flex items-center justify-center flex-shrink-0"
                    style={{ background: `${s.color}22` }}
                  >
                    <Icon size={14} style={{ color: s.color }} />
                  </div>
                  <div>
                    <div className="text-white text-base font-semibold leading-none">{s.value}</div>
                    <div className="text-blue-300 text-[10px] mt-0.5">{s.label}</div>
                  </div>
                </div>
              );
            })}
          </div>

          {/* Mini payroll preview card */}
          <div className="rounded border border-white/10 overflow-hidden" style={{ background: "rgba(255,255,255,0.04)" }}>
            <div className="px-4 py-2.5 border-b border-white/8 flex items-center justify-between">
              <span className="text-white text-[11px] font-medium">Payroll Terbaru</span>
              <span className="text-blue-400 text-[10px]">Okt 2026</span>
            </div>
            <div className="divide-y divide-white/5">
              {[
                { initials: "TS", name: "Test Staff", status: "PAID" },
                { initials: "AF", name: "Ahmad Fauzi", status: "PAID" },
                { initials: "BS", name: "Budi Santoso", status: "PENDING" },
              ].map((r) => (
                <div key={r.name} className="px-4 py-2.5 flex items-center gap-3">
                  <div className="w-6 h-6 rounded bg-blue-500/25 flex items-center justify-center text-[9px] font-semibold text-blue-200 flex-shrink-0">
                    {r.initials}
                  </div>
                  <span className="text-blue-100 text-[11px] flex-1">{r.name}</span>
                  <span className={`text-[9px] font-semibold ${r.status === "PAID" ? "text-emerald-400" : "text-amber-400"}`}>
                    {r.status}
                  </span>
                </div>
              ))}
            </div>
          </div>

          <div className="mt-auto pt-8 text-blue-500 text-[10px]">
            © 2026 Human Plus Institute
          </div>
        </div>
      </div>

      {/* Right panel — form */}
      <div className="flex-1 flex items-center justify-center bg-white px-10">
        <div className="w-full max-w-[320px]">
          {/* Mobile brand */}
          <div className="flex items-center gap-2 mb-8 lg:hidden">
            <div className="w-6 h-6 rounded bg-blue-600 flex items-center justify-center">
              <Building2 size={12} className="text-white" />
            </div>
            <span className="text-sm font-semibold text-foreground">Human Plus Institute</span>
          </div>

          <div className="mb-7">
            <h1 className="text-lg font-semibold text-foreground">Login Payroll</h1>
            <p className="text-xs text-muted-foreground mt-1">
              Gunakan akun yang telah dibuat oleh HR/GA.
            </p>
          </div>

          <div className="space-y-4">
            <div>
              <label className="block text-xs font-medium text-foreground mb-1.5">Email</label>
              <input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="w-full px-3 py-2 text-xs border border-border rounded bg-white focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all"
              />
            </div>

            <div>
              <div className="flex items-center justify-between mb-1.5">
                <label className="text-xs font-medium text-foreground">Password</label>
                <span className="text-[10px] text-blue-500 cursor-pointer hover:underline">Lupa password?</span>
              </div>
              <div className="relative">
                <input
                  type={showPass ? "text" : "password"}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  className="w-full px-3 py-2 pr-9 text-xs border border-border rounded bg-white focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all"
                />
                <button
                  type="button"
                  onClick={() => setShowPass(!showPass)}
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
                >
                  {showPass ? <EyeOff size={13} /> : <Eye size={13} />}
                </button>
              </div>
            </div>

            <button
              onClick={onLogin}
              className="w-full py-2 text-sm font-medium rounded bg-blue-600 text-white hover:bg-blue-700 active:bg-blue-800 transition-colors"
            >
              Masuk
            </button>
          </div>

          {/* Divider */}
          <div className="flex items-center gap-3 my-5">
            <div className="flex-1 h-px bg-border" />
            <span className="text-[10px] text-muted-foreground">akses terbatas</span>
            <div className="flex-1 h-px bg-border" />
          </div>

          {/* Info note */}
          <div className="p-3 rounded border border-border bg-slate-50 flex items-start gap-2.5">
            <div className="w-5 h-5 rounded bg-blue-100 flex items-center justify-center flex-shrink-0 mt-0.5">
              <Building2 size={11} className="text-blue-600" />
            </div>
            <p className="text-[10px] text-muted-foreground leading-relaxed">
              Sistem ini hanya untuk karyawan internal. Hubungi <span className="text-foreground font-medium">HR/GA</span> jika belum memiliki akun.
            </p>
          </div>

          <p className="mt-6 text-center text-[10px] text-muted-foreground">
            © 2026 Human Plus Institute
          </p>
        </div>
      </div>
    </div>
  );
}

export default function App() {
  const [page, setPage] = useState<Page>("login");

  if (page === "login") {
    return <LoginPage onLogin={() => setPage("dashboard")} />;
  }

  return (
    <div className="flex h-screen overflow-hidden" style={{ fontFamily: "Inter, sans-serif" }}>
      <Sidebar activePage={page} onNavigate={setPage} />
      <div className="flex-1 flex flex-col overflow-hidden">
        <Topbar onLogout={() => setPage("login")} />
        {page === "dashboard" && <DashboardPage />}
        {page === "payroll" && <PayrollPage />}
      </div>
    </div>
  );
}
