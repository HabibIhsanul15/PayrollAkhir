import { useEffect, useMemo, useState } from "react";
import { api } from "@/lib/api";
import { X } from "lucide-react";

const parseLocalDate = (value) => {
  if (value instanceof Date) {
    return Number.isNaN(value.getTime()) ? null : value;
  }

  const datePart = String(value || "").slice(0, 10);
  if (!/^\d{4}-\d{2}-\d{2}$/.test(datePart)) return null;
  return new Date(`${datePart}T00:00:00`);
};

const formatDate = (value) => {
  const date = parseLocalDate(value);
  return date && !Number.isNaN(date.getTime())
    ? date.toLocaleDateString("id-ID", { day: "numeric", month: "long", year: "numeric" })
    : "-";
};

const emptyForm = () => ({
  employee_id: "",
  mutation_type: "promotion",
  position_id: "",
  period_month: "",
  notes: "",
  document: null,
});

const getEditPeriodMonth = (editData) => {
  if (!editData) return "";
  if (editData.payroll_period?.period_month) return editData.payroll_period.period_month;
  if (editData.period_month) return editData.period_month;

  const effectiveDate = parseLocalDate(editData.effective_date);
  return effectiveDate
    ? `${effectiveDate.getFullYear()}-${String(effectiveDate.getMonth() + 1).padStart(2, "0")}`
    : "";
};

const formFromEdit = (editData) => ({
  employee_id: editData?.employee_id ? String(editData.employee_id) : "",
  mutation_type: editData?.mutation_type || "promotion",
  position_id: editData?.target_position_id ? String(editData.target_position_id) : "",
  period_month: getEditPeriodMonth(editData),
  notes: editData?.reason || "",
  document: null,
});

