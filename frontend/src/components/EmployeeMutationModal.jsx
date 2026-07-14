import { useEffect, useMemo, useState } from "react";
import { api } from "@/lib/api";
import { X } from "lucide-react";

export default function EmployeeMutationModal({ isOpen, onClose, employee, onSuccess }) {
  const [grades, setGrades] = useState([]);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [err, setErr] = useState("");

  const [form, setForm] = useState({
    mutation_type: "promotion",
    grade_id: "",
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
        effective_from: nextMonth.toISOString().split("T")[0],
        notes: "",
      });
      setErr("");
    }
  }, [isOpen]);

  useEffect(() => {
    setForm((current) => ({ ...current, grade_id: "" }));
  }, [form.mutation_type]);

  const loadGrades = async () => {
    setLoading(true);
    try {
      const res = await api("/master/grades?active_only=1");
      setGrades(Array.isArray(res) ? res : []);
    } catch {
      setErr("Gagal memuat data master jabatan.");
    } finally {
      setLoading(false);
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

  const currentGradeId = Number(employee?.grade?.id ?? employee?.grade_id ?? 0);
  const currentGradeLevel = Number(employee?.grade?.level ?? NaN);
  const hasCurrentGradeLevel = Number.isFinite(currentGradeLevel) && currentGradeLevel > 0;
  const mutationLabel = form.mutation_type === "promotion" ? "Promosi" : "Demosi";

  const eligibleGrades = useMemo(() => {
    if (!hasCurrentGradeLevel) {
      return [];
    }

    return grades.filter((grade) => {
      const targetId = Number(grade?.id ?? 0);
      const targetLevel = Number(grade?.level ?? NaN);

      if (!targetId || targetId === currentGradeId || !Number.isFinite(targetLevel)) {
        return false;
      }

      return form.mutation_type === "promotion"
        ? targetLevel < currentGradeLevel
        : targetLevel > currentGradeLevel;
    });
  }, [grades, form.mutation_type, hasCurrentGradeLevel, currentGradeId, currentGradeLevel]);

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
            <X size={20} />
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
                <div>
                  <div className="text-[11px] text-slate-500 mb-1">Level Sekarang</div>
                  <div className="text-sm font-semibold text-slate-900">
                    {hasCurrentGradeLevel ? `Level ${currentGradeLevel}` : "-"}
                  </div>
                </div>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-slate-700">Jenis Perubahan Jabatan</label>
                <select
                  value={form.mutation_type}
                  onChange={(e) => setForm(p => ({ ...p, mutation_type: e.target.value }))}
                  className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-indigo-300 focus:ring-4 focus:ring-indigo-200/40"
                >
                  <option value="promotion">Promosi (Naik Jabatan)</option>
                  <option value="demotion">Demosi (Turun Jabatan)</option>
                </select>
                <p className="text-[10px] text-slate-500">
                  {form.mutation_type === "promotion"
                    ? "Promosi hanya menampilkan jabatan dengan level lebih tinggi. Karena level 1 paling tinggi, target promosi memakai angka level yang lebih kecil."
                    : "Demosi hanya menampilkan jabatan dengan level di bawah posisi saat ini, yaitu angka level yang lebih besar."}
                </p>
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
              <label className="text-xs font-semibold text-slate-700">Jabatan Tujuan {mutationLabel}</label>
              <select
                value={form.grade_id}
                onChange={(e) => setForm((current) => ({ ...current, grade_id: e.target.value }))}
                disabled={loading || !hasCurrentGradeLevel || eligibleGrades.length === 0}
                className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-indigo-300 focus:ring-4 focus:ring-indigo-200/40"
              >
                <option value="">-- Pilih Jabatan Tujuan --</option>
                {eligibleGrades.map((g) => (
                  <option key={g.id} value={g.id}>
                    {g.name} ({g.code.toUpperCase()}) - Level {g.level}
                  </option>
                ))}
              </select>
              {!hasCurrentGradeLevel && (
                <p className="text-[10px] text-rose-600">
                  Level jabatan saat ini belum tersedia, jadi sistem belum bisa menentukan opsi promosi atau demosi.
                </p>
              )}
              {hasCurrentGradeLevel && !loading && eligibleGrades.length === 0 && (
                <p className="text-[10px] text-slate-500">
                  Tidak ada jabatan aktif yang cocok untuk {mutationLabel.toLowerCase()} dari level saat ini.
                </p>
              )}
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-slate-700">Keterangan / Alasan</label>
              <textarea
                value={form.notes}
                onChange={(e) => setForm(p => ({ ...p, notes: e.target.value }))}
                rows={3}
                placeholder="Contoh: Kenaikan jabatan tahunan / evaluasi kinerja semester..."
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
            disabled={saving || loading || !form.grade_id || !hasCurrentGradeLevel}
            className="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-all focus:ring-4 focus:ring-indigo-500/30 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {saving ? "Menyimpan..." : `Simpan ${mutationLabel}`}
          </button>
        </div>
      </div>
    </div>
  );
}
