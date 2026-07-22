import { useMemo, useState } from "react";
import useSWR from "swr";
import { useNavigate, useParams } from "react-router-dom";
import { getToken, getUser } from "@/lib/auth";
import { Button } from "@/components/ui/button";

import { Badge } from "@/components/ui/badge";
import { api } from "@/lib/api";
import PeriodDisplay from "@/components/PeriodDisplay";
import { Briefcase, UserCircle, Wallet, FileText, Download, ChevronLeft, CreditCard } from "lucide-react";

import OverrideAllowanceModal from "@/components/OverrideAllowanceModal";
import RecalculateConfirmModal from "@/components/RecalculateConfirmModal";
import { useConfirm } from "@/components/ConfirmProvider";

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

function formatDetail(detail, mandays, rate_amount, allowanceName = "") {
  let defaultTypeLabel = " (Bulanan)";
  const nameLower = String(allowanceName || "").toLowerCase();
  if (nameLower.includes("trip") || nameLower.includes("perjalanan dinas")) {
    defaultTypeLabel = " (Per Perjalanan Dinas)";
  } else if (nameLower.includes("harian") || nameLower.includes("makan")) {
    defaultTypeLabel = " (Harian)";
  }

  if (!detail || typeof detail !== "object") return defaultTypeLabel.trim();
  
  const parts = [];
  if (detail.num_trips != null) parts.push(`${formatPlainNumber(detail.num_trips)} Trip`);
  if (detail.project_assignments_mandays != null) parts.push(`${formatPlainNumber(detail.project_assignments_mandays)} Hr Project`);
  if (detail.is_on_probation) parts.push("Probation 50%");
  if (detail.is_prorated) parts.push("Prorata Promosi/Demosi");
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
  
  // Determine Type Label (Harian/Bulanan/Trip) based on inputs
  let typeLabel = defaultTypeLabel;
  if (detail.num_trips != null) {
      typeLabel = " (Per Perjalanan Dinas)";
  } else if (mandays > 0 || detail.total_mandays != null || wfo != null || wfh != null || detail.mandays_outside_city != null || detail.out_of_town_days != null) {
      typeLabel = " (Harian)";
  }

  if (rate_amount > 0 && (detail.num_trips != null || mandays > 0)) {
      const multiplier = mandays > 0 ? mandays : (detail.num_trips || 1);
      desc += (desc ? " • " : "") + new Intl.NumberFormat("id-ID").format(rate_amount) + " x " + formatPlainNumber(multiplier);
      if (detail.multiplier != null) {
          desc += " x " + formatPlainNumber(detail.multiplier);
      }
  }
  return (desc ? `(${desc}) ` : "") + typeLabel.trim();
}

