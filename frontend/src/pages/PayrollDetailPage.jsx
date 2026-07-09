import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { fetchPayrollDetail } from "@/lib/payrollsApi";
import { getToken, getUser } from "@/lib/auth";
import { Button } from "@/components/ui/button";
import RoleRoute from "../components/RoleRoute";
import SecurityInspectionTab from "../components/SecurityInspectionTab";
import { Badge } from "@/components/ui/badge";
import { api } from "@/lib/api";

import OverrideAllowanceModal from "@/components/OverrideAllowanceModal";
import RecalculateConfirmModal from "@/components/RecalculateConfirmModal";

function formatIDR(n) {
  const num = Number(n ?? 0);
  const safe = Number.isFinite(num) ? num : 0;
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
  }).format(safe);
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
    "01": "Jan",
    "02": "Feb",
    "03": "Mar",
    "04": "Apr",
    "05": "Mei",
    "06": "Jun",
    "07": "Jul",
    "08": "Agu",
    "09": "Sep",
    "10": "Okt",
    "11": "Nov",
    "12": "Des",
  };
  return `${map[m] || m} ${y}`;
}

function formatDetail(detail, mandays, rate_amount) {
  if (!detail || typeof detail !== "object") return "";
  const parts = [];
  if (detail.num_trips != null) parts.push(`${detail.num_trips} Trip`);
  if (detail.project_assignments_mandays != null) parts.push(`${detail.project_assignments_mandays} Hr Project`);
  if (detail.is_on_probation) parts.push("Probation 50%");
  if (detail.is_prorated) parts.push("Prorata Mutasi");
  if (detail.num_toddlers != null) parts.push(`${detail.num_toddlers} Anak`);
  if (detail.mandays_outside_city != null) parts.push(`${detail.mandays_outside_city} Hr Dinas`);
  if (detail.out_of_town_days != null) parts.push(`${detail.out_of_town_days} Hr Dinas`);
  if (detail.total_mandays != null) parts.push(`${detail.total_mandays} Hari`);
  
  const wfo = detail.mandays_ho_wfo ?? detail.wfo_days;
  const wfh = detail.mandays_ho_wfh ?? detail.wfh_days;
  if (wfo != null || wfh != null) {
      const h = [];
      if (wfo) h.push(`${wfo} WFO`);
      if (wfh) h.push(`${wfh} WFH`);
      if (h.length > 0) parts.push(h.join(", "));
  }
  if (detail.mandays_project != null && detail.project_assignments_mandays == null) {
      parts.push(`${detail.mandays_project} Hr Project`);
  }
  
  let desc = parts.join(" | ");
  if (rate_amount > 0 && (detail.num_trips != null || mandays > 0)) {
      const multiplier = mandays > 0 ? mandays : (detail.num_trips || 1);
      desc += (desc ? " • " : "") + new Intl.NumberFormat("id-ID").format(rate_amount) + " x " + multiplier;
      if (detail.multiplier != null) {
          desc += " x " + detail.multiplier;
      }
  }
  return desc ? `(${desc})` : "";
}

// akses nominal
function AccessBadge({ masked }) {
  if (masked) {
    return (
      <Badge className="rounded-full border border-slate-200 bg-slate-50 text-slate-700">
        MASKED
      </Badge>
    );
  }
  return (
    <Badge className="rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700">
      OK
    </Badge>
  );
}

function InfoItem({ label, value }) {
  return (
    <div className="bg-white border border-border rounded shadow-sm px-4 py-3">
      <div className="text-[11px] text-slate-500">{label}</div>
      <div className="mt-0.5 text-sm font-medium text-foreground">
        {value ?? "-"}
      </div>
    </div>
  );
}

