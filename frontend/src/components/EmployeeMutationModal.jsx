import { useEffect, useMemo, useState } from "react";
import { api } from "@/lib/api";
import { X } from "lucide-react";

export default function EmployeeMutationModal({ isOpen, onClose, employee, onSuccess }) {
  const [positions, setPositions] = useState([]);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [err, setErr] = useState("");

  const [form, setForm] = useState({
    mutation_type: "promotion",
    position_id: "",
    period_choice: "current",
    notes: "",
  });

  useEffect(() => {
    let active = true;
    if (isOpen) {
      const fetchData = async () => {
        setLoading(true);
        try {
          const positionsRes = await api("/master/positions?active_only=1");
          if (active) {
            setPositions(Array.isArray(positionsRes) ? positionsRes : []);
            
            setForm({
              mutation_type: "promotion",
              position_id: "",
              period_choice: "current",
              notes: "",
            });
            setErr("");
          }
        } catch {
          if (active) setErr("Gagal memuat data master.");
        } finally {
          if (active) setLoading(false);
        }
      };
      fetchData();
    }
    return () => { active = false; };
  }, [isOpen]);

  useEffect(() => {
    setForm((current) => ({ ...current, position_id: "" }));
  }, [form.mutation_type]);

  const submit = async (e) => {
    e.preventDefault();
    setErr("");
    setSaving(true);
    
    if (!form.position_id) {
      setErr("Jabatan (position) baru harus dipilih.");
      setSaving(false);
      return;
    }
    
    let effectiveDate = new Date();
    if (form.period_choice === "next") {
      effectiveDate.setDate(effectiveDate.getDate() + 30);
    }

    try {
      await api(`/employees/${employee.id}/mutate`, {
        method: "POST",
        body: {
          mutation_type: form.mutation_type,
          position_id: parseInt(form.position_id),
          effective_from: effectiveDate.toISOString().split("T")[0],
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

  const currentPositionId = Number(employee?.position?.id ?? employee?.position_id ?? 0);
  const currentPositionLevel = Number(employee?.position?.level ?? NaN);
  const hascurrentPositionLevel = Number.isFinite(currentPositionLevel) && currentPositionLevel > 0;
  const mutationLabel = form.mutation_type === "promotion" ? "Promosi" : "Demosi";

  const eligiblePositions = useMemo(() => {
    if (!hascurrentPositionLevel) {
      return [];
    }

    return positions.filter((position) => {
      const targetId = Number(position?.id ?? 0);
      const targetLevel = Number(position?.level ?? NaN);

      if (!targetId || targetId === currentPositionId || !Number.isFinite(targetLevel)) {
        return false;
      }

      return form.mutation_type === "promotion"
        ? targetLevel < currentPositionLevel
        : targetLevel > currentPositionLevel;
    });
  }, [positions, form.mutation_type, hascurrentPositionLevel, currentPositionId, currentPositionLevel]);

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
                  <div className="text-sm font-semibold text-slate-900">{employee.position?.name || '-'}</div>
                </div>
                <div>
                  <div className="text-[11px] text-slate-500 mb-1">Level Sekarang</div>
                  <div className="text-sm font-semibold text-slate-900">
                    {hascurrentPositionLevel ? `Level ${currentPositionLevel}` : "-"}
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
                <label className="text-xs font-semibold text-slate-700">Pilih Opsi Keberlakuan</label>
                <select
                  required
                  value={form.period_choice}
                  onChange={(e) => setForm(p => ({ ...p, period_choice: e.target.value }))}
                  className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-indigo-300 focus:ring-4 focus:ring-indigo-200/40"
                >
                  <option value="current">Mulai Periode Berjalan (Gaji Bulan Ini)</option>
                  <option value="next">Mulai Periode Selanjutnya (Gaji Bulan Depan)</option>
                </select>
                <p className="text-[10px] text-emerald-600 mt-1 font-medium bg-emerald-50 p-1.5 rounded border border-emerald-100">
                  * Otomatis mengunci efektifitas ke awal Cutoff periode yang dipilih.
                </p>
              </div>
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-slate-700">Jabatan Tujuan {mutationLabel}</label>
              <select
                value={form.position_id}
                onChange={(e) => setForm((current) => ({ ...current, position_id: e.target.value }))}
                disabled={loading || !hascurrentPositionLevel || eligiblePositions.length === 0}
                className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-indigo-300 focus:ring-4 focus:ring-indigo-200/40"
              >
                <option value="">-- Pilih Jabatan Tujuan --</option>
                {eligiblePositions.map((g) => (
                  <option key={g.id} value={g.id}>
                    {g.name} ({g.code.toUpperCase()}) - Level {g.level}
                  </option>
                ))}
              </select>
              {!hascurrentPositionLevel && (
                <p className="text-[10px] text-rose-600">
                  Level jabatan saat ini belum tersedia, jadi sistem belum bisa menentukan opsi promosi atau demosi.
                </p>
              )}
              {hascurrentPositionLevel && !loading && eligiblePositions.length === 0 && (
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
            disabled={saving || loading || !form.position_id || !hascurrentPositionLevel}
            className="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-all focus:ring-4 focus:ring-indigo-500/30 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {saving ? "Menyimpan..." : `Simpan ${mutationLabel}`}
          </button>
        </div>
      </div>
    </div>
  );
}
