import { useState } from "react";
import { Link } from "react-router-dom";
import useSWR from "swr";
import { api } from "@/lib/api";
import { getUser, isAuthed } from "@/lib/auth";
import { currentPayrollMonth, formatRupiah } from "@/lib/utils";
import AlertMessage from "@/components/AlertMessage";
import { Search, FileText, CheckCircle2, Calculator, Trash2, Send, CheckCircle, CreditCard, AlertTriangle, XCircle } from "lucide-react";
import PayrollPreviewModal from "@/components/PayrollPreviewModal";
import RejectPayrollModal from "@/components/RejectPayrollModal";
import { useConfirm } from "@/components/ConfirmProvider";

const STATUS_CONFIG = {
  ready: {
    label: "Siap Diajukan",
    icon: CheckCircle2,
    className: "bg-emerald-50 text-emerald-700 border-emerald-200",
  },
  draft: {
    label: "Draft",
    icon: Calculator,
    className: "bg-slate-100 text-slate-700 border-slate-200",
  },
  submitted: {
    label: "Menunggu Direktur",
    icon: Send,
    className: "bg-orange-50 text-orange-700 border-orange-200",
  },
  approved: {
    label: "Disetujui",
    icon: CheckCircle,
    className: "bg-blue-50 text-blue-700 border-blue-200",
  },
  paid: {
    label: "Dibayar",
    icon: CreditCard,
    className: "bg-emerald-100 text-emerald-700 border-emerald-200",
  },
  rejected: {
    label: "Ditolak",
    icon: XCircle,
    className: "bg-rose-50 text-rose-700 border-rose-200",
  },
  failed: {
    label: "Perlu Data",
    icon: AlertTriangle,
    className: "bg-rose-50 text-rose-700 border-rose-200",
  },
};

function resolvePayrollStatus(row) {
  if (row.status === "failed") return "failed";
  return row.payroll_status || "ready";
}

function grossPay(row) {
  return Number(row?.gaji_pokok || 0) + Number(row?.total_allowances || 0);
}

function grossPaySummary(summary) {
  return Number(summary?.total_gaji_pokok || 0) + Number(summary?.total_allowances || 0);
}

function buildSummary(rows) {
  return rows.reduce(
    (acc, row) => ({
      total_gaji_pokok: acc.total_gaji_pokok + Number(row?.gaji_pokok || 0),
      total_allowances: acc.total_allowances + Number(row?.total_allowances || 0),
      total_deductions: acc.total_deductions + Number(row?.total_deductions || 0),
      total_nett: acc.total_nett + Number(row?.total_nett || 0),
    }),
    {
      total_gaji_pokok: 0,
      total_allowances: 0,
      total_deductions: 0,
      total_nett: 0,
    }
  );
}

function formatPlainNumber(value) {
  if (value === null || value === undefined || value === "") return "-";
  const number = Number(value);
  if (!Number.isFinite(number)) return "-";
  return new Intl.NumberFormat("id-ID", {
    maximumFractionDigits: 2,
  }).format(number);
}

function makeTransferRef(row, period) {
  const periodKey = String(period || "").replace("-", "") || "000000";
  const payrollId = String(row?.payroll_id || row?.id || 0).padStart(5, "0");
  return `TRF-${periodKey}-${payrollId}`;
}