export default function PayrollDetailPage() {
  const { id } = useParams();
  const nav = useNavigate();
  const user = getUser();
  const isFat = user?.role?.toLowerCase() === "fat";

  const [row, setRow] = useState(null);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");
  const [activeTab, setActiveTab] = useState("detail");

  const [pdfLoading, setPdfLoading] = useState(false);
  const [proofLoading, setProofLoading] = useState(false);
  const [isSaving, setIsSaving] = useState(false);

  // Modals state
  const [overrideData, setOverrideData] = useState(null);
  const [recalcOpen, setRecalcOpen] = useState(false);
  const [recalcMsg, setRecalcMsg] = useState("");

  const API_BASE = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000";

  const openPayrollPdf = async (payrollId) => {
    try {
      setPdfLoading(true);

      const token = getToken();
      if (!token) throw new Error("Token login tidak ditemukan. Silakan login ulang.");

      // buka tab dulu supaya popup tidak diblok
      const newTab = window.open("", "_blank", "noopener,noreferrer");

      const res = await fetch(`${API_BASE}/api/payrolls/${payrollId}/pdf`, {
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: "application/pdf",
        },
      });

      if (!res.ok) {
        if (newTab) newTab.close();

        // coba baca message json kalau ada
        let msg = `Gagal membuka PDF (HTTP ${res.status}).`;
        try {
          const j = await res.json();
          if (j?.message) msg = j.message;
        } catch {}

        throw new Error(msg);
      }

      const blob = await res.blob();
      const url = URL.createObjectURL(blob);

      if (newTab) newTab.location.href = url;
      else window.location.href = url;

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
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: "*/*",
        },
      });

      if (!res.ok) {
        if (newTab) newTab.close();

        let msg = `HTTP ${res.status}`;
        try {
          const j = await res.json();
          if (j?.message) msg = j.message;
        } catch {}

        throw new Error(msg);
      }

      const blob = await res.blob();
      const url = URL.createObjectURL(blob);

      if (newTab) newTab.location.href = url;
      else window.location.href = url;

      setTimeout(() => URL.revokeObjectURL(url), 60_000);
    } catch (e) {
      alert(e?.message || "Bukti transfer belum tersedia / kamu tidak punya akses.");
    } finally {
      setProofLoading(false);
    }
  };

  const loadDetail = () => {
    setLoading(true);
    setErr("");
    fetchPayrollDetail(id)
      .then((data) => setRow(data?.data ?? data))
      .catch((e) => setErr(e?.message || "Gagal memuat detail."))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadDetail();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  const periodeLabel = useMemo(
    () => monthLabel(periodKey(row?.periode)),
    [row?.periode]
  );

  const computedTotal = useMemo(() => {
    if (!row) return 0;
    if (row.total !== null && row.total !== undefined) return row.total;
    const gp = Number(row.gaji_pokok ?? 0);
    const tj = Number(row.tunjangan ?? 0);
    const pt = Number(row.potongan ?? 0);
    return gp + tj - pt;
  }, [row]);

  const isPaid = useMemo(
    () => String(row?.status || "").toLowerCase() === "paid",
    [row?.status]
  );
  
  const canOverrideOrRecalculate = useMemo(() => {
    return isFat && row?.status === "draft" && row?.calculation_mode === "auto";
  }, [isFat, row]);

  const handleSaveOverride = async (payload) => {
    if (!overrideData) return;
    setIsSaving(true);
    try {
      await api(`/payrolls/${id}/allowances/${overrideData.id}`, {
        method: "PATCH",
        body: payload
      });
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
      await api(`/payrolls/${id}/recalculate`, {
        method: "POST",
        body: { force }
      });
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
    <div>
      <div className="space-y-6">
        <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <div className="hidden">
              <span className="h-2 w-2 rounded-full bg-sky-500" />
              <span className="text-[10px] font-semibold text-muted-foreground">
                Human Plus Institute
              </span>
            </div>

            <div className="mt-4 flex items-center gap-3 flex-wrap">
              <h1 className="text-lg font-semibold text-foreground">
                Slip Gaji
              </h1>

              {!loading && row && <AccessBadge masked={!!row.masked} />}

              {!loading && row && isPaid && (
                <Badge className="rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700">
                  PAID
                </Badge>
              )}
              
              {!loading && row && row.calculation_mode === "auto" && (
                <Badge className="rounded-full border border-indigo-200 bg-indigo-50 text-indigo-700">
                  AUTO CALC
                </Badge>
              )}
            </div>

            <p className="mt-1 text-sm text-slate-600">
              Periode:{" "}
              <span className="font-semibold text-slate-800">
                {periodeLabel || "-"}
              </span>
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <Button
              variant="outline"
              onClick={() => nav(-1)}
              className="bg-white border border-border rounded text-xs text-muted-foreground hover:text-foreground transition-colors"
            >
              Kembali
            </Button>
            
            {canOverrideOrRecalculate && (
              <Button
                variant="outline"
                onClick={() => handleRecalculate(false)}
                disabled={isSaving}
                className="rounded bg-indigo-50 text-indigo-700 border-indigo-200 hover:bg-indigo-100"
              >
                {isSaving ? "Processing..." : "Recalculate"}
              </Button>
            )}

            <Button
              variant="outline"
              onClick={() => row?.id && openPayrollPdf(row.id)}
              disabled={!row || row.masked || pdfLoading}
              className="bg-white border border-border rounded text-xs text-muted-foreground hover:text-foreground transition-colors"
              title={row?.masked ? "Tidak punya akses melihat nominal" : "Buka PDF di tab baru"}
            >
              {pdfLoading ? "Membuka PDF..." : "Buka PDF (Print)"}
            </Button>

            {row?.id && isPaid && (
              <Button
                variant="outline"
                onClick={() => openProof(row.id)}
                disabled={proofLoading}
                className="bg-white border border-border rounded text-xs text-muted-foreground hover:text-foreground transition-colors"
                title="Buka bukti transfer di tab baru"
              >
                {proofLoading ? "Membuka..." : "Bukti Transfer"}
              </Button>
            )}
          </div>
        </div>

        {loading && (
          <div className="bg-white border border-border rounded shadow-sm px-6 py-6 text-xs text-muted-foreground">
            Loading slip gaji...
          </div>
        )}

        {!loading && err && (
          <div className="bg-white border border-border rounded shadow-sm p-4 my-4">
            <div className="font-bold text-rose-800 mb-1">Gagal memuat data</div>
            {err}
          </div>
        )}

        {!loading && !err && row && (
          <div className="space-y-6">
            
            <div className="flex border-b border-slate-200">
              <button
                className={`px-4 py-3 text-sm font-medium border-b-2 transition-colors ${activeTab === 'detail' ? 'border-sky-500 text-sky-700' : 'border-transparent text-slate-500 hover:text-slate-700'}`}
                onClick={() => setActiveTab('detail')}
              >
                Detail Gaji
              </button>
              {(user?.role?.toLowerCase() === 'director' || user?.role?.toLowerCase() === 'fat') && (
                <button
                  className={`px-4 py-3 text-sm font-medium border-b-2 transition-colors ${activeTab === 'security' ? 'border-sky-500 text-sky-700' : 'border-transparent text-slate-500 hover:text-slate-700'}`}
                  onClick={() => setActiveTab('security')}
                >
                  Security Inspection
                </button>
              )}
            </div>

            {activeTab === 'security' && (
              <SecurityInspectionTab payrollId={row.id} />
            )}

            {activeTab === 'detail' && (
              <>
                <div className="bg-white border border-border rounded shadow-sm overflow-hidden">
              <div className="px-4 py-3 border-b border-slate-200/70 flex items-center justify-between">
                <div>
                  <div className="text-sm font-medium text-foreground">
                    Informasi Karyawan
                  </div>
                  <div className="text-xs text-slate-500">
                    Ringkasan data pegawai dan pembuat payroll.
                  </div>
                </div>

                <div className="text-xs text-slate-500">
                  ID: <span className="font-semibold text-slate-700">{id}</span>
                </div>
              </div>

              <div className="p-4 grid grid-cols-1 md:grid-cols-2 gap-8">
                <div className="space-y-4">
                  <div className="flex flex-col gap-1">
                    <span className="text-[11px] text-slate-500 uppercase tracking-wider font-semibold">Nama Lengkap</span>
                    <span className="text-sm font-medium text-foreground">{row.employee_name || "-"}</span>
                  </div>
                  <div className="flex flex-col gap-1">
                    <span className="text-[11px] text-slate-500 uppercase tracking-wider font-semibold">Kode Pegawai</span>
                    <span className="text-sm font-medium text-slate-700">{row.employee_code || "-"}</span>
                  </div>
                  <div className="flex flex-col gap-1">
                    <span className="text-[11px] text-slate-500 uppercase tracking-wider font-semibold">Departemen</span>
                    <span className="text-sm font-medium text-slate-700">{row.employee?.department || "-"}</span>
                  </div>
                  <div className="flex flex-col gap-1">
                    <span className="text-[11px] text-slate-500 uppercase tracking-wider font-semibold">Posisi</span>
                    <span className="text-sm font-medium text-slate-700">{row.employee?.position || "-"}</span>
                  </div>
                </div>

                <div className="space-y-4">
                  <div className="flex flex-col gap-1">
                    <span className="text-[11px] text-slate-500 uppercase tracking-wider font-semibold">Grade</span>
                    <span className="text-sm font-medium text-slate-700">{row.employee?.grade_name || "-"}</span>
                  </div>
                  <div className="flex flex-col gap-1">
                    <span className="text-[11px] text-slate-500 uppercase tracking-wider font-semibold">Status Kerja</span>
                    <span className="text-sm font-medium text-slate-700">
                      {row.employee?.employment_type_name || "-"} / {row.employee?.work_basis_name || "-"}
                    </span>
                  </div>
                  <div className="flex flex-col gap-1">
                    <span className="text-[11px] text-slate-500 uppercase tracking-wider font-semibold">Bank</span>
                    <span className="text-sm font-medium text-foreground">{row.employee?.bank_name || "-"}</span>
                  </div>
                  <div className="flex flex-col gap-1">
                    <span className="text-[11px] text-slate-500 uppercase tracking-wider font-semibold">No. Rekening</span>
                    <span className="text-sm font-medium text-slate-700">
                      {row.employee?.bank_account_number_decrypted || "-"}
                      <br/>
                      <span className="text-xs text-[10px] text-muted-foreground mt-0.5 block">a.n {row.employee?.bank_account_name || "-"}</span>
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <div className="bg-white border border-border rounded shadow-sm overflow-hidden">
              <div className="px-4 py-3 border-b border-slate-200/70 flex items-center justify-between">
                <div>
                  <div className="text-sm font-medium text-foreground">
                    Rincian Gaji
                  </div>
                  <div className="text-xs text-slate-500">
                    Nominal hanya tampil jika kamu punya akses.
                  </div>
                </div>

                {!row.masked ? (
                  <Badge className="rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700">
                    Visible
                  </Badge>
                ) : (
                  <Badge className="rounded-full border border-slate-200 bg-slate-50 text-slate-700">
                    Restricted
                  </Badge>
                )}
              </div>

              <div className="p-4">
                {row.masked ? (
                  <div className="rounded border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800">
                    Kamu tidak memiliki akses untuk melihat nominal gaji.
                  </div>
                ) : (
                  <div className="overflow-hidden rounded border border-slate-200">
                    <div className="grid grid-cols-1 divide-y divide-slate-200">
                      <div className="flex items-center justify-between px-5 py-4 bg-white">
                         <div className="flex flex-col gap-1">
                           <span className="text-sm font-medium text-slate-700">Gaji Pokok</span>
                           
                           {row.monthly_recaps && row.monthly_recaps.length > 0 ? (
                             <div className="text-xs text-slate-500 mt-1 mb-1 space-y-2">
                               {row.monthly_recaps.map((recap, idx) => (
                                 <div key={idx} className="bg-slate-50 p-2 rounded border border-slate-100 relative overflow-hidden">
                                   {row.monthly_recaps.length > 1 && (
                                     <div className="absolute top-0 right-0 bg-sky-100 text-sky-800 text-[9px] font-bold px-2 py-0.5 rounded-bl">
                                       Segmen {idx + 1}
                                     </div>
                                   )}
                                   <div className="font-semibold text-slate-700 mb-1">
                                     {recap.grade_name} (Mulai: {recap.effective_from})
                                   </div>
                                   <div className="grid grid-cols-2 gap-x-4 gap-y-1">
                                     <div>Rate Harian: <span className="font-medium text-slate-700">{formatIDR(recap.mandays_rate)}</span></div>
                                     <div>Subtotal Hari: <span className="font-medium text-slate-700">{recap.total_mandays} hari</span></div>
                                   </div>
                                 </div>
                               ))}
                             </div>
                           ) : (
                             row.active_salary_profile && (
                               <div className="text-xs text-slate-500 mt-1 mb-1">
                                 <div className="grid grid-cols-2 gap-x-4 gap-y-1 bg-slate-50 p-2 rounded border border-slate-100">
                                   <div>Gaji Bulanan: <span className="font-medium text-slate-700">{formatIDR(row.active_salary_profile.base_salary)}</span></div>
                                   <div>Rate Harian: <span className="font-medium text-slate-700">{formatIDR(row.active_salary_profile.mandays_rate)}</span></div>
                                 </div>
                               </div>
                             )
                           )}
                           
                           {row.mandays_summary && (
                             <div className="text-[11px] text-slate-500 space-y-0.5">
                               <div>WFO: <span className="font-medium text-slate-700">{row.mandays_summary.mandays_ho_wfo} hari</span></div>
                               {row.mandays_summary.mandays_outside_city > 0 && <div>Luar Kota: <span className="font-medium text-slate-700">{row.mandays_summary.mandays_outside_city} hari</span></div>}
                               {row.mandays_summary.mandays_project > 0 && <div>Proyek: <span className="font-medium text-slate-700">{row.mandays_summary.mandays_project} hari</span></div>}
                               {row.mandays_summary.mandays_training > 0 && <div>Training: <span className="font-medium text-slate-700">{row.mandays_summary.mandays_training} hari</span></div>}
                               <div>WFH: <span className="font-medium text-slate-700">{row.mandays_summary.mandays_ho_wfh} hari</span> <span className="text-amber-600 text-[10px]">(Tidak dikali mandays rate)</span></div>
                               <div className="pt-1 mt-1 border-t border-slate-100">Total Pengali Mandays: <span className="font-bold text-sky-700">{row.mandays_summary.total_mandays} hari</span></div>
                             </div>
                           )}
                         </div>
                         <span className="text-sm font-medium text-foreground self-start mt-0.5">
                           {formatIDR(row.gaji_pokok)}
                         </span>
                      </div>

                      {(row?.allowances?.length > 0 || row?.deductions?.length > 0) ? (
                        <>
                          {row.allowances?.map((al, idx) => (
                            <div key={`al-${idx}`} className="flex items-center justify-between px-5 py-4 bg-white/80 group hover:bg-slate-50 transition">
                              <div className="flex flex-col gap-1 w-full">
                                <div className="flex items-center gap-2">
                                  <span className="text-sm text-slate-700 font-medium">{al.allowance_type?.name || al.allowance_type || 'Tunjangan'}</span>
                                  {al.is_manual_override && (
                                    <Badge variant="outline" className="text-[10px] bg-yellow-100 text-yellow-800 border-yellow-200 px-1.5 py-0.5">
                                      Manual Override
                                    </Badge>
                                  )}
                                  {canOverrideOrRecalculate && (
                                    <button
                                      onClick={() => setOverrideData(al)}
                                      className="ml-2 text-slate-400 hover:text-sky-600 opacity-0 group-hover:opacity-100 transition"
                                      title="Override Allowance"
                                    >
                                      ✎ Edit
                                    </button>
                                  )}
                                </div>
                                <span className="text-xs text-slate-500 pl-1">
                                  {al.is_manual_override ? (
                                    `Alasan: ${al.condition_notes || al.override_reason || '-'}`
                                  ) : (
                                    formatDetail(al.calculation_detail, al.mandays, al.rate_amount)
                                  )}
                                </span>
                                
                                {al.calculation_detail?.segments && al.calculation_detail.segments.length > 0 && (
                                  <div className="mt-2 space-y-1 pl-1">
                                    {al.calculation_detail.segments.map((seg, sIdx) => (
                                      <div key={sIdx} className="flex items-center justify-between text-xs bg-slate-50/50 p-1.5 rounded border border-slate-100/50">
                                        <div className="text-slate-500">
                                          <span className="font-medium text-slate-600">{seg.grade}</span>
                                          {seg.mandays != null ? ` • ${seg.mandays} Hari` : ''}
                                          {seg.rate != null ? ` • Rate: ${formatIDR(seg.rate)}` : ''}
                                        </div>
                                        <div className="font-medium text-slate-600">
                                          {formatIDR(seg.amount)}
                                        </div>
                                      </div>
                                    ))}
                                  </div>
                                )}
                              </div>
                              <span className="text-xs font-semibold text-emerald-600 shrink-0 ml-4">
                                {formatIDR(al.amount)}
                              </span>
                            </div>
                          ))}
                          {row.deductions?.map((dd, idx) => (
                            <div key={`dd-${idx}`} className="flex items-center justify-between px-5 py-4 bg-white border-t border-slate-100">
                              <span className="text-sm text-slate-700 font-medium">{dd.deduction_label || 'Potongan'}</span>
                              <span className="text-xs font-semibold text-rose-600">
                                - {formatIDR(dd.amount)}
                              </span>
                            </div>
                          ))}
                        </>
                      ) : (
                        <>
                          <div className="flex items-center justify-between px-5 py-4 bg-white/80">
                            <span className="text-sm text-slate-600">Tunjangan</span>
                            <span className="text-sm font-medium text-foreground">
                              {formatIDR(row.tunjangan)}
                            </span>
                          </div>

                          <div className="flex items-center justify-between px-5 py-4 bg-white">
                            <span className="text-sm text-slate-600">Potongan</span>
                            <span className="text-sm font-medium text-foreground">
                              {formatIDR(row.potongan)}
                            </span>
                          </div>
                        </>
                      )}

                      <div className="flex items-center justify-between px-5 py-4 bg-gradient-to-r from-sky-50 to-indigo-50">
                        <span className="text-sm font-extrabold text-slate-900">
                          Total
                        </span>
                        <span className="text-base font-extrabold text-slate-900">
                          {formatIDR(computedTotal)}
                        </span>
                      </div>
                    </div>
                  </div>
                )}

                {!!row.catatan && (
                  <div className="mt-4 bg-white border border-border rounded shadow-sm px-5 py-4">
                    <div className="text-[11px] text-slate-500">Catatan</div>
                    <div className="mt-1 text-sm text-slate-800">{row.catatan}</div>
                  </div>
                )}
              </div>

              <div className="px-6 py-4 border-t border-slate-200/70 text-[11px] text-slate-500 flex items-center justify-between">
                <span>© {new Date().getFullYear()} Human Plus Institute</span>
                <span>Payroll Internal System</span>
              </div>
            </div>
          </>
        )}
      </div>
    )}
  </div>

      <OverrideAllowanceModal
        isOpen={!!overrideData}
        onClose={() => setOverrideData(null)}
        data={overrideData}
        onSave={handleSaveOverride}
        isSaving={isSaving}
      />

      <RecalculateConfirmModal
        isOpen={recalcOpen}
        onClose={() => setRecalcOpen(false)}
        message={recalcMsg}
        onConfirm={(force) => handleRecalculate(force)}
        isSaving={isSaving}
      />
    </div>
  );
}