export default function PayrollDetailPage() {
  const { id } = useParams();
  const nav = useNavigate();
  const user = getUser();
  const isFat = user?.role?.toLowerCase() === "fat";
  const isDirector = user?.role?.toLowerCase() === "director";
  const { confirm } = useConfirm();

  const { data: rawRow, error: errRow, isLoading, mutate } = useSWR(`/payrolls/${id}`);

  const row = rawRow?.data ?? rawRow ?? null;
  const loading = isLoading;
  const err = errRow?.message || "";

  const [pdfLoading, setPdfLoading] = useState(false);
  const [proofLoading, setProofLoading] = useState(false);
  const [isSaving, setIsSaving] = useState(false);

  const [overrideData, setOverrideData] = useState(null);
  const [recalcOpen, setRecalcOpen] = useState(false);
  const [recalcMsg, setRecalcMsg] = useState("");

  const [transferModal, setTransferModal] = useState({
    open: false,
    proofFile: null,
    paidRef: "",
    paidNote: "",
    submitting: false,
  });

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

  const periodeLabel = <PeriodDisplay period={periodKey(row?.periode)} />;

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

  const handleAction = async (action) => {
    let confirmMsg = "";
    if (action === "submit") confirmMsg = "Ajukan payroll ini ke Direktur?";
    if (action === "approve") confirmMsg = "Setujui payroll ini?";
    if (action === "reject") confirmMsg = "Tolak payroll ini?";
    
    const ok = await confirm(confirmMsg);
    if (!ok) return;

    let body = {};
    if (action === "reject") {
        const reason = window.prompt("Alasan penolakan:");
        if (reason === null) return; 
        if (reason.trim()) {
            body.approval_note = reason.trim();
        }
    }

    try {
      await api(`/payrolls/${id}/${action}`, { method: "POST", body });
      loadDetail();
    } catch (e) {
      alert(e?.message || `Gagal melakukan aksi ${action}`);
    }
  };

  const openTransferModal = () => {
    const periodKey = String(row?.periode || "").replace("-", "").slice(0, 6) || "000000";
    const payrollIdStr = String(row?.id || 0).padStart(5, "0");
    const defaultRef = `TRF-${periodKey}-${payrollIdStr}`;

    setTransferModal({
      open: true,
      proofFile: null,
      paidRef: defaultRef,
      paidNote: "",
      submitting: false,
    });
  };

  const closeTransferModal = () => {
    if (transferModal.submitting) return;
    setTransferModal({ open: false, proofFile: null, paidRef: "", paidNote: "", submitting: false });
  };

  const submitTransfer = async (e) => {
    e.preventDefault();
    if (!transferModal.proofFile) {
      await confirm("Bukti transfer wajib di-upload terlebih dahulu.", {
        title: "Bukti Transfer Kosong",
        isAlert: true,
        icon: "warning",
      });
      return;
    }

    const formData = new FormData();
    formData.append("proof", transferModal.proofFile);
    
    const periodKey = String(row?.periode || "").replace("-", "").slice(0, 6) || "000000";
    const payrollIdStr = String(row?.id || 0).padStart(5, "0");
    const defaultRef = `TRF-${periodKey}-${payrollIdStr}`;
    
    formData.append("paid_ref", transferModal.paidRef.trim() || defaultRef);
    if (transferModal.paidNote.trim()) formData.append("paid_note", transferModal.paidNote.trim());

    setTransferModal((prev) => ({ ...prev, submitting: true }));
    try {
      await api(`/payrolls/${id}/mark-paid`, {
        method: "POST",
        body: formData,
      });
      closeTransferModal();
      loadDetail();
      await confirm("Transfer gaji berhasil dicatat. Slip gaji sudah tersedia untuk staff terkait.", {
        title: "Transfer Tercatat",
        isAlert: true,
        icon: "success",
      });
    } catch (err) {
      setTransferModal((prev) => ({ ...prev, submitting: false }));
      await confirm(err?.message || "Gagal mencatat transfer gaji.", {
        title: "Error",
        isAlert: true,
        icon: "warning",
      });
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

          {isFat && row?.status === "draft" && (
            <Button
              onClick={() => handleAction("submit")}
              className="bg-blue-600 text-white hover:bg-blue-700 shadow-sm"
            >
              Ajukan ke Direktur
            </Button>
          )}

          {isDirector && row?.status === "submitted" && (
            <>
              <Button
                variant="outline"
                onClick={() => handleAction("reject")}
                className="bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 hover:text-rose-800 shadow-sm"
              >
                Tolak
              </Button>
              <Button
                onClick={() => handleAction("approve")}
                className="bg-emerald-600 text-white hover:bg-emerald-700 shadow-sm"
              >
                Setujui
              </Button>
            </>
          )}

          {isFat && row?.status === "approved" && (
            <Button
              onClick={openTransferModal}
              className="bg-emerald-600 text-white hover:bg-emerald-700 shadow-sm"
            >
              Catat Transfer
            </Button>
          )}

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
        <div className="space-y-4">

          {/* Header Card (Hero Section) */}
          <div className="bg-gradient-to-br from-indigo-900 via-blue-900 to-sky-800 rounded-2xl p-8 shadow-xl text-white relative">
            <div className="absolute top-0 right-0 p-8 opacity-10 pointer-events-none">
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
                <div className="text-xl font-bold">{row?.period_month ? row.period_month : periodeLabel}</div>
                {row?.period_from && row?.period_to && (
                  <div className="text-blue-200/80 text-xs mt-1">
                    {new Date(row.period_from).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })} - {new Date(row.period_to).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })}
                  </div>
                )}
                <div className="text-blue-200/60 text-xs mt-2">ID: #{row?.id}</div>
              </div>
            </div>
          </div>

          {/* Cards Section */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            
            {/* Employee Information Card */}
            <div className="bg-white rounded-2xl shadow-sm border border-slate-200/60 overflow-hidden flex flex-col">
              <div className="px-4 py-3 border-b border-slate-100 bg-slate-50/50 flex items-center gap-2">
                <UserCircle className="text-slate-500" size={16} />
                <h3 className="font-semibold text-slate-800 text-sm">Profil Pegawai</h3>
              </div>
              <div className="p-4 space-y-3 flex-1">
                <div>
                  <p className="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Nama Lengkap</p>
                  <p className="font-bold text-slate-800 text-lg">{row.employee_name || "-"}</p>
                  <p className="text-sm font-medium text-slate-500 mt-0.5">{row.employee_code || "-"}</p>
                </div>
                <div>
                  <p className="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1 flex items-center gap-1.5"><Briefcase size={12} /> Posisi & Departemen</p>
                  <p className="font-medium text-slate-700">{row.employee?.position_name || row.employee?.Position?.name || row.employee?.position || "-"}</p>
                  {row.employee?.department && row.employee?.department !== "-" && (
                    <p className="text-sm text-slate-500 mt-0.5">Departemen: {row.employee.department}</p>
                  )}
                </div>
              </div>
            </div>

            {/* Info Pembayaran & Kehadiran Card */}
            <div className="bg-white rounded-2xl shadow-sm border border-slate-200/60 overflow-hidden flex flex-col">
              <div className="px-4 py-3 border-b border-slate-100 bg-slate-50/50 flex items-center gap-2">
                <CreditCard className="text-slate-500" size={16} />
                <h3 className="font-semibold text-slate-800 text-sm">Informasi Kehadiran & Pembayaran</h3>
              </div>
              <div className="p-4 space-y-3 flex-1">
                <div>
                  <p className="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Total Hari Kerja</p>
                  <p className="font-bold text-slate-800 text-lg">
                    {row.monthly_recaps && row.monthly_recaps.length > 0 
                      ? `${row.monthly_recaps.reduce((acc, curr) => acc + (curr.total_mandays || 0), 0)} Hari` 
                      : row.mandays_summary?.total_mandays ? `${row.mandays_summary.total_mandays} Hari` : "Tidak tersedia"}
                  </p>
                  <p className="text-sm font-medium text-slate-500 mt-0.5">Berdasarkan Monthly Recap</p>
                </div>
                <div>
                  <p className="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1 flex items-center gap-1.5">Informasi Rekening</p>
                  <p className="font-medium text-slate-700">{row.employee?.bank_name || "-"} · {row.employee?.bank_account_number_decrypted || row.employee?.bank_account_number || "-"}</p>
                  <p className="text-sm text-slate-500 mt-0.5">a.n {row.employee?.bank_account_name || "-"}</p>
                </div>
                {isPaid && (
                  <div className="pt-4 border-t border-slate-100 mt-2">
                    <p className="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1 flex items-center gap-1.5">Info Transfer</p>
                    <p className="font-mono font-bold text-slate-800">{row.paid_ref || "-"}</p>
                    <p className="text-sm text-slate-500 mt-0.5">Tanggal transfer: {row.paid_at || "-"}</p>
                  </div>
                )}
              </div>
            </div>
          </div>

              {/* Salary Breakdown (Split Layout) */}
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                
                {/* Kolom Pendapatan */}
                <div className="bg-white rounded-2xl shadow-sm border border-slate-200/60 overflow-hidden flex flex-col">
                  <div className="px-4 py-3 border-b border-emerald-100 bg-emerald-50/30 flex items-center justify-between">
                    <h3 className="font-bold text-emerald-800 text-sm">Pendapatan (Earnings)</h3>
                  </div>
                  <div className="p-4 flex-1 space-y-3">
                    {row.masked ? (
                      <div className="p-4 bg-slate-50 rounded-xl text-center text-slate-400 text-sm border border-dashed border-slate-200">Nominal Disembunyikan</div>
                    ) : (
                      <>
                        {/* Gaji Pokok Blok */}
                        <div className="group flex flex-col p-3 bg-slate-50/80 rounded-xl border border-slate-100 hover:border-emerald-200 transition-colors">
                          <div className="flex justify-between items-start mb-2">
                            <span className="font-semibold text-slate-800">Gaji Pokok</span>
                            <span className="font-bold text-emerald-700">{formatIDR(row.gaji_pokok)}</span>
                          </div>

                          {row.monthly_recaps && row.monthly_recaps.length > 0 ? (
                            <div className="space-y-2 mt-2">
                              {row.monthly_recaps.map((recap, idx) => (
                                <div key={idx} className="bg-white p-2.5 rounded-lg border border-slate-100 text-xs">
                                  <div className="font-medium text-slate-700 mb-1">{recap.position_name} <span className="text-slate-400 font-normal">(Mulai: {recap.effective_from})</span></div>
                                  <div className="flex justify-between text-slate-500">
                                    <span>{formatIDR(recap.base_salary_amount)} / Hari</span>
                                    <span>{recap.base_salary_basis === "monthly" ? `${formatPlainNumber(recap.total_mandays)} hari prorata` : `${formatPlainNumber(recap.total_mandays)} hari`}</span>
                                  </div>
                                </div>
                              ))}
                            </div>
                          ) : (
                            row.active_salary_profile && (
                              <div className="bg-white p-2.5 rounded-lg border border-slate-100 text-xs mt-2">
                                <div className="flex justify-between text-slate-500">
                                  <span>{formatIDR(row.active_salary_profile.base_salary_amount)} / Hari</span>
                                  <span>-</span>
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
                            <div key={`al-${idx}`} className="group flex flex-col p-3 bg-white rounded-xl border border-slate-100 hover:border-emerald-200 transition-colors">
                              <div className="flex justify-between items-start">
                                <div className="flex flex-col">
                                  <div className="flex items-center gap-2">
                                    <span className="font-semibold text-slate-800">{al.allowance_type?.name || al.allowance_type || 'Tunjangan'}</span>
                                    {al.is_manual_override && <Badge className="text-[9px] px-1.5 py-0 h-4 bg-amber-100 text-amber-700 hover:bg-amber-100 border-none">OVERRIDE</Badge>}
                                  </div>
                                  <span className="text-xs text-slate-500 mt-1">
                                    {al.is_manual_override ? 'Penyesuaian manual oleh Finance' : formatDetail(al.calculation_detail, al.mandays, (al.mandays > 0 && !al.calculation_detail?.is_prorated) ? al.amount / al.mandays : 0, al.allowance_type?.name || al.allowance_type)}
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
                                      <span className="text-slate-600 font-medium">{seg.position} {seg.mandays != null ? `(${formatPlainNumber(seg.mandays)} hr)` : ''} {seg.rate != null ? ` x ${formatIDR(seg.rate)}` : ''}</span>
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
                  <div className="bg-emerald-50/50 border-t border-emerald-100 p-4 flex justify-between items-center">
                    <span className="font-bold text-emerald-900">Total Pendapatan</span>
                    <span className="text-lg font-extrabold text-emerald-700">
                      {row.masked ? "Rp •••••" : formatIDR(Number(row.gaji_pokok || 0) + Number(row.tunjangan || 0))}
                    </span>
                  </div>
                </div>

                {/* Kolom Potongan */}
                <div className="bg-white rounded-2xl shadow-sm border border-slate-200/60 overflow-hidden flex flex-col">
                  <div className="px-4 py-3 border-b border-rose-100 bg-rose-50/30 flex items-center justify-between">
                    <h3 className="font-bold text-rose-800 text-sm">Potongan (Deductions)</h3>
                  </div>
                  <div className="p-4 flex-1 space-y-2">
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
                  <div className="bg-rose-50/50 border-t border-rose-100 p-4 flex justify-between items-center">
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

      <OverrideAllowanceModal isOpen={!!overrideData} onClose={() => setOverrideData(null)} data={overrideData} onSave={handleSaveOverride} isSaving={isSaving} />
      <RecalculateConfirmModal isOpen={recalcOpen} onClose={() => setRecalcOpen(false)} message={recalcMsg} onConfirm={(force) => handleRecalculate(force)} isSaving={isSaving} />

      {/* Catat Transfer Modal */}
      {transferModal.open && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-in fade-in duration-200">
          <div className="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden animate-in zoom-in-95 duration-200">
            <div className="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
              <h2 className="text-lg font-bold text-slate-800 flex items-center gap-2">
                <CreditCard className="text-emerald-600" size={20} />
                Catat Transfer Gaji
              </h2>
            </div>
            
            <form onSubmit={submitTransfer} className="p-6 space-y-5">
              <div className="space-y-3 bg-slate-50 p-4 rounded-xl border border-slate-100">
                <div className="text-sm">
                  <span className="text-slate-500">Penerima:</span>
                  <div className="font-semibold text-slate-900">{row?.employee_name}</div>
                </div>
                <div className="text-sm">
                  <span className="text-slate-500">Rekening:</span>
                  <div className="font-mono text-slate-700 font-medium bg-white px-2 py-1 rounded border mt-1">
                    Bank {row?.bank_name || "-"} - {row?.bank_account_number || "-"}
                  </div>
                </div>
                <div className="text-sm pt-2 border-t border-slate-200 border-dashed flex justify-between items-center">
                  <span className="text-slate-500 font-medium">Total Transfer:</span>
                  <span className="font-bold text-blue-700">{formatIDR(computedTotal)}</span>
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Bukti Transfer (PDF/Img) <span className="text-rose-500">*</span></label>
                <input
                  type="file"
                  required
                  accept=".pdf,image/*"
                  className="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 border border-slate-200 rounded-xl p-1 cursor-pointer"
                  disabled={transferModal.submitting}
                  onChange={(e) => setTransferModal((prev) => ({ ...prev, proofFile: e.target.files?.[0] || null }))}
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Nomor Referensi (Opsional)</label>
                <input
                  type="text"
                  placeholder={transferModal.paidRef}
                  className="w-full border-slate-200 rounded-lg text-sm px-3 py-2 focus:ring-emerald-500 focus:border-emerald-500"
                  value={transferModal.paidRef}
                  disabled={transferModal.submitting}
                  onChange={(e) => setTransferModal((prev) => ({ ...prev, paidRef: e.target.value }))}
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Catatan Tambahan (Opsional)</label>
                <textarea
                  className="w-full border-slate-200 rounded-lg text-sm px-3 py-2 focus:ring-emerald-500 focus:border-emerald-500 min-h-[80px]"
                  placeholder="Contoh: Ditransfer batch sore..."
                  value={transferModal.paidNote}
                  disabled={transferModal.submitting}
                  onChange={(e) => setTransferModal((prev) => ({ ...prev, paidNote: e.target.value }))}
                />
              </div>

              <div className="pt-2 flex gap-3">
                <Button
                  type="button"
                  variant="outline"
                  className="flex-1 border-slate-200 hover:bg-slate-50 text-slate-600"
                  onClick={closeTransferModal}
                  disabled={transferModal.submitting}
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  className="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white"
                  disabled={transferModal.submitting}
                >
                  {transferModal.submitting ? "Menyimpan..." : "Simpan Transfer"}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
