import { useEffect, useMemo, useState } from "react";
import useSWR from "swr";
import { api } from "@/lib/api";
import { useNavigate } from "react-router-dom";
import { getUser, isAuthed, getToken } from "@/lib/auth";
import { initials, monthLabel } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import AvatarInitial from "@/components/AvatarInitial";
import AlertMessage from "@/components/AlertMessage";
import Pagination from "@/components/Pagination";
import { Search, ChevronDown, RefreshCw, Plus, Pencil, Trash2, CheckCircle2, XCircle, FileText } from "lucide-react";
import PayrollCreateModal from "@/components/PayrollCreateModal";

export default function PayrollList() {
  const user = getUser();
  const role = String(user?.role || "").toLowerCase();

  const isStaff = role === "staff" || role === "employee";
  const canCreate = !isStaff; 
  const canApprove = role === "director"; 
  const canMarkPaid = role === "fat"; 

  const [q, setQ] = useState("");
  const [period, setPeriod] = useState("all");

  const { data, error, isLoading, mutate } = useSWR(isAuthed() ? "/payrolls" : null);

  const loading = isLoading;
  const err = error?.message;

  const rawData = Array.isArray(data) ? data : data?.data ?? [];
  const [localRows, setLocalRows] = useState([]);

  useEffect(() => {
    if (rawData) setLocalRows(rawData);
  }, [rawData]);

  const rows = localRows;

  const [page, setPage] = useState(1);
  const PAGE_SIZE = 8;

  const navigate = useNavigate();
  const [createModalOpen, setCreateModalOpen] = useState(false);

  const isFAT = role === "fat";
  const isDirector = role === "director";
  const canAction = isFAT || isDirector;

  // Modal Mark Paid
  const [paidOpen, setPaidOpen] = useState(false);
  const [paidTarget, setPaidTarget] = useState(null);
  const [paidFile, setPaidFile] = useState(null);
  const [paidRef, setPaidRef] = useState("");
  const [paidNote, setPaidNote] = useState("");
  const [paidSubmitting, setPaidSubmitting] = useState(false);

  const load = () => {
    mutate();
  };

  const openPaidModal = (row) => {
    setPaidTarget(row);
    setPaidFile(null);
    setPaidRef("");
    setPaidNote("");
    setPaidOpen(true);
  };

  const closePaidModal = () => {
    if (paidSubmitting) return;
    setPaidOpen(false);
    setPaidTarget(null);
    setPaidFile(null);
    setPaidRef("");
    setPaidNote("");
  };

  const periodKey = (value) => {
    const s = String(value || "").trim();
    if (!s) return "";
    if (/^\d{4}-\d{2}$/.test(s)) return s;
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s.slice(0, 7);
    return s.length >= 7 ? s.slice(0, 7) : s;
  };

  const statusLower = (s) => String(s || "").toLowerCase();

  const onDelete = async (id) => {
    const ok = confirm("Yakin mau hapus payroll ini?");
    if (!ok) return;
    try {
      await api(`/payrolls/${id}`, { method: "DELETE" });
      setLocalRows((prev) => prev.filter((x) => x.id !== id));
      mutate(undefined, { revalidate: true });
    } catch (e) {
      alert(e?.message || "Gagal menghapus payroll.");
    }
  };

  const onRequestPayment = async (id) => {
    const ok = confirm("Kirim payroll ini ke Director untuk approval?");
    if (!ok) return;
    try {
      await api(`/payrolls/${id}/request-payment`, { method: "POST" });
      mutate(undefined, { revalidate: true });
    } catch (e) {
      alert(e?.message || "Gagal request payment.");
    }
  };

  const onApprove = async (id) => {
    const ok = confirm("Approve payroll ini?");
    if (!ok) return;
    try {
      await api(`/payrolls/${id}/approve`, { method: "POST" });
      mutate(undefined, { revalidate: true });
    } catch (e) {
      alert(e?.message || "Gagal approve.");
    }
  };

  const onReject = async (id) => {
    const note = prompt("Alasan reject (wajib diisi):");
    if (!note) return alert("Alasan reject wajib diisi.");
    try {
      await api(`/payrolls/${id}/reject`, {
        method: "POST",
        body: { approval_note: note },
      });
      mutate(undefined, { revalidate: true });
    } catch (e) {
      alert(e?.message || "Gagal reject.");
    }
  };

  const submitMarkPaid = async () => {
    if (!paidTarget?.id) return;
    if (!paidFile) {
      alert("Bukti transfer wajib di-upload dulu.");
      return;
    }

    setPaidSubmitting(true);
    try {
      const fd = new FormData();
      fd.append("proof", paidFile);
      if (paidRef) fd.append("paid_ref", paidRef);
      if (paidNote) fd.append("paid_note", paidNote);

      const BASE_URL = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000";
      const url = `${BASE_URL}/api/payrolls/${paidTarget.id}/mark-paid`;
      const token = getToken();

      const res = await fetch(url, {
        method: "POST",
        headers: {
          Accept: "application/json",
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        body: fd,
      });

      const text = await res.text();
      let data = null;
      try { data = text ? JSON.parse(text) : null; } catch { data = text; }

      if (!res.ok) {
        const msg = data && typeof data === "object" && data.message ? data.message : `HTTP ${res.status}`;
        throw new Error(msg);
      }

      closePaidModal();
      mutate(undefined, { revalidate: true });
      alert("Berhasil! Payroll sudah PAID + bukti transfer tersimpan.");
    } catch (e) {
      alert(e?.message || "Gagal mark paid payroll.");
    } finally {
      setPaidSubmitting(false);
    }
  };

  const periodOptions = useMemo(() => {
    const set = new Set();
    rows.forEach((r) => {
      const key = periodKey(r?.periode);
      if (key) set.add(key);
    });
    const sorted = Array.from(set).sort((a, b) => (a < b ? 1 : -1));
    return ["all", ...sorted];
  }, [rows]);

  const filtered = useMemo(() => {
    const qq = q.trim().toLowerCase();
    return rows.filter((r) => {
      const name = String(r?.employee_name ?? "").toLowerCase();
      const code = String(r?.employee_code ?? "").toLowerCase();
      const pKey = periodKey(r?.periode);

      const matchQ = !qq || name.includes(qq) || code.includes(qq);
      const matchP = period === "all" || pKey === period;

      return matchQ && matchP;
    });
  }, [rows, q, period]);

  useEffect(() => {
    setPage(1);
  }, [q, period]);

  const summary = useMemo(() => {
    const total = filtered.length;
    const masked = filtered.filter((x) => !!x.masked).length;
    return { total, masked };
  }, [filtered]);

  const resetFilters = () => {
    setQ("");
    setPeriod("all");
  };

  const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  const safePage = Math.min(page, totalPages);
  const start = (safePage - 1) * PAGE_SIZE;
  const end = start + PAGE_SIZE;
  const paged = filtered.slice(start, end);

  const getStatusColor = (status) => {
    const s = statusLower(status);
    if (s === "paid") return "text-emerald-600";
    if (s === "approved") return "text-sky-600";
    if (s === "rejected") return "text-red-600";
    if (s === "requested") return "text-amber-600";
    return "text-slate-500";
  };

  return (
    <div>
      {/* Title + actions */}
      <div className="flex items-start justify-between mb-5">
        <div>
          <h1 className="text-lg font-semibold text-foreground">Payroll</h1>
          <p className="text-xs text-muted-foreground mt-0.5">
            Kelola dan lihat slip gaji per periode. Gunakan pencarian & filter agar lebih cepat.
          </p>
          <div className="flex items-center gap-3 mt-2 text-[10px] text-muted-foreground">
            <span>Total: <strong className="text-foreground">{summary.total}</strong></span>
            <span className="text-border">·</span>
            <span>Masked: <strong className="text-foreground">{summary.masked}</strong></span>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <button 
            onClick={load}
            disabled={loading}
            className="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-border rounded text-xs text-muted-foreground hover:text-foreground transition-colors disabled:opacity-50"
          >
            <RefreshCw size={11} className={loading ? "animate-spin" : ""} />
            Refresh
          </button>
          {isFAT && (
            <button 
              onClick={() => setCreateModalOpen(true)}
              className="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 rounded text-xs font-medium text-white hover:bg-blue-700 transition-colors"
            >
              <Plus size={11} />
              Create Payroll
            </button>
          )}
        </div>
      </div>
      
      <PayrollCreateModal 
        open={createModalOpen} 
        onClose={() => setCreateModalOpen(false)} 
        onSuccess={load}
      />

      <AlertMessage type="error" message={err} className="mb-4 px-4 py-3" />

      {/* Filters */}
      <div className="bg-white border border-border rounded p-4 mb-4" style={{ boxShadow: "0 1px 4px 0 rgba(0,0,0,0.04)" }}>
        <div className="flex flex-col md:flex-row md:items-end gap-3">
          <div className="flex-1">
            <label className="block text-[10px] font-medium text-muted-foreground mb-1.5">Cari Karyawan</label>
            <div>
              <Search size={12} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground" />
              <input
                type="text"
                placeholder="Nama / kode karyawan..."
                value={q}
                onChange={(e) => setQ(e.target.value)}
                className="w-full pl-8 pr-3 py-1.5 text-xs border border-border rounded bg-white focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all placeholder:text-muted-foreground"
              />
            </div>
          </div>
          <div className="w-full md:w-52">
            <label className="block text-[10px] font-medium text-muted-foreground mb-1.5">Filter Periode</label>
            <div>
              <select
                value={period}
                onChange={(e) => setPeriod(e.target.value)}
                className="w-full appearance-none pl-3 pr-7 py-1.5 text-xs border border-border rounded bg-white focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all"
              >
                {periodOptions.map((p) => (
                  <option key={p} value={p}>
                    {p === "all" ? "Semua periode" : monthLabel(p)}
                  </option>
                ))}
              </select>
              <ChevronDown size={11} className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" />
            </div>
          </div>
          <button
            onClick={resetFilters}
            className="px-3 py-1.5 text-xs text-muted-foreground border border-border rounded bg-white hover:bg-muted transition-colors whitespace-nowrap"
          >
            Reset
          </button>
        </div>
      </div>

      {/* Table */}
      <div className="bg-white border border-border rounded overflow-hidden" style={{ boxShadow: "0 1px 4px 0 rgba(0,0,0,0.04)" }}>
        <div className="px-5 py-3 border-b border-border flex items-center justify-between">
          <span className="text-sm font-medium text-foreground">Payroll Records</span>
          <span className="text-[10px] text-muted-foreground">
            Menampilkan {paged.length} dari {summary.total} record
          </span>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full border-collapse">
            <thead>
              <tr className="border-b border-border bg-slate-50/50">
                <th className="text-left px-5 py-2.5 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">ID</th>
                <th className="text-left px-4 py-2.5 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Karyawan</th>
                <th className="text-left px-4 py-2.5 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Periode</th>
                <th className="text-left px-4 py-2.5 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Status</th>
                <th className="text-left px-4 py-2.5 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Akses Nominal</th>
                {canAction && <th className="text-right px-5 py-2.5 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Aksi</th>}
              </tr>
            </thead>
            <tbody>
              {loading && (
                <tr>
                  <td colSpan={canAction ? 6 : 5} className="py-8 text-center text-xs text-muted-foreground">
                    Memuat data...
                  </td>
                </tr>
              )}
              {!loading && paged.length === 0 && (
                <tr>
                  <td colSpan={canAction ? 6 : 5} className="py-8 text-center">
                    <div className="flex flex-col items-center justify-center gap-2">
                      <FileText size={14} className="text-slate-300" />
                      <p className="text-xs text-muted-foreground">Tidak ada data payroll.</p>
                    </div>
                  </td>
                </tr>
              )}
              {!loading && paged.map((row) => (
                <tr
                  key={row.id}
                  className="border-b border-border last:border-0 hover:bg-slate-50/70 transition-colors cursor-pointer"
                  onClick={() => navigate(`/payrolls/${row.id}`)}
                >
                  <td className="px-5 py-3 text-[11px] font-medium text-blue-600">
                    #{row.id}
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2.5">
                      <AvatarInitial letters={initials(row.employee_name)} />
                      <div>
                        <div className="text-xs font-medium text-foreground">{row.employee_name || "-"}</div>
                        <div className="text-[10px] text-muted-foreground">{row.employee_code || "-"}</div>
                      </div>
                    </div>
                  </td>
                  <td className="px-4 py-3 text-xs text-muted-foreground">
                    {monthLabel(periodKey(row.periode))}
                  </td>
                  <td className="px-4 py-3">
                    <span className={`text-[10px] font-semibold uppercase tracking-wider ${getStatusColor(row.status)}`}>
                      {row.status}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-[10px]">
                    {row.masked ? (
                      <span className="text-slate-400">Masked</span>
                    ) : (
                      <span className="text-blue-600 font-medium">Nominal OK</span>
                    )}
                  </td>
                  
                  {canAction && (
                    <td className="px-5 py-3" onClick={(e) => e.stopPropagation()}>
                      <div className="flex items-center justify-end gap-1.5 flex-wrap">
                        {isDirector && (
                          <>
                            {statusLower(row.status) === "requested" && (
                              <>
                                <button
                                  onClick={() => onApprove(row.id)}
                                  className="flex items-center gap-1 px-2 py-1 text-[10px] font-medium text-emerald-600 border border-emerald-200 bg-emerald-50 rounded hover:bg-emerald-100 transition-colors"
                                >
                                  <CheckCircle2 size={10} /> Approve
                                </button>
                                <button
                                  onClick={() => onReject(row.id)}
                                  className="flex items-center gap-1 px-2 py-1 text-[10px] font-medium text-red-600 border border-red-200 bg-red-50 rounded hover:bg-red-100 transition-colors"
                                >
                                  <XCircle size={10} /> Reject
                                </button>
                              </>
                            )}
                          </>
                        )}

                        {isFAT && (
                          <>
                            {statusLower(row.status) === "draft" && (
                              <button
                                onClick={() => onRequestPayment(row.id)}
                                className="flex items-center gap-1 px-2 py-1 text-[10px] font-medium text-indigo-600 border border-indigo-200 bg-indigo-50 rounded hover:bg-indigo-100 transition-colors"
                              >
                                Request Apprv
                              </button>
                            )}
                            {statusLower(row.status) === "approved" && (
                              <button
                                onClick={() => openPaidModal(row)}
                                className="flex items-center gap-1 px-2 py-1 text-[10px] font-medium text-white bg-emerald-600 rounded hover:bg-emerald-700 transition-colors"
                              >
                                <CheckCircle2 size={10} /> Mark Paid
                              </button>
                            )}

                            <button
                              onClick={() => onDelete(row.id)}
                              className="flex items-center gap-1 px-2 py-1 text-[10px] font-medium text-red-600 border border-red-200 rounded hover:bg-red-50 transition-colors"
                            >
                              <Trash2 size={9} />
                            </button>
                          </>
                        )}
                      </div>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        
        <Pagination page={safePage} totalPages={totalPages} onPageChange={setPage} />
      </div>

      {/* MODAL MARK PAID */}
      {paidOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" onClick={closePaidModal} />
          <div className="relative w-full max-w-md bg-white rounded-lg shadow-lg border border-border overflow-hidden">
            <div className="px-5 py-4 border-b border-border">
              <h2 className="text-xs font-semibold text-foreground">Mark Paid + Upload Bukti</h2>
              <p className="text-[10px] text-muted-foreground mt-0.5">Wajib upload bukti transfer agar tidak asal klik "Paid".</p>
            </div>
            <div className="p-5 space-y-4">
              <div className="bg-slate-50 border border-border rounded p-3">
                <div className="text-[10px] text-muted-foreground uppercase tracking-wider">Payroll</div>
                <div className="text-xs font-semibold text-foreground mt-1">
                  {paidTarget?.employee_name || "-"} ({paidTarget?.employee_code || "-"})
                </div>
                <div className="text-[10px] text-muted-foreground mt-0.5">
                  Periode: {monthLabel(periodKey(paidTarget?.periode))}
                </div>
              </div>

              <div>
                <label className="block text-[11px] font-medium text-foreground mb-1">
                  Bukti Transfer <span className="text-red-500">*</span>
                </label>
                <p className="text-[9px] text-muted-foreground mb-2">Format: PDF/JPG/PNG (maks 4MB).</p>
                <input
                  type="file"
                  accept=".pdf,.jpg,.jpeg,.png"
                  className="w-full text-xs text-muted-foreground file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-[10px] file:font-semibold file:bg-slate-100 file:text-foreground hover:file:bg-slate-200"
                  onChange={(e) => setPaidFile(e.target.files?.[0] || null)}
                  disabled={paidSubmitting}
                />
              </div>

              <div>
                <label className="block text-[11px] font-medium text-foreground mb-1">
                  No. Referensi Transfer (opsional)
                </label>
                <input
                  value={paidRef}
                  onChange={(e) => setPaidRef(e.target.value)}
                  placeholder="Contoh: MBANK-20260115-XYZ"
                  className="w-full px-3 py-2 text-xs border border-border rounded bg-white focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100"
                  disabled={paidSubmitting}
                />
              </div>

              <div>
                <label className="block text-[11px] font-medium text-foreground mb-1">
                  Catatan (opsional)
                </label>
                <textarea
                  value={paidNote}
                  onChange={(e) => setPaidNote(e.target.value)}
                  placeholder="Catatan untuk audit"
                  rows={2}
                  className="w-full px-3 py-2 text-xs border border-border rounded bg-white focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 resize-none"
                  disabled={paidSubmitting}
                />
              </div>
            </div>
            <div className="px-5 py-3 border-t border-border bg-slate-50 flex items-center justify-end gap-2">
              <button
                onClick={closePaidModal}
                disabled={paidSubmitting}
                className="px-3 py-1.5 text-[11px] font-medium text-muted-foreground hover:text-foreground transition-colors"
              >
                Batal
              </button>
              <button
                onClick={submitMarkPaid}
                disabled={paidSubmitting || !paidFile}
                className="px-3 py-1.5 text-[11px] font-medium text-white bg-blue-600 rounded hover:bg-blue-700 transition-colors disabled:opacity-50"
              >
                {paidSubmitting ? "Menyimpan..." : "Confirm Paid"}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
