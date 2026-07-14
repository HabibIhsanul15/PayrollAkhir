import { useMemo, useState } from "react";
import useSWR from "swr";
import { useNavigate, useParams } from "react-router-dom";
import { getUser } from "@/lib/auth";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import AlertMessage from "@/components/AlertMessage";
import StatusBadge from "@/components/StatusBadge";
import EmployeeHistoryHub from "@/components/EmployeeHistoryHub";
import EmployeeMutationModal from "@/components/EmployeeMutationModal";
import {
  EmployeeDisplayField,
  EmployeeNotice,
  EmployeePageHeader,
  EmployeeSectionCard,
} from "@/components/employee/EmployeePageBlocks";

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
    }[normalized] || normalized || "-"
  );
}

function formatCurrencyValue(value) {
  const num = Number(value ?? 0);
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
  const [isMutationModalOpen, setIsMutationModalOpen] = useState(false);

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
      address: isMasked ? (emp?.address ? "**** (disembunyikan)" : "-") : emp?.address || "-",
      bank_name: emp?.bank_name || "-",
      bank_account_name: emp?.bank_account_name || "-",
      bank_account_number: isMasked ? maskValue(emp?.bank_account_number) : emp?.bank_account_number || "-",
    };
  }, [emp, masked, reveal]);

  const accountView = useMemo(() => {
    if (!hasAccount) {
      return {
        status: "Belum ada akun",
        email: "-",
        role: "-",
        name: "-",
      };
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
      value: emp?.grade ? `${emp.grade.name} (${String(emp.grade.code || "").toUpperCase()})` : emp?.position || "-",
      helper: emp?.grade?.level ? `Level ${emp.grade.level}` : undefined,
    },
    {
      label: "Basis Gaji Pokok",
      value: formatBaseSalaryBasisValue(emp?.salary_profile_summary?.base_salary_basis),
    },
    {
      label: "Nominal Gaji Pokok Default",
      value: formatCurrencyValue(emp?.salary_profile_summary?.base_salary_amount),
    },
    {
      label: "Jumlah Balita",
      value: String(emp?.num_toddlers || 0),
    },
  ];

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
                <Button
                  className="rounded bg-indigo-600 text-white hover:bg-indigo-700"
                  onClick={() => setIsMutationModalOpen(true)}
                >
                  Promosi / Demosi
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

                <EmployeeDisplayField
                  label="Kategori Trainer"
                  value={
                    emp.is_trainer ? (
                      <Badge className="rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700">
                        Trainer
                      </Badge>
                    ) : (
                      <Badge className="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700">
                        Bukan Trainer
                      </Badge>
                    )
                  }
                  helper={
                    emp.is_trainer
                      ? "Dipakai pada tunjangan training jika jabatan punya aturan trainer."
                      : "Belum ada flag trainer pada profil pegawai ini."
                  }
                />
              </div>
            </EmployeeSectionCard>

            {canViewAccount ? (
              <EmployeeSectionCard
                title="Informasi Akun"
                description="Keterkaitan akun login pegawai dengan akses ke aplikasi."
                actions={
                  hasAccount ? (
                    <Badge className="rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700">
                      sudah ada akun
                    </Badge>
                  ) : (
                    <Badge className="rounded-full border border-slate-200 bg-slate-50 text-slate-700">
                      belum ada akun
                    </Badge>
                  )
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
        <EmployeeHistoryHub employeeId={id} employeeName={emp?.name} />
      )}

      <EmployeeMutationModal
        isOpen={isMutationModalOpen}
        onClose={() => setIsMutationModalOpen(false)}
        employee={emp}
        onSuccess={() => {
          setIsMutationModalOpen(false);
          mutate();
        }}
      />
    </div>
  );
}
