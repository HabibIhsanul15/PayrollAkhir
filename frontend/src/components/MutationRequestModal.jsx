import { useEffect, useMemo, useState } from "react";
import { api } from "@/lib/api";
import { X } from "lucide-react";

export default function MutationRequestModal({ isOpen, onClose, onSuccess, editData }) {
  const [employees, setEmployees] = useState([]);
  const [positions, setPositions] = useState([]);
  const [periods, setPeriods] = useState([]);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [err, setErr] = useState("");

  const [form, setForm] = useState({
    employee_id: "",
    mutation_type: "promotion",
    position_id: "",
    period_choice: "current",
    notes: "",
    document: null,
  });

  useEffect(() => {
    let active = true;
    if (isOpen) {
      const fetchData = async () => {
        setLoading(true);
        try {
          const [employeesRes, positionsRes, periodsRes] = await Promise.all([
            api("/employees?status=active"),
            api("/master/positions?active_only=1"),
            api("/payroll-periods")
          ]);
          
          if (active) {
            // Check if response has data property (paginated) or is array
            setEmployees(employeesRes?.data || (Array.isArray(employeesRes) ? employeesRes : []));
            setPositions(Array.isArray(positionsRes) ? positionsRes : []);
            setPeriods(Array.isArray(periodsRes) ? periodsRes : (periodsRes?.data || []));
            
            if (editData) {
              const eDate = new Date(editData.effective_date);
              const now = new Date();
              const isNextMonth = eDate.getMonth() !== now.getMonth();
              
              setForm({
                employee_id: editData.employee_id.toString(),
                mutation_type: editData.mutation_type,
                position_id: editData.target_position_id.toString(),
                period_choice: isNextMonth ? "next" : "current",
                notes: editData.reason || "",
                document: null,
              });
            } else {
              setForm({
                employee_id: "",
                mutation_type: "promotion",
                position_id: "",
                period_choice: "current",
                notes: "",
                document: null,
              });
            }
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
    if (!editData) {
      setForm((current) => ({ ...current, position_id: "" }));
    }
  }, [form.mutation_type, form.employee_id, editData]);

  const selectedEmployee = useMemo(() => {
    if (!form.employee_id) return null;
    return employees.find(e => e.id.toString() === form.employee_id.toString());
  }, [form.employee_id, employees]);

  const submit = async (e) => {
    e.preventDefault();
    setErr("");
    setSaving(true);
    
    if (!form.employee_id) {
      setErr("Karyawan harus dipilih.");
      setSaving(false);
      return;
    }
    
    if (!form.position_id) {
      setErr("Jabatan (position) baru harus dipilih.");
      setSaving(false);
      return;
    }
    
    // Hitung tanggal efektif yang dikirim (sama dengan yg ditampilkan ke user)
    const effectiveDate = calculatedEffectiveDate;

    try {

      const formData = new FormData();
      formData.append("employee_id", form.employee_id);
      formData.append("mutation_type", form.mutation_type);
      formData.append("position_id", form.position_id);
      formData.append("effective_from", effectiveDate.toISOString().split("T")[0]);
      if (form.notes) formData.append("reason", form.notes);
      if (form.document) formData.append("document", form.document);

      const url = editData ? `/mutation-requests/${editData.id}` : "/mutation-requests";
      const method = editData ? "PUT" : "POST";
      
      // FormData doesn't natively support PUT easily with files in some frameworks without _method
      if (editData) {
        formData.append("_method", "PUT");
      }

      const res = await api(url, {
        method: "POST", // we use POST with _method=PUT for Laravel file uploads
        body: formData,
      });

      onSuccess();
    } catch (e) {
      setErr(e.message || "Gagal mengajukan promosi/demosi.");
    } finally {
      setSaving(false);
    }
  };

  const currentPositionId = Number(selectedEmployee?.position?.id ?? selectedEmployee?.position_id ?? 0);
  const currentPositionLevel = Number(selectedEmployee?.position?.level ?? NaN);
  const hascurrentPositionLevel = selectedEmployee && Number.isFinite(currentPositionLevel) && currentPositionLevel > 0;
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

  // Hitung tanggal efektif untuk ditampilkan
  const calculatedEffectiveDate = useMemo(() => {
    const today = new Date();
    const targetDate = new Date(today);
    if (form.period_choice === "next") {
      targetDate.setMonth(targetDate.getMonth() + 1);
    }

    // Cari periode yang aktif untuk bulan tsb jika ada
    if (periods.length > 0) {
      // Sort periode dari yang terbaru (start_date descending)
      const sortedPeriods = [...periods].sort((a, b) => new Date(b.start_date) - new Date(a.start_date));
      // Cari periode yang mengcover targetDate
      const matched = sortedPeriods.find(p => {
        const start = new Date(p.start_date);
        const end = new Date(p.end_date);
        return targetDate >= start && targetDate <= end;
      });
      if (matched) {
        return new Date(matched.start_date);
      }
    }
    
    // Fallback: awal bulan dari targetDate
    return new Date(targetDate.getFullYear(), targetDate.getMonth(), 1);
  }, [form.period_choice, periods]);

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
      <div className="w-full max-w-2xl bg-white rounded-2xl shadow-xl overflow-hidden flex flex-col max-h-[90vh]">
        <div className="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
          <div>
            <h3 className="text-lg font-semibold text-slate-900">{editData ? "Edit Pengajuan Mutasi" : "Buat Pengajuan Mutasi"}</h3>
            <p className="text-sm text-slate-500 mt-1">Pilih karyawan yang ingin diajukan perubahan jabatannya.</p>
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
            
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-slate-700">Pilih Karyawan</label>
              <select
                value={form.employee_id}
                onChange={(e) => setForm((current) => ({ ...current, employee_id: e.target.value }))}
                disabled={loading || !!editData}
                className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-indigo-300 focus:ring-4 focus:ring-indigo-200/40"
              >
                <option value="">-- Pilih Karyawan Aktif --</option>
                {employees.map((emp) => (
                  <option key={emp.id} value={emp.id}>
                    {emp.name} ({emp.employee_code})
                  </option>
                ))}
              </select>
            </div>

            {selectedEmployee && (
              <>
                <div className="bg-slate-50 p-4 rounded-xl border border-slate-200">
                  <h4 className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Informasi Saat Ini</h4>
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <div className="text-[11px] text-slate-500 mb-1">Jabatan Sekarang</div>
                      <div className="text-sm font-semibold text-slate-900">{selectedEmployee.position?.name || '-'}</div>
                    </div>
                    <div>
                      <div className="text-[11px] text-slate-500 mb-1">Level Sekarang</div>
                      <div className="text-sm font-semibold text-slate-900">
                        {hascurrentPositionLevel ? `Level ${currentPositionLevel}` : '-'}
                      </div>
                    </div>
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-6">
                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-slate-700">Jenis Perubahan Jabatan</label>
                    <select
                      value={form.mutation_type}
                      onChange={(e) => setForm((current) => ({ ...current, mutation_type: e.target.value }))}
                      className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-indigo-300 focus:ring-4 focus:ring-indigo-200/40"
                    >
                      <option value="promotion">Promosi (Naik Jabatan)</option>
                      <option value="demotion">Demosi (Turun Jabatan)</option>
                    </select>
                    <p className="text-[10px] text-slate-500">
                      {form.mutation_type === "promotion" 
                        ? "Promosi hanya menampilkan jabatan dengan level lebih tinggi. Karena level 1 paling tinggi, target promosi memakai angka level yang lebih kecil."
                        : "Demosi hanya menampilkan jabatan dengan level lebih rendah (angka level lebih besar)."
                      }
                    </p>
                  </div>

                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-slate-700">Pilih Opsi Keberlakuan</label>
                    <select
                      value={form.period_choice}
                      onChange={(e) => setForm((current) => ({ ...current, period_choice: e.target.value }))}
                      className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-indigo-300 focus:ring-4 focus:ring-indigo-200/40"
                    >
                      <option value="current">Mulai Periode Berjalan (Gaji Bulan Ini)</option>
                      <option value="next">Mulai Bulan Depan</option>
                    </select>
                    <div className="bg-emerald-50 border border-emerald-100 rounded-lg p-2 mt-2">
                      <p className="text-[11px] text-emerald-800 font-medium flex justify-between items-center">
                        <span>Akan berlaku mulai:</span>
                        <span className="font-bold bg-emerald-200/50 px-2 py-0.5 rounded text-emerald-900">
                          {calculatedEffectiveDate.toLocaleDateString('id-ID', {day: 'numeric', month: 'long', year: 'numeric'})}
                        </span>
                      </p>
                      <p className="text-[9px] text-emerald-600 mt-1 italic">
                        * Mengikuti tanggal mulai (cutoff) periode penggajian
                      </p>
                    </div>
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
                        {g.name} ({g.code?.toUpperCase()}) - Level {g.level}
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

                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-slate-700">Lampiran Dokumen (Opsional)</label>
                  <input
                    type="file"
                    accept=".pdf,.jpg,.jpeg,.png"
                    onChange={(e) => setForm(p => ({ ...p, document: e.target.files[0] }))}
                    className="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                  />
                  <p className="text-[10px] text-slate-500">Maks. 2MB (PDF, JPG, PNG). Surat/Memo Persetujuan Mutasi.</p>
                </div>
              </>
            )}

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
            disabled={saving || loading || !form.employee_id || !form.position_id || !hascurrentPositionLevel}
            className="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-all focus:ring-4 focus:ring-indigo-500/30 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {saving ? "Mengajukan..." : editData ? "Simpan Perubahan" : `Ajukan ${mutationLabel}`}
          </button>
        </div>
      </div>
    </div>
  );
}
