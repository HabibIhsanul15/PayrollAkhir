import { Link, Outlet, useNavigate, useLocation } from "react-router-dom";
import { getUser, isAuthed, clearAuth } from "@/lib/auth";
import { useEffect, useMemo, useState } from "react";
import { LogOut, ChevronRight } from "lucide-react";
import { cn } from "@/lib/utils";
import {
  LayoutDashboard,
  DollarSign,
  ClipboardList,
  BarChart3,
  Users,
  FolderOpen,
  User,
} from "lucide-react";

function menuByRole(role) {
  const r = String(role || "").toLowerCase();

  // STAFF (default)
  const base = [
    { to: "/payrolls", label: "Payroll", icon: DollarSign },
    { to: "/my-profile", label: "Profil Saya", icon: User },
  ];

  // FAT
  if (r === "fat") {
    return [
      { to: "/dashboard", label: "Dasbor", icon: LayoutDashboard },
      { to: "/payrolls", label: "Payroll", icon: DollarSign },
      { to: "/reports/payroll", label: "Laporan Payroll", icon: BarChart3 },
      { to: "/master/positions", label: "Gaji Jabatan", icon: FolderOpen },
      { to: "/master/allowance-types", label: "Jenis Tunjangan", icon: ClipboardList },
      { to: "/master/position-rates", label: "Tarif Tunjangan Jabatan", icon: BarChart3 },
      { to: "/my-profile", label: "Profil Saya", icon: User },
    ];
  }

  // DIRECTOR
  if (r === "director") {
    return [
      { to: "/dashboard", label: "Dasbor", icon: LayoutDashboard },
      { to: "/payrolls", label: "Payroll", icon: DollarSign },
      { to: "/reports/payroll", label: "Laporan Payroll", icon: BarChart3 },
      { to: "/monthly-recaps", label: "Rekap Bulanan", icon: ClipboardList },
      { to: "/my-profile", label: "Profil Saya", icon: User },
    ];
  }

  // HCGA
  if (r === "hcga") {
    return [
      { to: "/dashboard", label: "Dasbor", icon: LayoutDashboard },
      { to: "/employees", label: "Data Karyawan", icon: Users },
      { to: "/master/positions", label: "Jabatan", icon: FolderOpen },
      { to: "/monthly-recaps", label: "Rekap Bulanan", icon: ClipboardList },
      { to: "/my-profile", label: "Profil Saya", icon: User },
    ];
  }

  return base;
}

function roleLabel(role) {
  const r = String(role || "").toLowerCase();
  return (
    {
      fat: "Finance Admin",
      director: "Director",
      staff: "Staff",
      employee: "Staff",
      hcga: "HCGA",
      admin: "Admin",
    }[r] || r || "-"
  );
}

export default function AppLayout() {
  const navigate = useNavigate();
  const location = useLocation();

  const [user, setUser] = useState(() => getUser());
  const [booting, setBooting] = useState(true);

  useEffect(() => {
    const sync = () => setUser(getUser());
    window.addEventListener("auth:changed", sync);
    window.addEventListener("storage", sync);
    return () => {
      window.removeEventListener("auth:changed", sync);
      window.removeEventListener("storage", sync);
    };
  }, []);

  useEffect(() => {
    if (!isAuthed()) {
      setBooting(false);
      clearAuth();
      navigate("/login", { replace: true });
      return;
    }
    if (!user) return;
    setBooting(false);
  }, [navigate, user]);

  const menus = useMemo(() => menuByRole(user?.role), [user?.role]);

  const handleLogout = () => {
    clearAuth();
    navigate("/login", { replace: true });
  };

  if (booting || (isAuthed() && !user)) {
    return (
      <div className="h-screen w-full bg-background flex items-center justify-center">
        <div className="rounded border bg-card px-6 py-5 shadow-[0_1px_4px_0_rgba(0,0,0,0.04)] text-center">
          <div className="text-sm font-semibold text-foreground">Loading...</div>
          <div className="text-xs text-muted-foreground mt-1">Menyiapkan sesi login</div>
        </div>
      </div>
    );
  }

  if (!isAuthed() || !user) return null;

  const isActive = (to) =>
    location.pathname === to || location.pathname.startsWith(to + "/");

  // Get active menu label for breadcrumb
  const activeMenu = menus.find(m => isActive(m.to))?.label || "Page";

  return (
    <div className="flex h-screen overflow-hidden bg-background">
      {/* Sidebar - Perfectly matched to Figma reference */}
      <aside className="w-56 flex-shrink-0 flex flex-col border-r border-border bg-white z-20">
        {/* Brand */}
        <div className="px-4 py-4 border-b border-border flex items-center justify-center">
          <img src="/logo.png" alt="Human Plus Logo" className="h-10 object-contain w-full" />
        </div>

        {/* Section label */}
        <div className="px-4 pt-5 pb-1.5 flex items-center justify-between">
          <span className="text-[9px] font-semibold uppercase tracking-widest text-muted-foreground opacity-60">
            Menu
          </span>
        </div>

        {/* Nav items */}
        <nav className="flex-1 px-2 pb-4 flex flex-col gap-px overflow-y-auto">
          {menus.map((m) => {
            const active = isActive(m.to);
            const Icon = m.icon;
            return (
              <Link
                key={m.to}
                to={m.to}
                className={cn(
                  "w-full flex items-center gap-2.5 px-3 py-2 rounded text-left transition-colors relative",
                  active
                    ? "bg-blue-50 text-blue-600 font-medium"
                    : "text-muted-foreground hover:bg-slate-50 hover:text-foreground"
                )}
              >
                {Icon && <Icon size={13} className={cn("flex-shrink-0", active && "text-blue-500")} />}
                <span className="text-xs truncate">{m.label}</span>
                {active && <div className="absolute right-0 top-1/2 -translate-y-1/2 w-1 h-4 rounded-l-full bg-blue-500" />}
              </Link>
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

      {/* Main Column */}
      <div className="flex-1 flex flex-col min-w-0 bg-background">
        {/* Topbar */}
        <header className="h-12 bg-white border-b border-border flex items-center justify-between px-5 flex-shrink-0 z-10">
          <div className="flex items-center gap-2">
            <div className="w-1.5 h-1.5 rounded-full bg-emerald-500"></div>
            <span className="text-xs text-muted-foreground">Payroll App</span>
          </div>
          <div className="flex items-center gap-4">
            <span className="text-xs text-muted-foreground">
              {user?.name ?? "-"} · <span className="text-foreground font-medium">{roleLabel(user?.role)}</span>
            </span>
            <button
              onClick={handleLogout}
              className="flex items-center gap-1.5 px-2.5 py-1 text-xs text-muted-foreground border border-border rounded hover:bg-muted hover:text-foreground transition-colors"
            >
              <LogOut size={11} />
              Logout
            </button>
          </div>
        </header>

        {/* Breadcrumb row */}
        <div className="bg-white border-b border-border px-6 py-2.5 flex items-center justify-between flex-shrink-0">
          <div className="flex items-center gap-2 text-xs text-muted-foreground">
            <div className="w-1.5 h-1.5 rounded-full bg-blue-500"></div>
            <span>Human Plus Institute</span>
            <ChevronRight size={11} className="opacity-40" />
            <span className="text-foreground font-medium">{activeMenu}</span>
          </div>
        </div>

        {/* Content area */}
        <main className="flex-1 overflow-auto p-6">
          <div className="max-w-6xl mx-auto">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  );
}