export default function MutationRequestModal({ isOpen, onClose, onSuccess, editData }) {
  const [employees, setEmployees] = useState([]);
  const [positions, setPositions] = useState([]);
  const [periods, setPeriods] = useState([]);
  const [mutationRequests, setMutationRequests] = useState([]);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [err, setErr] = useState("");

  const [form, setForm] = useState(() => formFromEdit(editData));

  useEffect(() => {
    let active = true;
    if (isOpen) {
      setForm(formFromEdit(editData));
      setErr("");

      const fetchData = async () => {
        setLoading(true);
        try {
          if (editData) {
            // Saat edit, data karyawan dan pengajuan sudah tersedia dari detail.
            // Cukup ambil master jabatan dan periode yang diperlukan oleh form.
            const [positionsRes, periodsRes] = await Promise.all([
              api("/master/positions?active_only=1"),
              api("/payroll-periods"),
            ]);

            if (!active) return;

            setPositions(Array.isArray(positionsRes) ? positionsRes : []);
            const periodList = Array.isArray(periodsRes) ? periodsRes : (periodsRes?.data || []);
            setPeriods(periodList);
            return;
          }

          const [employeesRes, positionsRes, periodsRes, mutationRequestsRes] = await Promise.all([
            api("/employees?status=active"),
            api("/master/positions?active_only=1"),
            api("/payroll-periods"),
            api("/mutation-requests"),
          ]);

          if (active) {
            setEmployees(employeesRes?.data || (Array.isArray(employeesRes) ? employeesRes : []));
            setPositions(Array.isArray(positionsRes) ? positionsRes : []);
            const periodList = Array.isArray(periodsRes) ? periodsRes : (periodsRes?.data || []);
            setPeriods(periodList);
            setMutationRequests(Array.isArray(mutationRequestsRes) ? mutationRequestsRes : (mutationRequestsRes?.data || []));

            const today = new Date();
            const currentPeriod = periodList.find((period) => {
              const start = parseLocalDate(period.start_date);
              const end = parseLocalDate(period.end_date);
              return start && end && today >= start && today <= end;
            });
            const firstAvailablePeriod = currentPeriod || periodList.find((period) => {
              const end = parseLocalDate(period.end_date);
              return end && end >= today;
            });

            setForm({
              ...emptyForm(),
              period_month: firstAvailablePeriod?.period_month || "",
            });
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
  }, [isOpen, editData]);

  useEffect(() => {
    if (!editData) {
      setForm((current) => ({ ...current, position_id: "" }));
    }
  }, [form.mutation_type, form.employee_id, editData]);

  const selectedEmployee = useMemo(() => {
    if (!form.employee_id) return null;
    return employees.find(e => e.id.toString() === form.employee_id.toString())
      || (editData && Number(editData.employee_id) === Number(form.employee_id) ? editData.employee : null);
  }, [form.employee_id, employees, editData]);

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
    
    try {

      const formData = new FormData();
      formData.append("employee_id", form.employee_id);
      formData.append("mutation_type", form.mutation_type);
      formData.append("position_id", form.position_id);
      if (!form.period_month) {
        setErr("Bulan payroll harus dipilih.");
        setSaving(false);
        return;
      }
      formData.append("period_month", form.period_month);
      if (form.notes) formData.append("reason", form.notes);
      if (form.document) formData.append("document", form.document);

      const url = editData ? `/mutation-requests/${editData.id}` : "/mutation-requests";
      
      if (editData) {
        formData.append("_method", "PUT");
      }

      await api(url, {
        method: "POST",
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

  const activeMutation = useMemo(() => {
    if (!form.employee_id) return null;

    return mutationRequests.find((item) => {
      if (Number(item.employee_id) !== Number(form.employee_id)) return false;
      if (editData && Number(item.id) === Number(editData.id)) return false;
      if (item.status === "pending") return true;
      return item.status === "approved" && parseLocalDate(item.effective_date) > new Date();
    }) || null;
  }, [mutationRequests, form.employee_id, editData]);

  const selectedPeriod = useMemo(
    () => periods.find((period) => period.period_month === form.period_month) || null,
    [form.period_month, periods]
  );

  const calculatedEffectiveDate = useMemo(() => {
    return selectedPeriod ? parseLocalDate(selectedPeriod.start_date) : null;
  }, [selectedPeriod]);

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-sm">
      <div className="w-full max-w-2xl bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]">
        <div className="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
          <div>
            <h3 className="text-lg font-semibold text-slate-900">{editData ? "Edit Pengajuan Promosi/Demosi" : "Buat Pengajuan Promosi/Demosi"}</h3>
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

                {activeMutation && (
                  <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-xs font-medium text-amber-800">
                    {activeMutation.status === "pending"
                      ? "Pengajuan promosi/demosi masih aktif."
                      : `Promosi/demosi aktif sampai ${formatDate(activeMutation.effective_date)}.`}
                  </div>
                )}

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
                  </div>

                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-slate-700">Bulan Payroll</label>
                    <select
                      value={form.period_month}
                      onChange={(e) => setForm((current) => ({ ...current, period_month: e.target.value }))}
                      className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-indigo-300 focus:ring-4 focus:ring-indigo-200/40"
                    >
                      <option value="">-- Pilih Bulan Payroll --</option>
                      {periods.map((period) => (
                        <option key={period.id} value={period.period_month}>
                          {period.period_month}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>

                <div className="grid grid-cols-3 gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                  <div>
                    <div className="text-[10px] uppercase text-slate-500">Bulan Payroll</div>
                    <div className="mt-1 text-sm font-semibold text-slate-900">{selectedPeriod?.period_month || "-"}</div>
                  </div>
                  <div>
                    <div className="text-[10px] uppercase text-slate-500">Periode Gaji</div>
                    <div className="mt-1 text-sm font-semibold text-slate-900">
                      {selectedPeriod ? `${formatDate(selectedPeriod.start_date)} - ${formatDate(selectedPeriod.end_date)}` : "-"}
                    </div>
                  </div>
                  <div>
                    <div className="text-[10px] uppercase text-slate-500">Efektif Sejak</div>
                    <div className="mt-1 text-sm font-semibold text-slate-900">
                      {calculatedEffectiveDate ? formatDate(calculatedEffectiveDate) : "-"}
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
                  <p className="text-[10px] text-slate-500">Maks. 2MB (PDF, JPG, PNG). Surat/Memo Persetujuan Promosi/Demosi.</p>
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
            disabled={saving || loading || !form.employee_id || !form.position_id || !hascurrentPositionLevel || !!activeMutation}
            className="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-all focus:ring-4 focus:ring-indigo-500/30 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {saving ? "Mengajukan..." : editData ? "Simpan Perubahan" : `Ajukan ${mutationLabel}`}
          </button>
        </div>
      </div>
    </div>
  );
}
