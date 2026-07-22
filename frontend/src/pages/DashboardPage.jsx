import { useMemo, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import useSWR from "swr";
import { getUser } from "@/lib/auth";
import { currentPayrollMonth, monthLabel } from "@/lib/utils";
import PeriodDisplay from "@/components/PeriodDisplay";
import EmployeeHistoryHub from "@/components/EmployeeHistoryHub";

import {
  DollarSign,
  Users,
  UserCheck,
  ClipboardList,
  ChevronDown,
  TrendingUp,
  Activity
} from "lucide-react";

function todayMonth() {
  return currentPayrollMonth();
}

function periodeLabel(isoDate) {
  if (!isoDate) return "—";
  const d = new Date(isoDate);
  if (Number.isNaN(d.getTime())) return "—";
  return d.toLocaleString("id-ID", { month: "short", year: "numeric" });
}

export default function DashboardPage() {
  const navigate = useNavigate();
  const user = getUser();
  const role = String(user?.role || "").toLowerCase();

  const [month, setMonth] = useState(() => todayMonth());
  const isHCGA = role === "hcga";
  const isPayrollSummaryRole = role === "fat" || role === "director" || role === "staff";

  let apiUrl = "";
  if (isHCGA) {
    apiUrl = "/dashboard/hcga";
  } else if (isPayrollSummaryRole) {
    apiUrl = `/dashboard/summary?month=${encodeURIComponent(month)}`;
  } else {
    apiUrl = `/dashboard/summary?month=${encodeURIComponent(month)}`;
  }

  const { data, error, isLoading } = useSWR(apiUrl);

  const loading = isLoading;
  const err = error?.message;

  const hcgaCards = data?.cards || {};
  const hcgaLists = data?.lists || {};
  const noAccountList = Array.isArray(hcgaLists?.no_account) ? hcgaLists.no_account : [];

  const kpi = data?.kpi || {};
  const recent = Array.isArray(data?.recent_payrolls) ? data.recent_payrolls : [];
  const trend = useMemo(() => Array.isArray(data?.trend) ? data.trend : [], [data]);
  const statusCounts = Array.isArray(data?.status_counts) ? data.status_counts : [];

  const maxTrend = useMemo(() => {
    let mx = 0;
    for (const t of trend) mx = Math.max(mx, Number(t?.total || 0));
    return mx || 1;
  }, [trend]);

  return (
    <div className="min-h-screen bg-[#F8FAFC] pb-10">
      <div className="w-full">
        {/* Premium Header Box */}
        <div className="relative bg-gradient-to-br from-slate-900 via-indigo-900 to-slate-900 overflow-hidden rounded-3xl shadow-2xl mb-8 p-8 md:p-10">
          <div className="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-10"></div>
          <div className="absolute top-[-50px] right-[-50px] w-96 h-96 bg-indigo-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob"></div>
          <div className="absolute top-[50px] left-[-50px] w-72 h-72 bg-blue-400 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000"></div>

          <div className="relative z-10 flex flex-col md:flex-row md:items-end justify-between gap-6 text-white">
            <div>
              <h1 className="text-3xl md:text-4xl font-extrabold tracking-tight mb-2 drop-shadow-md">
                Dashboard Overview
              </h1>
              <p className="text-indigo-200 text-sm font-medium">
                {isHCGA
                  ? "Ringkasan HR & onboarding (karyawan & akun)."
                  : role === "staff"
                  ? "Ringkasan payroll dan riwayat jabatan Anda."
                  : "Ringkasan operasional payroll bulan ini."}
              </p>
            </div>
            
            <div className="flex items-center gap-3">
              {!isHCGA && (
                <div className="relative group">
                  <input
                    id="month-picker"
                    type="month"
                    value={month}
                    onChange={(e) => setMonth(e.target.value)}
                    onClick={(e) => {
                      try { e.target.showPicker(); } catch { /* Browser does not support showPicker. */ }
                    }}
                    className="absolute inset-0 opacity-0 cursor-pointer w-full h-full z-20"
                  />
                  <div className="flex items-center gap-2 px-5 py-2.5 bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/20 rounded-xl text-sm font-semibold text-white pointer-events-none transition-all duration-300 shadow-[0_4px_12px_rgba(0,0,0,0.1)]">
                    <span>{monthLabel(month)}</span>
                    <ChevronDown size={14} className="opacity-70 group-hover:opacity-100 transition-opacity" />
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>

        {err && (
          <div className="mb-8 rounded-xl bg-rose-500/10 backdrop-blur-md px-5 py-4 text-sm text-rose-200 border border-rose-500/30 flex items-center gap-3">
            <Activity className="w-5 h-5 text-rose-400" />
            {err}
          </div>
        )}

        {/* HCGA DASHBOARD */}
        {isHCGA ? (
          <div className="space-y-6">
            <div className="grid grid-cols-4 gap-6">
              {[
                { label: "Karyawan Aktif", val: hcgaCards.active, sub: "Status aktif saat ini", icon: Users, gradient: "from-blue-500 to-indigo-600" },
                { label: "Karyawan Nonaktif", val: hcgaCards.inactive, sub: "Tidak dapat mengakses", icon: UserCheck, gradient: "from-slate-400 to-slate-500" },
                { label: "Belum Punya Akun", val: hcgaCards.no_account, sub: "Kandidat create account", icon: Users, gradient: "from-emerald-400 to-teal-500" },
                { label: "Menunggu Rekap", val: hcgaCards.pending_recap, sub: "Bulan Ini", icon: ClipboardList, gradient: "from-orange-400 to-amber-500" },
              ].map((c, i) => {
                const Icon = c.icon;
                return (
                  <div key={i} className="group bg-white/80 backdrop-blur-xl rounded-3xl border border-white shadow-[0_8px_30px_rgb(0,0,0,0.04)] p-6 hover:shadow-[0_20px_40px_rgb(0,0,0,0.08)] hover:-translate-y-1 transition-all duration-300 overflow-hidden relative">
                    <div className={`absolute top-0 right-0 w-32 h-32 bg-gradient-to-br ${c.gradient} opacity-5 rounded-bl-full -mr-10 -mt-10 transition-transform duration-500 group-hover:scale-110`}></div>
                    <div className="flex items-center justify-between mb-4 relative z-10">
                      <div className={`w-12 h-12 rounded-2xl bg-gradient-to-br ${c.gradient} flex items-center justify-center text-white shadow-lg shadow-${c.gradient.split('-')[1]}/30`}>
                        <Icon size={20} />
                      </div>
                    </div>
                    <div className="relative z-10">
                      <div className="text-4xl font-extrabold text-slate-800 tracking-tight mb-1">
                        {loading ? "…" : (c.val ?? 0)}
                      </div>
                      <p className="text-sm font-semibold text-slate-500">{c.label}</p>
                      <p className="text-[11px] text-slate-400 mt-2 font-medium">{c.sub}</p>
                    </div>
                  </div>
                );
              })}
            </div>
            
            <div className="grid grid-cols-1 gap-6">
              <div className="bg-white/90 backdrop-blur-xl rounded-3xl border border-white shadow-[0_8px_30px_rgb(0,0,0,0.04)] overflow-hidden">
                <div className="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-white/50">
                  <div className="text-base font-bold text-slate-800 flex items-center gap-2">
                    <div className="w-2 h-6 rounded-full bg-indigo-500"></div>
                    Top 5 • Belum Punya Akun
                  </div>
                </div>
                <div className="p-0">
                  <div className="grid grid-cols-3 gap-4 px-8 py-4 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-widest bg-slate-50/50">
                    <div className="col-span-2">Karyawan</div>
                    <div className="text-right">Aksi</div>
                  </div>
                  {loading ? (
                    <div className="py-12 text-center text-sm font-medium text-slate-400 animate-pulse">Memuat data...</div>
                  ) : noAccountList.length === 0 ? (
                    <div className="py-16 flex flex-col items-center justify-center gap-4">
                      <div className="w-16 h-16 rounded-full bg-emerald-50 flex items-center justify-center shadow-inner">
                        <UserCheck size={28} className="text-emerald-500" />
                      </div>
                      <p className="text-sm font-medium text-slate-500">Semua karyawan sudah memiliki akun sistem.</p>
                    </div>
                  ) : (
                    <div className="divide-y divide-slate-100">
                      {noAccountList.map((e) => (
                        <div key={e.id} className="grid grid-cols-3 gap-4 px-8 py-5 items-center hover:bg-slate-50/80 transition-colors group">
                          <div className="col-span-2 flex items-center gap-4">
                            <div className="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-100 to-blue-50 flex items-center justify-center text-indigo-700 font-bold text-sm shadow-sm group-hover:scale-105 transition-transform">
                              {e.name.charAt(0)}
                            </div>
                            <div>
                              <div className="text-sm font-bold text-slate-800">{e.name}</div>
                              <div className="text-xs font-medium text-slate-500">{e.employee_code} • {e.department || "-"}</div>
                            </div>
                          </div>
                          <div className="text-right">
                            <Link to={`/employees/${e.id}`} className="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-indigo-50 text-indigo-600 font-semibold text-xs hover:bg-indigo-600 hover:text-white transition-all shadow-sm">
                              Kelola Akun
                            </Link>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>
        ) : role === "staff" ? (
          /* STAFF DASHBOARD */
          <div className="space-y-6">
            {/* 2 Charts (Trend & Status) */}
            <div className="grid grid-cols-2 gap-6">
              <div className="bg-white/90 backdrop-blur-xl rounded-3xl border border-white shadow-[0_8px_30px_rgb(0,0,0,0.04)] overflow-hidden">
                <div className="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-white/50">
                  <div className="text-base font-bold text-slate-800 flex items-center gap-2">
                    <TrendingUp className="w-5 h-5 text-indigo-500" />
                    Trend Gaji (6 Bulan)
                  </div>
                </div>
                <div className="p-8">
                  {loading ? (
                    <div className="py-10 text-sm font-medium text-slate-400 text-center animate-pulse">Memuat...</div>
                  ) : trend.length === 0 ? (
                    <div className="py-12 flex flex-col items-center justify-center gap-3">
                      <div className="w-16 h-16 rounded-full bg-slate-50 flex items-center justify-center shadow-inner">
                        <ClipboardList size={24} className="text-slate-300" />
                      </div>
                      <p className="text-sm font-medium text-slate-400">Belum ada data trend.</p>
                    </div>
                  ) : (
                    <div className="space-y-5">
                      {trend.map((t) => {
                        const val = Number(t?.total || 0);
                        const w = Math.round((val / maxTrend) * 100);
                        return (
                          <div key={t.month} className="flex items-center gap-5 group">
                            <div className="w-[80px] text-xs text-slate-500 font-bold uppercase tracking-wider">
                              {t.month}
                            </div>
                            <div className="flex-1">
                              <div className="h-3 rounded-full bg-slate-100 overflow-hidden shadow-inner">
                                <div 
                                  className="h-full rounded-full bg-gradient-to-r from-blue-500 to-indigo-500 relative transition-all duration-1000 ease-out" 
                                  style={{ width: `${w}%` }} 
                                >
                                  <div className="absolute inset-0 bg-white/20 w-full animate-[shimmer_2s_infinite]"></div>
                                </div>
                              </div>
                            </div>
                            <div className="w-12 text-right text-sm font-extrabold text-slate-700">
                              {val}
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  )}
                </div>
              </div>

              <div className="bg-white/90 backdrop-blur-xl rounded-3xl border border-white shadow-[0_8px_30px_rgb(0,0,0,0.04)] overflow-hidden flex flex-col">
                <div className="px-8 py-6 border-b border-slate-100 bg-white/50">
                  <div className="text-base font-bold text-slate-800 flex items-center gap-2">
                    <Activity className="w-5 h-5 text-indigo-500" />
                    Status Payroll Anda
                  </div>
                  <div className="text-xs font-semibold text-slate-400 mt-1"><PeriodDisplay period={month} /></div>
                </div>
                <div className="p-8 flex-1 flex flex-col justify-center">
                  {loading ? (
                    <div className="text-sm font-medium text-slate-400 text-center animate-pulse">Memuat...</div>
                  ) : statusCounts.length === 0 ? (
                    <div className="text-sm font-medium text-slate-400 text-center">Belum ada status.</div>
                  ) : (
                    <div className="space-y-6">
                      {statusCounts.map((s) => (
                        <div key={s.status} className="group">
                          <div className="flex items-end justify-between mb-2">
                            <span className="text-xs font-bold text-slate-500 uppercase tracking-widest">{s.status}</span>
                            <span className="text-lg font-black text-slate-800 leading-none">{s.total}</span>
                          </div>
                          <div className="h-2 bg-slate-100 rounded-full overflow-hidden shadow-inner">
                            <div 
                              className="h-full rounded-full bg-gradient-to-r from-indigo-400 to-indigo-600 transition-all duration-1000" 
                              style={{ width: `${(s.total / (kpi.payroll_count || 1)) * 100}%` }} 
                            />
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </div>
            </div>

            {/* Riwayat Jabatan dan Payroll */}
            {user?.employee?.id ? (
              <EmployeeHistoryHub employeeId={user.employee.id} role={role} />
            ) : (
              <div className="p-8 bg-white/90 rounded-3xl border border-white text-center shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
                <p className="text-slate-500">Akun Anda belum terhubung dengan profil Karyawan.</p>
              </div>
            )}
          </div>
        ) : (
          /* PAYROLL SUMMARY DASHBOARD */
          <div className="space-y-6">
            {/* Stat cards */}
            <div className="grid grid-cols-3 gap-6">
              {[
                {
                  label: "Payroll Bulan Ini",
                  value: loading ? "…" : (kpi.payroll_count ?? 0),
                  sub: "Total terbuat",
                  icon: DollarSign,
                  gradient: "from-blue-600 to-indigo-600",
                  trend: <PeriodDisplay period={month} />,
                },
                {
                  label: "Karyawan Aktif",
                  value: loading ? "…" : (kpi.employees_active ?? 0),
                  sub: "Status aktif",
                  icon: Users,
                  gradient: "from-emerald-500 to-teal-500",
                  trend: "Bulan ini",
                },
                {
                  label: "Karyawan Nonaktif",
                  value: loading ? "…" : (kpi.employees_inactive ?? 0),
                  sub: "Tidak akses sistem",
                  icon: UserCheck,
                  gradient: "from-slate-500 to-slate-600",
                  trend: "Stabil",
                },
              ].map((card, idx) => {
                const Icon = card.icon;
                return (
                  <div
                    key={idx}
                    className="group bg-white/90 backdrop-blur-xl rounded-3xl border border-white shadow-[0_8px_30px_rgb(0,0,0,0.06)] p-6 hover:shadow-[0_20px_40px_rgb(0,0,0,0.12)] hover:-translate-y-1.5 transition-all duration-300 relative overflow-hidden"
                  >
                    <div className={`absolute -right-6 -top-6 w-32 h-32 bg-gradient-to-br ${card.gradient} opacity-[0.07] rounded-full group-hover:scale-150 transition-transform duration-700 ease-out`}></div>
                    <div className="relative z-10">
                      <div className="flex items-start justify-between mb-6">
                        <div className={`w-12 h-12 rounded-2xl bg-gradient-to-br ${card.gradient} flex items-center justify-center text-white shadow-lg shadow-${card.gradient.split('-')[1]}/30`}>
                          <Icon size={22} />
                        </div>
                        <span className="text-[10px] font-bold px-3 py-1.5 rounded-full bg-slate-100 text-slate-500 shadow-inner">
                          {card.trend}
                        </span>
                      </div>
                      <div className="text-4xl font-extrabold text-slate-800 tracking-tight mb-2">
                        {card.value}
                      </div>
                      <div className="flex items-center justify-between mt-1">
                        <span className="text-sm font-bold text-slate-500">{card.label}</span>
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>

            {/* Middle row */}
            <div className="grid grid-cols-3 gap-6">
              <div className="col-span-2 bg-white/90 backdrop-blur-xl rounded-3xl border border-white shadow-[0_8px_30px_rgb(0,0,0,0.04)] overflow-hidden">
                <div className="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-white/50">
                  <div className="text-base font-bold text-slate-800 flex items-center gap-2">
                    <TrendingUp className="w-5 h-5 text-indigo-500" />
                    Trend Payroll (6 Bulan)
                  </div>
                </div>
                <div className="p-8">
                  {loading ? (
                    <div className="py-10 text-sm font-medium text-slate-400 text-center animate-pulse">Memuat...</div>
                  ) : trend.length === 0 ? (
                    <div className="py-12 flex flex-col items-center justify-center gap-3">
                      <div className="w-16 h-16 rounded-full bg-slate-50 flex items-center justify-center shadow-inner">
                        <ClipboardList size={24} className="text-slate-300" />
                      </div>
                      <p className="text-sm font-medium text-slate-400">Belum ada data trend.</p>
                    </div>
                  ) : (
                    <div className="space-y-5">
                       {trend.map((t) => {
                        const val = Number(t?.total || 0);
                        const w = Math.round((val / maxTrend) * 100);
                        return (
                          <div key={t.month} className="flex items-center gap-5 group">
                            <div className="w-[80px] text-xs text-slate-500 font-bold uppercase tracking-wider">
                              {t.month}
                            </div>
                            <div className="flex-1">
                              <div className="h-3 rounded-full bg-slate-100 overflow-hidden shadow-inner">
                                <div 
                                  className="h-full rounded-full bg-gradient-to-r from-blue-500 to-indigo-500 relative transition-all duration-1000 ease-out" 
                                  style={{ width: `${w}%` }} 
                                >
                                  <div className="absolute inset-0 bg-white/20 w-full animate-[shimmer_2s_infinite]"></div>
                                </div>
                              </div>
                            </div>
                            <div className="w-12 text-right text-sm font-extrabold text-slate-700">
                              {val}
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  )}
                </div>
              </div>

              <div className="bg-white/90 backdrop-blur-xl rounded-3xl border border-white shadow-[0_8px_30px_rgb(0,0,0,0.04)] overflow-hidden flex flex-col">
                <div className="px-8 py-6 border-b border-slate-100 bg-white/50">
                  <div className="text-base font-bold text-slate-800 flex items-center gap-2">
                    <Activity className="w-5 h-5 text-indigo-500" />
                    Status Payroll
                  </div>
                  <div className="text-xs font-semibold text-slate-400 mt-1"><PeriodDisplay period={month} /></div>
                </div>
                <div className="p-8 flex-1 flex flex-col justify-center">
                  {loading ? (
                    <div className="text-sm font-medium text-slate-400 text-center animate-pulse">Memuat...</div>
                  ) : statusCounts.length === 0 ? (
                    <div className="text-sm font-medium text-slate-400 text-center">Belum ada status.</div>
                  ) : (
                    <div className="space-y-6">
                      {statusCounts.map((s) => (
                        <div key={s.status} className="group">
                          <div className="flex items-end justify-between mb-2">
                            <span className="text-xs font-bold text-slate-500 uppercase tracking-widest">{s.status}</span>
                            <span className="text-lg font-black text-slate-800 leading-none">{s.total}</span>
                          </div>
                          <div className="h-2 bg-slate-100 rounded-full overflow-hidden shadow-inner">
                            <div 
                              className="h-full rounded-full bg-gradient-to-r from-indigo-400 to-indigo-600 transition-all duration-1000" 
                              style={{ width: `${(s.total / (kpi.payroll_count || 1)) * 100}%` }} 
                            />
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </div>
            </div>

            {/* Bottom row: Recent payrolls */}
            <div className="bg-white/90 backdrop-blur-xl rounded-3xl border border-white shadow-[0_8px_30px_rgb(0,0,0,0.04)] overflow-hidden">
              <div className="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-white/50">
                <div>
                  <div className="text-base font-bold text-slate-800">Payroll Terbaru</div>
                  <div className="text-xs font-semibold text-slate-400 mt-1">Data terbaru pada <PeriodDisplay period={month} /></div>
                </div>
                <span className="text-xs font-bold px-4 py-2 rounded-xl bg-indigo-50 text-indigo-600">
                  {recent.length} Transaksi
                </span>
              </div>
              
              <div className="p-0">
                <div className="grid grid-cols-12 gap-4 px-8 py-4 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-widest bg-slate-50/50">
                  <div className="col-span-1">ID</div>
                  <div className="col-span-4">Pegawai</div>
                  <div className="col-span-2">Periode</div>
                  <div className="col-span-3">Status</div>
                  <div className="col-span-2">Hari Kerja</div>
                </div>

                {loading ? (
                  <div className="py-16 flex items-center justify-center">
                    <p className="text-sm font-medium text-slate-400 animate-pulse">Memuat data terbaru...</p>
                  </div>
                ) : recent.length === 0 ? (
                  <div className="py-16 flex flex-col items-center justify-center gap-4">
                    <div className="w-16 h-16 rounded-full bg-slate-50 flex items-center justify-center shadow-inner">
                      <ClipboardList size={28} className="text-slate-300" />
                    </div>
                    <p className="text-sm font-medium text-slate-500">Belum ada data payroll untuk periode ini.</p>
                  </div>
                ) : (
                  <div className="divide-y divide-slate-100">
                    {recent.map((r) => (
                      <div 
                        key={r.id} 
                        onClick={() => navigate(`/payrolls/${r.id}`)}
                        className="grid grid-cols-12 gap-4 px-8 py-5 items-center hover:bg-slate-50/80 transition-colors group cursor-pointer"
                      >
                        <div className="col-span-1 text-sm font-bold text-indigo-600">
                          #{r.id}
                        </div>
                        <div className="col-span-4 flex items-center gap-3">
                          <div className="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 font-bold text-xs shadow-sm">
                            {r.employee_name ? r.employee_name.charAt(0) : "?"}
                          </div>
                          <div>
                            <div className="text-sm font-bold text-slate-800">{r.employee_name || "—"}</div>
                            <div className="text-xs font-semibold text-slate-400 mt-0.5">{r.employee_code || "—"} • {r.position || "—"} • {r.department || "—"}</div>
                          </div>
                        </div>
                        <div className="col-span-2 text-xs font-semibold text-slate-500">
                          {periodeLabel(r.periode)}
                        </div>
                        <div className="col-span-3">
                          <span className={`inline-flex items-center px-3 py-1 rounded-lg text-[10px] font-extrabold uppercase tracking-widest ${
                            r.status === 'paid' ? 'bg-emerald-100 text-emerald-700' :
                            r.status === 'approved' ? 'bg-blue-100 text-blue-700' :
                            'bg-amber-100 text-amber-700'
                          }`}>
                            {r.status}
                          </span>
                        </div>
                        <div className="col-span-2">
                          <span className="inline-flex text-xs font-bold text-slate-500 bg-slate-100 px-3 py-1 rounded-lg shadow-inner border border-slate-200">
                            {Number(r.total_mandays ?? 0)} Hari
                          </span>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
