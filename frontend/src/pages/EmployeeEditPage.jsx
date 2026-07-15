import { useEffect, useState } from "react";
import useSWR from "swr";
import { useNavigate, useParams } from "react-router-dom";
import { api } from "@/lib/api";
import { getUser, updateAuthUser } from "@/lib/auth";
import { digitsOnly, nonNegativeIntegerInput } from "@/lib/employeeFormHelpers";
import { Button } from "@/components/ui/button";
import AlertMessage from "@/components/AlertMessage";
import {
  EmployeeNotice,
  EmployeePageHeader,
  EmployeeSectionCard,
} from "@/components/employee/EmployeePageBlocks";

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
  });
  const [positions, setPositions] = useState([]);
  const [formInitialized, setFormInitialized] = useState(false);

  useEffect(() => {
    if (!canManage) {
      nav("/employees", { replace: true });
    }
  }, [canManage, nav]);

  const { data: swrData, error: swrErr, isLoading } = useSWR(
    canManage ? `/employees/${id}/edit-data` : null,
    async () => {
      const [data, positionsList] = await Promise.all([
        api(`/employees/${id}`),
        api("/master/positions?active_only=1"),
      ]);

      return { data, positionsList };
    }
  );

  useEffect(() => {
    if (swrErr) {
      setErr(swrErr.message);
    }

    if (!swrData || formInitialized) return;

    const positionsList = Array.isArray(swrData.positionsList) ? swrData.positionsList : [];
    const data = swrData.data;
    const activePosition = positionsList.find((position) => String(position.id) === String(data.position_id));
    const positionRate = activePosition?.allowance_rates?.find(
      (rate) => rate.allowance_type?.code === "position"
    );

    setPositions(positionsList);
    setForm({
      employee_code: data.employee_code ?? "",
      name: data.name ?? "",
      join_date: data.join_date ?? "",
      position_id: data.position_id ?? "",
      position_allowance: positionRate?.rate_amount ?? "",
      base_salary_basis: data.salary_profile_summary?.base_salary_basis ?? activePosition?.base_salary_basis ?? "daily",
      base_salary_amount:
        data.salary_profile_summary?.base_salary_amount ?? activePosition?.default_base_salary_amount ?? "",
      num_toddlers: data.num_toddlers ? String(data.num_toddlers) : "",
      is_trainer: !!data.is_trainer,
      nik: data.nik ?? "",
      npwp: data.npwp ?? "",
      phone: data.phone ?? "",
      address: data.address ?? "",
      bank_name: data.bank_name ?? "",
      bank_account_name: data.bank_account_name ?? "",
      bank_account_number: data.bank_account_number ?? "",
    });
    setFormInitialized(true);
  }, [swrData, swrErr, formInitialized]);

  function setField(key, value) {
    setForm((prev) => ({ ...prev, [key]: value }));
    setErr("");
  }

  function setDigitField(key, value, maxLength) {
    setField(key, digitsOnly(value, maxLength));
  }

  function setNumToddlersField(value) {
    setField("num_toddlers", nonNegativeIntegerInput(value));
  }

  async function submit(event) {
    event.preventDefault();
    setErr("");
    setSaving(true);

    try {
      await api(`/employees/${id}`, {
        method: "PUT",
        body: {
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
        },
      });

      try {
        const me = await api("/me");
        updateAuthUser({ name: me?.name, role: me?.role });
      } catch {
        updateAuthUser({ name: form.name });
      }

      nav(`/employees/${id}`, { replace: true });
    } catch (error) {
      setErr(error.message);
    } finally {
      setSaving(false);
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
        title="Edit Pegawai"
        description="Perbarui data orangnya. Aturan gaji pokok dan tunjangan tetap mengikuti master jabatan aktif."
        actions={
          <Button
            variant="outline"
            className="rounded border-slate-200 bg-white/70 hover:bg-white"
            onClick={() => nav(-1)}
          >
            Kembali
          </Button>
        }
      />

      <AlertMessage type="error" message={err} className="px-3 py-2" />

      {isLoading ? (
        <EmployeeSectionCard title="Memuat Data" description="Menyiapkan data pegawai dan master jabatan.">
          <p className="text-xs text-muted-foreground">Memuat data...</p>
        </EmployeeSectionCard>
      ) : (
        <form onSubmit={submit} className="space-y-5">
          <EmployeeSectionCard
            title="Informasi Dasar"
            description="Bagian inti identitas karyawan yang tampil di seluruh modul."
          >
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <Input label="Kode Pegawai" value={form.employee_code} readOnly disabled onChange={() => {}} />
              <Input
                label="Nama"
                value={form.name}
                required
                onChange={(value) => setField("name", value)}
              />
              <Input
                label="Tanggal Masuk"
                type="date"
                value={form.join_date}
                onChange={(value) => setField("join_date", value)}
              />
              <EmployeeNotice tone="info" className="md:col-span-2">
                Perubahan tanggal masuk memengaruhi titik awal histori jabatan dan profil gaji karyawan.
              </EmployeeNotice>
            </div>
          </EmployeeSectionCard>

          <EmployeeSectionCard
            title="Informasi Jabatan & Payroll"
            description="Jabatan aktif tidak diedit langsung dari halaman ini agar histori promosi dan demosi tetap rapi."
          >
            <div className="space-y-4">
              <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div className="space-y-1">
                  <label className="text-xs font-medium text-slate-600">Jabatan Aktif</label>
                  <select
                    value={form.position_id}
                    disabled={!!swrData?.data?.position_id}
                    onChange={(e) => setForm({ ...form, position_id: e.target.value })}
                    className={`w-full rounded-xl border px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-indigo-300 focus:ring-4 focus:ring-indigo-200/40 ${
                      !!swrData?.data?.position_id ? 'bg-slate-100 border-slate-200' : 'bg-white border-slate-300'
                    }`}
                  >
                    <option value="">-- Pilih Jabatan --</option>
                    {positions.map((position) => (
                      <option key={position.id} value={position.id}>
                        {position.name} ({position.code.toUpperCase()})
                      </option>
                    ))}
                  </select>
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
                </div>
              </div>

              <EmployeeNotice>
                {!!swrData?.data?.position_id 
                  ? "Jabatan aktif diubah melalui proses promosi atau demosi supaya histori jabatan dan perubahan payroll tetap konsisten."
                  : "Pilih jabatan awal untuk karyawan ini. Setelah disimpan, perubahan selanjutnya harus melalui proses promosi atau demosi."}
              </EmployeeNotice>

              {selectedPosition ? (
                <EmployeeNotice tone="info">
                  Jabatan aktif: <b>{selectedPosition.name}</b>. Nominal gaji pokok dan tunjangan jabatan dikelola oleh Finance pada master payroll.
                </EmployeeNotice>
              ) : (
                <EmployeeNotice>Data jabatan aktif belum terbaca.</EmployeeNotice>
              )}
            </div>
          </EmployeeSectionCard>



          <EmployeeSectionCard
            title="Data Pribadi"
            description="Data pribadi sensitif dipisahkan dari data jabatan agar mudah diaudit dan dijelaskan."
          >
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <Input
                label="NIK"
                value={form.nik}
                onChange={(value) => setDigitField("nik", value, 32)}
                inputMode="numeric"
                maxLength={32}
                autoComplete="off"
              />
              <Input
                label="NPWP"
                value={form.npwp}
                onChange={(value) => setDigitField("npwp", value, 32)}
                inputMode="numeric"
                maxLength={32}
                autoComplete="off"
              />
              <Input
                label="No. Telepon"
                value={form.phone}
                onChange={(value) => setDigitField("phone", value, 20)}
                inputMode="numeric"
                maxLength={20}
                autoComplete="off"
              />
              <Textarea
                label="Alamat"
                value={form.address}
                onChange={(value) => setField("address", value)}
              />
            </div>
          </EmployeeSectionCard>

          <EmployeeSectionCard
            title="Informasi Bank"
            description="Data rekening dipisahkan agar fokus saat pengecekan transfer payroll."
          >
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <Input
                label="Nama Bank"
                value={form.bank_name}
                onChange={(value) => setField("bank_name", value)}
              />
              <Input
                label="Nama Pemilik Rekening"
                value={form.bank_account_name}
                onChange={(value) => setField("bank_account_name", value)}
              />
              <Input
                label="Nomor Rekening"
                value={form.bank_account_number}
                onChange={(value) => setDigitField("bank_account_number", value, 50)}
                inputMode="numeric"
                maxLength={50}
                autoComplete="off"
              />
            </div>
          </EmployeeSectionCard>

          <div className="sticky bottom-0 z-10 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white/95 px-4 py-3 shadow-sm backdrop-blur">
            <div className="text-xs text-slate-500">
              Perubahan data pribadi langsung memengaruhi tampilan detail pegawai setelah disimpan.
            </div>
            <div className="flex flex-wrap gap-3">
              <Button
                type="button"
                variant="outline"
                className="rounded border-slate-200 bg-white hover:bg-slate-50"
                onClick={() => nav(-1)}
              >
                Batal
              </Button>
              <Button
                type="submit"
                disabled={saving}
                className="rounded bg-blue-600 px-4 py-1.5 text-xs font-medium text-white transition-colors hover:bg-blue-700"
              >
                {saving ? "Menyimpan..." : "Simpan Perubahan"}
              </Button>
            </div>
          </div>
        </form>
      )}
    </div>
  );
}

function Input({
  label,
  value,
  onChange,
  required,
  readOnly,
  disabled,
  type = "text",
  maxLength,
  inputMode,
  autoComplete,
}) {
  return (
    <div className="space-y-1.5">
      <label className="text-xs font-medium text-slate-600">{label}</label>
      <input
        type={type}
        maxLength={maxLength}
        inputMode={inputMode}
        autoComplete={autoComplete}
        className={[
          "w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-sky-300 focus:ring-4 focus:ring-sky-200/40",
          readOnly || disabled ? "cursor-not-allowed bg-slate-100 text-slate-600" : "bg-white",
        ].join(" ")}
        value={value}
        required={required}
        readOnly={readOnly}
        disabled={disabled}
        onChange={(event) => onChange?.(event.target.value)}
      />
    </div>
  );
}

function Textarea({ label, value, onChange }) {
  return (
    <div className="space-y-1.5 md:col-span-2">
      <label className="text-xs font-medium text-slate-600">{label}</label>
      <textarea
        rows={3}
        className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-sky-300 focus:ring-4 focus:ring-sky-200/40"
        value={value}
        onChange={(event) => onChange(event.target.value)}
      />
    </div>
  );
}
