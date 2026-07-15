import { useEffect, useMemo, useState } from "react";
import useSWR from "swr";
import { Link, useNavigate } from "react-router-dom";
import { api } from "@/lib/api";
import { getUser } from "@/lib/auth";
import { currentPayrollMonth, monthLabel } from "@/lib/utils";
import PeriodDisplay from "@/components/PeriodDisplay";
import StatusBadge from "@/components/StatusBadge";
import AlertMessage from "@/components/AlertMessage";

import { Button } from "@/components/ui/button";
import { Download, RefreshCw, ChevronDown, FileText } from "lucide-react";

function todayMonth() {
  return currentPayrollMonth();
}

function fmtRp(n) {
  const x = Number(n || 0);
  return x.toLocaleString("id-ID", { style: "currency", currency: "IDR" });
}

function StatusBadgeText({ status }) {
  return <StatusBadge status={status} variant="text" />;
}

function downloadText(filename, text) {
  const blob = new Blob([text], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

function toCsv(rows) {
  const headers = [
    "No",
    "EmployeeCode",
    "EmployeeName",
    "BankName",
    "BankAccountNumber",
    "Periode",
    "Status",
    "GajiPokok",
    "Tunjangan",
    "Potongan",
    "Total",
    "Alg",
    "CreatedAt",
  ];

  const esc = (v) => {
    const s = String(v ?? "");
    if (/[,"\n]/.test(s)) return `"${s.replace(/"/g, '""')}"`;
    return s;
  };

  const lines = [];
  lines.push(headers.join(","));

  for (let i = 0; i < rows.length; i++) {
    const r = rows[i];
    lines.push(
      [
        i + 1,
        r.employee_code,
        r.employee_name,
        r.bank_name || "-",
        r.bank_account_number || "-",
        r.periode,
        r.status,
        r.gaji_pokok,
        r.tunjangan,
        r.potongan,
        r.total,
        r.salary_alg,
        r.created_at,
      ]
        .map(esc)
        .join(",")
    );
  }

  return lines.join("\n");
}

export default function PayrollReportPage() {
  const nav = useNavigate();
  const user = getUser();
  const role = String(user?.role || "").toLowerCase();

  const [month, setMonth] = useState(() => todayMonth());
  const [status, setStatus] = useState("");

  const qs = new URLSearchParams();
  qs.set("month", month);
  if (status) qs.set("status", status);

  const { data, error, isLoading, mutate } = useSWR(`/reports/payroll?${qs.toString()}`);

  const loading = isLoading;
  const err = error?.message;

  const rows = Array.isArray(data?.rows) ? data.rows : [];
  const summary = data?.summary || {};

  function load() {
    mutate();
  }

  const title = useMemo(() => {
    const roleLabel = role === "fat" ? "Finance Admin" : "Director";
    return `Payroll Report • ${roleLabel}`;
  }, [role]);

  function exportCsv() {
    const csv = toCsv(rows);
    const file = `payroll-report-${month}${status ? `-${status}` : ""}.csv`;
    downloadText(file, csv);
  }

  return (
    <div>
      {/* Title + actions */}
      <div className="flex items-start justify-between mb-5">
        <div>
          <h1 className="text-lg font-semibold text-foreground">{title}</h1>
          <p className="text-xs text-muted-foreground mt-0.5">
            Laporan payroll (nominal ditampilkan) khusus FAT & Director.
          </p>
        </div>
        <div className="flex items-center gap-2">

          <button 
            onClick={exportCsv}
            disabled={loading || rows.length === 0}
            className="flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 rounded text-xs font-medium text-white hover:bg-slate-900 transition-colors disabled:opacity-50"
          >
            <Download size={11} />
            Export CSV
          </button>
        </div>
      </div>

      {err && (
        <div className="mb-4 rounded bg-rose-50 px-4 py-3 text-xs text-rose-600 border border-rose-100">
          {err}
        </div>
      )}

      {/* Filters */}
      <div className="bg-white border border-border rounded p-4 mb-4" style={{ boxShadow: "0 1px 4px 0 rgba(0,0,0,0.04)" }}>
        <div className="flex flex-col md:flex-row md:items-end gap-3 max-w-xl">
          <div className="flex-1">
            <label className="block text-[10px] font-medium text-muted-foreground mb-1.5">Periode Bulan</label>
            <input
              type="month"
              value={month}
              onChange={(e) => setMonth(e.target.value)}
              className="w-full px-3 py-1.5 text-xs border border-border rounded bg-white focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all"
            />
          </div>
          <div className="w-full md:w-52">
            <label className="block text-[10px] font-medium text-muted-foreground mb-1.5">Status</label>
            <div>
              <select
                value={status}
                onChange={(e) => setStatus(e.target.value)}
                className="w-full appearance-none pl-3 pr-7 py-1.5 text-xs border border-border rounded bg-white focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all"
              >
                <option value="">Semua Status</option>
                <option value="draft">DRAFT</option>
                <option value="requested">REQUESTED</option>
                <option value="approved">APPROVED</option>
                <option value="paid">PAID</option>
                <option value="rejected">REJECTED</option>
              </select>
              <ChevronDown size={11} className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" />
            </div>
          </div>
        </div>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-5 gap-4 mb-4">
        <div className="bg-white border border-border rounded p-4" style={{ boxShadow: "0 1px 4px 0 rgba(0,0,0,0.04)" }}>
          <div className="text-[10px] font-medium text-muted-foreground uppercase tracking-wider mb-1">Jumlah Payroll</div>
          <div className="text-xl font-semibold text-foreground">{loading ? "…" : summary.count ?? 0}</div>
          <div className="text-[10px] text-muted-foreground mt-1"><PeriodDisplay period={month} /></div>
        </div>
        <div className="bg-white border border-border rounded p-4" style={{ boxShadow: "0 1px 4px 0 rgba(0,0,0,0.04)" }}>
          <div className="text-[10px] font-medium text-muted-foreground uppercase tracking-wider mb-1">Total Gaji Pokok</div>
          <div className="text-xl font-semibold text-foreground">{loading ? "…" : fmtRp(summary.sum_gaji_pokok ?? 0)}</div>
          <div className="text-[10px] text-muted-foreground mt-1">Akumulasi</div>
        </div>
        <div className="bg-white border border-border rounded p-4" style={{ boxShadow: "0 1px 4px 0 rgba(0,0,0,0.04)" }}>
          <div className="text-[10px] font-medium text-muted-foreground uppercase tracking-wider mb-1">Total Tunjangan</div>
          <div className="text-xl font-semibold text-foreground">{loading ? "…" : fmtRp(summary.sum_tunjangan ?? 0)}</div>
          <div className="text-[10px] text-muted-foreground mt-1">Akumulasi</div>
        </div>
        <div className="bg-white border border-border rounded p-4" style={{ boxShadow: "0 1px 4px 0 rgba(0,0,0,0.04)" }}>
          <div className="text-[10px] font-medium text-muted-foreground uppercase tracking-wider mb-1">Total Potongan</div>
          <div className="text-xl font-semibold text-foreground">{loading ? "…" : fmtRp(summary.sum_potongan ?? 0)}</div>
          <div className="text-[10px] text-muted-foreground mt-1">Akumulasi</div>
        </div>
        <div className="bg-white border border-border rounded p-4" style={{ boxShadow: "0 1px 4px 0 rgba(0,0,0,0.04)" }}>
          <div className="text-[10px] font-medium text-muted-foreground uppercase tracking-wider mb-1">Total Dibayarkan</div>
          <div className="text-xl font-semibold text-blue-600">{loading ? "…" : fmtRp(summary.sum_total ?? 0)}</div>
          <div className="text-[10px] text-muted-foreground mt-1">Net Dibayarkan</div>
        </div>
      </div>

      {/* Table */}
      <div className="bg-white border border-border rounded overflow-hidden" style={{ boxShadow: "0 1px 4px 0 rgba(0,0,0,0.04)" }}>
        <div className="px-5 py-3 border-b border-border flex items-center justify-between">
          <div>
            <span className="text-sm font-medium text-foreground">Detail Payroll</span>
            <span className="text-[10px] text-muted-foreground ml-2">
              Periode <PeriodDisplay period={month} /> • {status ? `Status: ${status.toUpperCase()}` : "Semua status"}
            </span>
          </div>
          <span className="text-[10px] text-muted-foreground">
            {loading ? "Memuat..." : `${rows.length} record`}
          </span>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full border-collapse min-w-[900px]">
            <thead>
              <tr className="border-b border-border bg-slate-50/50">
                <th className="text-left px-5 py-2.5 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider w-[50px]">No</th>
                <th className="text-left px-4 py-2.5 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Pegawai</th>
                <th className="text-left px-4 py-2.5 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Periode</th>
                <th className="text-left px-4 py-2.5 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Status</th>
                <th className="text-right px-4 py-2.5 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Gaji Pokok</th>
                <th className="text-right px-4 py-2.5 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Tunjangan</th>
                <th className="text-right px-4 py-2.5 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Potongan</th>
                <th className="text-right px-5 py-2.5 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Total</th>
              </tr>
            </thead>
            <tbody>
              {loading && (
                <tr>
                  <td colSpan={8} className="py-8 text-center text-xs text-muted-foreground">
                    Memuat data...
                  </td>
                </tr>
              )}
              {!loading && rows.length === 0 && (
                <tr>
                  <td colSpan={8} className="py-8 text-center">
                    <div className="flex flex-col items-center justify-center gap-2">
                      <FileText size={14} className="text-slate-300" />
                      <p className="text-xs text-muted-foreground">Tidak ada laporan payroll.</p>
                    </div>
                  </td>
                </tr>
              )}
              {!loading && rows.map((r, idx) => (
                <tr
                  key={r.id}
                  className="border-b border-border last:border-0 hover:bg-slate-50/70 transition-colors"
                >
                  <td className="px-5 py-4 text-xs font-medium text-foreground">
                    {idx + 1}
                    <div className="text-[9px] text-muted-foreground font-normal mt-1">
                      <Link to={`/payrolls/${r.id}`} className="text-blue-600 hover:underline">Lihat</Link>
                    </div>
                  </td>
                  <td className="px-4 py-4">
                    <div className="text-xs font-medium text-foreground">{r.employee_name || "—"}</div>
                    <div className="text-[10px] text-muted-foreground mt-0.5">{r.employee_code || "—"}</div>
                  </td>
                  <td className="px-4 py-4 text-xs text-muted-foreground">
                    {r.periode ? new Date(r.periode).toLocaleString("id-ID", { month: "short", year: "numeric" }) : "—"}
                  </td>
                  <td className="px-4 py-4">
                    <StatusBadge status={r.status} variant="text" />
                  </td>
                  <td className="px-4 py-4 text-right text-xs font-medium text-slate-700">
                    {fmtRp(r.gaji_pokok)}
                  </td>
                  <td className="px-4 py-4 text-right">
                    <div className="text-xs font-medium text-slate-700">{fmtRp(r.tunjangan)}</div>
                    {r.allowances?.length > 0 && (
                      <div className="text-[9px] text-muted-foreground mt-1">
                        {r.allowances.map((a, i) => (
                          <div key={i}>{a.allowance_type?.name}: {fmtRp(a.amount)}</div>
                        ))}
                      </div>
                    )}
                  </td>
                  <td className="px-4 py-4 text-right">
                    <div className="text-xs font-medium text-slate-700">{fmtRp(r.potongan)}</div>
                    {r.deductions?.length > 0 && (
                      <div className="text-[9px] text-muted-foreground mt-1">
                        {r.deductions.map((d, i) => (
                          <div key={i}>{d.deduction_label || 'Potongan'}: {fmtRp(d.amount)}</div>
                        ))}
                      </div>
                    )}
                  </td>
                  <td className="px-5 py-4 text-right text-xs font-bold text-foreground">
                    {fmtRp(r.total)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
