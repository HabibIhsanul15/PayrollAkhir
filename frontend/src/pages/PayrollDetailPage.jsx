import { useMemo, useState } from "react";
import useSWR from "swr";
import { useNavigate, useParams } from "react-router-dom";
import { getToken, getUser } from "@/lib/auth";
import { Button } from "@/components/ui/button";
import SecurityInspectionTab from "../components/SecurityInspectionTab";
import { Badge } from "@/components/ui/badge";
import { api } from "@/lib/api";
import { Building2, Briefcase, UserCircle, Wallet, FileText, Download, ChevronLeft, CreditCard } from "lucide-react";

import OverrideAllowanceModal from "@/components/OverrideAllowanceModal";
import RecalculateConfirmModal from "@/components/RecalculateConfirmModal";

function formatIDR(n) {
  const num = Number(n ?? 0);
  const safe = Number.isFinite(num) ? num : 0;
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(safe);
}

function formatPlainNumber(value) {
  if (value === null || value === undefined || value === "") return "-";
  const number = Number(value);
  if (!Number.isFinite(number)) return "-";
  return new Intl.NumberFormat("id-ID", {
    maximumFractionDigits: 2,
  }).format(number);
}

function periodKey(value) {
  const s = String(value || "").trim();
  if (!s) return "";
  if (/^\d{4}-\d{2}$/.test(s)) return s; // YYYY-MM
  if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s.slice(0, 7); // YYYY-MM-DD -> YYYY-MM
  return s.length >= 7 ? s.slice(0, 7) : s;
}

function monthLabel(yyyyMM) {
  if (!/^\d{4}-\d{2}$/.test(yyyyMM)) return yyyyMM || "-";
  const [y, m] = yyyyMM.split("-");
  const map = {
    "01": "Jan", "02": "Feb", "03": "Mar", "04": "Apr", "05": "Mei", "06": "Jun",
    "07": "Jul", "08": "Agu", "09": "Sep", "10": "Okt", "11": "Nov", "12": "Des",
  };
  return `${map[m] || m} ${y}`;
}

function formatDetail(detail, mandays, rate_amount) {
  if (!detail || typeof detail !== "object") return "";
  const parts = [];
  if (detail.num_trips != null) parts.push(`${formatPlainNumber(detail.num_trips)} Trip`);
  if (detail.project_assignments_mandays != null) parts.push(`${formatPlainNumber(detail.project_assignments_mandays)} Hr Project`);
  if (detail.is_on_probation) parts.push("Probation 50%");
  if (detail.is_prorated) parts.push("Prorata Mutasi");
  if (detail.num_toddlers != null) parts.push(`${formatPlainNumber(detail.num_toddlers)} Anak`);
  if (detail.mandays_outside_city != null) parts.push(`${formatPlainNumber(detail.mandays_outside_city)} Hr Dinas`);
  if (detail.out_of_town_days != null) parts.push(`${formatPlainNumber(detail.out_of_town_days)} Hr Dinas`);
  if (detail.total_mandays != null) parts.push(`${formatPlainNumber(detail.total_mandays)} Hari`);

  const wfo = detail.mandays_ho_wfo ?? detail.wfo_days;
  const wfh = detail.mandays_ho_wfh ?? detail.wfh_days;
  if (wfo != null || wfh != null) {
      const h = [];
      if (wfo) h.push(`${formatPlainNumber(wfo)} WFO`);
      if (wfh) h.push(`${formatPlainNumber(wfh)} WFH`);
      if (h.length > 0) parts.push(h.join(", "));
  }
  if (detail.mandays_project != null && detail.project_assignments_mandays == null) {
      parts.push(`${formatPlainNumber(detail.mandays_project)} Hr Project`);
  }

  let desc = parts.join(" | ");
  if (rate_amount > 0 && (detail.num_trips != null || mandays > 0)) {
      const multiplier = mandays > 0 ? mandays : (detail.num_trips || 1);
      desc += (desc ? " • " : "") + new Intl.NumberFormat("id-ID").format(rate_amount) + " x " + formatPlainNumber(multiplier);
      if (detail.multiplier != null) {
          desc += " x " + formatPlainNumber(detail.multiplier);
      }
  }
  return desc ? `(${desc})` : "";
}

