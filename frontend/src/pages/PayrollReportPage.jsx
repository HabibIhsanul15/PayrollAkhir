import { useMemo, useState } from "react";
import useSWR from "swr";
import { getUser } from "@/lib/auth";
import { currentPayrollMonth } from "@/lib/utils";
import PeriodDisplay from "@/components/PeriodDisplay";
import StatusBadge from "@/components/StatusBadge";
import { ChevronDown, Download, FileText, X } from "lucide-react";

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
  const user = getUser();
  const role = String(user?.role || "").toLowerCase();

  const [month, setMonth] = useState(() => todayMonth());
  const [status, setStatus] = useState("");
  const [detailRow, setDetailRow] = useState(null);

  const qs = new URLSearchParams();
  qs.set("month", month);
  qs.set("status", status || "all");

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

        <div className="overflow-hidden">
          <table className="w-full min-w-0 table-fixed border-collapse">
            <colgroup>
              <col className="w-[4%]" />
              <col className="w-[14%]" />
              <col className="w-[12%]" />
              <col className="w-[9%]" />
              <col className="w-[9%]" />
              <col className="w-[12%]" />
              <col className="w-[12%]" />
              <col className="w-[12%]" />
              <col className="w-[10%]" />
              <col className="w-[6%]" />
            </colgroup>
            <thead>
              <tr className="border-b border-border bg-slate-50/50">
                <th className="px-2 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">No</th>
                <th className="px-2 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Pegawai</th>
                <th className="px-2 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Jabatan</th>
                <th className="px-2 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Periode</th>
                <th className="px-2 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Status</th>
                <th className="px-2 py-2.5 text-right text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Gaji Pokok</th>
                <th className="px-2 py-2.5 text-right text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Tunjangan</th>
                <th className="px-2 py-2.5 text-right text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Potongan</th>
                <th className="px-2 py-2.5 text-right text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Total</th>
                <th className="px-2 py-2.5 text-center text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Aksi</th>
              </tr>
            </thead>
            <tbody>
              {loading && (
                <tr>
                  <td colSpan={10} className="py-8 text-center text-xs text-muted-foreground">
                    Memuat data...
                  </td>
                </tr>
              )}
              {!loading && rows.length === 0 && (
                <tr>
                  <td colSpan={10} className="py-8 text-center">
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
                  <td className="px-2 py-4 text-xs font-medium text-foreground">
                    {idx + 1}
                  </td>
                  <td className="px-2 py-4">
                    <div className="text-xs font-medium text-foreground">{r.employee_name || "—"}</div>
                    <div className="text-[10px] text-muted-foreground mt-0.5">{r.employee_code || "—"}</div>
                  </td>
                  <td className="break-words px-2 py-4 text-xs text-slate-700">
                    {r.position_name || "Belum ditentukan"}
                  </td>
                  <td className="px-2 py-4 text-xs text-muted-foreground">
                    {r.periode ? new Date(r.periode).toLocaleString("id-ID", { month: "short", year: "numeric" }) : "—"}
                  </td>
                  <td className="px-2 py-4">
                    <StatusBadge status={r.status} variant="text" />
                  </td>
                  <td className="whitespace-nowrap px-2 py-4 text-right text-[11px] font-medium text-slate-700">
                    {fmtRp(r.gaji_pokok)}
                  </td>
                  <td className="whitespace-nowrap px-2 py-4 text-right text-[11px] font-medium text-slate-700">
                    {fmtRp(r.tunjangan)}
                  </td>
                  <td className="whitespace-nowrap px-2 py-4 text-right text-[11px] font-medium text-slate-700">
                    {fmtRp(r.potongan)}
                  </td>
                  <td className="whitespace-nowrap px-2 py-4 text-right text-[11px] font-bold text-foreground">
                    {fmtRp(r.total)}
                  </td>
                  <td className="px-2 py-4 text-center">
                    <button
                      type="button"
                      onClick={() => setDetailRow(r)}
                      className="inline-flex items-center rounded border border-blue-200 bg-blue-50 px-2 py-1 text-[11px] font-medium text-blue-700 hover:bg-blue-100"
                    >
                      Detail
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      <PayrollReportDetailModal
        row={detailRow}
        periodMonth={month}
        onClose={() => setDetailRow(null)}
      />
    </div>
  );
}

function detailFormula(item) {
  const detail = item?.calculation_detail || {};
  const units = detail.units ?? item?.mandays;
  const rate = Number(item?.rate_amount || 0);

  if (!units || !rate) return "";

  const unitLabel = detail.calculation_type === "per_trip" ? "perjalanan" : "hari";
  return `${Number(units).toLocaleString("id-ID")} ${unitLabel} × ${fmtRp(rate)}`;
}

