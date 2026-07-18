import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { Eye, EyeOff, KeyRound } from "lucide-react";
import { api } from "@/lib/api";
import {
  digitsOnly,
  generateEmployeeAccountPassword,
  nonNegativeIntegerInput,
} from "@/lib/employeeFormHelpers";
import { Button } from "@/components/ui/button";
import AlertMessage from "@/components/AlertMessage";
import {
  EmployeeNotice,
  EmployeePageHeader,
  EmployeeSectionCard,
} from "@/components/employee/EmployeePageBlocks";

export default function EmployeeCreatePage() {
  const navigate = useNavigate();

  const [form, setForm] = useState({
    employee_code: "",
    name: "",
    join_date: "",
    position_id: "",
    position_allowance: "",
    base_salary_basis: "daily",
    base_salary_amount: "",
    num_toddlers: "",
    is_trainer: false,
    nik: "",
    npwp: "",
    phone: "",
    address: "",
    bank_name: "",
    bank_account_name: "",
    bank_account_number: "",
    create_account: false,
    email: "",
    role: "staff",
    password: "",
    password_confirmation: "",
  });

  const [positions, setPositions] = useState([]);
  const [loading, setLoading] = useState(false);
  const [loadingCode, setLoadingCode] = useState(true);
  const [errors, setErrors] = useState({});
  const [serverError, setServerError] = useState("");
  const [showAccountPassword, setShowAccountPassword] = useState(false);

  function setField(key, value) {
    setForm((prev) => ({ ...prev, [key]: value }));
    setErrors((prev) => ({ ...prev, [key]: undefined }));
    setServerError("");
  }

  function setDigitField(key, value, maxLength) {
    setField(key, digitsOnly(value, maxLength));
  }

  function setNumToddlersField(value) {
    setField("num_toddlers", nonNegativeIntegerInput(value));
  }

  function toggleCreateAccount(checked) {
    setForm((prev) => ({
      ...prev,
      create_account: checked,
      email: "",
      role: checked ? (prev.role || "staff") : "staff",
      password: "",
      password_confirmation: "",
    }));
    setErrors((prev) => ({
      ...prev,
      email: undefined,
      role: undefined,
      password: undefined,
      password_confirmation: undefined,
    }));
    setShowAccountPassword(false);
    setServerError("");
  }

  function generatePassword() {
    const nextPassword = generateEmployeeAccountPassword();

    setForm((prev) => ({
      ...prev,
      password: nextPassword,
      password_confirmation: nextPassword,
    }));
    setErrors((prev) => ({
      ...prev,
      password: undefined,
      password_confirmation: undefined,
    }));
    setShowAccountPassword(true);
    setServerError("");
  }

  function applyselectedPosition(positionId) {
    const position = positions.find((item) => String(item.id) === String(positionId));

    if (position) {
      const positionRate = position.allowance_rates?.find(
        (rate) => rate.allowance_type?.code === "position"
      );

      setForm((prev) => ({
        ...prev,
        position_id: positionId,
        position_allowance: positionRate ? positionRate.rate_amount : 0,
        base_salary_basis: position.base_salary_basis || "daily",
        base_salary_amount: position.default_base_salary_amount || 0,
      }));
      setErrors((prev) => ({ ...prev, position_id: undefined }));
      setServerError("");
      return;
    }

    setForm((prev) => ({
      ...prev,
      position_id: positionId,
      position_allowance: "",
      base_salary_basis: "daily",
      base_salary_amount: "",
    }));
    setErrors((prev) => ({ ...prev, position_id: undefined }));
    setServerError("");
  }

  useEffect(() => {
    let mounted = true;

    async function loadData() {
      try {
        setLoadingCode(true);
        const nextCodeData = await api("/employees/next-code");
        const positionsList = await api("/master/positions?active_only=1");

        if (!mounted) return;

        if (nextCodeData?.next_employee_code) {
          setForm((prev) => ({
            ...prev,
            employee_code: nextCodeData.next_employee_code,
          }));
        }

        setPositions(Array.isArray(positionsList) ? positionsList : []);
      } catch (error) {
        console.error("Failed to load master lists:", error);
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
    const nextErrors = {};

    if (!form.employee_code.trim()) nextErrors.employee_code = "Kode pegawai wajib terisi.";
    if (!form.name.trim()) nextErrors.name = "Nama wajib diisi.";
    if (!form.position_id) nextErrors.position_id = "Jabatan wajib dipilih.";

    if (form.nik && form.nik.length !== 16) {
      nextErrors.nik = "NIK harus berjumlah 16 digit angka.";
    }
    if (form.npwp && (form.npwp.length < 15 || form.npwp.length > 16)) {
      nextErrors.npwp = "NPWP harus berjumlah 15-16 digit angka.";
    }

    setErrors(nextErrors);
    return Object.keys(nextErrors).length === 0;
  }

  async function handleSubmit(event) {
    event.preventDefault();
    if (!validate()) return;

    setLoading(true);
    setServerError("");

    try {
      const payload = {
        position_id: form.position_id ? parseInt(form.position_id, 10) : null,
        employee_code: form.employee_code,
        name: form.name,
        join_date: form.join_date || null,
        num_toddlers: form.num_toddlers === "" ? 0 : (parseInt(form.num_toddlers, 10) || 0),
        is_trainer: !!form.is_trainer,
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

      await api("/employees", {
        method: "POST",
        body: payload,
      });

      navigate("/employees");
    } catch (error) {
      if (error?.data?.errors) {
        const mapped = {};
        for (const key of Object.keys(error.data.errors)) {
          mapped[key] = Array.isArray(error.data.errors[key])
            ? error.data.errors[key][0]
            : String(error.data.errors[key]);
        }
        setErrors(mapped);
      } else {
        setServerError(error?.message || "Tidak bisa terhubung ke server. Pastikan backend Laravel berjalan.");
      }
    } finally {
      setLoading(false);
    }
  }

  const selectedPosition = positions.find((position) => String(position.id) === String(form.position_id));
  const hasChildcare =
    selectedPosition?.allowance_rates?.some((rate) => rate.allowance_type?.code === "childcare") || false;
  const hasTraining =
    selectedPosition?.allowance_rates?.some((rate) => rate.allowance_type?.code === "training") || false;

  return (
    <div className="space-y-6">
      <EmployeePageHeader
        title="Tambah Pegawai"
        description="Isi data inti pegawai. Aturan gaji pokok dan tunjangan mengikuti master jabatan yang dipilih."
        actions={
          <Button
            variant="outline"
            className="rounded border-slate-200 bg-white/70 hover:bg-white"
            onClick={() => navigate("/employees")}
            disabled={loading}
          >
            Kembali
          </Button>
        }
      />

      <AlertMessage type="error" message={serverError} className="px-3 py-2" />

      <form onSubmit={handleSubmit} className="space-y-5">
        <EmployeeSectionCard
          title="Informasi Dasar"
          description="Bagian inti identitas karyawan. Field bertanda * wajib diisi."
        >
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <Field
              label="Kode Pegawai *"
              placeholder={loadingCode ? "Mengambil kode..." : "EMP-0001"}
              value={form.employee_code}
              onChange={(value) => setField("employee_code", value.toUpperCase())}
              error={errors.employee_code}
              disabled
            />
            <Field
              label="Nama *"
              placeholder="Contoh: Pegawai Satu"
              value={form.name}
              onChange={(value) => setField("name", value)}
              error={errors.name}
            />
            <Field
              label="Tanggal Masuk"
              type="date"
              value={form.join_date}
              onChange={(value) => setField("join_date", value)}
              error={errors.join_date}
            />
            <EmployeeNotice tone="info" className="md:col-span-2">
              Status pegawai otomatis diset <b>aktif</b> saat data dibuat. Tanggal masuk dipakai sebagai acuan awal histori jabatan dan profil gaji.
            </EmployeeNotice>
          </div>
        </EmployeeSectionCard>

        <EmployeeSectionCard
          title="Informasi Jabatan & Payroll"
          description="Jabatan aktif menjadi acuan gaji pokok dan daftar tunjangan default."
        >
          <div className="space-y-4">
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div className="space-y-1">
                <label className="text-xs font-medium text-slate-600">Jabatan *</label>
                <select
                  value={form.position_id}
                  onChange={(event) => applyselectedPosition(event.target.value)}
                  className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-indigo-300 focus:ring-4 focus:ring-indigo-200/40"
                >
                  <option value="">-- Pilih Jabatan --</option>
                  {positions.map((position) => (
                    <option key={position.id} value={position.id}>
                      {position.name} ({position.code.toUpperCase()})
                    </option>
                  ))}
                </select>
                {errors.position_id ? <p className="text-xs text-rose-500">{errors.position_id}</p> : null}
              </div>
              <div className="space-y-1">
                <label className="text-xs font-medium text-slate-600">Jumlah Balita</label>
                <input
                  type="text"
                  inputMode="numeric"
                  pattern="[0-9]*"
                  placeholder="Isi jika relevan"
                  value={form.num_toddlers}
                  onChange={(event) => setNumToddlersField(event.target.value)}
                  className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-indigo-300 focus:ring-4 focus:ring-indigo-200/40"
                />
                <p className="text-[11px] text-slate-500">
                  {hasChildcare
                    ? "Dipakai oleh tunjangan yang mensyaratkan jumlah balita pada jabatan ini."
                    : "Tetap boleh diisi lebih dulu. Nilai ini akan dipakai jika ada tunjangan yang mensyaratkannya."}
                </p>
                {errors.num_toddlers ? <p className="text-xs text-rose-500">{errors.num_toddlers}</p> : null}
              </div>
            </div>

            {selectedPosition ? (
              <EmployeeNotice tone="info">
                Jabatan terpilih: <b>{selectedPosition.name}</b>. Nominal gaji pokok dan tunjangan jabatan dikelola oleh Finance pada master payroll.
              </EmployeeNotice>
            ) : (
              <EmployeeNotice>Pilih jabatan aktif yang akan menjadi acuan payroll pegawai.</EmployeeNotice>
            )}
          </div>
        </EmployeeSectionCard>



        <EmployeeSectionCard
          title="Data Pribadi"
          description="Data pribadi sensitif disimpan terenkripsi dan dipakai untuk kebutuhan administrasi."
        >
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <Field
              label="NIK"
              placeholder="Contoh: 3171234567890001"
              value={form.nik}
              onChange={(value) => setDigitField("nik", value, 16)}
              error={errors.nik}
              inputMode="numeric"
              maxLength={16}
              autoComplete="off"
            />
            <Field
              label="NPWP"
              placeholder="Contoh: 012345678912345"
              value={form.npwp}
              onChange={(value) => setDigitField("npwp", value, 16)}
              error={errors.npwp}
              inputMode="numeric"
              maxLength={16}
              autoComplete="off"
            />
            <Field
              label="No. Telepon"
              placeholder="Contoh: 08xxxxxxxxxx"
              value={form.phone}
              onChange={(value) => setDigitField("phone", value, 20)}
              error={errors.phone}
              inputMode="numeric"
              maxLength={20}
              autoComplete="off"
            />
            <Textarea
              label="Alamat"
              placeholder="Alamat lengkap..."
              value={form.address}
              onChange={(value) => setField("address", value)}
              error={errors.address}
            />
          </div>
        </EmployeeSectionCard>

        <EmployeeSectionCard
          title="Informasi Bank"
          description="Data rekening dipakai untuk kebutuhan transfer payroll dan nomor rekening dibatasi angka saja."
        >
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <Field
              label="Nama Bank"
              placeholder="Contoh: BCA / BRI / Mandiri"
              value={form.bank_name}
              onChange={(value) => setField("bank_name", value)}
              error={errors.bank_name}
            />
            <Field
              label="Nama Pemilik Rekening"
              placeholder="Contoh: Nama pemilik rekening"
              value={form.bank_account_name}
              onChange={(value) => setField("bank_account_name", value)}
              error={errors.bank_account_name}
            />
            <Field
              label="Nomor Rekening"
              placeholder="Contoh: 1234567890"
              value={form.bank_account_number}
              onChange={(value) => setDigitField("bank_account_number", value, 50)}
              error={errors.bank_account_number}
              inputMode="numeric"
              maxLength={50}
              autoComplete="off"
            />
          </div>
        </EmployeeSectionCard>

        <EmployeeSectionCard
          title="Akun Login Pegawai"
          description="Opsional. Email dan password dimulai kosong agar operator bisa isi manual atau gunakan generator."
        >
          <div className="space-y-4">
            <label className="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
              <input
                type="checkbox"
                id="create_account"
                className="mt-0.5 h-4 w-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500"
                checked={form.create_account}
                onChange={(event) => toggleCreateAccount(event.target.checked)}
              />
              <span className="text-sm font-medium text-slate-800">
                Buat akun login untuk pegawai ini
                <span className="mt-1 block text-xs font-normal text-slate-500">
                  Jika tidak dicentang, data pegawai tetap tersimpan tanpa akun aplikasi.
                </span>
              </span>
            </label>

            {form.create_account ? (
              <div className="grid grid-cols-1 gap-x-6 gap-y-5 md:grid-cols-2">
                <Field
                  label="Email Login"
                  type="email"
                  placeholder="Contoh: user@domain.com"
                  value={form.email}
                  onChange={(value) => setField("email", value)}
                  error={errors.email}
                  autoComplete="off"
                  name="employee_login_email"
                />

                <div className="space-y-1">
                  <label className="text-xs font-medium text-slate-600">Peran Akun (Role)</label>
                  <select
                    value={form.role}
                    onChange={(event) => setField("role", event.target.value)}
                    className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-sky-300 focus:ring-4 focus:ring-sky-200/40"
                  >
                    <option value="staff">Staff</option>
                    <option value="hcga">HCGA</option>
                    <option value="fat">FAT</option>
                    <option value="director">Director</option>
                  </select>
                  {errors.role ? <div className="text-xs text-rose-700">{errors.role}</div> : null}
                </div>

                <div className="md:col-span-2 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
                  <div className="text-[11px] text-slate-500">
                    Email dan password dibuat kosong dulu. Isi manual atau gunakan generator password.
                  </div>
                  <div className="flex flex-wrap items-center gap-2">
                    <button
                      type="button"
                      onClick={() => setShowAccountPassword((prev) => !prev)}
                      className="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] font-medium text-slate-700 hover:bg-slate-50"
                    >
                      {showAccountPassword ? <EyeOff size={12} /> : <Eye size={12} />}
                      {showAccountPassword ? "Sembunyikan" : "Lihat Password"}
                    </button>
                    <button
                      type="button"
                      onClick={generatePassword}
                      className="inline-flex items-center gap-1 rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-[11px] font-medium text-blue-700 hover:bg-blue-100"
                    >
                      <KeyRound size={12} />
                      Generate Password
                    </button>
                  </div>
                </div>

                <Field
                  type={showAccountPassword ? "text" : "password"}
                  label="Password"
                  placeholder="Minimal 8 karakter"
                  value={form.password}
                  onChange={(value) => setField("password", value)}
                  error={errors.password}
                  autoComplete="new-password"
                  name="employee_login_password"
                />
                <Field
                  type={showAccountPassword ? "text" : "password"}
                  label="Konfirmasi Password"
                  placeholder="Ketik ulang password"
                  value={form.password_confirmation}
                  onChange={(value) => setField("password_confirmation", value)}
                  error={errors.password_confirmation}
                  autoComplete="new-password"
                  name="employee_login_password_confirmation"
                />
              </div>
            ) : (
              <EmployeeNotice>Aktifkan opsi ini kalau pegawai perlu login ke aplikasi.</EmployeeNotice>
            )}
          </div>
        </EmployeeSectionCard>

        <div className="sticky bottom-0 z-10 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white/95 px-4 py-3 shadow-sm backdrop-blur">
          <div className="text-xs text-slate-500">
            Status pegawai otomatis aktif saat dibuat dan histori jabatan mulai mengikuti tanggal masuk.
          </div>
          <div className="flex flex-wrap gap-3">
            <Button
              type="button"
              variant="outline"
              onClick={() => navigate("/employees")}
              disabled={loading}
              className="rounded border-slate-200 bg-white hover:bg-slate-50"
            >
              Batal
            </Button>
            <Button
              type="submit"
              disabled={loading || loadingCode}
              className="rounded bg-blue-600 px-4 py-1.5 text-xs font-medium text-white transition-colors hover:bg-blue-700"
            >
              {loading ? "Menyimpan..." : "Simpan Pegawai"}
            </Button>
          </div>
        </div>
      </form>
    </div>
  );
}

function Field({
  label,
  value,
  onChange,
  placeholder,
  error,
  disabled,
  type = "text",
  maxLength,
  inputMode,
  autoComplete,
  name,
}) {
  return (
    <div className="space-y-1.5">
      <label className="text-xs font-medium text-slate-600">{label}</label>
      <input
        type={type}
        maxLength={maxLength}
        inputMode={inputMode}
        autoComplete={autoComplete}
        name={name}
        value={value}
        placeholder={placeholder}
        disabled={disabled}
        onChange={(event) => onChange(event.target.value)}
        className={[
          "w-full rounded-xl border px-3 py-2.5 text-sm outline-none transition",
          disabled ? "cursor-not-allowed bg-slate-100 text-slate-700" : "bg-white text-slate-900",
          "border-slate-200",
          "focus:border-sky-300 focus:ring-4 focus:ring-sky-200/40",
          error ? "border-rose-300" : "",
        ].join(" ")}
      />
      {error ? <div className="text-xs text-rose-700">{error}</div> : null}
    </div>
  );
}

function Textarea({ label, value, onChange, placeholder, error }) {
  return (
    <div className="space-y-1.5 md:col-span-2">
      <label className="text-xs font-medium text-slate-600">{label}</label>
      <textarea
        value={value}
        placeholder={placeholder}
        onChange={(event) => onChange(event.target.value)}
        className={[
          "min-h-[90px] w-full rounded-xl border px-3 py-2.5 text-sm outline-none transition",
          "border-slate-200 bg-white text-slate-900",
          "focus:border-sky-300 focus:ring-4 focus:ring-sky-200/40",
          error ? "border-rose-300" : "",
        ].join(" ")}
      />
      {error ? <div className="text-xs text-rose-700">{error}</div> : null}
    </div>
  );
}
