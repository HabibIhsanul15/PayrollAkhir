import { useState, useEffect } from "react";
import { api } from "@/lib/api";

export default function EmployeeMutationModal({ isOpen, onClose, employee, onSuccess }) {
  const [grades, setGrades] = useState([]);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [err, setErr] = useState("");

  const [form, setForm] = useState({
    mutation_type: "promotion",
    grade_id: "",
    position_allowance: "",
    mandays_rate: "",
    effective_from: "",
    notes: "",
  });

  useEffect(() => {
    if (isOpen) {
      loadGrades();
      
      // Default to 1st of next month for effective date
      const nextMonth = new Date();
      nextMonth.setMonth(nextMonth.getMonth() + 1);
      nextMonth.setDate(1);
      
      setForm({
        mutation_type: "promotion",
        grade_id: "",
        position_allowance: "",
        mandays_rate: "",
        effective_from: nextMonth.toISOString().split("T")[0],
        notes: "",
      });
      setErr("");
    }
  }, [isOpen]);

  const loadGrades = async () => {
    setLoading(true);
    try {
      const res = await api("/master/grades");
      setGrades(Array.isArray(res) ? res : []);
    } catch (e) {
      setErr("Gagal memuat data master jabatan.");
    } finally {
      setLoading(false);
    }
  };

  const handleGradeChange = (gid) => {
    const g = grades.find((gr) => String(gr.id) === String(gid));
    if (g) {
      const posRate = g.allowance_rates?.find(r => r.allowance_type?.code === 'position');
      setForm((prev) => ({ 
        ...prev, 
        grade_id: gid,
        position_allowance: posRate ? posRate.rate_amount : 0,
        mandays_rate: g.default_mandays_rate || 0
      }));
    } else {
      setForm((prev) => ({ ...prev, grade_id: gid, position_allowance: "", mandays_rate: "" }));
    }
  };

  const submit = async (e) => {
    e.preventDefault();
    setErr("");
    setSaving(true);
    
    if (!form.grade_id) {
      setErr("Jabatan (Grade) baru harus dipilih.");
      setSaving(false);
      return;
    }
    
    try {
      await api(`/employees/${employee.id}/mutate`, {
        method: "POST",
        body: {
          mutation_type: form.mutation_type,
          grade_id: parseInt(form.grade_id),
          position_allowance: parseFloat(form.position_allowance) || 0,
          mandays_rate: parseFloat(form.mandays_rate) || 0,
          effective_from: form.effective_from,
          notes: form.notes,
        },
      });
      onSuccess();
    } catch (e) {
      setErr(e.message || "Gagal melakukan mutasi.");
    } finally {
      setSaving(false);
    }
  };

  if (!isOpen || !employee) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
      <div className="w-full max-w-2xl bg-white rounded-2xl shadow-xl overflow-hidden flex flex-col max-h-[90vh]">
        <div className="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
          <div>
            <h3 className="text-lg font-semibold text-slate-900">Promosi / Demosi Karyawan</h3>
            <p className="text-sm text-slate-500 mt-1">{employee.name} - {employee.employee_code}</p>
          </div>
          <button 
            onClick={onClose}
            className="text-slate-400 hover:text-slate-600 transition-colors"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
          </button>
        </div>

        <div className="p-6 overflow-y-auto">
          {err && (
            <div className="mb-6 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-600 border border-rose-200">
              {err}
            </div>
          )}

          <form id="mutation-form" onSubmit={submit} className="space-y-6">
            
            {/* Jabatan Saat Ini */}
            <div className="bg-slate-50 p-4 rounded-xl border border-slate-200 mb-6">
              <h4 className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Informasi Saat Ini</h4>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <div className="text-[11px] text-slate-500 mb-1">Jabatan Sekarang</div>
                  <div className="text-sm font-semibold text-slate-900">{employee.grade?.name || '-'}</div>
                </div>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-slate-700">Jenis Mutasi</label>
                <select
                  value={form.mutation_type}
                  onChange={(e) => setForm(p => ({ ...p, mutation_type: e.target.value }))}
                  className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-indigo-300 focus:ring-4 focus:ring-indigo-200/40"
                >
                  <option value="promotion">Promosi (Naik Jabatan)</option>
                  <option value="demotion">Demosi (Turun Jabatan)</option>
                  <option value="mutation">Mutasi (Pindah Posisi / Rotasi)</option>
                </select>
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-slate-700">Tanggal Efektif</label>
                <input
                  type="date"
                  required
                  value={form.effective_from}
                  onChange={(e) => setForm(p => ({ ...p, effective_from: e.target.value }))}
                  className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-indigo-300 focus:ring-4 focus:ring-indigo-200/40"
                />
                <p className="text-[10px] text-slate-500">* Dianjurkan tanggal 1 pada bulan baru untuk perhitungan gaji yang akurat.</p>
              </div>
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-slate-700">Jabatan Baru</label>
              <select
                value={form.grade_id}
                onChange={(e) => handleGradeChange(e.target.value)}
                className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-indigo-300 focus:ring-4 focus:ring-indigo-200/40"
              >
                <option value="">-- Pilih Jabatan Baru --</option>
                {grades.map((g) => (
                  <option key={g.id} value={g.id}>
                    {g.name} ({g.code.toUpperCase()})
                  </option>
                ))}
              </select>
            </div>

            {form.grade_id && (
              <div className="bg-indigo-50/50 p-4 rounded-xl border border-indigo-100">
                <h4 className="text-xs font-bold text-indigo-700 uppercase tracking-wider mb-4">Penyesuaian Gaji</h4>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="text-[11px] font-medium text-slate-500 uppercase tracking-wider mb-1.5 block">Tunjangan Jabatan Baru</label>
                    <div className="relative">
                      <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-500 font-medium">Rp</span>
                      <input 
                        type="number" 
                        min="0" 
                        value={form.position_allowance} 
                        onChange={(e) => setForm(p => ({ ...p, position_allowance: e.target.value }))}
                        className="w-full rounded-xl border border-slate-200 bg-white pl-10 pr-3 py-2 text-sm font-semibold text-slate-900 outline-none transition focus:border-indigo-300 focus:ring-4 focus:ring-indigo-200/40"
                      />
                    </div>
                  </div>
                  <div>
                    <label className="text-[11px] font-medium text-slate-500 uppercase tracking-wider mb-1.5 block">Gaji Harian (Mandays) Baru</label>
                    <div className="relative">
                      <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-500 font-medium">Rp</span>
                      <input 
                        type="number" 
                        min="0" 
                        value={form.mandays_rate} 
                        onChange={(e) => setForm(p => ({ ...p, mandays_rate: e.target.value }))}
                        className="w-full rounded-xl border border-slate-200 bg-white pl-10 pr-3 py-2 text-sm font-semibold text-slate-900 outline-none transition focus:border-indigo-300 focus:ring-4 focus:ring-indigo-200/40"
                      />
                    </div>
                  </div>
                </div>
              </div>
            )}

            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-slate-700">Keterangan / Alasan</label>
              <textarea
                value={form.notes}
                onChange={(e) => setForm(p => ({ ...p, notes: e.target.value }))}
                rows={3}
                placeholder="Contoh: Kenaikan jabatan tahunan / rotasi divisi..."
                className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-indigo-300 focus:ring-4 focus:ring-indigo-200/40 resize-none"
              ></textarea>
            </div>

          </form>
        </div>

        <div className="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-3">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 text-sm font-semibold text-slate-600 hover:text-slate-900 transition-colors"
          >
            Batal
          </button>
          <button
            form="mutation-form"
            type="submit"
            disabled={saving || loading || !form.grade_id}
            className="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-all focus:ring-4 focus:ring-indigo-500/30 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {saving ? "Menyimpan..." : "Simpan Mutasi"}
          </button>
        </div>
      </div>
    </div>
  );
}