function PayrollReportDetailModal({ row, periodMonth, onClose }) {
  if (!row) return null;

  const totalAllowances = Number(row.tunjangan || 0);
  const totalDeductions = Number(row.potongan || 0);
  const totalIncome = Number(row.gaji_pokok || 0) + totalAllowances;

  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-sm sm:p-6">
      <div className="flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-xl border border-border bg-white shadow-xl">
        <div className="flex shrink-0 items-center justify-between border-b border-slate-100 px-6 py-4">
          <h3 className="text-lg font-bold text-slate-800">Detail Slip Gaji</h3>
          <button type="button" onClick={onClose} className="text-slate-400 hover:text-slate-600" aria-label="Tutup detail">
            <X size={20} />
          </button>
        </div>

        <div className="min-w-0 flex-1 overflow-y-auto p-5 sm:p-7">
          <div className="grid grid-cols-1 gap-4 rounded-lg bg-slate-50 p-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
              <span className="mb-1 block text-slate-500">Karyawan</span>
              <strong className="text-slate-800">{row.employee_name || "-"}</strong>
              <div className="mt-1 text-xs text-slate-500">{row.employee_code || "-"}</div>
            </div>
            <div>
              <span className="mb-1 block text-slate-500">Jabatan</span>
              <strong className="text-slate-800">{row.position_name || "Belum ditentukan"}</strong>
            </div>
            <div>
              <span className="mb-1 block text-slate-500">Periode</span>
              <strong className="text-slate-800"><PeriodDisplay period={periodMonth} /></strong>
              <div className="mt-1 text-xs text-slate-500">
                {row.period_from && row.period_to
                  ? `${new Date(row.period_from).toLocaleDateString("id-ID", { day: "numeric", month: "short", year: "numeric" })} - ${new Date(row.period_to).toLocaleDateString("id-ID", { day: "numeric", month: "short", year: "numeric" })}`
                  : "Periode 28–27"}
              </div>
            </div>
            <div>
              <span className="mb-1 block text-slate-500">Status</span>
              <StatusBadge status={row.status} variant="text" />
            </div>
          </div>

          <section className="mt-6">
            <h4 className="mb-3 border-b pb-2 font-semibold text-slate-800">Rincian Pendapatan</h4>
            <div className="space-y-2 text-sm">
              <div className="flex justify-between gap-4">
                <span className="text-slate-600">Gaji Pokok</span>
                <span className="font-medium text-slate-800">{fmtRp(row.gaji_pokok)}</span>
              </div>

              {(row.allowances || []).map((allowance, index) => (
                <div key={allowance.id || index} className="flex justify-between gap-4">
                  <div className="min-w-0">
                    <div className="break-words text-slate-600">
                      {allowance.allowance_type?.name || allowance.allowance_type || "Tunjangan"}
                    </div>
                    {detailFormula(allowance) && (
                      <div className="mt-0.5 text-xs text-slate-400">{detailFormula(allowance)}</div>
                    )}
                  </div>
                  <span className="shrink-0 font-medium text-slate-800">{fmtRp(allowance.amount)}</span>
                </div>
              ))}

              <div className="flex justify-between gap-4 border-t pt-2 font-semibold text-slate-800">
                <span>Total Pendapatan</span>
                <span>{fmtRp(totalIncome)}</span>
              </div>
            </div>
          </section>

          <section className="mt-6">
            <h4 className="mb-3 border-b pb-2 font-semibold text-slate-800">Rincian Potongan</h4>
            <div className="space-y-2 text-sm">
              {(row.deductions || []).length === 0 && (
                <div className="italic text-slate-400">Belum ada potongan.</div>
              )}
              {(row.deductions || []).map((deduction, index) => (
                <div key={deduction.id || index} className="flex justify-between gap-4 text-red-600">
                  <span className="min-w-0 break-words">{deduction.deduction_label || "Potongan"}</span>
                  <span className="shrink-0 font-medium">-{fmtRp(deduction.amount)}</span>
                </div>
              ))}
              <div className="flex justify-between gap-4 border-t pt-2 font-semibold text-red-700">
                <span>Total Potongan</span>
                <span>-{fmtRp(totalDeductions)}</span>
              </div>
            </div>
          </section>

          <div className="mt-6 flex items-center justify-between rounded-lg bg-blue-50 p-4">
            <span className="font-bold text-blue-900">Total Nett Diterima</span>
            <span className="text-xl font-bold text-blue-700">{fmtRp(row.total)}</span>
          </div>
        </div>

        <div className="flex shrink-0 justify-end border-t border-slate-100 bg-slate-50 px-6 py-4">
          <button type="button" onClick={onClose} className="rounded border bg-white px-5 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
            Tutup
          </button>
        </div>
      </div>
    </div>
  );
}
