import { useMemo, useState } from "react";
import useSWR from "swr";
import { useNavigate, useParams } from "react-router-dom";
import { getUser } from "@/lib/auth";
import { api } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import AlertMessage from "@/components/AlertMessage";
import StatusBadge from "@/components/StatusBadge";
import EmployeeHistoryHub from "@/components/EmployeeHistoryHub";
import {
  EmployeeDisplayField,
  EmployeeNotice,
  EmployeePageHeader,
  EmployeeSectionCard,
} from "@/components/employee/EmployeePageBlocks";
import { X, CheckCircle2 } from "lucide-react";

function maskValue(value, keep = 4) {
  const text = String(value ?? "").trim();
  if (!text) return "-";
  if (text.length <= keep) return "*".repeat(text.length);
  return "*".repeat(Math.max(0, text.length - keep)) + text.slice(-keep);
}

function maskEmail(email) {
  const text = String(email || "").trim();
  if (!text || !text.includes("@")) return "-";
  const [name, domain] = text.split("@");
  if (!name) return `****@${domain}`;
  const keep = Math.min(2, name.length);
  return `${name.slice(0, keep)}${"*".repeat(Math.max(0, name.length - keep))}@${domain}`;
}

function roleLabel(role) {
  const normalized = String(role || "").toLowerCase();
  return (
    {
      fat: "Finance Admin",
      director: "Director",
      staff: "Staff",
      employee: "Staff",
      hcga: "HCGA",
      admin: "Admin",
    }[normalized] || normalized.toUpperCase() || "-"
  );
}

function formatCurrencyValue(num) {
  if (!Number.isFinite(num) || num <= 0) {
    return "-";
  }

  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(num);
}

function formatBaseSalaryBasisValue(basis) {
  if (!basis) return "-";
  return basis === "monthly" ? "Bulanan" : "Harian";
}