export default function PayrollList() {
  const user = getUser();
  const role = String(user?.role || "").toLowerCase();
  const { confirm } = useConfirm();

  const [period, setPeriod] = useState(() => currentPayrollMonth());
  const [q, setQ] = useState("");
  
  const [previewModal, setPreviewModal] = useState({
    open: false,
    employeeId: null,
    payrollId: null,
    payrollStatus: null,
  });
  const [transferModal, setTransferModal] = useState({
    open: false,
    row: null,
    proofFile: null,
    paidRef: "",
    paidNote: "",
    submitting: false,
  });
  const [rejectModal, setRejectModal] = useState({
    open: false,
    payrollId: null,
    submitting: false,
    errorMessage: "",
  });

  const isFAT = role === "fat";
  const isDirector = role === "director";
  const isStaff = role === "staff" || role === "employee";
  const pageTitle = isStaff
    ? "Slip Gaji Saya"
    : isDirector
      ? "Persetujuan Payroll"
      : "Pengajuan Payroll";
  const pageDescription = isStaff
    ? "Slip gaji yang sudah dibayar akan muncul di halaman ini."
    : isDirector
      ? "Tinjau payroll yang diajukan Finance, lalu setujui atau tolak sebelum pembayaran diproses."
      : "Tinjau perhitungan gaji, lalu ajukan payroll ke Direktur untuk disetujui.";
  const payrollKey = !isAuthed()
    ? null
    : isStaff
      ? `/payrolls?period_month=${period}&status=paid`
      : `/payrolls/batch-preview?period_month=${period}`;

  // Fetch Processing Grid Data
  const { data, error, isLoading, mutate } = useSWR(
    payrollKey,
    async (url) => {
      if (isStaff) return api(url);
      const res = await api(url, { method: "POST", body: { period_month: period } });
      return res;
    }
  );

  const loading = isLoading;
  const err = error?.message;

  const staffResults = Array.isArray(data)
    ? data.map((row) => ({
        employee_id: row.employee_id,
        employee_name: row.employee_name || "-",
        bank_name: row.bank_name,
        bank_account_number: row.bank_account_number,
        status: "generated",
        payroll_id: row.id,
        payroll_status: row.status,
        total_mandays: row.total_mandays,
        gaji_pokok: Number(row.gaji_pokok || 0),
        total_allowances: Number(row.tunjangan || 0),
        total_deductions: Number(row.potongan || 0),
        total_nett: Number(row.total || 0),
        periode: row.periode,
      }))
    : [];
  const rawResults = isStaff ? staffResults : (data?.results || []);
  const results = isStaff
    ? rawResults
      : isDirector
        ? rawResults.filter((r) => r.payroll_id && ["submitted", "approved", "paid", "rejected"].includes(r.payroll_status))
        : rawResults;
  const summary = buildSummary(results);

  const filtered = results.filter((r) =>
    String(r.employee_name || "").toLowerCase().includes(q.toLowerCase())
  );

  const load = () => {
    mutate();
  };

  const createDraftIfNeeded = async (row) => {
    if (row.payroll_id) return row.payroll_id;

    const created = await api("/payrolls/auto", {
      method: "POST",
      body: {
        employee_id: row.employee_id,
        period_month: period,
      },
    });

    return created?.id;
  };

  const submitToDirector = async (payrollId) => {
    if (!payrollId) {
      throw new Error("Payroll belum berhasil dibuat.");
    }

    await api(`/payrolls/${payrollId}/submit`, { method: "POST" });
  };

  const handleRequestApproval = async (row) => {
    if (row.status === "failed") {
      await confirm(row.errors?.[0] || "Data payroll belum lengkap.", {
        title: "Belum Bisa Diajukan",
        isAlert: true,
        icon: "warning",
      });
      return;
    }

    const message = row.payroll_id
      ? `Ajukan payroll ${row.employee_name} periode ${period} ke Direktur?`
      : `Payroll ${row.employee_name} belum menjadi draft. Sistem akan membuat draft dari perhitungan saat ini lalu mengajukannya ke Direktur. Lanjutkan?`;

    const ok = await confirm(message, { title: "Ajukan ke Direktur", icon: "info" });
    if (!ok) return;

    try {
      const payrollId = await createDraftIfNeeded(row);
      await submitToDirector(payrollId);
      await confirm("Payroll berhasil diajukan ke Direktur.", {
        title: "Sukses",
        isAlert: true,
        icon: "success",
      });
      load();
    } catch (e) {
      await confirm(e?.message || "Gagal mengajukan payroll.", {
        title: "Error",
        isAlert: true,
        icon: "warning",
      });
    }
  };

  const handleRequestAllApproval = async () => {
    const candidates = results.filter((row) => {
      const statusKey = resolvePayrollStatus(row);
      return statusKey === "ready" || statusKey === "draft" || statusKey === "rejected";
    });

    if (candidates.length === 0) {
      await confirm("Tidak ada payroll yang siap diajukan untuk periode ini.", {
        title: "Tidak Ada Data",
        isAlert: true,
        icon: "info",
      });
      return;
    }

    const ok = await confirm(
      `Ajukan ${candidates.length} payroll periode ${period} ke Direktur? Payroll yang belum menjadi draft akan dibuat otomatis terlebih dahulu.`,
      { title: "Ajukan Semua ke Direktur", icon: "info" }
    );
    if (!ok) return;

    let success = 0;
    const failures = [];

    for (const row of candidates) {
      try {
        const payrollId = await createDraftIfNeeded(row);
        await submitToDirector(payrollId);
        success++;
      } catch (e) {
        failures.push(`${row.employee_name}: ${e?.message || "gagal diproses"}`);
      }
    }

    load();

    const message = failures.length
      ? `${success} payroll berhasil diajukan. ${failures.length} payroll gagal:\n${failures.join("\n")}`
      : `${success} payroll berhasil diajukan ke Direktur.`;

    await confirm(message, {
      title: failures.length ? "Sebagian Berhasil" : "Sukses",
      isAlert: true,
      icon: failures.length ? "warning" : "success",
    });
  };
  const handleAction = async (id, action) => {
    if (action === "reject") {
      setRejectModal({ open: true, payrollId: id, submitting: false, errorMessage: "" });
      return;
    }

    let confirmMsg = "";
    if (action === "submit") confirmMsg = "Ajukan payroll ini ke Direktur?";
    if (action === "approve") confirmMsg = "Setujui payroll ini?";
    
    const ok = await confirm(confirmMsg, {
      title: action === "approve" ? "Setujui Payroll" : "Ajukan ke Direktur",
      icon: action === "approve" ? "success" : "info",
    });
    if (!ok) return;

    try {
      await api(`/payrolls/${id}/${action}`, { method: "POST", body: {} });
      load();
    } catch (e) {
      alert(e?.message || `Gagal melakukan aksi ${action}`);
    }
  };

  const closeRejectModal = () => {
    if (rejectModal.submitting) return;
    setRejectModal({ open: false, payrollId: null, submitting: false, errorMessage: "" });
  };

  const submitReject = async (reason) => {
    const payrollId = rejectModal.payrollId;
    if (!payrollId) return;

    setRejectModal((current) => ({ ...current, submitting: true, errorMessage: "" }));
    try {
      await api(`/payrolls/${payrollId}/reject`, { method: "POST", body: { note: reason } });
      setRejectModal({ open: false, payrollId: null, submitting: false, errorMessage: "" });
      load();
    } catch (e) {
      setRejectModal((current) => ({
        ...current,
        submitting: false,
        errorMessage: e?.message || "Gagal menolak payroll.",
      }));
    }
  };

  const openTransferModal = (row) => {
    setTransferModal({
      open: true,
      row,
      proofFile: null,
      paidRef: makeTransferRef(row, period),
      paidNote: "",
      submitting: false,
    });
  };

  const closeTransferModal = () => {
    if (transferModal.submitting) return;
    setTransferModal({
      open: false,
      row: null,
      proofFile: null,
      paidRef: "",
      paidNote: "",
      submitting: false,
    });
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

    const row = transferModal.row;
    const formData = new FormData();
    formData.append("proof", transferModal.proofFile);
    formData.append("paid_ref", transferModal.paidRef.trim() || makeTransferRef(row, period));
    if (transferModal.paidNote.trim()) formData.append("paid_note", transferModal.paidNote.trim());

    setTransferModal((prev) => ({ ...prev, submitting: true }));
    try {
      await api(`/payrolls/${row.payroll_id}/mark-paid`, {
        method: "POST",
        body: formData,
      });
      closeTransferModal();
      load();
      await confirm("Transfer gaji berhasil dicatat. Slip gaji sudah tersedia untuk pegawai terkait.", {
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

  const handleDelete = async (id) => {
    const ok = await confirm("Yakin ingin menghapus payroll tergenerate ini?");
    if (!ok) return;
    try {
      await api(`/payrolls/${id}`, { method: "DELETE" });
      load();
    } catch (e) {
      alert(e?.message || "Gagal menghapus payroll.");
    }
  };

  return (
    <div>
      <div className="space-y-6">
        <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <div className="hidden">
              <span className="h-2 w-2 rounded-full bg-blue-500" />
              <span className="text-[10px] font-semibold text-muted-foreground">Payroll App</span>
            </div>
            <h1 className="mt-4 text-lg font-semibold text-foreground">
              {pageTitle}
            </h1>
            <p className="mt-1 text-sm text-slate-600">
              {pageDescription}
            </p>
          </div>

          <div className="flex items-center gap-2">
            {isFAT && (
              <button
                onClick={handleRequestAllApproval}
                className="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 rounded text-xs font-medium text-white hover:bg-blue-700 shadow-sm transition-colors"
              >
                <Send size={14} />
                Ajukan Semua ke Direktur
              </button>
            )}
          </div>
        </div>

        <div className="rounded border border-border bg-white shadow-sm overflow-hidden">
          <div className="p-4 border-b border-border bg-slate-50/50 flex flex-col md:flex-row gap-4 justify-between md:items-center">
            <div className="flex-1 w-full relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={14} />
              <input
                type="text"
                placeholder="Cari karyawan..."
                className="w-full pl-9 pr-4 py-1.5 text-[13px] border border-slate-200 rounded outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition-all bg-white"
                value={q}
                onChange={(e) => setQ(e.target.value)}
              />
            </div>
            
            <div className="flex items-center gap-3">
              <div className="flex items-center gap-2">
                <span className="text-[11px] font-medium text-slate-500">Periode</span>
                <input
                  type="month"
                  value={period}
                  onChange={(e) => setPeriod(e.target.value)}
                  className="px-2 py-1 text-[13px] border border-slate-200 rounded outline-none focus:border-blue-400 bg-white"
                />
              </div>
            </div>
          </div>

          {err && <AlertMessage type="error" message={err} className="m-4" />}

          {loading ? (
            <div className="p-8 text-center text-[13px] text-slate-500">
              <div className="animate-spin h-6 w-6 border-2 border-blue-500 border-t-transparent rounded-full mx-auto mb-3" />
              Memuat perhitungan payroll...
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse min-w-[950px]">
                <thead>
                  <tr className="border-b border-slate-200 bg-slate-50">
                    <th className="px-4 py-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider w-1/4">
                      Karyawan
                    </th>
                    <th className="px-4 py-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                      Kehadiran
                    </th>
                    <th className="px-4 py-3 font-semibold text-slate-600 text-right w-[160px]">GAJI KOTOR</th>
                    <th className="px-4 py-3 font-semibold text-slate-600 text-right w-[150px]">POTONGAN</th>
                    <th className="px-4 py-3 font-semibold text-slate-600 text-right w-[150px]">TOTAL NETT</th>
                    <th className="px-4 py-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider text-center">
                      Status
                    </th>
                    <th className="px-4 py-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider text-right">
                      Aksi
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {filtered.length === 0 ? (
                    <tr>
                      <td colSpan={7} className="px-4 py-8 text-center text-[13px] text-slate-500">
                        {isDirector
                          ? "Belum ada payroll yang diajukan untuk periode ini."
                          : "Tidak ada data karyawan."}
                      </td>
                    </tr>
                  ) : (
                    filtered.map((r, i) => {
                      const isFailed = r.status === 'failed';
                      const statusKey = resolvePayrollStatus(r);
                      const statusMeta = STATUS_CONFIG[statusKey] || STATUS_CONFIG.ready;
                      const StatusIcon = statusMeta.icon;
                      const canRequestApproval = isFAT && ["ready", "draft", "rejected"].includes(statusKey);
                      
                      return (
                        <tr key={i} className="hover:bg-slate-50/80 transition-colors">
                          <td className="px-4 py-3">
                            <div className="flex items-center gap-3">
                              <div>
                                <div className="text-[13px] font-medium text-slate-900">{r.employee_name}</div>
                                {(r.bank_name || r.bank_account_number) && (
                                  <div className="text-[10px] text-slate-500 mt-0.5">
                                    Bank {r.bank_name || 'Bank?'} - {r.bank_account_number || 'Norek?'}
                                  </div>
                                )}
                              </div>
                            </div>
                          </td>
                          <td className="px-4 py-3 text-slate-600 text-[13px]">
                            {r.total_mandays ? `${formatPlainNumber(r.total_mandays)} Hari` : "-"}
                          </td>
                          <td className="px-4 py-3 text-right font-semibold text-slate-900 text-[13px]">
                            {grossPay(r) > 0 ? formatRupiah(grossPay(r)) : "-"}
                          </td>
                          <td className="px-4 py-3 text-right font-semibold text-red-600 text-[13px]">
                            {r.total_deductions > 0 ? `-${formatRupiah(r.total_deductions)}` : "-"}
                          </td>
                          <td className="px-4 py-3 text-right font-semibold text-blue-700 text-[13px]">
                            {formatRupiah(r.total_nett)}
                          </td>
                          <td className="px-4 py-3 text-center">
                              <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold border ${statusMeta.className}`}>
                                <StatusIcon size={12} />
                                {statusMeta.label}
                              </span>
                            {isFailed && (
                              <div className="flex flex-col items-center">
                                <span className="text-[10px] text-rose-500 mt-1 line-clamp-1 max-w-[120px]" title={r.errors?.[0]}>
                                  {r.errors?.[0]}
                                </span>
                              </div>
                            )}
                          </td>
                          <td className="px-4 py-3 text-right">
                              <div className="flex items-center justify-end gap-2">
                                {canRequestApproval && (
                                  <button onClick={() => handleRequestApproval(r)} className="px-2 py-1 flex items-center gap-1 text-[10px] font-medium text-orange-600 bg-orange-50 border border-orange-200 hover:bg-orange-100 rounded">
                                    <Send size={12} />
                                    {statusKey === "rejected" ? "Ajukan Ulang" : "Ajukan"}
                                  </button>
                                )}
                                {isDirector && r.payroll_status === 'submitted' && (
                                  <>
                                    <button onClick={() => handleAction(r.payroll_id, 'approve')} className="px-2 py-1 flex items-center gap-1 text-[10px] font-medium text-blue-600 bg-blue-50 border border-blue-200 hover:bg-blue-100 rounded">
                                      Setujui
                                    </button>
                                    <button onClick={() => handleAction(r.payroll_id, 'reject')} className="px-2 py-1 flex items-center gap-1 text-[10px] font-medium text-red-600 bg-red-50 border border-red-200 hover:bg-red-100 rounded">
                                      Tolak
                                    </button>
                                  </>
                                )}
                                {isFAT && r.payroll_status === 'approved' && (
                                  <button onClick={() => openTransferModal(r)} className="px-2 py-1 flex items-center gap-1 text-[10px] font-medium text-emerald-600 bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 rounded">
                                    <CreditCard size={12} />
                                    Catat Transfer
                                  </button>
                                )}
                                {isFAT && r.payroll_status !== "paid" && (
                                  <button
                                    onClick={() => setPreviewModal({
                                      open: true,
                                      employeeId: r.employee_id,
                                      payrollId: r.payroll_id || null,
                                      payrollStatus: r.payroll_status || null,
                                    })}
                                    className="flex items-center gap-1 px-2 py-1 text-[10px] font-medium text-blue-600 border border-blue-200 bg-blue-50 rounded hover:bg-blue-100 transition-colors"
                                    title="Detail perhitungan payroll"
                                  >
                                    <FileText size={12} />
                                    Detail
                                  </button>
                                )}
                                {isFAT && r.payroll_id && r.payroll_status === "paid" && (
                                  <Link
                                    to={`/payrolls/${r.payroll_id}`}
                                    className="flex items-center gap-1 px-2 py-1 text-[10px] font-medium text-blue-600 border border-blue-200 bg-blue-50 rounded hover:bg-blue-100 transition-colors"
                                    title="Lihat slip gaji"
                                  >
                                    <FileText size={12} />
                                    Lihat Slip Gaji
                                  </Link>
                                )}
                                {isDirector && r.payroll_id && r.payroll_status !== "paid" && (
                                  <button
                                    onClick={() => setPreviewModal({
                                      open: true,
                                      employeeId: r.employee_id,
                                      payrollId: r.payroll_id,
                                      payrollStatus: r.payroll_status || null,
                                    })}
                                    className="flex items-center gap-1 px-2 py-1 text-[10px] font-medium text-blue-600 border border-blue-200 bg-blue-50 rounded hover:bg-blue-100 transition-colors"
                                    title="Detail perhitungan payroll"
                                  >
                                    <FileText size={12} />
                                    Detail
                                  </button>
                                )}
                                {isDirector && r.payroll_id && r.payroll_status === "paid" && (
                                  <Link
                                    to={`/payrolls/${r.payroll_id}`}
                                    className="flex items-center gap-1 px-2 py-1 text-[10px] font-medium text-blue-600 border border-blue-200 bg-blue-50 rounded hover:bg-blue-100 transition-colors"
                                    title="Lihat slip gaji"
                                  >
                                    <FileText size={12} />
                                    Lihat Slip Gaji
                                  </Link>
                                )}
                                {isStaff && r.payroll_status === "paid" && (
                                  <Link
                                    to={`/payrolls/${r.payroll_id}`}
                                    className="flex items-center gap-1 px-2 py-1 text-[10px] font-medium text-blue-600 border border-blue-200 bg-blue-50 rounded hover:bg-blue-100 transition-colors"
                                    title="Lihat slip gaji"
                                  >
                                    <FileText size={12} />
                                    Lihat Slip
                                  </Link>
                                )}
                                {isFAT && r.payroll_status === 'draft' && (
                                  <button
                                    onClick={() => handleDelete(r.payroll_id)}
                                    className="flex items-center gap-1 px-2 py-1 text-[10px] font-medium text-red-600 border border-red-200 rounded hover:bg-red-50 transition-colors"
                                    title="Hapus"
                                  >
                                    <Trash2 size={12} />
                                    Hapus
                                  </button>
                                )}
                              </div>
                          </td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
                {/* Summary Row */}
                {results.length > 0 && (
                  <tfoot className="bg-slate-50 border-t border-slate-200">
                    <tr>
                      <td colSpan={2} className="px-4 py-3 text-right text-[12px] font-bold text-slate-700">
                        Total Keseluruhan
                      </td>
                      <td className="px-4 py-3 text-right text-[14px] font-bold text-slate-900">
                        {formatRupiah(grossPaySummary(summary))}
                      </td>
                      <td className="px-4 py-3 text-right text-[14px] font-bold text-red-600">
                        {summary?.total_deductions > 0 ? `-${formatRupiah(summary.total_deductions)}` : "-"}
                      </td>
                      <td className="px-4 py-3 text-right text-[14px] font-bold text-blue-700">
                        {formatRupiah(summary?.total_nett)}
                      </td>
                      <td colSpan={2}></td>
                    </tr>
                  </tfoot>
                )}
              </table>
            </div>
          )}
        </div>
      </div>

      {/* Preview & Deduction Modal */}
      <PayrollPreviewModal
        isOpen={previewModal.open}
        onClose={() => setPreviewModal({ open: false, employeeId: null, payrollId: null, payrollStatus: null })}
        employeeId={previewModal.employeeId}
        payrollId={previewModal.payrollId}
        periodMonth={period}
        isFAT={isFAT}
        canEditDeductions={isFAT && (!previewModal.payrollStatus || ["draft", "rejected"].includes(previewModal.payrollStatus))}
        onDeductionSaved={() => mutate()}
      />

      <RejectPayrollModal
        open={rejectModal.open}
        onClose={closeRejectModal}
        onConfirm={submitReject}
        isSubmitting={rejectModal.submitting}
        errorMessage={rejectModal.errorMessage}
      />

      {transferModal.open && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
          <div className="w-full max-w-lg rounded-lg border border-slate-200 bg-white shadow-xl overflow-hidden">
            <div className="px-5 py-4 border-b border-slate-200">
              <h2 className="text-base font-semibold text-slate-900">Catat Transfer Gaji</h2>
              <p className="mt-1 text-xs text-slate-500">
                Transfer dilakukan melalui bank, lalu bukti dan referensinya dicatat di sistem.
              </p>
            </div>

            <form onSubmit={submitTransfer}>
              <div className="p-5 space-y-4">
                <div className="rounded border border-slate-200 bg-slate-50 p-3 text-sm">
                  <div className="font-semibold text-slate-900">{transferModal.row?.employee_name}</div>
                  <div className="mt-1 text-slate-600">
                    Bank {transferModal.row?.bank_name || "-"} - {transferModal.row?.bank_account_number || "-"}
                  </div>
                  <div className="mt-2 flex items-center justify-between border-t border-slate-200 pt-2">
                    <span className="text-slate-500">Nominal Transfer</span>
                    <span className="font-bold text-blue-700">{formatRupiah(transferModal.row?.total_nett)}</span>
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-medium text-slate-700 mb-1">
                    Bukti Transfer <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="file"
                    accept=".pdf,.jpg,.jpeg,.png"
                    required
                    disabled={transferModal.submitting}
                    onChange={(e) => setTransferModal((prev) => ({ ...prev, proofFile: e.target.files?.[0] || null }))}
                    className="w-full text-xs text-slate-600 file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-medium file:text-slate-700 hover:file:bg-slate-200"
                  />
                  <p className="mt-1 text-[11px] text-slate-500">Format PDF/JPG/PNG, maksimal 4MB.</p>
                </div>

                <div>
                  <label className="block text-xs font-medium text-slate-700 mb-1">Nomor Referensi Transfer</label>
                  <div className="w-full rounded border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-sm font-semibold text-slate-800">
                    {transferModal.paidRef}
                  </div>
                  <p className="mt-1 text-[11px] text-slate-500">Nomor dibuat otomatis oleh sistem saat transfer dicatat.</p>
                </div>

                <div>
                  <label className="block text-xs font-medium text-slate-700 mb-1">Catatan</label>
                  <textarea
                    rows={3}
                    value={transferModal.paidNote}
                    disabled={transferModal.submitting}
                    onChange={(e) => setTransferModal((prev) => ({ ...prev, paidNote: e.target.value }))}
                    placeholder="Opsional, misalnya transfer via mobile banking."
                    className="w-full resize-none rounded border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                  />
                </div>
              </div>

              <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
                <button
                  type="button"
                  onClick={closeTransferModal}
                  disabled={transferModal.submitting}
                  className="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 disabled:opacity-50"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={transferModal.submitting}
                  className="rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                >
                  {transferModal.submitting ? "Menyimpan..." : "Simpan Transfer"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
