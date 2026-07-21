import { useState, useEffect } from "react";
import { api } from "@/lib/api";
import { FileText, CheckCircle, XCircle, Download, Plus } from "lucide-react";
import { getUser } from "@/lib/auth";
import MutationRequestModal from "@/components/MutationRequestModal";
import MutationDetailModal from "@/components/MutationDetailModal";

export default function MutationApprovalPage() {
  const [user, setUser] = useState(null);
  const [requests, setRequests] = useState([]);
  const [loading, setLoading] = useState(true);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [selectedRequestId, setSelectedRequestId] = useState(null);

  const fetchRequests = async () => {
    setLoading(true);
    try {
      const res = await api("/mutation-requests");
      setRequests(Array.isArray(res) ? res : []);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    setUser(getUser());
    fetchRequests();
  }, []);

  const API_BASE = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000";

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center mb-5">
        <div>
          <h1 className="text-lg font-semibold text-foreground">
            {user?.role === 'hcga' ? 'Pengajuan Promosi' : 'Persetujuan Promosi'}
          </h1>
          <p className="text-xs text-muted-foreground mt-0.5">Daftar pengajuan promosi dan demosi karyawan.</p>
        </div>
        {user?.role === 'hcga' && (
          <button
            onClick={() => setIsModalOpen(true)}
            className="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors shadow-sm"
          >
            <Plus size={16} />
            Buat Pengajuan Baru
          </button>
        )}
      </div>

      <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm text-slate-600">
            <thead className="bg-slate-50 text-slate-500 font-medium border-b border-slate-200">
              <tr>
                <th className="px-4 py-3">Tanggal Pengajuan</th>
                <th className="px-4 py-3">Karyawan</th>
                <th className="px-4 py-3">Tipe</th>
                <th className="px-4 py-3">Jabatan Tujuan</th>
                <th className="px-4 py-3">Efektif Sejak</th>
                <th className="px-4 py-3">Keterangan</th>
                <th className="px-4 py-3 text-center">Status</th>
                <th className="px-4 py-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {loading ? (
                <tr><td colSpan="8" className="text-center py-8">Memuat data...</td></tr>
              ) : requests.length === 0 ? (
                <tr><td colSpan="8" className="text-center py-8 text-slate-500">Tidak ada pengajuan promosi atau demosi.</td></tr>
              ) : (
                requests.map(req => (
                  <tr 
                    key={req.id} 
                    className="hover:bg-indigo-50 cursor-pointer transition-colors"
                    onClick={() => setSelectedRequestId(req.id)}
                  >
                    <td className="px-4 py-3">{new Date(req.created_at).toLocaleDateString("id-ID")}</td>
                    <td className="px-4 py-3 font-medium text-slate-900">
                      {req.employee?.name}
                      <div className="text-xs text-slate-500">{req.employee?.employee_code}</div>
                    </td>
                    <td className="px-4 py-3">
                      <span className={`px-2 py-1 rounded text-xs font-semibold ${req.mutation_type === 'promotion' ? 'bg-indigo-50 text-indigo-700' : 'bg-orange-50 text-orange-700'}`}>
                        {req.mutation_type === 'promotion' ? 'Promosi' : 'Demosi'}
                      </span>
                    </td>
                    <td className="px-4 py-3">{req.target_position?.name}</td>
                    <td className="px-4 py-3">{new Date(req.effective_date).toLocaleDateString("id-ID")}</td>
                    <td className="px-4 py-3">
                      <div className="text-xs text-slate-600 truncate max-w-[150px]" title={req.reason || '-'}>
                        {req.reason || '-'}
                      </div>
                    </td>
                    <td className="px-4 py-3 text-center">
                      <span className={`px-2 py-1 rounded text-xs font-semibold ${
                        req.status === 'approved' ? 'bg-emerald-50 text-emerald-700' : 
                        req.status === 'rejected' ? 'bg-rose-50 text-rose-700' : 
                        req.status === 'cancelled' ? 'bg-slate-100 text-slate-700' : 
                        'bg-amber-50 text-amber-700'
                      }`}>
                        {req.status === 'approved' ? 'Disetujui' : 
                         req.status === 'rejected' ? 'Ditolak' : 
                         req.status === 'cancelled' ? 'Dibatalkan' : 'Menunggu'}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-right">
                      <button className="text-xs font-semibold text-indigo-600 hover:text-indigo-800 bg-indigo-50 px-2 py-1.5 rounded transition-colors border border-indigo-100">
                        Lihat Detail
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
      
      <MutationRequestModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        onSuccess={() => {
          setIsModalOpen(false);
          fetchRequests();
        }}
      />

      <MutationDetailModal
        isOpen={!!selectedRequestId}
        onClose={() => setSelectedRequestId(null)}
        requestId={selectedRequestId}
        userRole={user?.role}
        onSuccess={() => {
          setSelectedRequestId(null);
          fetchRequests();
        }}
      />
    </div>
  );
}
