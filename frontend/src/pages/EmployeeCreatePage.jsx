import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { api } from "@/lib/api";
import { Button } from "@/components/ui/button";
import AlertMessage from "@/components/AlertMessage";

export default function EmployeeCreatePage() {
  const navigate = useNavigate();

  const [form, setForm] = useState({
    employee_code: "",
    name: "",
    department: "",
    position: "",
    status: "active",

    // Phase 1 fields
    grade_id: "",
    position_allowance: "",
    mandays_rate: "",
    employment_type_id: "",
    work_basis_id: "",
    num_toddlers: 0,
    is_trainer: false,
    is_on_probation: false,

    // private/sensitive
    nik: "",
    npwp: "",
    phone: "",
    address: "",

    bank_name: "",
    bank_account_name: "",
    bank_account_number: "",

    // Account creation
    create_account: false,
    email: "",
    role: "staff",
    password: "",
    password_confirmation: "",
  });

  const [grades, setGrades] = useState([]);
  const [employmentTypes, setEmploymentTypes] = useState([]);
  const [workBases, setWorkBases] = useState([]);

  const [loading, setLoading] = useState(false);
  const [loadingCode, setLoadingCode] = useState(true);
  const [errors, setErrors] = useState({});
  const [serverError, setServerError] = useState("");

  function setField(key, value) {
    setForm((prev) => ({ ...prev, [key]: value }));
    setErrors((prev) => ({ ...prev, [key]: undefined }));
    setServerError("");
  }

  // Load next code and master lists
  useEffect(() => {
    let mounted = true;

    async function loadData() {
      try {
        setLoadingCode(true);
        const nextCodeData = await api("/employees/next-code");
        if (mounted && nextCodeData?.next_employee_code) {
          setForm((p) => ({
            ...p,
            employee_code: nextCodeData.next_employee_code,
            department: p.department || "Finance",
            position: p.position || "Staff",
          }));
        }

        // Fetch master tables
        const [gradesList, empTypesList, workBasesList] = await Promise.all([
          api("/master/grades"),
          api("/master/employment-types"),
          api("/master/work-bases"),
        ]);

        if (mounted) {
          setGrades(Array.isArray(gradesList) ? gradesList : []);
          setEmploymentTypes(Array.isArray(empTypesList) ? empTypesList : []);
          setWorkBases(Array.isArray(workBasesList) ? workBasesList : []);
        }
      } catch (err) {
        console.error("Failed to load master lists:", err);
      } finally {
        if (mounted) setLoadingCode(false);
      }
    }

    loadData();

    return () => {
      mounted = false;
    };
  }, []);

  function validate() {
    const e = {};

    if (!form.employee_code.trim()) e.employee_code = "Kode pegawai wajib terisi.";
    if (!form.name.trim()) e.name = "Nama wajib diisi.";
    if (!form.department.trim()) e.department = "Departemen wajib diisi.";
    if (!form.position.trim()) e.position = "Jabatan wajib diisi.";
    if (!["active", "inactive"].includes(form.status)) e.status = "Status tidak valid.";

    setErrors(e);
    return Object.keys(e).length === 0;
  }

  async function handleSubmit(ev) {
    ev.preventDefault();
    if (!validate()) return;

    setLoading(true);
    setServerError("");

    try {
      const payload = {
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

        create_account: form.create_account,
        email: form.create_account ? form.email : null,
        role: form.create_account ? form.role : null,
        password: form.create_account ? form.password : null,
        password_confirmation: form.create_account ? form.password_confirmation : null,
      };

      const data = await api("/employees", {
        method: "POST",
        body: payload,
      });

      const employeeId = data?.employee?.id;
      if (!employeeId) {
        setServerError("Pegawai berhasil dibuat, tapi ID tidak terbaca dari response.");
        return;
      }

      navigate("/employees");
    } catch (err) {
      if (err?.data?.errors) {
        const mapped = {};
        for (const k of Object.keys(err.data.errors)) {
          mapped[k] = Array.isArray(err.data.errors[k])
            ? err.data.errors[k][0]
            : String(err.data.errors[k]);
        }
        setErrors(mapped);
      } else {
        setServerError(err?.message || "Tidak bisa terhubung ke server. Pastikan backend Laravel berjalan.");
      }
    } finally {
      setLoading(false);
    }
  }

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
              Tambah Pegawai
            </h1>
            <p className="mt-1 text-sm text-slate-600">
              Isi data pegawai. Setelah disimpan, kamu akan diarahkan ke halaman profil gaji.
            </p>
          </div>

          <Button
            variant="outline"
            className="rounded bg-white/70 border-slate-200 hover:bg-white"
            onClick={() => navigate("/employees")}
            disabled={loading}
          >
            Kembali
          </Button>
        </div>

        <AlertMessage type="error" message={serverError} className="mb-4 px-3 py-2" />

        {/* Form card */}
        <div className="bg-white border border-border rounded shadow-sm overflow-hidden">
          <div className="px-4 py-3 border-b border-slate-200/70">
            <div className="text-sm font-medium text-foreground">
              Form Pegawai
            </div>
            <div className="text-xs text-slate-500">
              Lengkapi informasi dasar, data kepegawaian & payroll, data sensitif, dan informasi bank.
            </div>
          </div>

          <form onSubmit={handleSubmit} className="p-4 space-y-8">
            {/* BASIC INFO */}
            <section className="space-y-4">
              <div className="flex items-center justify-between">
                <h3 className="text-sm font-bold text-slate-900">
                  Informasi Dasar
                </h3>
                <span className="text-xs text-slate-500">
                  Field bertanda * wajib diisi
                </span>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Field
                  label="Kode Pegawai *"
                  placeholder={loadingCode ? "Mengambil kode..." : "EMP-0001"}
                  value={form.employee_code}
                  onChange={(v) => setField("employee_code", v.toUpperCase())}
                  error={errors.employee_code}
                  disabled
                />

                <Field
                  label="Nama *"
                  placeholder="Contoh: Pegawai Satu"
                  value={form.name}
                  onChange={(v) => setField("name", v)}
                  error={errors.name}
                />

                <Field
                  label="Jabatan *"
                  placeholder="Contoh: Staff"
                  value={form.position}
                  onChange={(v) => setField("position", v)}
                  error={errors.position}
                />
                <Field
                  label="Departemen *"
                  placeholder="Contoh: Finance"
                  value={form.department}
                  onChange={(v) => setField("department", v)}
                  error={errors.department}
                />
                <div className="md:col-span-2 space-y-1">
                  <label className="text-xs font-medium text-slate-600">
                    Status *
                  </label>
                  <select
                    value={form.status}
                    onChange={(e) => setField("status", e.target.value)}
                    className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-indigo-300 focus:ring-4 focus:ring-indigo-200/40"
                  >
                    <option value="active">Aktif</option>
                    <option value="inactive">Nonaktif</option>
                  </select>
                </div>
              </div>
            </section>

            <div className="h-px bg-slate-200/70" />

            {/* KEPEGAWAIAN & PAYROLL */}
            <section className="space-y-4">
              <h3 className="text-sm font-bold text-slate-900">
                Informasi Kepegawaian & Payroll (Fase 1)
              </h3>

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
                                onChange={(e) => setField("position_allowance", e.target.value)}
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
                                onChange={(e) => setField("mandays_rate", e.target.value)}
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
                                  {selectedGrade.allowance_rates.map((rate, idx) => {
                                    let displayedAmount = rate.rate_amount;
                                    let isDynamic = false;

                                    if (rate.allowance_type?.code === 'childcare') {
                                      const numToddlers = parseInt(form.num_toddlers || 0, 10);
                                      displayedAmount = (rate.rate_amount || 0) * numToddlers;
                                      isDynamic = true;
                                    }

                                    return (
                                      <tr key={idx} className="hover:bg-slate-50/50 transition-colors">
                                        <td className="px-3 py-2.5 font-medium text-slate-800">
                                          {rate.allowance_type?.name}
                                          {isDynamic && form.num_toddlers > 0 && <span className="ml-2 text-[9px] bg-green-100 text-green-700 px-1 py-0.5 rounded border border-green-200">Re-calculated</span>}
                                        </td>
                                        <td className="px-3 py-2.5">{getInfoBadge(rate.allowance_type?.calculation_type)}</td>
                                        <td className="px-3 py-2.5 text-right font-semibold text-slate-900">Rp {formatNum(displayedAmount)}</td>
                                      </tr>
                                    );
                                  })}
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
                    onChange={(e) => setField("employment_type_id", e.target.value)}
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
                  <label className="text-xs font-medium text-slate-600">
                    Jumlah Balita (Childcare)
                  </label>
                  <input
                    type="number"
                    min="0"
                    disabled={!form.grade_id || !hasChildcare}
                    placeholder={!form.grade_id ? "Pilih grade terlebih dahulu" : (!hasChildcare ? "Tidak berlaku untuk grade ini" : "")}
                    value={form.num_toddlers}
                    onChange={(e) => setField("num_toddlers", parseInt(e.target.value) || 0)}
                    className={`w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-indigo-300 focus:ring-4 focus:ring-indigo-200/40 ${(!form.grade_id || !hasChildcare) ? 'bg-slate-100 cursor-not-allowed' : 'bg-white'}`}
                  />
                  {errors.num_toddlers && <p className="text-xs text-rose-500">{errors.num_toddlers}</p>}
                </div>

                <div className={`flex items-center gap-2 py-2 ${(!form.grade_id || !hasTraining) ? 'opacity-50' : ''}`}>
                  <input
                    type="checkbox"
                    id="is_trainer"
                    disabled={!form.grade_id || !hasTraining}
                    checked={form.is_trainer}
                    onChange={(e) => setField("is_trainer", e.target.checked)}
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
                    onChange={(e) => setField("is_on_probation", e.target.checked)}
                    className="h-4 w-4 rounded border-slate-200 text-sky-600 focus:ring-sky-500/40"
                  />
                  <label htmlFor="is_on_probation" className="text-xs font-semibold text-slate-800 cursor-pointer select-none">
                    Dalam Masa Percobaan Promosi
                  </label>
                </div>
              </div>
            </section>

            <div className="h-px bg-slate-200/70" />

            {/* PRIVATE / SENSITIVE INFO */}
            <section className="space-y-4">
              <h3 className="text-sm font-bold text-slate-900">
                Data Pribadi / Sensitif
              </h3>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Field
                  label="NIK"
                  placeholder="Contoh: 3273xxxxxxxxxxxx"
                  value={form.nik}
                  onChange={(v) => setField("nik", v)}
                  error={errors.nik}
                />

                <Field
                  label="NPWP"
                  placeholder="Contoh: xx.xxx.xxx.x-xxx.xxx"
                  value={form.npwp}
                  onChange={(v) => setField("npwp", v)}
                  error={errors.npwp}
                />

                <Field
                  label="No. Telepon"
                  placeholder="Contoh: 08xxxxxxxxxx"
                  value={form.phone}
                  onChange={(v) => setField("phone", v)}
                  error={errors.phone}
                />

                <Textarea
                  label="Alamat"
                  placeholder="Alamat lengkap..."
                  value={form.address}
                  onChange={(v) => setField("address", v)}
                  error={errors.address}
                />
              </div>
            </section>

            <div className="h-px bg-slate-200/70" />

            {/* BANK */}
            <section className="space-y-4">
              <h3 className="text-sm font-bold text-slate-900">
                Informasi Bank
              </h3>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Field
                  label="Nama Bank"
                  placeholder="Contoh: BCA / BRI / Mandiri"
                  value={form.bank_name}
                  onChange={(v) => setField("bank_name", v)}
                  error={errors.bank_name}
                />

                <Field
                  label="Nama Pemilik Rekening"
                  placeholder="Contoh: Nama pemilik rekening"
                  value={form.bank_account_name}
                  onChange={(v) => setField("bank_account_name", v)}
                  error={errors.bank_account_name}
                />

                <Field
                  label="Nomor Rekening"
                  placeholder="Contoh: 1234567890"
                  value={form.bank_account_number}
                  onChange={(v) => setField("bank_account_number", v)}
                  error={errors.bank_account_number}
                />
              </div>
            </section>

            {/* SEKSI 4: AKUN LOGIN (OPSIONAL) */}
            <section className="bg-slate-50 border border-slate-200/60 rounded-2xl p-5 sm:p-6 mb-6">
              <div className="flex items-center gap-3 mb-4">
                <input
                  type="checkbox"
                  id="create_account"
                  className="w-4 h-4 text-sky-500 rounded border-slate-300 focus:ring-sky-500"
                  checked={form.create_account}
                  onChange={(e) => setField("create_account", e.target.checked)}
                />
                <label htmlFor="create_account" className="font-semibold text-sm text-slate-800">
                  Buat Akun Login untuk Pegawai Ini
                </label>
              </div>

              {form.create_account && (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5 animate-in fade-in slide-in-from-top-2 duration-300">
                  <Field
                    label="Email Login"
                    placeholder="Contoh: user@domain.com"
                    value={form.email}
                    onChange={(v) => setField("email", v)}
                    error={errors.email}
                  />

                  <div className="space-y-1">
                    <label className="text-xs font-medium text-slate-600">Peran Akun (Role)</label>
                    <select
                      value={form.role}
                      onChange={(e) => setField("role", e.target.value)}
                      className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none transition bg-white focus:border-sky-300 focus:ring-4 focus:ring-sky-200/40"
                    >
                      <option value="staff">Staff</option>
                      <option value="hcga">HCGA</option>
                      <option value="fat">FAT</option>
                      <option value="director">Director</option>
                    </select>
                    {errors.role && <div className="text-xs text-rose-700">{errors.role}</div>}
                  </div>

                  <Field
                    type="password"
                    label="Password"
                    placeholder="Minimal 8 karakter"
                    value={form.password}
                    onChange={(v) => setField("password", v)}
                    error={errors.password}
                  />

                  <Field
                    type="password"
                    label="Konfirmasi Password"
                    placeholder="Ketik ulang password"
                    value={form.password_confirmation}
                    onChange={(v) => setField("password_confirmation", v)}
                  />
                </div>
              )}
            </section>

            {/* ACTIONS */}
            <div className="flex flex-wrap gap-3 pt-2">
              <Button
                type="submit"
                disabled={loading || loadingCode}
                className="px-4 py-1.5 bg-blue-600 rounded text-xs font-medium text-white hover:bg-blue-700 transition-colors"
              >
                {loading ? "Menyimpan..." : "Simpan Pegawai"}
              </Button>

              <Button
                type="button"
                variant="outline"
                onClick={() => navigate("/employees")}
                disabled={loading}
                className="rounded bg-white/70 border-slate-200 hover:bg-white"
              >
                Batal
              </Button>
            </div>
          </form>

          <div className="px-6 py-4 border-t border-slate-200/70 text-[11px] text-slate-500 flex items-center justify-between">
            <span>© {new Date().getFullYear()} Human Plus Institute</span>
            <span>Payroll Internal System</span>
          </div>
        </div>
      </div>
    </div>
  );
}