export default function PayrollDetailPage() {
  const { id } = useParams();
  const nav = useNavigate();
  const user = getUser();
  const isFat = user?.role?.toLowerCase() === "fat";

  const { data: rawRow, error: errRow, isLoading, mutate } = useSWR(`/payrolls/${id}`);

  const row = rawRow?.data ?? rawRow ?? null;
  const loading = isLoading;
  const err = errRow?.message || "";

  const [activeTab, setActiveTab] = useState("detail");
  const [pdfLoading, setPdfLoading] = useState(false);
  const [proofLoading, setProofLoading] = useState(false);
  const [isSaving, setIsSaving] = useState(false);

  const [overrideData, setOverrideData] = useState(null);
  const [recalcOpen, setRecalcOpen] = useState(false);
  const [recalcMsg, setRecalcMsg] = useState("");

  const API_BASE = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000";

  const openPayrollPdf = async (payrollId) => {
    try {
      setPdfLoading(true);
      const token = getToken();
      if (!token) throw new Error("Token login tidak ditemukan. Silakan login ulang.");

      const newTab = window.open("", "_blank", "noopener,noreferrer");
      const res = await fetch(`${API_BASE}/api/payrolls/${payrollId}/pdf`, {
        headers: { Authorization: `Bearer ${token}`, Accept: "application/pdf" },
      });

        if (!res.ok) {
          if (newTab) newTab.close();
          let msg = `Gagal membuka PDF (HTTP ${res.status}).`;
          try {
            const j = await res.json();
            if (j?.message) msg = j.message;
          } catch {
            // Ignore non-JSON error payloads.
          }
          throw new Error(msg);
        }

      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      if (newTab) newTab.location.href = url; else window.location.href = url;
      setTimeout(() => URL.revokeObjectURL(url), 60_000);
    } catch (e) {
      alert(e?.message || "Gagal membuka PDF.");
    } finally {
      setPdfLoading(false);
    }
  };

  const openProof = async (payrollId) => {
    try {
      setProofLoading(true);
      const token = getToken();
      if (!token) throw new Error("Token login tidak ditemukan. Silakan login ulang.");
      const newTab = window.open("", "_blank", "noopener,noreferrer");
      const res = await fetch(`${API_BASE}/api/payrolls/${payrollId}/proof`, {
        headers: { Authorization: `Bearer ${token}`, Accept: "*/*" },
      });

      if (!res.ok) {
        if (newTab) newTab.close();
        let msg = `HTTP ${res.status}`;
        try {
          const j = await res.json();
          if (j?.message) msg = j.message;
        } catch {
          // Ignore non-JSON error payloads.
        }
        throw new Error(msg);
      }

      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      if (newTab) newTab.location.href = url; else window.location.href = url;
      setTimeout(() => URL.revokeObjectURL(url), 60_000);
    } catch (e) {
      alert(e?.message || "Bukti transfer belum tersedia / kamu tidak punya akses.");
    } finally {
      setProofLoading(false);
    }
  };

  const loadDetail = () => mutate();

  const periodeLabel = useMemo(() => monthLabel(periodKey(row?.periode)), [row?.periode]);

  const computedTotal = useMemo(() => {
    if (!row) return 0;
    if (row.total !== null && row.total !== undefined) return row.total;
    const gp = Number(row.gaji_pokok ?? 0);
    const tj = Number(row.tunjangan ?? 0);
    const pt = Number(row.potongan ?? 0);
    return gp + tj - pt;
  }, [row]);

  const isPaid = useMemo(() => String(row?.status || "").toLowerCase() === "paid", [row?.status]);
  const canOverrideOrRecalculate = useMemo(() => isFat && row?.status === "draft" && row?.calculation_mode === "auto", [isFat, row]);

  const handleSaveOverride = async (payload) => {
    if (!overrideData) return;
    setIsSaving(true);
    try {
      await api(`/payrolls/${id}/allowances/${overrideData.id}`, { method: "PATCH", body: payload });
      setOverrideData(null);
      loadDetail();
    } catch (e) {
      alert(e?.data?.message || "Gagal override allowance");
    } finally {
      setIsSaving(false);
    }
  };

  const handleRecalculate = async (force = false) => {
    setIsSaving(true);
    try {
      await api(`/payrolls/${id}/recalculate`, { method: "POST", body: { force } });
      setRecalcOpen(false);
      loadDetail();
    } catch (e) {
      const status = e?.status;
      const msg = e?.data?.message || "Gagal recalculate";
      if (status === 422 && msg.toLowerCase().includes("manual override")) {
        setRecalcMsg(msg);
        setRecalcOpen(true);
      } else {
        alert(msg);
      }
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <div className="pb-20 animate-in fade-in duration-500">
      {/* Header Actions */}
      <div className="flex items-center justify-between mb-6">
        <button
          onClick={() => nav(-1)}
          className="flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-800 transition-colors"
        >
          <ChevronLeft size={16} /> Kembali ke Daftar
        </button>

        <div className="flex flex-wrap items-center gap-2">
          {canOverrideOrRecalculate && (
            <Button
              variant="outline"
              onClick={() => handleRecalculate(false)}
              disabled={isSaving}
              className="bg-indigo-50 border-indigo-200 text-indigo-700 hover:bg-indigo-100 hover:text-indigo-800"
            >
              {isSaving ? "Memproses..." : "Recalculate Data"}
            </Button>
          )}

          <Button
            onClick={() => row?.id && openPayrollPdf(row.id)}
            disabled={!row || row.masked || pdfLoading}
            className="bg-white text-slate-700 border border-slate-300 hover:bg-slate-50 shadow-sm"
          >
            <Download size={14} className="mr-2" />
            {pdfLoading ? "Menyiapkan PDF..." : "Unduh PDF"}
          </Button>

          {row?.id && isPaid && (
            <Button
              onClick={() => openProof(row.id)}
              disabled={proofLoading}
              className="bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 shadow-sm"
            >
              <FileText size={14} className="mr-2" />
              {proofLoading ? "Membuka..." : "Bukti Transfer"}
            </Button>
          )}
        </div>
      </div>

      {loading && (
        <div className="h-64 flex items-center justify-center border border-dashed border-slate-300 rounded-2xl bg-white">
          <div className="text-slate-400 font-medium">Memuat rincian gaji...</div>
        </div>
      )}

      {!loading && err && (
        <div className="bg-rose-50 border border-rose-200 text-rose-700 rounded-xl p-6 text-center">
          <p className="font-bold mb-2">Gagal memuat slip gaji</p>
          <p className="text-sm">{err}</p>
        </div>
      )}

      {!loading && !err && row && (
        <div className="space-y-6">

          {/* Hero Section */}
          <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-900 via-blue-900 to-sky-800 p-8 shadow-xl text-white">
            <div className="absolute top-0 right-0 p-8 opacity-10">
              <Wallet size={120} />
            </div>
            <div className="relative z-10 flex flex-col md:flex-row md:justify-between md:items-end gap-6">
              <div>
                <div className="flex items-center gap-3 mb-4">
                  <h1 className="text-2xl font-bold tracking-tight">Slip Gaji</h1>
                  {row.masked ? (
                    <Badge className="bg-white/10 text-white hover:bg-white/20 border-white/20 backdrop-blur-md">MASKED</Badge>
                  ) : (
                    <Badge className="bg-emerald-500/20 text-emerald-200 border-emerald-500/30 backdrop-blur-md">VISIBLE</Badge>
                  )}
                  {isPaid && (
                    <Badge className="bg-emerald-500 text-white border-none shadow-[0_0_15px_rgba(16,185,129,0.5)]">PAID</Badge>
                  )}
                  {row.calculation_mode === "auto" && (
                    <Badge className="bg-sky-500/20 text-sky-200 border-sky-500/30 backdrop-blur-md">AUTO-CALC</Badge>
                  )}
                </div>
                <div className="text-blue-200 text-sm mb-1 uppercase tracking-wider font-semibold">Take Home Pay</div>
                <div className="text-4xl md:text-5xl font-extrabold tracking-tight">
                  {row.masked ? "Rp •••••••••" : formatIDR(computedTotal)}
                </div>
              </div>
              <div className="text-right">
                <div className="text-blue-200 text-sm mb-1">Periode</div>
                <div className="text-xl font-bold">{periodeLabel}</div>
                <div className="text-blue-200/60 text-xs mt-2">ID: #{row.id}</div>
              </div>
            </div>
          </div>

          {/* Navigation Tabs */}
          <div className="flex items-center gap-6 border-b border-slate-200 px-2">
            <button
              className={`pb-3 text-sm font-semibold transition-all border-b-2 ${activeTab === 'detail' ? 'border-blue-600 text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-800'}`}
              onClick={() => setActiveTab('detail')}
            >
              Detail Rincian Gaji
            </button>
            {(user?.role?.toLowerCase() === 'director' || user?.role?.toLowerCase() === 'fat') && (
              <button
                className={`pb-3 text-sm font-semibold transition-all border-b-2 ${activeTab === 'security' ? 'border-blue-600 text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-800'}`}
                onClick={() => setActiveTab('security')}
              >
                Security Inspection
              </button>
            )}
          </div>

          {activeTab === 'security' && <SecurityInspectionTab payrollId={row.id} />}

          {activeTab === 'detail' && (
            <div className="space-y-6">

              {/* Employee Information Card */}
              <div className="bg-white rounded-2xl shadow-sm border border-slate-200/60 overflow-hidden">
                <div className="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center gap-2">
                  <UserCircle className="text-slate-400" size={18} />
                  <h3 className="font-semibold text-slate-800 text-sm">Informasi Pegawai</h3>
                </div>
                <div className="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                  <div className="space-y-1">
                    <p className="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Nama Lengkap</p>
                    <p className="font-medium text-slate-800">{row.employee_name || "-"}</p>
                    <p className="text-xs text-slate-500">{row.employee_code || "-"}</p>
                  </div>
                  <div className="space-y-1">
                    <p className="text-[11px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5"><Briefcase size={12} /> Posisi & Grade</p>
                    <p className="font-medium text-slate-800">{row.employee?.position || "-"}</p>
                    <p className="text-xs text-slate-500">{row.employee?.grade_name || "-"} · {row.employee?.department || "-"}</p>
                  </div>
                  <div className="space-y-1">
                    <p className="text-[11px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5"><Building2 size={12} /> Basis Gaji Pokok</p>
                    <p className="font-medium text-slate-800">{row.employee?.base_salary_basis_label || "-"}</p>
                    <p className="text-xs text-slate-500">Tanggal masuk: {row.employee?.join_date || "-"}</p>
                  </div>
                  <div className="space-y-1 md:col-span-3 pt-4 border-t border-slate-100">
                    <p className="text-[11px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5"><CreditCard size={12} /> Informasi Rekening</p>
                    <p className="font-medium text-slate-800">{row.employee?.bank_name || "-"} · {row.employee?.bank_account_number_decrypted || "-"}</p>
                    <p className="text-xs text-slate-500">a.n {row.employee?.bank_account_name || "-"}</p>
                  </div>
                  {isPaid && (
                    <div className="space-y-1 md:col-span-3 pt-4 border-t border-slate-100">
                      <p className="text-[11px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5"><CreditCard size={12} /> Informasi Transfer</p>
                      <p className="font-mono font-semibold text-slate-800">{row.paid_ref || "-"}</p>
                      <p className="text-xs text-slate-500">Tanggal transfer: {row.paid_at || "-"}</p>
                    </div>
                  )}
                </div>
              </div>

              {/* Salary Breakdown (Split Layout) */}
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {/* Kolom Pendapatan */}
                <div className="bg-white rounded-2xl shadow-sm border border-slate-200/60 overflow-hidden flex flex-col">
                  <div className="px-6 py-4 border-b border-emerald-100 bg-emerald-50/30 flex items-center justify-between">
                    <h3 className="font-bold text-emerald-800 text-sm">Pendapatan (Earnings)</h3>
                  </div>
                  <div className="p-6 flex-1 space-y-4">
                    {row.masked ? (
                      <div className="p-4 bg-slate-50 rounded-xl text-center text-slate-400 text-sm border border-dashed border-slate-200">Nominal Disembunyikan</div>
                    ) : (
                      <>
                        {/* Gaji Pokok Blok */}
                        <div className="group flex flex-col p-4 bg-slate-50/80 rounded-xl border border-slate-100 hover:border-emerald-200 transition-colors">
                          <div className="flex justify-between items-start mb-2">
                            <span className="font-semibold text-slate-800">Gaji Pokok</span>
                            <span className="font-bold text-emerald-700">{formatIDR(row.gaji_pokok)}</span>
                          </div>

                          {row.monthly_recaps && row.monthly_recaps.length > 0 ? (
                            <div className="space-y-2 mt-2">
                              {row.monthly_recaps.map((recap, idx) => (
                                <div key={idx} className="bg-white p-2.5 rounded-lg border border-slate-100 text-xs">
                                  <div className="font-medium text-slate-700 mb-1">{recap.grade_name} <span className="text-slate-400 font-normal">(Mulai: {recap.effective_from})</span></div>
                                  <div className="flex justify-between text-slate-500">
                                    <span>{recap.base_salary_basis_label}: {formatIDR(recap.base_salary_amount)}</span>
                                    <span>{recap.base_salary_basis === "monthly" ? `${formatPlainNumber(recap.total_mandays)} hari prorata` : `${formatPlainNumber(recap.total_mandays)} hari`}</span>
                                  </div>
                                </div>
                              ))}
                            </div>
                          ) : (
                            row.active_salary_profile && (
                              <div className="bg-white p-2.5 rounded-lg border border-slate-100 text-xs mt-2">
                                <div className="flex justify-between text-slate-500">
                                  <span>{row.active_salary_profile.base_salary_basis === "monthly" ? "Gaji Bulanan" : "Gaji Harian"}: {formatIDR(row.active_salary_profile.base_salary_amount)}</span>
                                  <span>Basis: {row.active_salary_profile.base_salary_basis === "monthly" ? "Bulanan" : "Harian"}</span>
                                </div>
                              </div>
                            )
                          )}

                          {row.mandays_summary && (
                            <div className="mt-3 pt-3 border-t border-slate-200 border-dashed text-[11px] text-slate-500 grid grid-cols-2 gap-y-1">
                              <div>WFO: <strong className="text-slate-700">{formatPlainNumber(row.mandays_summary.mandays_ho_wfo)} hari</strong></div>
                              {row.mandays_summary.mandays_outside_city > 0 && <div>Luar Kota: <strong className="text-slate-700">{formatPlainNumber(row.mandays_summary.mandays_outside_city)} hari</strong></div>}
                              {row.mandays_summary.mandays_project > 0 && <div>Proyek: <strong className="text-slate-700">{formatPlainNumber(row.mandays_summary.mandays_project)} hari</strong></div>}
                              {row.mandays_summary.mandays_training > 0 && <div>Training: <strong className="text-slate-700">{formatPlainNumber(row.mandays_summary.mandays_training)} hari</strong></div>}
                              <div>WFH: <strong className="text-slate-700">{formatPlainNumber(row.mandays_summary.mandays_ho_wfh)} hari</strong> <span className="text-rose-500 text-[9px] block">Tidak dikali rate</span></div>
                              <div className="col-span-2 pt-1 mt-1 font-medium text-emerald-700">Total Pengali: {formatPlainNumber(row.mandays_summary.total_mandays)} hari</div>
                            </div>
                          )}
                        </div>

                        {/* Tunjangan Blok */}
                        {row.allowances?.length > 0 ? (
                          row.allowances.map((al, idx) => (
                            <div key={`al-${idx}`} className="group flex flex-col p-4 bg-white rounded-xl border border-slate-100 hover:border-emerald-200 transition-colors">
                              <div className="flex justify-between items-start">
                                <div className="flex flex-col">
                                  <div className="flex items-center gap-2">
                                    <span className="font-semibold text-slate-800">{al.allowance_type?.name || al.allowance_type || 'Tunjangan'}</span>
                                    {al.is_manual_override && <Badge className="text-[9px] px-1.5 py-0 h-4 bg-amber-100 text-amber-700 hover:bg-amber-100 border-none">OVERRIDE</Badge>}
                                  </div>
                                  <span className="text-xs text-slate-500 mt-1">
                                    {al.is_manual_override ? `Alasan: ${al.condition_notes || al.override_reason || '-'}` : formatDetail(al.calculation_detail, al.mandays, al.rate_amount)}
                                  </span>
                                </div>
                                <div className="flex flex-col items-end gap-1">
                                  <span className="font-bold text-emerald-700">{formatIDR(al.amount)}</span>
                                  {canOverrideOrRecalculate && (
                                    <button onClick={() => setOverrideData(al)} className="text-[10px] text-indigo-500 hover:text-indigo-700 opacity-0 group-hover:opacity-100 transition-opacity font-medium">✏️ Edit</button>
                                  )}
                                </div>
                              </div>
                              {al.calculation_detail?.segments && al.calculation_detail.segments.length > 0 && (
                                <div className="mt-3 space-y-1.5">
                                  {al.calculation_detail.segments.map((seg, sIdx) => (
                                    <div key={sIdx} className="flex justify-between items-center bg-slate-50 p-2 rounded-lg text-[11px] border border-slate-100">
                                      <span className="text-slate-600 font-medium">{seg.grade} {seg.mandays != null ? `(${formatPlainNumber(seg.mandays)} hr)` : ''} {seg.rate != null ? ` x ${formatIDR(seg.rate)}` : ''}</span>
                                      <span className="text-slate-700 font-semibold">{formatIDR(seg.amount)}</span>
                                    </div>
                                  ))}
                                </div>
                              )}
                            </div>
                          ))
                        ) : (
                          !row.allowances && (
                            <div className="flex justify-between p-4 bg-white rounded-xl border border-slate-100">
                              <span className="font-semibold text-slate-800">Total Tunjangan</span>
                              <span className="font-bold text-emerald-700">{formatIDR(row.tunjangan)}</span>
                            </div>
                          )
                        )}
                      </>
                    )}
                  </div>
                  <div className="bg-emerald-50/50 border-t border-emerald-100 p-6 flex justify-between items-center">
                    <span className="font-bold text-emerald-900">Total Pendapatan</span>
                    <span className="text-lg font-extrabold text-emerald-700">
                      {row.masked ? "Rp •••••" : formatIDR(Number(row.gaji_pokok || 0) + Number(row.tunjangan || 0))}
                    </span>
                  </div>
                </div>

                {/* Kolom Potongan */}
                <div className="bg-white rounded-2xl shadow-sm border border-slate-200/60 overflow-hidden flex flex-col">
                  <div className="px-6 py-4 border-b border-rose-100 bg-rose-50/30 flex items-center justify-between">
                    <h3 className="font-bold text-rose-800 text-sm">Potongan (Deductions)</h3>
                  </div>
                  <div className="p-6 flex-1 space-y-3">
                    {row.masked ? (
                      <div className="p-4 bg-slate-50 rounded-xl text-center text-slate-400 text-sm border border-dashed border-slate-200">Nominal Disembunyikan</div>
                    ) : (
                      <>
                        {row.deductions?.length > 0 ? (
                          row.deductions.map((dd, idx) => (
                            <div key={`dd-${idx}`} className="flex justify-between items-center p-4 bg-white rounded-xl border border-slate-100 hover:border-rose-100 transition-colors">
                              <span className="font-semibold text-slate-700">{dd.deduction_label || 'Potongan'}</span>
                              <span className="font-bold text-rose-600">- {formatIDR(dd.amount)}</span>
                            </div>
                          ))
                        ) : (
                          row.potongan > 0 ? (
                            <div className="flex justify-between items-center p-4 bg-white rounded-xl border border-slate-100">
                              <span className="font-semibold text-slate-700">Total Potongan</span>
                              <span className="font-bold text-rose-600">- {formatIDR(row.potongan)}</span>
                            </div>
                          ) : (
                            <div className="flex flex-col items-center justify-center h-full text-slate-400 space-y-2 opacity-70 min-h-[200px]">
                              <div className="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center border border-slate-100"><CreditCard size={20} /></div>
                              <span className="text-sm">Tidak ada potongan bulan ini.</span>
                            </div>
                          )
                        )}
                      </>
                    )}
                  </div>
                  <div className="bg-rose-50/50 border-t border-rose-100 p-6 flex justify-between items-center">
                    <span className="font-bold text-rose-900">Total Potongan</span>
                    <span className="text-lg font-extrabold text-rose-700">
                      {row.masked ? "Rp •••••" : `- ${formatIDR(row.potongan)}`}
                    </span>
                  </div>
                </div>

              </div>

              {/* Catatan Section */}
              {!!row.catatan && (
                <div className="bg-amber-50/50 rounded-2xl border border-amber-200/60 p-6">
                  <h4 className="text-xs font-bold text-amber-800 uppercase tracking-wider mb-2">Catatan Khusus</h4>
                  <p className="text-sm text-amber-900/80 leading-relaxed">{row.catatan}</p>
                </div>
              )}
            </div>
          )}
        </div>
      )}

      <OverrideAllowanceModal isOpen={!!overrideData} onClose={() => setOverrideData(null)} data={overrideData} onSave={handleSaveOverride} isSaving={isSaving} />
      <RecalculateConfirmModal isOpen={recalcOpen} onClose={() => setRecalcOpen(false)} message={recalcMsg} onConfirm={(force) => handleRecalculate(force)} isSaving={isSaving} />
    </div>
  );
}
