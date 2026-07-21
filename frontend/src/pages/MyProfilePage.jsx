import { useEffect, useMemo, useState } from "react";
import useSWR from "swr";
import { Button } from "@/components/ui/button";
import AlertMessage from "@/components/AlertMessage";
import { getUser, updateAuthUser } from "@/lib/auth";
import { digitsOnly } from "@/lib/employeeFormHelpers";
import {
  updateMeEmployee,
  updateMe,
  updatePassword,
} from "@/lib/meApi";
import EmployeeHistoryHub from "@/components/EmployeeHistoryHub";

const EMPTY_EMP_FORM = {
  name: "",
  phone: "",
  address: "",
  nik: "",
  npwp: "",
  bank_name: "",
  bank_account_name: "",
  bank_account_number: "",
};

const EMPTY_META = {
  employee_code: "",
  position: "",
  status: "",
};

export default function MyProfilePage() {
  const authUser = getUser();
  const role = authUser?.role || "";
  const isStaff = role === "staff" || role === "employee";

  const [account, setAccount] = useState({
    name: authUser?.name || "",
    email: authUser?.email || "",
    role: role || "",
  });

  const [empForm, setEmpForm] = useState(EMPTY_EMP_FORM);
  const [empInitialForm, setEmpInitialForm] = useState(null);
  const [isEditingEmp, setIsEditingEmp] = useState(false);
  const [empSaving, setEmpSaving] = useState(false);
  const [empErr, setEmpErr] = useState("");
  const [empOk, setEmpOk] = useState("");

  const [pwForm, setPwForm] = useState({
    current_password: "",
    password: "",
    password_confirmation: "",
  });
  const [pwSaving, setPwSaving] = useState(false);
  const [pwErr, setPwErr] = useState("");
  const [pwOk, setPwOk] = useState("");

  const [staffHistoryTab, setStaffHistoryTab] = useState("profile");

  const [meta, setMeta] = useState(EMPTY_META);

  const empDirty = useMemo(() => {
    if (!empInitialForm) return false;
    return JSON.stringify(empInitialForm) !== JSON.stringify(empForm);
  }, [empForm, empInitialForm]);

  const { data: rawMe, error: errMe, isLoading: loadMe } = useSWR("/me");
  const { data: rawEmp, error: errEmp, isLoading: loadEmp } = useSWR(isStaff ? "/me/employee" : null);

  const accLoading = loadMe;
  const empLoading = loadEmp;

  useEffect(() => {
    if (rawMe) {
      const mapped = {
        name: rawMe.name || "",
        email: rawMe.email || "",
        role: rawMe.role || "",
      };
      setAccount(mapped);
    }
  }, [rawMe, errMe]);

  useEffect(() => {
    if (isStaff && rawEmp) {
      setMeta({
        employee_code: rawEmp.employee_code || "",
        position: rawEmp.position || "",
        status: rawEmp.status || "",
      });

      const mapped = {
        name: rawEmp.name || "",
        phone: rawEmp.phone || "",
        address: rawEmp.address || "",
        nik: rawEmp.nik || "",
        npwp: rawEmp.npwp || "",
        bank_name: rawEmp.bank_name || "",
        bank_account_name: rawEmp.bank_account_name || "",
        bank_account_number: rawEmp.bank_account_number || "",
      };

      setEmpForm(mapped);
      setEmpInitialForm({ ...mapped });
      setIsEditingEmp(false);
    }
    if (isStaff && errEmp) {
      setEmpErr(errEmp?.message || "Gagal memuat profil karyawan.");
    }
  }, [isStaff, rawEmp, errEmp]);

  const onChangePw = (k) => (e) => setPwForm((p) => ({ ...p, [k]: e.target.value }));

  const onSavePassword = async () => {
    setPwErr("");
    setPwOk("");

    if (!pwForm.current_password) return setPwErr("Password saat ini wajib diisi.");
    if (!pwForm.password) return setPwErr("Password baru wajib diisi.");
    if (pwForm.password.length < 8) return setPwErr("Password baru minimal 8 karakter.");
    if (pwForm.password !== pwForm.password_confirmation) {
      return setPwErr("Konfirmasi password tidak sama.");
    }

    setPwSaving(true);
    try {
      // ✅ FIX: payload sesuai backend (password + password_confirmation)
      const res = await updatePassword({
        current_password: pwForm.current_password,
        password: pwForm.password,
        password_confirmation: pwForm.password_confirmation,
      });

      setPwOk(res?.message || "Password berhasil diubah.");
      setPwForm({
        current_password: "",
        password: "",
        password_confirmation: "",
      });
    } catch (e) {
      setPwErr(e?.message || "Gagal mengubah password.");
    } finally {
      setPwSaving(false);
    }
  };

  const onChangeEmp = (k) => (e) => setEmpForm((p) => ({ ...p, [k]: e.target.value }));
  const onChangeEmpDigits = (k, maxLength) => (e) => setEmpForm((p) => ({ ...p, [k]: digitsOnly(e.target.value, maxLength) }));

  const onSaveEmp = async () => {
    if (!isEditingEmp) return;

    setEmpErr("");
    setEmpOk("");

    if (!empDirty) {
      setIsEditingEmp(false);
      return;
    }

    setEmpSaving(true);
    try {
      const res = await updateMeEmployee(empForm);

      if (empForm.name) updateAuthUser({ name: empForm.name });

      setEmpOk(res?.message || "Profil berhasil disimpan.");
      setEmpInitialForm({ ...empForm });
      setIsEditingEmp(false);
    } catch (e) {
      setEmpErr(e?.message || "Gagal menyimpan profil.");
    } finally {
      setEmpSaving(false);
    }
  };

  const onCancelEmp = () => {
    setEmpErr("");
    setEmpOk("");
    setIsEditingEmp(false);
    if (empInitialForm) setEmpForm({ ...empInitialForm });
  };

  // ===== LOADING GLOBAL =====
  if (accLoading || (isStaff && empLoading)) {
    return (
      <div className="bg-white border border-border rounded shadow-sm p-4">
        <div className="text-sm text-slate-600">Loading profil...</div>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {/* HEADER */}
      <div className="bg-white border border-border rounded shadow-sm p-4">
        <h1 className="text-lg font-semibold text-foreground">
          {isStaff ? "Profil & Riwayat" : "Keamanan Akun"}
        </h1>
        <p className="mt-1 text-sm text-slate-600">
          {isStaff ? "Kelola informasi pribadi, riwayat karir, dan keamanan akun Anda." : "Kelola keamanan dan ubah password akun Anda."}
        </p>
      </div>

      {/* ACCOUNT SETTINGS (ALL ROLES) */}
      <div className="bg-white border border-border rounded shadow-sm p-4">
        <div className="flex items-start justify-between gap-4 flex-wrap">
          <div>
            <div className="text-sm font-medium text-foreground">Account Settings</div>
            <div className="mt-1 text-sm text-slate-600">
              Informasi akun Anda.
            </div>
          </div>
        </div>

        <div className="mt-5 grid md:grid-cols-3 gap-5">
          <Input label="Nama" value={account.name} disabled />
          <Input label="Email" value={account.email} disabled />
          <Input label="Role" value={account.role} disabled />
        </div>
      </div>

      {/* PASSWORD SETTINGS (ALL ROLES) */}
      <div className="bg-white border border-border rounded shadow-sm p-4">
        <div className="flex items-start justify-between gap-4 flex-wrap">
          <div>
            <div className="text-sm font-medium text-foreground">Security</div>
            <div className="mt-1 text-sm text-slate-600">Disarankan ganti password secara berkala.</div>
          </div>

          <Button
            onClick={onSavePassword}
            disabled={pwSaving}
            className="bg-blue-600 rounded text-xs font-medium text-white hover:bg-blue-700 transition-colors"
          >
            {pwSaving ? "Menyimpan..." : "Update Password"}
          </Button>
        </div>

        <AlertMessage type="error" message={pwErr} className="mt-4" />
        <AlertMessage type="success" message={pwOk} className="mt-4" />

        <div className="mt-5 grid md:grid-cols-3 gap-5">
          <Input
            type="password"
            label="Current Password"
            value={pwForm.current_password}
            onChange={onChangePw("current_password")}
            placeholder="Password saat ini"
            autoComplete="new-password"
          />
          <Input
            type="password"
            label="New Password"
            value={pwForm.password}
            onChange={onChangePw("password")}
            placeholder="Minimal 8 karakter"
            autoComplete="new-password"
          />
          <Input
            type="password"
            label="Confirm New Password"
            value={pwForm.password_confirmation}
            onChange={onChangePw("password_confirmation")}
            placeholder="Ulangi password baru"
            autoComplete="new-password"
          />
        </div>
      </div>

      {/* EMPLOYEE SECTION (STAFF ONLY) */}
      {isStaff && (
        <>
          <div className="flex items-center gap-1 border-b border-slate-200">
            <button
              type="button"
              onClick={() => setStaffHistoryTab("profile")}
              className={`border-b-2 px-4 py-3 text-sm font-semibold transition-colors ${
                staffHistoryTab === "profile"
                  ? "border-blue-600 text-blue-700"
                  : "border-transparent text-slate-500 hover:text-slate-800"
              }`}
            >
              Informasi Karyawan
            </button>
            <button
              type="button"
              onClick={() => setStaffHistoryTab("history")}
              className={`border-b-2 px-4 py-3 text-sm font-semibold transition-colors ${
                staffHistoryTab === "history"
                  ? "border-blue-600 text-blue-700"
                  : "border-transparent text-slate-500 hover:text-slate-800"
              }`}
            >
              Riwayat Jabatan & Gaji
            </button>
          </div>

          {staffHistoryTab === "history" ? (
            rawEmp?.id ? <EmployeeHistoryHub employeeId={rawEmp.id} role={role} /> : null
          ) : (
            <>
              <div className="bg-white border border-border rounded shadow-sm p-4">
                <div className="text-sm font-medium text-foreground">Info Karyawan (read-only)</div>
                <div className="mt-4 grid md:grid-cols-3 gap-4">
                  <Field label="Employee Code" value={meta.employee_code} />
                  <Field label="Position" value={meta.position} />
                  <Field label="Status" value={meta.status || "-"} />
                </div>
              </div>

              <div className="bg-white border border-border rounded shadow-sm p-4">
                <div className="flex items-start justify-between gap-4 flex-wrap">
                  <div>
                    <div className="text-sm font-medium text-foreground">Private / Sensitive Info</div>
                    <div className="mt-1 text-sm text-slate-600">
                      {isEditingEmp ? "Mode edit aktif. Ubah data lalu simpan." : "Lihat data pribadi & rekening. Klik Edit untuk mengubah."}
                    </div>
                  </div>

                  {!isEditingEmp ? (
                    <Button
                      onClick={() => {
                        setEmpErr("");
                        setEmpOk("");
                        setIsEditingEmp(true);
                      }}
                      className="px-4 py-1.5 bg-blue-600 rounded text-xs font-medium text-white hover:bg-blue-700 transition-colors"
                    >
                      Edit
                    </Button>
                  ) : (
                    <div className="flex gap-2">
                      <Button variant="outline" onClick={onCancelEmp} disabled={empSaving} className="rounded font-extrabold">
                        Cancel
                      </Button>

                      <Button
                        onClick={onSaveEmp}
                        disabled={empSaving || !empDirty}
                        className="px-4 py-1.5 bg-blue-600 rounded text-xs font-medium text-white hover:bg-blue-700 transition-colors"
                      >
                        {empSaving ? "Menyimpan..." : "Simpan"}
                      </Button>
                    </div>
                  )}
                </div>

                <AlertMessage type="error" message={empErr} className="mt-4" />
                <AlertMessage type="success" message={empOk} className="mt-4" />

                <div className="mt-5 grid md:grid-cols-2 gap-5">
                  <Input label="Nama" value={empForm.name} onChange={onChangeEmp("name")} disabled={!isEditingEmp} />
                  <Input label="Phone" value={empForm.phone} onChange={onChangeEmpDigits("phone", 30)} disabled={!isEditingEmp} inputMode="numeric" maxLength={30} autoComplete="off" />
                  <Input label="NIK" value={empForm.nik} onChange={onChangeEmpDigits("nik", 32)} disabled={!isEditingEmp} inputMode="numeric" maxLength={32} autoComplete="off" />
                  <Input label="NPWP" value={empForm.npwp} onChange={onChangeEmpDigits("npwp", 32)} disabled={!isEditingEmp} inputMode="numeric" maxLength={32} autoComplete="off" />
                  <Input label="Bank Name" value={empForm.bank_name} onChange={onChangeEmp("bank_name")} disabled={!isEditingEmp} />
                  <Input
                    label="Bank Account Name"
                    value={empForm.bank_account_name}
                    onChange={onChangeEmp("bank_account_name")}
                    disabled={!isEditingEmp}
                  />
                  <Input
                    label="Bank Account Number"
                    value={empForm.bank_account_number}
                    onChange={onChangeEmpDigits("bank_account_number", 50)}
                    disabled={!isEditingEmp}
                    inputMode="numeric"
                    maxLength={50}
                    autoComplete="off"
                  />
                  <Textarea label="Address" value={empForm.address} onChange={onChangeEmp("address")} disabled={!isEditingEmp} />
                </div>
              </div>
            </>
          )}
        </>
      )}
    </div>
  );
}

function Field({ label, value }) {
  return (
    <div className="space-y-1">
      <div className="text-xs text-slate-500">{label}</div>
      <div className="font-medium text-foreground">{value ?? "-"}</div>
    </div>
  );
}

function Input({ label, type = "text", ...props }) {
  const [show, setShow] = useState(false);
  const isPassword = type === "password";
  const inputType = isPassword ? (show ? "text" : "password") : type;

  return (
    <div className="space-y-2">
      <div className="text-xs font-semibold text-slate-700">{label}</div>
      <div className="relative">
        <input
          {...props}
          type={inputType}
          className={[
            "w-full rounded border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition",
            "focus:border-sky-300 focus:ring-4 focus:ring-sky-200/40",
            props.disabled ? "opacity-70 cursor-not-allowed bg-slate-50" : "",
            isPassword ? "pr-10" : ""
          ].join(" ")}
        />
        {isPassword && (
          <button
            type="button"
            className="absolute inset-y-0 right-0 px-3 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none"
            onClick={() => setShow(!show)}
            tabIndex="-1"
            title={show ? "Sembunyikan password" : "Lihat password"}
          >
            {show ? (
              <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
              </svg>
            ) : (
              <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
            )}
          </button>
        )}
      </div>
    </div>
  );
}

function Textarea({ label, ...props }) {
  return (
    <div className="space-y-2 md:col-span-2">
      <div className="text-xs font-semibold text-slate-700">{label}</div>
      <textarea
        {...props}
        rows={4}
        className={[
          "w-full rounded border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition",
          "focus:border-sky-300 focus:ring-4 focus:ring-sky-200/40",
          props.disabled ? "opacity-70 cursor-not-allowed bg-slate-50" : "",
        ].join(" ")}
      />
    </div>
  );
}
