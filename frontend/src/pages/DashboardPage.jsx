import { useEffect, useMemo, useState } from "react";
import { Link } from "react-router-dom";
import useSWR from "swr";
import { api } from "@/lib/api";
import { getUser } from "@/lib/auth";
import { monthLabel } from "@/lib/utils";

import {
  DollarSign,
  Users,
  UserCheck,
  ClipboardList,
  ChevronDown,
  RefreshCw,
} from "lucide-react";

function todayMonth() {
  const d = new Date();
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, "0");
  return `${yyyy}-${mm}`;
}



function periodeLabel(isoDate) {
  if (!isoDate) return "—";
  const d = new Date(isoDate);
  if (Number.isNaN(d.getTime())) return "—";
  return d.toLocaleString("id-ID", { month: "short", year: "numeric" });
}

export default function DashboardPage() {
  const user = getUser();
  const role = String(user?.role || "").toLowerCase();

  const [month, setMonth] = useState(() => todayMonth());
  const isHCGA = role === "hcga";
  const isPayrollSummaryRole = role === "fat" || role === "director" || role === "staff";

  // Determine API URL based on role
  let apiUrl = "";
  if (isHCGA) {
    apiUrl = "/dashboard/hcga";
  } else if (isPayrollSummaryRole) {
    apiUrl = `/dashboard/summary?month=${encodeURIComponent(month)}`;
  } else {
    apiUrl = `/dashboard/summary?month=${encodeURIComponent(month)}`;
  }

  // Use SWR for instant caching and revalidation
  const { data, error, isLoading, mutate } = useSWR(apiUrl);

  const loading = isLoading;
  const err = error?.message;

  function load() {
    mutate();
  }

  const hcgaCards = data?.cards || {};
  const hcgaLists = data?.lists || {};
  const noAccountList = Array.isArray(hcgaLists?.no_account) ? hcgaLists.no_account : [];
  const noSalaryList = Array.isArray(hcgaLists?.no_salary_profile) ? hcgaLists.no_salary_profile : [];

  const kpi = data?.kpi || {};
  const recent = Array.isArray(data?.recent_payrolls) ? data.recent_payrolls : [];
  const trend = Array.isArray(data?.trend) ? data.trend : [];
  const statusCounts = Array.isArray(data?.status_counts) ? data.status_counts : [];

  const maxTrend = useMemo(() => {
    let mx = 0;
    for (const t of trend) mx = Math.max(mx, Number(t?.total || 0));
    return mx || 1;
  }, [trend]);

  return (
    <div>
      {/* Title + period */}
      <div className="flex items-start justify-between mb-6">
        <div>
          <h1 className="text-lg font-semibold text-foreground">Dashboard</h1>
          <p className="text-xs text-muted-foreground mt-0.5">
            {isHCGA
              ? "Ringkasan HR & onboarding (karyawan & akun)."
              : "Ringkasan payroll per bulan (tanpa membocorkan nominal gaji)."}
          </p>
        </div>
        
        <div className="flex items-center gap-2">
          {!isHCGA && (
            <div>
              <input
                type="month"
                value={month}
                onChange={(e) => setMonth(e.target.value)}
                className="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
              />
              <div className="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-border rounded text-xs text-foreground pointer-events-none">
                <span>{monthLabel(month)}</span>
                <ChevronDown size={11} className="text-muted-foreground" />
              </div>
            </div>
          )}
          <button 
            onClick={load}
            disabled={loading}
            className="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-border rounded text-xs text-muted-foreground hover:text-foreground transition-colors disabled:opacity-50"
          >
            <RefreshCw size={11} className={loading ? "animate-spin" : ""} />
            Refresh
          </button>
        </div>
      </div>

      {err && (
        <div className="mb-6 rounded bg-rose-50 px-4 py-3 text-xs text-rose-600 border border-rose-100">
          {err}
        </div>
      )}

      {/* HCGA DASHBOARD */}
      {isHCGA ? (
        <>
          <div className="grid grid-cols-4 gap-4 mb-5">
            {[
              { label: "Karyawan Aktif", val: hcgaCards.active, sub: "Status aktif saat ini", icon: Users, color: "#2563EB", bg: "#EFF6FF" },
              { label: "Karyawan Nonaktif", val: hcgaCards.inactive, sub: "Tidak dapat mengakses", icon: UserCheck, color: "#94A3B8", bg: "#F8FAFC" },
              { label: "Belum Punya Akun", val: hcgaCards.no_account, sub: "Kandidat create account", icon: Users, color: "#059669", bg: "#ECFDF5" },
              { label: "Menunggu Rekap", val: hcgaCards.pending_recap, sub: "Bulan Ini", icon: ClipboardList, color: "#D97706", bg: "#FEF3C7" },
            ].map((c, i) => {
              const Icon = c.icon;
              return (
                <div key={i} className="bg-white rounded border border-border overflow-hidden" style={{ boxShadow: "0 1px 4px 0 rgba(0,0,0,0.04)" }}>
                  <div className="h-0.5 w-full" style={{ background: c.color }} />
                  <div className="p-4">
                    <div className="flex items-start justify-between mb-3">
                      <p className="text-[11px] font-medium text-muted-foreground leading-tight">{c.label}</p>
                      <div className="w-7 h-7 rounded flex items-center justify-center flex-shrink-0" style={{ background: c.bg }}>
                        <Icon size={13} style={{ color: c.color }} />
                      </div>
                    </div>
                    <div className="text-2xl font-semibold text-foreground tracking-tight mb-1" style={{ letterSpacing: "-0.02em" }}>
                      {loading ? "…" : (c.val ?? 0)}
                    </div>
                    <div className="flex items-center justify-between mt-2 pt-2 border-t border-border">
                      <span className="text-[10px] text-muted-foreground">{c.sub}</span>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
          
          <div className="grid grid-cols-1 gap-4">
            <div className="bg-white rounded border border-border overflow-hidden" style={{ boxShadow: "0 1px 4px 0 rgba(0,0,0,0.04)" }}>
              <div className="px-5 py-3.5 border-b border-border">
                <div className="text-xs font-semibold text-foreground">Top 5 • Belum Punya Akun</div>
              </div>
              <div className="p-0">
                <div className="grid grid-cols-3 gap-2 px-5 py-2.5 border-b border-border text-[9px] font-semibold text-muted-foreground uppercase tracking-widest bg-slate-50/50">
                  <div className="col-span-2">Karyawan</div>
                  <div>Aksi</div>
                </div>
                {loading ? (
                  <div className="py-8 text-center text-xs text-muted-foreground">Memuat...</div>
                ) : noAccountList.length === 0 ? (
                  <div className="py-8 flex flex-col items-center justify-center gap-2">
                    <div className="w-8 h-8 rounded-full bg-emerald-50 flex items-center justify-center">
                      <UserCheck size={14} className="text-emerald-500" />
                    </div>
                    <p className="text-xs text-muted-foreground">Semua karyawan sudah punya akun.</p>
                  </div>
                ) : (
                  <div>
                    {noAccountList.map((e) => (
                      <div key={e.id} className="px-5 py-3 border-b border-border last:border-b-0 flex items-center justify-between hover:bg-slate-50 transition-colors">
                        <div>
                          <div className="text-xs font-medium text-foreground">{e.name}</div>
                          <div className="text-[10px] text-muted-foreground">{e.employee_code} • {e.department || "-"}</div>
                        </div>
                        <Link to={`/employees/${e.id}`} className="text-[10px] text-blue-600 font-medium hover:underline">
                          Kelola
                        </Link>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          </div>
        </>
      ) : (
        /* PAYROLL SUMMARY DASHBOARD */
        <>
          {/* Stat cards */}
          <div className="grid grid-cols-3 gap-4 mb-5">
            {[
              {
                label: "Payroll Bulan Ini",
                value: loading ? "…" : (kpi.payroll_count ?? 0),
                sub: "Jumlah payroll terbuat",
                icon: DollarSign,
                accent: "#2563EB",
                accentLight: "#EFF6FF",
                trend: monthLabel(month),
              },
              {
                label: "Karyawan Aktif",
                value: loading ? "…" : (kpi.employees_active ?? 0),
                sub: "Status aktif saat ini",
                icon: Users,
                accent: "#059669",
                accentLight: "#ECFDF5",
                trend: "Bulan ini",
              },
              {
                label: "Karyawan Nonaktif",
                value: loading ? "…" : (kpi.employees_inactive ?? 0),
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

          {/* Middle row */}
          <div className="grid grid-cols-3 gap-4 mb-5">
            <div className="col-span-2 bg-white rounded border border-border overflow-hidden" style={{ boxShadow: "0 1px 4px 0 rgba(0,0,0,0.04)" }}>
              <div className="px-5 py-3.5 border-b border-border flex items-center justify-between">
                <div>
                  <div className="text-xs font-semibold text-foreground">Trend Payroll (6 Bulan)</div>
                </div>
              </div>
              <div className="p-5">
                {loading ? (
                  <div className="py-4 text-xs text-muted-foreground text-center">Memuat...</div>
                ) : trend.length === 0 ? (
                  <div className="py-4 flex flex-col items-center justify-center gap-2">
                    <ClipboardList size={14} className="text-slate-300" />
                    <p className="text-xs text-muted-foreground">Belum ada data trend.</p>
                  </div>
                ) : (
                  <div className="space-y-3">
                    {trend.map((t) => {
                      const val = Number(t?.total || 0);
                      const w = Math.round((val / maxTrend) * 100);
                      return (
                        <div key={t.month} className="flex items-center gap-4">
                          <div className="w-[70px] text-[10px] text-muted-foreground font-medium">
                            {t.month}
                          </div>
                          <div className="flex-1">
                            <div className="h-1.5 rounded-full bg-slate-100 overflow-hidden">
                              <div className="h-1.5 rounded-full bg-blue-500" style={{ width: `${w}%` }} />
                            </div>
                          </div>
                          <div className="w-8 text-right text-[11px] font-semibold text-foreground">
                            {val}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                )}
              </div>
            </div>

            <div className="bg-white rounded border border-border overflow-hidden" style={{ boxShadow: "0 1px 4px 0 rgba(0,0,0,0.04)" }}>
              <div className="px-5 py-3.5 border-b border-border">
                <div className="text-xs font-semibold text-foreground">Status Payroll</div>
                <div className="text-[10px] text-muted-foreground mt-0.5">{monthLabel(month)}</div>
              </div>
              <div className="p-5">
                {loading ? (
                  <div className="text-xs text-muted-foreground text-center">Memuat...</div>
                ) : statusCounts.length === 0 ? (
                  <div className="text-xs text-muted-foreground text-center">Belum ada status.</div>
                ) : (
                  <div className="space-y-3">
                    {statusCounts.map((s) => (
                      <div key={s.status}>
                        <div className="flex items-center justify-between mb-1.5">
                          <span className="text-[11px] text-muted-foreground capitalize">{s.status}</span>
                          <span className="text-[11px] font-semibold text-foreground">{s.total}</span>
                        </div>
                        <div className="h-1 bg-slate-100 rounded-full overflow-hidden">
                          <div className="h-full rounded-full bg-blue-500" style={{ width: `${(s.total / (kpi.payroll_count || 1)) * 100}%` }} />
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Bottom row: Recent payrolls */}
          <div className="bg-white rounded border border-border overflow-hidden" style={{ boxShadow: "0 1px 4px 0 rgba(0,0,0,0.04)" }}>
            <div className="px-5 py-3.5 border-b border-border flex items-center justify-between">
              <div>
                <div className="text-xs font-semibold text-foreground">Payroll Terbaru</div>
                <div className="text-[10px] text-muted-foreground mt-0.5">Data terbaru pada {monthLabel(month)}</div>
              </div>
              <span className="text-[10px] px-2.5 py-1 rounded border border-border text-muted-foreground">
                {recent.length} data
              </span>
            </div>
            
            <div className="p-0">
              <div className="grid grid-cols-12 gap-2 px-5 py-2.5 border-b border-border text-[9px] font-semibold text-muted-foreground uppercase tracking-widest bg-slate-50/50">
                <div className="col-span-1">ID</div>
                <div className="col-span-4">Pegawai</div>
                <div className="col-span-2">Periode</div>
                <div className="col-span-3">Status</div>
                <div className="col-span-2">Algoritma</div>
              </div>

              {loading ? (
                <div className="py-10 flex items-center justify-center">
                  <p className="text-xs text-muted-foreground">Memuat...</p>
                </div>
              ) : recent.length === 0 ? (
                <div className="py-12 flex flex-col items-center justify-center gap-2">
                  <div className="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center">
                    <ClipboardList size={14} className="text-slate-300" />
                  </div>
                  <p className="text-xs text-muted-foreground">Belum ada data payroll untuk periode ini.</p>
                </div>
              ) : (
                <div className="divide-y divide-border">
                  {recent.map((r) => (
                    <div key={r.id} className="grid grid-cols-12 gap-2 px-5 py-3 items-center hover:bg-slate-50 transition-colors">
                      <div className="col-span-1 text-[11px] font-medium text-blue-600">
                        <Link to={`/payrolls/${r.id}`} className="hover:underline">
                          #{r.id}
                        </Link>
                      </div>
                      <div className="col-span-4">
                        <div className="text-xs font-medium text-foreground">{r.employee_name || "—"}</div>
                        <div className="text-[10px] text-muted-foreground mt-0.5">{r.employee_code || "—"}</div>
                      </div>
                      <div className="col-span-2 text-[11px] text-muted-foreground">
                        {periodeLabel(r.periode)}
                      </div>
                      <div className="col-span-3">
                        <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-medium uppercase tracking-wider bg-slate-100 text-slate-700">
                          {r.status}
                        </span>
                      </div>
                      <div className="col-span-2">
                        <span className="inline-flex text-[10px] text-muted-foreground">
                          {r.salary_alg}
                        </span>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </>
      )}
    </div>
  );
}