export default function EmployeeDetailPage() {
  const { id } = useParams();
  const nav = useNavigate();
  const user = getUser();
  const role = String(user?.role || "").toLowerCase();

  const isHCGA = role === "hcga";
  const isFAT = role === "fat";

  const { data: rawEmp, error: errEmp, isLoading, mutate } = useSWR(`/employees/${id}`);

  const emp = rawEmp;
  const err = errEmp?.message || "";

  const [reveal, setReveal] = useState(false);
  const [activeTab, setActiveTab] = useState("info");
  const [confirmResetModalOpen, setConfirmResetModalOpen] = useState(false);
  const [resetModalOpen, setResetModalOpen] = useState(false);
  const [resetting, setResetting] = useState(false);
  const [newPassword, setNewPassword] = useState("");
  const [resetError, setResetError] = useState("");

  const isOwner =
    !!emp?.user_id &&
    !!user?.id &&
    Number(emp.user_id) === Number(user.id);

  const masked = !!emp?.masked;
  const canToggleSensitive = !masked && (isHCGA || isOwner || isFAT);
  const canViewAccount = isHCGA || isOwner || isFAT;
  const hasAccount = !!emp?.user;

  const piiView = useMemo(() => {
    const isMasked = masked || !reveal;

    return {
      nik: isMasked ? maskValue(emp?.nik) : emp?.nik || "-",
      npwp: isMasked ? maskValue(emp?.npwp) : emp?.npwp || "-",
      phone: isMasked ? maskValue(emp?.phone) : emp?.phone || "-",
      address: isMasked ? "**************" : emp?.address || "-",
      bank: isMasked
        ? `***** (${emp?.bank_name || "-"})`
        : `${emp?.bank_account_number || "-"} a.n ${emp?.bank_account_name || "-"} (${emp?.bank_name || "-"})`,
    };
  }, [emp, masked, reveal]);

  const accountView = useMemo(() => {
    if (!hasAccount) {
      return { status: "Belum ada akun", name: "-", role: "-", email: "-" };
    }

    const accountRole = emp?.user?.role;
    const accountEmail = emp?.user?.email;

    return {
      status: "Sudah ada akun",
      name: emp?.user?.name || "-",
      role: roleLabel(accountRole),
      email: isHCGA ? (accountEmail || "-") : maskEmail(accountEmail),
    };
  }, [emp, hasAccount, isHCGA]);

  const employmentFields = [
    {
      label: "Jabatan Aktif",
      value: emp?.Position ? `${emp.Position.name} (${String(emp.Position.code || "").toUpperCase()})` : (emp?.position || "-"),
      helper: emp?.Position?.level ? `Level ${emp.Position.level}` : undefined,
    },
    {
      label: "Basis Gaji Pokok",
      value: formatBaseSalaryBasisValue(emp?.salary_profile_summary?.base_salary_basis),
    },
    {
      label: "Jumlah Balita",
      value: String(emp?.num_toddlers || 0),
    },
  ];

  const handleResetPasswordClick = () => {
    setConfirmResetModalOpen(true);
  };

  const performResetPassword = async () => {
    setConfirmResetModalOpen(false);
    setResetError("");
    setResetting(true);
    try {
      const res = await api(`/employees/${id}/reset-password`, { method: "POST" });
      setNewPassword(res.new_password);
      setResetModalOpen(true);
    } catch (err) {
      window.alert(err.message || "Gagal mereset password.");
    } finally {
      setResetting(false);
    }
  };

  return (
    <div className="space-y-6">
      <EmployeePageHeader
        title="Detail Pegawai"
        description="Lihat ringkasan data orang, jabatan aktif, payroll, dan data sensitif yang melekat pada pegawai ini."
        actions={
          <>
            <Button
              variant="outline"
              className="rounded border-slate-200 bg-white/70 hover:bg-white"
              onClick={() => nav(-1)}
            >
              Kembali
            </Button>
            {isHCGA ? (
              <>
                <Button
                  variant="outline"
                  className="rounded border-slate-200 bg-white hover:bg-slate-50"
                  onClick={() => nav(`/employees/${id}/edit`)}
                >
                  Edit Data
                </Button>
              </>
            ) : null}
          </>
        }
      />

      <AlertMessage type="error" message={err} className="px-3 py-2" />

      <div className="flex flex-wrap gap-2 border-b border-slate-200">
        {[
          { id: "info", label: "Profil Pegawai" },
          { id: "history", label: "Riwayat Gaji & Jabatan" },
        ].map((tab) => (
          <button
            key={tab.id}
            onClick={() => setActiveTab(tab.id)}
            className={[
              "rounded-t-xl border border-b-0 px-4 py-2.5 text-sm font-medium transition-colors",
              activeTab === tab.id
                ? "border-slate-200 bg-white text-slate-900"
                : "border-transparent bg-transparent text-slate-500 hover:text-slate-700",
            ].join(" ")}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {activeTab === "info" ? (
        isLoading ? (
          <EmployeeSectionCard title="Memuat Data" description="Menyiapkan detail pegawai.">
            <p className="text-xs text-muted-foreground">Memuat data...</p>
          </EmployeeSectionCard>
        ) : !emp ? (
          <EmployeeSectionCard title="Data Tidak Ditemukan">
            <p className="text-xs text-muted-foreground">Data pegawai tidak ditemukan.</p>
          </EmployeeSectionCard>
        ) : (
          <div className="space-y-5">
            <EmployeeSectionCard
              title="Informasi Dasar"
              description="Ringkasan identitas utama yang dipakai lintas modul."
              actions={
                <div className="flex flex-wrap items-center gap-2">
                  <StatusBadge status={emp.status} />
                  {canToggleSensitive ? (
                    <Button
                      variant="outline"
                      className="rounded border-slate-200 bg-white hover:bg-slate-50"
                      onClick={() => setReveal((current) => !current)}
                    >
                      {reveal ? "Sembunyikan Data Sensitif" : "Tampilkan Data Sensitif"}
                    </Button>
                  ) : null}
                </div>
              }
            >
              <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <EmployeeDisplayField label="Kode Pegawai" value={emp.employee_code} />
                <EmployeeDisplayField label="Nama" value={emp.name} />
                <EmployeeDisplayField label="Tanggal Masuk" value={emp.join_date || "-"} />
                <EmployeeDisplayField label="Posisi Tampil" value={emp.position || "-"} />
              </div>
            </EmployeeSectionCard>

            <EmployeeSectionCard
              title="Informasi Jabatan & Payroll"
              description="Jabatan aktif menentukan basis gaji pokok dan komponen payroll bawaan."
            >
              <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                {employmentFields.map((field) => (
                  <EmployeeDisplayField
                    key={field.label}
                    label={field.label}
                    value={field.value}
                    helper={field.helper}
                  />
                ))}

                </div>
              </EmployeeSectionCard>

            {canViewAccount ? (
              <EmployeeSectionCard
                title="Informasi Akun"
                description="Keterkaitan akun login pegawai dengan akses ke aplikasi."
                actions={
                  <div className="flex gap-2 items-center">
                    {hasAccount ? (
                      <Badge className="rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700">
                        sudah ada akun
                      </Badge>
                    ) : (
                      <Badge className="rounded-full border border-slate-200 bg-slate-50 text-slate-700">
                        belum ada akun
                      </Badge>
                    )}
                    {hasAccount && isHCGA ? (
                      <Button
                        variant="outline"
                        className="rounded border-rose-200 text-rose-600 hover:bg-rose-50 flex items-center gap-2"
                        onClick={handleResetPasswordClick}
                        disabled={resetting}
                      >
                        {resetting ? "Memproses..." : "Reset Password"}
                      </Button>
                    ) : null}
                  </div>
                }
              >
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <EmployeeDisplayField label="Status Akun" value={accountView.status} />
                  <EmployeeDisplayField label="Role Akun" value={accountView.role} />
                  <EmployeeDisplayField label="Nama Akun" value={accountView.name} />
                  <EmployeeDisplayField label="Email Akun" value={accountView.email} mono />
                </div>

                {!isHCGA && hasAccount ? (
                  <div className="mt-4">
                    <EmployeeNotice>Email dimasking untuk keamanan. HCGA tetap bisa melihat email penuh.</EmployeeNotice>
                  </div>
                ) : null}

                {!hasAccount && isHCGA ? (
                  <div className="mt-4">
                    <EmployeeNotice>
                      Akun login bisa dibuat saat input pegawai baru atau melalui alur administrasi akun terpisah.
                    </EmployeeNotice>
                  </div>
                ) : null}
              </EmployeeSectionCard>
            ) : null}

            <EmployeeSectionCard
              title="Data Pribadi"
              description="Data sensitif pegawai yang dibatasi tampilannya sesuai hak akses."
            >
              {masked ? (
                <div className="mb-4">
                  <EmployeeNotice tone="warning">
                    Informasi sensitif sedang disembunyikan karena akun ini tidak memiliki akses penuh.
                  </EmployeeNotice>
                </div>
              ) : null}
              <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <EmployeeDisplayField label="NIK" value={piiView.nik} mono />
                <EmployeeDisplayField label="NPWP" value={piiView.npwp} mono />
                <EmployeeDisplayField label="No. Telepon" value={piiView.phone} mono />
                <EmployeeDisplayField label="Alamat" value={piiView.address} full />
              </div>
            </EmployeeSectionCard>

            <EmployeeSectionCard
              title="Informasi Bank"
              description="Data rekening dipisahkan agar mudah dicek pada tahap transfer payroll."
            >
              <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <EmployeeDisplayField label="Nama Bank" value={piiView.bank_name} />
                <EmployeeDisplayField label="Nama Pemilik Rekening" value={piiView.bank_account_name} />
                <EmployeeDisplayField
                  label="Nomor Rekening"
                  value={piiView.bank_account_number}
                  mono
                  full
                />
              </div>
            </EmployeeSectionCard>
          </div>
        )
      ) : (
        <EmployeeHistoryHub employeeId={id} employeeName={emp?.name} role={role} />
      )}

      {/* Reset Password Modal */}
      {resetModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onClick={() => setResetModalOpen(false)} />
          <div className="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-slate-200/50 animate-in fade-in zoom-in-95">
            <button
              onClick={() => setResetModalOpen(false)}
              className="absolute right-4 top-4 rounded-full p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
            >
              <X className="h-4 w-4" />
            </button>

            <div className="mb-6 flex flex-col items-center text-center">
              <div className="mb-4 rounded-full bg-emerald-100 p-3">
                <CheckCircle2 className="h-8 w-8 text-emerald-600" />
              </div>
              <h3 className="text-xl font-bold text-slate-900">Password Berhasil Di-reset</h3>
              <p className="mt-2 text-sm text-slate-500">
                Gunakan password di bawah ini untuk login kembali. Harap simpan dengan baik.
              </p>
            </div>

            <div className="mb-6 rounded-xl border border-dashed border-indigo-200 bg-indigo-50/50 p-4 text-center">
              <div className="text-xs font-semibold uppercase tracking-wider text-indigo-500 mb-1">
                Password Baru
              </div>
              <div className="text-2xl font-mono font-bold text-indigo-700 select-all">
                {newPassword}
              </div>
            </div>

            <Button
              className="w-full rounded-xl bg-indigo-600 py-2.5 font-semibold text-white shadow-sm hover:bg-indigo-700"
              onClick={() => setResetModalOpen(false)}
            >
              Tutup
            </Button>
          </div>
        </div>
      )}

      {/* Konfirmasi Reset Password Modal */}
      {confirmResetModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onClick={() => setConfirmResetModalOpen(false)} />
          <div className="relative w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-slate-200/50 animate-in fade-in zoom-in-95">
            <h3 className="mb-2 text-lg font-bold text-slate-900">Konfirmasi Reset</h3>
            <p className="mb-6 text-sm text-slate-500">
              Yakin ingin mereset password akun pegawai ini? Password lama tidak akan bisa digunakan lagi.
            </p>
            <div className="flex justify-end gap-3">
              <Button
                variant="outline"
                className="rounded-xl border-slate-200 hover:bg-slate-50"
                onClick={() => setConfirmResetModalOpen(false)}
              >
                Batal
              </Button>
              <Button
                className="rounded-xl bg-rose-600 font-semibold text-white hover:bg-rose-700"
                onClick={performResetPassword}
              >
                Ya, Reset
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
