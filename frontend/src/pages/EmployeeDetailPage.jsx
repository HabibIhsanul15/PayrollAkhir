import { useEffect, useState, useMemo } from "react";
import useSWR from "swr";
import { useParams, Link, useNavigate } from "react-router-dom";
import { api } from "@/lib/api";
import { getUser } from "@/lib/auth";

import { Button } from "@/components/ui/button";
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import StatusBadge from "@/components/StatusBadge";
import EmployeeHistoryHub from "@/components/EmployeeHistoryHub";
import EmployeeMutationModal from "@/components/EmployeeMutationModal";


function maskValue(v, keep = 4) {
  const s = String(v ?? "").trim();
  if (!s) return "-";
  if (s.length <= keep) return "•".repeat(s.length);
  return "•".repeat(Math.max(0, s.length - keep)) + s.slice(-keep);
}

function maskEmail(email) {
  const e = String(email || "").trim();
  if (!e || !e.includes("@")) return "-";
  const [name, domain] = e.split("@");
  if (!name) return `••••@${domain}`;
  const keep = Math.min(2, name.length);
  return `${name.slice(0, keep)}${"•".repeat(Math.max(0, name.length - keep))}@${domain}`;
}

function roleLabel(role) {
  const r = String(role || "").toLowerCase();
  return (
    {
      fat: "Finance Admin",
      director: "Director",
      staff: "Staff",
      employee: "Staff",
      hcga: "HCGA",
      admin: "Admin",
    }[r] || r || "-"
  );
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
  const loading = isLoading;
  const err = errEmp?.message || "";

  const [reveal, setReveal] = useState(false);
  const [activeTab, setActiveTab] = useState("info");
  const [isMutationModalOpen, setIsMutationModalOpen] = useState(false);

  const fetchEmployee = () => {
    mutate();
  };

  const masked = !!emp?.masked;

  // owner = employee ini adalah user yang sedang login
  const isOwner =
    !!emp?.user_id &&
    !!user?.id &&
    Number(emp.user_id) === Number(user.id);

  // boleh toggle sensitive kalau tidak masked oleh backend dan role berhak
  const canToggleSensitive = !masked && (isHCGA || isOwner || isFAT);

  const piiView = useMemo(() => {
    const isMasked = masked || !reveal;

    return {
      nik: isMasked ? maskValue(emp?.nik) : emp?.nik || "-",
      npwp: isMasked ? maskValue(emp?.npwp) : emp?.npwp || "-",
      phone: isMasked ? maskValue(emp?.phone) : emp?.phone || "-",
      address: isMasked
        ? emp?.address
          ? "•••••• (hidden)"
          : "-"
        : emp?.address || "-",

      bank_name: emp?.bank_name || "-",
      bank_account_name: emp?.bank_account_name || "-",
      bank_account_number: isMasked
        ? maskValue(emp?.bank_account_number)
        : emp?.bank_account_number || "-",
    };
  }, [emp, masked, reveal]);

  // ===== ACCOUNT INFO =====
  // Backend idealnya mengirim emp.user = {id,name,email,role}
  const hasAccount = !!emp?.user;
  const canViewAccount = isHCGA || isOwner || isFAT; // kalau FAT nggak mau, hapus isFAT
  const accountView = useMemo(() => {
    if (!hasAccount) {
      return {
        status: "Belum ada akun",
        email: "-",
        role: "-",
        name: "-",
      };
    }

    const accRole = emp?.user?.role;
    const accEmail = emp?.user?.email;

    return {
      status: "Sudah ada akun",
      name: emp?.user?.name || "-",
      role: roleLabel(accRole),
      // HCGA lihat full, selain itu dimasking
      email: isHCGA ? (accEmail || "-") : maskEmail(accEmail),
    };
  }, [emp, hasAccount, isHCGA]);

  return (
    <div>
      {/* soft background */}
      <div className="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
        <div className="absolute -top-40 -left-40 h-[520px] w-[520px] rounded-full bg-sky-200/45 blur-3xl" />
        <div className="absolute -bottom-44 -right-44 h-[620px] w-[620px] rounded-full bg-indigo-200/35 blur-3xl" />
      </div>

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
              Employee Detail
            </h1>
            <p className="mt-1 text-sm text-slate-600">
              Detail data pegawai untuk kebutuhan payroll dan administrasi.
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <Button
              variant="outline"
              className="rounded bg-white/70 border-slate-200 hover:bg-white"
              onClick={() => nav(-1)}
            >
              Back
            </Button>

            {isHCGA && (
              <>
                <Button
                  variant="outline"
                  className="rounded border-slate-200 bg-white hover:bg-slate-50"
                  onClick={() => nav(`/employees/${id}/edit`)}
                >
                  Edit
                </Button>
                <Button
                  className="rounded bg-indigo-600 hover:bg-indigo-700 text-white"
                  onClick={() => setIsMutationModalOpen(true)}
                >
                  Mutasi / Promosi
                </Button>
              </>
            )}
          </div>
        </div>

        {err && (
          <div className="rounded bg-rose-50 px-3 py-2 text-xs text-rose-600 border border-rose-100">
            {err}
          </div>
        )}

        {/* TABS */}
        <div className="flex space-x-1 border-b border-slate-200">
          <button
            onClick={() => setActiveTab("info")}
            className={`px-4 py-2.5 text-sm font-semibold transition-colors border-b-2 ${
              activeTab === "info"
                ? "border-indigo-600 text-indigo-700 bg-indigo-50/50"
                : "border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300"
            }`}
          >
            Profil & Informasi Dasar
          </button>
          <button
            onClick={() => setActiveTab("history")}
            className={`px-4 py-2.5 text-sm font-semibold transition-colors border-b-2 ${
              activeTab === "history"
                ? "border-indigo-600 text-indigo-700 bg-indigo-50/50"
                : "border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300"
            }`}
          >
            Riwayat Gaji & Jabatan
          </button>
        </div>

        {activeTab === "info" && (
          <Card className="bg-white border border-border rounded shadow-sm">
            <CardHeader>
            <div className="flex items-center justify-between gap-4 flex-wrap">
              <CardTitle className="text-base">Employee Information</CardTitle>

              <div className="flex items-center gap-2">
                <StatusBadge status={emp?.status} />

                {canToggleSensitive && (
                  <Button
                    variant="outline"
                    className="rounded border-slate-200 bg-white hover:bg-slate-50"
                    onClick={() => setReveal((v) => !v)}
                  >
                    {reveal ? "Hide Sensitive" : "Show Sensitive"}
                  </Button>
                )}
              </div>
            </div>
          </CardHeader>

          <CardContent>
            {loading ? (
              <p className="text-xs text-muted-foreground">Loading...</p>
            ) : !emp ? (
              <p className="text-xs text-muted-foreground">Data tidak ditemukan.</p>
            ) : (
              <div className="space-y-8">
                {/* basic */}
                <section className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <Field label="Employee Code" value={emp.employee_code} />
                  <Field label="Name" value={emp.name} />
                  <Field label="Department" value={emp.department} />
                  <Field label="Position" value={emp.position} />
                </section>

                {/* kepegawaian & payroll */}
                <section className="space-y-4">
                  <h3 className="text-sm font-bold text-slate-900">
                    Employment & Payroll Info (Fase 1)
                  </h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field label="Jabatan (Level)" value={emp.grade ? `${emp.grade.name} (${emp.grade.code.toUpperCase()})` : "-"} />
                    <Field label="Employment Type" value={emp.employment_type ? emp.employment_type.name : "-"} />
                    <Field label="Work Basis" value={emp.work_basis ? emp.work_basis.name : "-"} />
                    <Field label="Jumlah Balita (Childcare)" value={String(emp.num_toddlers || 0)} />

                    <div className="space-y-1">
                      <div className="text-xs font-medium text-slate-600">Trainer Category</div>
                      <div className="mt-1">
                        {emp.is_trainer ? (
                          <Badge className="rounded-full border border-sky-200 bg-sky-50 text-sky-700 font-semibold px-3 py-1 text-xs">
                            Trainer (1.5x Tunjangan Training)
                          </Badge>
                        ) : (
                          <Badge className="rounded-full border border-slate-200 bg-slate-50 text-slate-700 font-semibold px-3 py-1 text-xs">
                            Bukan Trainer
                          </Badge>
                        )}
                      </div>
                    </div>

                    <div className="space-y-1">
                      <div className="text-xs font-medium text-slate-600">Probation Status</div>
                      <div className="mt-1">
                        {emp.is_on_probation ? (
                          <Badge className="rounded-full border border-amber-200 bg-amber-50 text-amber-700 font-semibold px-3 py-1 text-xs animate-pulse">
                            Probation (Tunjangan Jabatan 50%)
                          </Badge>
                        ) : (
                          <Badge className="rounded-full border border-slate-200 bg-slate-50 text-slate-700 font-semibold px-3 py-1 text-xs">
                            Regular
                          </Badge>
                        )}
                      </div>
                    </div>
                  </div>
                </section>

                {/* account */}
                {canViewAccount && (
                  <section className="space-y-4">
                    <div className="flex items-center justify-between flex-wrap gap-2">
                      <h3 className="text-sm font-bold text-slate-900">
                        Account Information
                      </h3>

                      {hasAccount ? (
                        <Badge className="rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700">
                          sudah ada akun
                        </Badge>
                      ) : (
                        <Badge className="rounded-full border border-slate-200 bg-slate-50 text-slate-700">
                          belum ada akun
                        </Badge>
                      )}
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <Field label="Account Status" value={accountView.status} />
                      <Field label="Account Role" value={accountView.role} />
                      <Field label="Account Name" value={accountView.name} />
                      <Field label="Account Email" value={accountView.email} mono />
                    </div>

                    {!isHCGA && hasAccount && (
                      <div className="text-[11px] text-slate-500">
                        * Email dimasking untuk keamanan (hanya HCGA yang bisa lihat full).
                      </div>
                    )}

                    {!hasAccount && isHCGA && (
                      <div className="text-[11px] text-slate-500">
                        * Akun bisa dibuat dari menu <b>Create Account</b>.
                      </div>
                    )}
                  </section>
                )}

                {/* private */}
                <section className="space-y-4">
                  <h3 className="text-sm font-bold text-slate-900">
                    Private / Sensitive Info
                  </h3>

                  {masked && (
                    <div className="rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                      Info sensitif disembunyikan (akses terbatas).
                    </div>
                  )}

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field label="NIK" value={piiView.nik} mono />
                    <Field label="NPWP" value={piiView.npwp} mono />
                    <Field label="Phone" value={piiView.phone} mono />
                    <Field label="Address" value={piiView.address} full />
                  </div>
                </section>

                {/* bank */}
                <section className="space-y-4">
                  <h3 className="text-sm font-bold text-slate-900">
                    Bank Information
                  </h3>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field label="Bank Name" value={piiView.bank_name} />
                    <Field label="Account Name" value={piiView.bank_account_name} />
                    <Field
                      label="Account Number"
                      value={piiView.bank_account_number}
                      mono
                      full
                    />
                  </div>
                </section>
              </div>
            )}
          </CardContent>
        </Card>
        )}

        {activeTab === "history" && (
          <EmployeeHistoryHub employeeId={id} employeeName={emp?.name} />
        )}
      </div>

      <EmployeeMutationModal
        isOpen={isMutationModalOpen}
        onClose={() => setIsMutationModalOpen(false)}
        employee={emp}
        onSuccess={() => {
          setIsMutationModalOpen(false);
          fetchEmployee(); // Refresh data setelah mutasi
        }}
      />
    </div>
  );
}

function Field({ label, value, full, mono }) {
  return (
    <div className={full ? "md:col-span-2 space-y-1" : "space-y-1"}>
      <div className="text-xs font-medium text-slate-600">{label}</div>
      <div
        className={[
          "bg-white border border-border rounded shadow-sm px-4 py-3 text-sm text-slate-900",
          mono ? "font-mono" : "font-semibold",
        ].join(" ")}
      >
        {value ?? "-"}
      </div>
    </div>
  );
}
