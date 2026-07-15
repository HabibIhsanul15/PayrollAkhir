import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { X, Download, AlertCircle, FileText, CheckCircle, XCircle } from "lucide-react";
import MutationRequestModal from "./MutationRequestModal";

export default function MutationDetailModal({ isOpen, onClose, onSuccess, requestId, userRole }) {
  const [request, setRequest] = useState(null);
  const [loading, setLoading] = useState(false);
  const [actionLoading, setActionLoading] = useState(false);
  const [rejectionReason, setRejectionReason] = useState("");
  const [showRejectInput, setShowRejectInput] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [err, setErr] = useState("");

  const API_BASE = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000";

  useEffect(() => {
    let active = true;
    if (isOpen && requestId) {
      const fetchDetail = async () => {
        setLoading(true);
        setErr("");
        setShowRejectInput(false);
        setRejectionReason("");
        try {
          const res = await api(`/mutation-requests/${requestId}`);
          if (active) setRequest(res);
        } catch (e) {
          if (active) setErr(e.message || "Gagal memuat detail pengajuan.");
        } finally {
          if (active) setLoading(false);
        }
      };
      fetchDetail();
    }
    return () => { active = false; };
  }, [isOpen, requestId]);

  const handleAction = async (action) => {
    setErr("");
    
    if (action === "reject" && !showRejectInput) {
      setShowRejectInput(true);
      return;
    }
    
    if (action === "reject" && !rejectionReason.trim()) {
      setErr("Alasan penolakan harus diisi.");
      return;
    }

    if (action === "cancel") {
      if (!confirm("Yakin ingin membatalkan pengajuan ini?")) return;
    }

    if (action === "approve") {
      if (!confirm("Yakin ingin menyetujui pengajuan ini? Perubahan jabatan akan langsung dijadwalkan.")) return;
    }

    setActionLoading(true);
    try {
      const payload = action === "reject" ? { rejection_reason: rejectionReason } : undefined;
      await api(`/mutation-requests/${requestId}/${action}`, { method: "POST", body: payload });
      onSuccess();
    } catch (e) {
      setErr(e.message || `Gagal memproses aksi: ${action}`);
      setActionLoading(false);
    }
  };

  if (!isOpen) return null;

  return (
    <>
      <div className="fixed inset-0 z-40 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
        <div className="w-full max-w-2xl bg-white rounded-2xl shadow-xl overflow-hidden flex flex-col max-h-[90vh]">
          
          <div className="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <div>
              <h3 className="text-lg font-semibold text-slate-900">Detail Pengajuan Promosi / Demosi</h3>
              <p className="text-sm text-slate-500 mt-1">
                {loading ? "Memuat..." : request?.employee?.name || "Memuat..."}
              </p>
            </div>
            <button 
              onClick={onClose}
              className="text-slate-400 hover:text-slate-600 transition-colors"
            >
              <X size={20} />
            </button>
          </div>

          <div className="p-6 overflow-y-auto">
            {err && (
              <div className="mb-6 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-600 border border-rose-200">
                {err}
              </div>
            )}

            {loading ? (
              <div className="py-12 text-center text-slate-500">Memuat detail pengajuan...</div>
            ) : !request ? (
              <div className="py-12 text-center text-slate-500">Data tidak ditemukan.</div>
            ) : (
              <div className="space-y-6">
                
                <div className="flex items-center justify-between">
                  <div className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-semibold border ${
                    request.status === 'approved' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 
                    request.status === 'rejected' ? 'bg-rose-50 text-rose-700 border-rose-200' : 
                    request.status === 'cancelled' ? 'bg-slate-100 text-slate-700 border-slate-300' : 
                    'bg-amber-50 text-amber-700 border-amber-200'
                  }`}>
                    {request.status === 'approved' ? <><CheckCircle size={16}/> Disetujui</> : 
                     request.status === 'rejected' ? <><XCircle size={16}/> Ditolak</> : 
                     request.status === 'cancelled' ? <><AlertCircle size={16}/> Dibatalkan</> : 
                     <><FileText size={16}/> Menunggu Persetujuan</>}
                  </div>
                  <div className="text-sm text-slate-500">
                    Diajukan pada {new Date(request.created_at).toLocaleDateString('id-ID')}
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div className="bg-slate-50 p-4 rounded-xl border border-slate-100 space-y-1">
                    <p className="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Tipe Mutasi</p>
                    <p className="font-semibold text-slate-900">
                      {request.mutation_type === 'promotion' ? 'Promosi' : 'Demosi'}
                    </p>
                  </div>
                  <div className="bg-slate-50 p-4 rounded-xl border border-slate-100 space-y-1">
                    <p className="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Efektif Sejak</p>
                    <p className="font-semibold text-slate-900">
                      {new Date(request.effective_date).toLocaleDateString('id-ID', {day: 'numeric', month: 'long', year: 'numeric'})}
                    </p>
                  </div>
                </div>

                <div className="border border-slate-200 rounded-xl overflow-hidden">
                  <div className="grid grid-cols-2 divide-x divide-slate-200">
                    <div className="p-4 bg-white">
                      <p className="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Jabatan Asal</p>
                      <p className="font-semibold text-slate-900">{request.employee?.position?.name || '-'}</p>
                      <p className="text-sm text-slate-500">Level {request.employee?.position?.level || '-'}</p>
                    </div>
                    <div className="p-4 bg-white">
                      <p className="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Jabatan Tujuan</p>
                      <p className="font-semibold text-slate-900">{request.target_position?.name || '-'}</p>
                      <p className="text-sm text-slate-500">Level {request.target_position?.level || '-'}</p>
                    </div>
                  </div>
                </div>

                <div className="space-y-4">
                  <div>
                    <h4 className="text-xs font-semibold text-slate-700 mb-1">Keterangan / Alasan</h4>
                    <div className="bg-slate-50 p-3 rounded-lg border border-slate-200 text-sm text-slate-700 min-h-[60px]">
                      {request.reason || <span className="text-slate-400 italic">Tidak ada keterangan.</span>}
                    </div>
                  </div>

                  {request.document_path && (
                    <div>
                      <h4 className="text-xs font-semibold text-slate-700 mb-1">Lampiran Dokumen</h4>
                      <a 
                        href={`${API_BASE}/storage/${request.document_path}`} 
                        target="_blank" 
                        rel="noreferrer"
                        className="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-lg border border-indigo-200 transition-colors"
                      >
                        <Download size={16} />
                        Lihat Lampiran
                      </a>
                    </div>
                  )}

                  {request.status === 'rejected' && request.rejection_reason && (
                    <div className="mt-4 bg-rose-50 border border-rose-200 rounded-xl p-4">
                      <h4 className="text-xs font-bold text-rose-700 uppercase tracking-wider mb-1">Alasan Penolakan</h4>
                      <p className="text-sm text-rose-800">{request.rejection_reason}</p>
                    </div>
                  )}
                </div>

                {/* Director Reject Input */}
                {showRejectInput && request.status === 'pending' && userRole === 'director' && (
                  <div className="pt-4 border-t border-slate-100 mt-6 animate-in slide-in-from-top-2">
                    <label className="block text-xs font-semibold text-slate-700 mb-1.5">Masukan Alasan Penolakan</label>
                    <textarea
                      value={rejectionReason}
                      onChange={(e) => setRejectionReason(e.target.value)}
                      rows={3}
                      placeholder="Jelaskan alasan mengapa pengajuan ini ditolak..."
                      className="w-full rounded-xl border border-rose-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-rose-500 focus:ring-4 focus:ring-rose-500/20 resize-none mb-3"
                    ></textarea>
                    <div className="flex justify-end gap-2">
                      <button 
                        onClick={() => setShowRejectInput(false)}
                        className="px-3 py-1.5 text-sm font-medium text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors"
                      >
                        Batal
                      </button>
                      <button 
                        onClick={() => handleAction('reject')}
                        disabled={actionLoading}
                        className="px-4 py-1.5 text-sm font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-lg shadow-sm transition-colors disabled:opacity-50"
                      >
                        {actionLoading ? "Memproses..." : "Konfirmasi Tolak"}
                      </button>
                    </div>
                  </div>
                )}
              </div>
            )}
          </div>

          {!loading && request && (
            <div className="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-between items-center">
              <div>
                {/* Actions for HCGA */}
                {userRole === 'hcga' && request.status === 'pending' && (
                  <button
                    onClick={() => handleAction('cancel')}
                    disabled={actionLoading}
                    className="text-sm font-semibold text-rose-600 hover:text-rose-800 transition-colors disabled:opacity-50"
                  >
                    {actionLoading ? "Memproses..." : "Batalkan Pengajuan"}
                  </button>
                )}
              </div>
              <div className="flex gap-3">
                <button
                  type="button"
                  onClick={onClose}
                  className="px-4 py-2 text-sm font-semibold text-slate-600 hover:text-slate-900 transition-colors"
                >
                  Tutup
                </button>
                
                {/* Actions for Director */}
                {userRole === 'director' && request.status === 'pending' && !showRejectInput && (
                  <>
                    <button
                      onClick={() => handleAction('reject')}
                      disabled={actionLoading}
                      className="px-4 py-2 bg-rose-100 hover:bg-rose-200 text-rose-700 text-sm font-bold rounded-lg transition-colors disabled:opacity-50"
                    >
                      Tolak
                    </button>
                    <button
                      onClick={() => handleAction('approve')}
                      disabled={actionLoading}
                      className="px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-lg shadow-sm transition-all focus:ring-4 focus:ring-emerald-500/30 disabled:opacity-50"
                    >
                      Setujui Promosi
                    </button>
                  </>
                )}

                {/* Edit for HCGA */}
                {userRole === 'hcga' && request.status === 'pending' && (
                  <button
                    onClick={() => setIsEditModalOpen(true)}
                    disabled={actionLoading}
                    className="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-all focus:ring-4 focus:ring-indigo-500/30 disabled:opacity-50"
                  >
                    Edit Pengajuan
                  </button>
                )}
              </div>
            </div>
          )}
        </div>
      </div>

      {isEditModalOpen && (
        <MutationRequestModal
          isOpen={isEditModalOpen}
          onClose={() => setIsEditModalOpen(false)}
          onSuccess={() => {
            setIsEditModalOpen(false);
            onSuccess(); // refresh table & detail modal
          }}
          editData={request}
        />
      )}
    </>
  );
}