/* ---------- Small reusable inputs ---------- */
function Field({ label, value, onChange, placeholder, error, disabled, type = "text", min }) {
  return (
    <div className="space-y-1">
      <label className="text-xs font-medium text-slate-600">{label}</label>
      <input
        type={type}
        min={min}
        value={value}
        placeholder={placeholder}
        disabled={disabled}
        onChange={(e) => onChange(e.target.value)}
        className={[
          "w-full rounded-xl border px-3 py-2.5 text-sm outline-none transition",
          disabled ? "bg-slate-50 text-slate-700 cursor-not-allowed" : "bg-white text-slate-900",
          "border-slate-200",
          "focus:border-sky-300 focus:ring-4 focus:ring-sky-200/40",
          error ? "border-rose-300" : "",
        ].join(" ")}
      />
      {error && <div className="text-xs text-rose-700">{error}</div>}
    </div>
  );
}

function Textarea({ label, value, onChange, placeholder, error }) {
  return (
    <div className="md:col-span-2 space-y-1">
      <label className="text-xs font-medium text-slate-600">{label}</label>
      <textarea
        value={value}
        placeholder={placeholder}
        onChange={(e) => onChange(e.target.value)}
        className={[
          "w-full rounded-xl border px-3 py-2.5 text-sm outline-none transition min-h-[90px]",
          "border-slate-200 bg-white text-slate-900",
          "focus:border-sky-300 focus:ring-4 focus:ring-sky-200/40",
          error ? "border-rose-300" : "",
        ].join(" ")}
      />
      {error && <div className="text-xs text-rose-700">{error}</div>}
    </div>
  );
}
