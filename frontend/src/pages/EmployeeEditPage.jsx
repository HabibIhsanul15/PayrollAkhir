import { useEffect, useState } from "react";
import useSWR from "swr";
import { useNavigate, useParams } from "react-router-dom";
import { api } from "@/lib/api";
import { getUser, updateAuthUser } from "@/lib/auth";
import { Button } from "@/components/ui/button";
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";
import AlertMessage from "@/components/AlertMessage";

export default function EmployeeEditPage() {
  const { id } = useParams();
  const nav = useNavigate();

  const user = getUser();
  const role = String(user?.role || "").toLowerCase();
  const canManage = role === "hcga";

  const [err, setErr] = useState("");

  const [saving, setSaving] = useState(false);

  const [form, setForm] = useState({
    employee_code: "",
    name: "",
    department: "",
    position: "",
    status: "active",

    // Phase 1 fields
    grade_id: "",
    employment_type_id: "",
    work_basis_id: "",
    num_toddlers: 0,
    is_trainer: false,
    is_on_probation: false,

    nik: "",
    npwp: "",
    phone: "",
    address: "",

    bank_name: "",
    bank_account_name: "",
    bank_account_number: "",
  });

  const [grades, setGrades] = useState([]);
  const [employmentTypes, setEmploymentTypes] = useState([]);
  const [workBases, setWorkBases] = useState([]);

  // 🔒 Guard
  useEffect(() => {
    if (!canManage) nav("/employees", { replace: true });
  }, [canManage, nav]);

  const { data: swrData, error: swrErr, isLoading } = useSWR(canManage ? `/employees/${id}/edit-data` : null, async () => {
    const [data, gradesList, empTypesList, workBasesList, currentProfile] = await Promise.all([
      api(`/employees/${id}`),
      api("/master/grades"),
      api("/master/employment-types"),
      api("/master/work-bases"),
      api(`/employees/${id}/salary-profile`).catch(() => null)
    ]);
    return { data, gradesList, empTypesList, workBasesList, currentProfile };
  });

  const loading = isLoading;
  const error = swrErr?.message || "";

  useEffect(() => {
    if (swrData) {
      setGrades(Array.isArray(swrData.gradesList) ? swrData.gradesList : []);
      setEmploymentTypes(Array.isArray(swrData.empTypesList) ? swrData.empTypesList : []);
      setWorkBases(Array.isArray(swrData.workBasesList) ? swrData.workBasesList : []);

      const { data, currentProfile } = swrData;
      setForm({
        employee_code: data.employee_code ?? "",
        name: data.name ?? "",
        department: data.department ?? "",
        position: data.position ?? "",
        status: data.status ?? "active",
        grade_id: data.grade_id ?? "",
        position_allowance: currentProfile?.position_allowance ?? "",
        mandays_rate: currentProfile?.mandays_rate ?? "",
        employment_type_id: data.employment_type_id ?? "",
        work_basis_id: data.work_basis_id ?? "",
        num_toddlers: data.num_toddlers ?? 0,
        is_trainer: !!data.is_trainer,
        is_on_probation: !!data.is_on_probation,
        nik: data.nik ?? "",
        npwp: data.npwp ?? "",
        phone: data.phone ?? "",
        address: data.address ?? "",
        bank_name: data.bank_name ?? "",
        bank_account_name: data.bank_account_name ?? "",
        bank_account_number: data.bank_account_number ?? "",
      });
    }
    if (swrErr) setErr(swrErr.message);
  }, [swrData, swrErr]);

  const submit = async (e) => {
    e.preventDefault();
    setErr("");
    setSaving(true);

    try {
      await api(`/employees/${id}`, {
        method: "PUT",
        body: {
          ...form,
          department: form.department || null,
          position: form.position || null,
          grade_id: form.grade_id ? parseInt(form.grade_id) : null,
          position_allowance: form.position_allowance !== "" ? parseFloat(form.position_allowance) : null,
          mandays_rate: form.mandays_rate !== "" ? parseFloat(form.mandays_rate) : null,
          employment_type_id: form.employment_type_id ? parseInt(form.employment_type_id) : null,
          work_basis_id: form.work_basis_id ? parseInt(form.work_basis_id) : null,
          num_toddlers: parseInt(form.num_toddlers) || 0,
          is_trainer: !!form.is_trainer,
          is_on_probation: !!form.is_on_probation,
          nik: form.nik || null,
          npwp: form.npwp || null,
          phone: form.phone || null,
          address: form.address || null,
          bank_name: form.bank_name || null,
          bank_account_name: form.bank_account_name || null,
          bank_account_number: form.bank_account_number || null,
        },
      });

      // Update Salary Profile 
      // If HCGA submits form, we update the current salary profile or create a new one effective today
      try {
        await api(`/employees/${id}/salary-profiles`, {
          method: "POST",
          body: {
            effective_from: new Date().toISOString().split("T")[0],
            grade_id: form.grade_id ? parseInt(form.grade_id) : null,
            position: form.position || null,
            position_allowance: form.position_allowance !== "" ? parseFloat(form.position_allowance) : null,
            mandays_rate: form.mandays_rate !== "" ? parseFloat(form.mandays_rate) : null,
          }
        });
      } catch (err) {
        console.warn("Failed to update salary profile on employee edit:", err);
      }

      // sync header user name if this employee matches currently logged in user
      try {
        const me = await api("/me");
        updateAuthUser({ name: me?.name, role: me?.role });
      } catch {
        updateAuthUser({ name: form.name });
      }

      nav(`/employees/${id}`, { replace: true });
    } catch (e) {
      setErr(e.message);
    } finally {
      setSaving(false);
    }
  };

  const selectedGrade = grades.find((g) => String(g.id) === String(form.grade_id));
  const hasChildcare = selectedGrade?.allowance_rates?.some((r) => r.allowance_type?.code === 'childcare') || false;
  const hasTraining = selectedGrade?.allowance_rates?.some((r) => r.allowance_type?.code === 'training') || false;

  return (
    <div>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <div className="hidden">
              <span className="h-2 w-2 rounded-full bg-sky-500" />
              <span className="text-[10px] font-semibold text-muted-foreground">
                Human Plus Institute
              </span>
            </div>

            <h1 className="mt-4 text-lg font-semibold text-foreground">
              Edit Employee
            </h1>
            <p className="mt-1 text-sm text-slate-600">
              Perbarui data pegawai untuk kebutuhan payroll dan administrasi.
            </p>
          </div>

          <Button
            variant="outline"
            className="rounded bg-white/70 border-slate-200 hover:bg-white"
            onClick={() => nav(-1)}
          >
            Back
          </Button>
        </div>

        <AlertMessage type="error" message={err} className="mb-4 px-3 py-2" />

        {/* Form Card */}
        <Card className="bg-white border border-border rounded shadow-sm">
          <CardHeader>
            <CardTitle className="text-base">Employee Form</CardTitle>
          </CardHeader>

          <CardContent>
            {loading ? (
              <p className="text-xs text-muted-foreground">Loading data...</p>
            ) : (
              <form onSubmit={submit} className="space-y-8">
                {/* BASIC */}
                <section className="space-y-4">
                  <h3 className="text-sm font-bold text-slate-900">Basic Information</h3>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Input
                      label="Employee Code"
                      value={form.employee_code}
                      required
                      readOnly
                      disabled
                      onChange={() => {}}
                    />
                    <Input
                      label="Name"
                      value={form.name}
                      required
                      onChange={(v) => setForm((p) => ({ ...p, name: v }))}
                    />
                    <Input
                      label="Department"
                      value={form.department}
                      onChange={(v) => setForm((p) => ({ ...p, department: v }))}
                    />
                    <Input
                      label="Position"
                      value={form.position}
                      onChange={(v) => setForm((p) => ({ ...p, position: v }))}
                    />
                  </div>

                  <Select
                    label="Status"
                    value={form.status}
                    options={["active", "inactive"]}
                    onChange={(v) => setForm((p) => ({ ...p, status: v }))}
                  />
                </section>

                <div className="h-px bg-slate-200/70" />

                {/* KEPEGAWAIAN & PAYROLL */}
                <section className="space-y-4">
                  <h3 className="text-sm font-bold text-slate-900">Employment & Payroll Details (Fase 1)</h3>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-1">
                      <label className="text-xs font-medium text-slate-600">Grade / Jabatan Level</label>
                      <select
                        value={form.grade_id}
                        onChange={(e) => {
                          const gid = e.target.value;
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
                        }}
                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-indigo-300 focus:ring-4 focus:ring-indigo-200/40"
                      >
                        <option value="">-- Pilih Grade --</option>
                        {grades.map((g) => (
                          <option key={g.id} value={g.id}>
                            {g.name} ({g.code.toUpperCase()})
                          </option>
                        ))}
                      </select>
                    </div>

                    {/* --- PREVIEW HAK & TUNJANGAN --- */}
                    {selectedGrade && (() => {
                      const formatNum = (num) => new Intl.NumberFormat("id-ID").format(num);
                      
                      const getInfoBadge = (type) => {
                        switch (type) {
                          case 'per_trip':
                            return <span className="bg-sky-100 text-sky-700 px-2 py-0.5 rounded text-[10px] font-bold border border-sky-200">Dibayar per Perjalanan</span>;
                          case 'per_mandays':
                            return <span className="bg-amber-100 text-amber-700 px-2 py-0.5 rounded text-[10px] font-bold border border-amber-200">Dibayar per Hari Hadir</span>;
                          case 'formula':
                            return <span className="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded text-[10px] font-bold border border-emerald-200">Dikalikan Syarat (Mis. Jml Anak)</span>;
                          case 'fixed':
                            return <span className="bg-purple-100 text-purple-700 px-2 py-0.5 rounded text-[10px] font-bold border border-purple-200">Tetap Setiap Bulan</span>;
                          default:
                            return <span className="bg-slate-100 text-slate-700 px-2 py-0.5 rounded text-[10px] font-bold border border-slate-200 uppercase">{type}</span>;
                        }
                      };

                      return (
                        <div className="md:col-span-2 mt-2 mb-4 bg-slate-50/50 border border-slate-200 rounded-xl overflow-hidden">
                          <div className="bg-slate-100/50 px-4 py-3 border-b border-slate-200">
                            <h3 className="text-sm font-semibold text-slate-800">Preview Standar Gaji: {selectedGrade.name}</h3>
                            <p className="text-[11px] text-slate-500 mt-0.5">Besaran gaji ini adalah nilai default. Anda bisa melakukan override nanti.</p>
                          </div>
                          
                          <div className="p-4">
                            <div className="flex flex-col sm:flex-row gap-8 mb-6">
                              <div className="flex-1">
                                <label className="text-[11px] font-medium text-slate-500 uppercase tracking-wider mb-1 block">Tunjangan Jabatan</label>
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
                                <p className="text-[10px] text-slate-400 mt-1">
                                  * Dapat diubah, default dari master data.
                                </p>
                              </div>
                              <div className="flex-1">
                                <label className="text-[11px] font-medium text-slate-500 uppercase tracking-wider mb-1 block">Gaji Harian (Mandays)</label>
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
                                <p className="text-[10px] text-slate-400 mt-1">
                                  * Dapat diubah, default dari master data.
                                </p>
                              </div>
                            </div>

                            {selectedGrade.allowance_rates && selectedGrade.allowance_rates.length > 0 && (
                              <div>
                                <div className="text-[11px] font-medium text-slate-500 uppercase tracking-wider mb-2">Daftar Tunjangan (Allowances)</div>
                                <div className="border border-slate-200 rounded-lg overflow-hidden">
                                  <table className="w-full text-left text-xs text-slate-700">
                                    <thead className="bg-slate-50 border-b border-slate-200 text-slate-500">
                                      <tr>
                                        <th className="px-3 py-2.5 font-medium">Nama Tunjangan</th>
                                        <th className="px-3 py-2.5 font-medium">Aturan Pencairan (Cara Hitung)</th>
                                        <th className="px-3 py-2.5 font-medium text-right">Tarif Dasar</th>
                                      </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 bg-white">
                                      {selectedGrade.allowance_rates.map((rate, idx) => (
                                        <tr key={idx} className="hover:bg-slate-50/50 transition-colors">
                                          <td className="px-3 py-2.5 font-medium text-slate-800">{rate.allowance_type?.name}</td>
                                          <td className="px-3 py-2.5">{getInfoBadge(rate.allowance_type?.calculation_type)}</td>
                                          <td className="px-3 py-2.5 text-right font-semibold text-slate-900">Rp {formatNum(rate.rate_amount)}</td>
                                        </tr>
                                      ))}
                                    </tbody>
                                  </table>
                                </div>
                              </div>
                            )}
                          </div>
                        </div>
                      );
                    })()}
                    {/* --- END PREVIEW --- */}

                    <div className="space-y-1">
                      <label className="text-xs font-medium text-slate-600">Tipe Hubungan Kerja (Employment Type)</label>
                      <select
                        value={form.employment_type_id}
                        onChange={(e) => setForm((p) => ({ ...p, employment_type_id: e.target.value }))}
                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-indigo-300 focus:ring-4 focus:ring-indigo-200/40"
                      >
                        <option value="">-- Pilih Tipe --</option>
                        {employmentTypes.map((et) => (
                          <option key={et.id} value={et.id}>
                            {et.name}
                          </option>
                        ))}
                      </select>
                    </div>

                    <div className={`space-y-1 ${(!form.grade_id || !hasChildcare) ? 'opacity-50' : ''}`}>
                      <label className="text-xs font-medium text-slate-600">Jumlah Balita (Childcare)</label>
                      <input
                        type="number"
                        min="0"
                        disabled={!form.grade_id || !hasChildcare}
                        placeholder={!form.grade_id ? "Pilih grade terlebih dahulu" : (!hasChildcare ? "Tidak berlaku untuk grade ini" : "")}
                        value={form.num_toddlers}
                        onChange={(e) => setForm((p) => ({ ...p, num_toddlers: parseInt(e.target.value) || 0 }))}
                        className={`w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-indigo-300 focus:ring-4 focus:ring-indigo-200/40 ${(!form.grade_id || !hasChildcare) ? 'bg-slate-100 cursor-not-allowed' : 'bg-white'}`}
                      />
                    </div>

                    <div className={`flex items-center gap-2 py-2 mt-2 ${(!form.grade_id || !hasTraining) ? 'opacity-50' : ''}`}>
                      <input
                        type="checkbox"
                        id="is_trainer"
                        disabled={!form.grade_id || !hasTraining}
                        checked={form.is_trainer}
                        onChange={(e) => setForm((p) => ({ ...p, is_trainer: e.target.checked }))}
                        className={`h-4 w-4 rounded border-slate-200 text-sky-600 focus:ring-sky-500/40 ${(!form.grade_id || !hasTraining) ? 'bg-slate-100 cursor-not-allowed' : ''}`}
                      />
                      <label htmlFor="is_trainer" className={`text-xs font-semibold cursor-pointer select-none ${(!form.grade_id || !hasTraining) ? 'text-slate-500 cursor-not-allowed' : 'text-slate-800'}`}>
                        Karyawan adalah Trainer {(!form.grade_id) ? "(Pilih grade terlebih dahulu)" : (!hasTraining && "(Tidak berlaku)")}
                      </label>
                    </div>

                    <div className="flex items-center gap-2 py-2">
                      <input
                        type="checkbox"
                        id="is_on_probation"
                        checked={form.is_on_probation}
                        onChange={(e) => setForm((p) => ({ ...p, is_on_probation: e.target.checked }))}
                        className="h-4 w-4 rounded border-slate-200 text-sky-600 focus:ring-sky-500/40"
                      />
                      <label htmlFor="is_on_probation" className="text-xs font-semibold text-slate-800 cursor-pointer select-none">
                        Dalam Masa Percobaan Promosi
                      </label>
                    </div>
                  </div>
                </section>

                <div className="h-px bg-slate-200/70" />

                {/* PRIVATE */}
                <section className="space-y-4">
                  <h3 className="text-sm font-bold text-slate-900">Private Information</h3>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Input
                      label="NIK"
                      value={form.nik}
                      onChange={(v) => setForm((p) => ({ ...p, nik: v }))}
                    />
                    <Input
                      label="NPWP"
                      value={form.npwp}
                      onChange={(v) => setForm((p) => ({ ...p, npwp: v }))}
                    />
                    <Input
                      label="Phone"
                      value={form.phone}
                      full
                      onChange={(v) => setForm((p) => ({ ...p, phone: v }))}
                    />
                    <Textarea
                      label="Address"
                      value={form.address}
                      onChange={(v) => setForm((p) => ({ ...p, address: v }))}
                    />
                  </div>
                </section>

                <div className="h-px bg-slate-200/70" />

                {/* BANK */}
                <section className="space-y-4">
                  <h3 className="text-sm font-bold text-slate-900">Bank Information</h3>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Input
                      label="Bank Name"
                      value={form.bank_name}
                      onChange={(v) => setForm((p) => ({ ...p, bank_name: v }))}
                    />
                    <Input
                      label="Account Name"
                      value={form.bank_account_name}
                      onChange={(v) => setForm((p) => ({ ...p, bank_account_name: v }))}
                    />
                    <Input
                      label="Account Number"
                      value={form.bank_account_number}
                      full
                      onChange={(v) => setForm((p) => ({ ...p, bank_account_number: v }))}
                    />
                  </div>
                </section>

                {/* ACTION */}
                <div className="flex gap-3 pt-4">
                  <Button
                    type="submit"
                    disabled={saving}
                    className="px-4 py-1.5 bg-blue-600 rounded text-xs font-medium text-white hover:bg-blue-700 transition-colors"
                  >
                    {saving ? "Saving..." : "Save Changes"}
                  </Button>

                  <Button
                    type="button"
                    variant="outline"
                    className="rounded"
                    onClick={() => nav(-1)}
                  >
                    Cancel
                  </Button>
                </div>
              </form>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

/* ===== Small reusable inputs ===== */
function Input({ label, value, onChange, required, full, readOnly, disabled, type = "text", min }) {
  return (
    <div className={full ? "md:col-span-2 space-y-1" : "space-y-1"}>
      <label className="text-xs font-medium text-slate-600">{label}</label>
      <input
        type={type}
        min={min}
        className={[
          "w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-sky-300 focus:ring-4 focus:ring-sky-200/40",
          (readOnly || disabled) ? "bg-slate-100 text-slate-600 cursor-not-allowed" : "bg-white",
        ].join(" ")}
        value={value}
        required={required}
        readOnly={readOnly}
        disabled={disabled}
        onChange={(e) => onChange?.(e.target.value)}
      />
    </div>
  );
}

function Textarea({ label, value, onChange }) {
  return (
    <div className="md:col-span-2 space-y-1">
      <label className="text-xs font-medium text-slate-600">{label}</label>
      <textarea
        rows={3}
        className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-sky-300 focus:ring-4 focus:ring-sky-200/40"
        value={value}
        onChange={(e) => onChange(e.target.value)}
      />
    </div>
  );
}

function Select({ label, value, options, onChange }) {
  return (
    <div className="space-y-1">
      <label className="text-xs font-medium text-slate-600">{label}</label>
      <select
        className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-indigo-300 focus:ring-4 focus:ring-indigo-200/40"
        value={value}
        onChange={(e) => onChange(e.target.value)}
      >
        {options.map((o) => (
          <option key={o} value={o}>{o}</option>
        ))}
      </select>
    </div>
  );
}
