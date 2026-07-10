import { useEffect, useMemo, useState } from "react";
import useSWR from "swr";
import { Button } from "@/components/ui/button";
import AlertMessage from "@/components/AlertMessage";
import { getUser, updateAuthUser } from "@/lib/auth";
import {
  updateMeEmployee,
  updateMe,
  updatePassword,
} from "@/lib/meApi";

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
  department: "",
  position: "",
  status: "",
};

export default function MyProfilePage() {
  const authUser = getUser();
  const role = authUser?.role || "";
  const isStaff = role === "staff" || role === "employee";

  // ===== ACCOUNT (ALL ROLES) =====
  const [accSaving, setAccSaving] = useState(false);
  const [accErr, setAccErr] = useState("");
  const [accOk, setAccOk] = useState("");

  const [account, setAccount] = useState({
    name: authUser?.name || "",
    email: authUser?.email || "",
    role: role || "",
  });
  const [accountInitial, setAccountInitial] = useState(null);
  const [accEditing, setAccEditing] = useState(false);

  const accDirty = useMemo(() => {
    if (!accountInitial) return false;
    return accountInitial.name !== account.name;
  }, [account, accountInitial]);

  // ===== PASSWORD (ALL ROLES) =====
  const [pwSaving, setPwSaving] = useState(false);
  const [pwErr, setPwErr] = useState("");
  const [pwOk, setPwOk] = useState("");

  // ✅ FIX: field sesuai backend Laravel (password + password_confirmation)
  const [pwForm, setPwForm] = useState({
    current_password: "",
    password: "",
    password_confirmation: "",
  });

  // ===== EMPLOYEE PROFILE (STAFF ONLY) =====
  const [empSaving, setEmpSaving] = useState(false);
  const [empErr, setEmpErr] = useState("");
  const [empOk, setEmpOk] = useState("");

  const [isEditingEmp, setIsEditingEmp] = useState(false);
  const [empInitialForm, setEmpInitialForm] = useState(null);

  const [empForm, setEmpForm] = useState(EMPTY_EMP_FORM);
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
      setAccountInitial({ ...mapped });
      updateAuthUser({ name: mapped.name, email: mapped.email, role: mapped.role });
    }
    if (errMe) {
      setAccErr(errMe?.message || "Gagal memuat akun.");
    }
  }, [rawMe, errMe]);

  useEffect(() => {
    if (isStaff && rawEmp) {
      setMeta({
        employee_code: rawEmp.employee_code || "",
        department: rawEmp.department || "",
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

  // ===== HANDLERS =====
  const onChangeAccount = (k) => (e) => setAccount((p) => ({ ...p, [k]: e.target.value }));

  const onSaveAccount = async () => {
    if (!accEditing) return;

    setAccErr("");
    setAccOk("");

    if (!accDirty) {
      setAccEditing(false);
      return;
    }

    setAccSaving(true);
    try {
      const res = await updateMe({ name: account.name });

      const newName = res?.user?.name ?? res?.name ?? account.name;
      const newEmail = res?.user?.email ?? res?.email ?? account.email;
      const newRole = res?.user?.role ?? res?.role ?? account.role;

      const mapped = { name: newName, email: newEmail, role: newRole };
      setAccount(mapped);
      setAccountInitial({ ...mapped });
      setAccEditing(false);

      updateAuthUser({ name: mapped.name, email: mapped.email, role: mapped.role });
      setAccOk(res?.message || "Akun berhasil diperbarui.");
    } catch (e) {
      setAccErr(e?.message || "Gagal menyimpan akun.");
    } finally {
      setAccSaving(false);
    }
  };

  const onCancelAccount = () => {
    setAccErr("");
    setAccOk("");
    setAccEditing(false);
    if (accountInitial) setAccount({ ...accountInitial });
  };

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
        <h1 className="text-lg font-semibold text-foreground">My Profile</h1>
        <p className="mt-1 text-sm text-slate-600">
          Kelola informasi akun dan keamanan.{" "}
          {isStaff ? "Data karyawan bisa diubah lewat Edit." : "Untuk admin, fokus di akun & password."}
        </p>
      </div>

      {/* ACCOUNT SETTINGS (ALL ROLES) */}
      <div className="bg-white border border-border rounded shadow-sm p-4">
        <div className="flex items-start justify-between gap-4 flex-wrap">
          <div>
            <div className="text-sm font-medium text-foreground">Account Settings</div>
            <div className="mt-1 text-sm text-slate-600">
              {accEditing ? "Mode edit aktif. Ubah nama lalu simpan." : "Lihat info akun. Klik Edit untuk mengubah nama."}
            </div>
          </div>

          {!accEditing ? (
            <Button
              onClick={() => {
                setAccErr("");
                setAccOk("");
                setAccEditing(true);
              }}
              className="px-4 py-1.5 bg-blue-600 rounded text-xs font-medium text-white hover:bg-blue-700 transition-colors"
            >
              Edit
            </Button>
          ) : (
            <div className="flex gap-2">
              <Button variant="outline" onClick={onCancelAccount} disabled={accSaving} className="rounded font-extrabold">
                Cancel
              </Button>

              <Button
                onClick={onSaveAccount}
                disabled={accSaving || !accDirty}
                className="px-4 py-1.5 bg-blue-600 rounded text-xs font-medium text-white hover:bg-blue-700 transition-colors"
              >
                {accSaving ? "Menyimpan..." : "Simpan"}
              </Button>
            </div>
          )}
        </div>

        <AlertMessage type="error" message={accErr} className="mt-4" />
        <AlertMessage type="success" message={accOk} className="mt-4" />

        <div className="mt-5 grid md:grid-cols-3 gap-5">
          <Input label="Nama" value={account.name} onChange={onChangeAccount("name")} disabled={!accEditing} />
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
          />
          <Input
            type="password"
            label="New Password"
            value={pwForm.password}
            onChange={onChangePw("password")}
            placeholder="Minimal 8 karakter"
          />
          <Input
            type="password"
            label="Confirm New Password"
            value={pwForm.password_confirmation}
            onChange={onChangePw("password_confirmation")}
            placeholder="Ulangi password baru"
          />
        </div>
      </div>

      {/* EMPLOYEE SECTION (STAFF ONLY) */}
      {isStaff && (
        <>
          <div className="bg-white border border-border rounded shadow-sm p-4">
            <div className="text-sm font-medium text-foreground">Info Karyawan (read-only)</div>
            <div className="mt-4 grid md:grid-cols-4 gap-4">
              <Field label="Employee Code" value={meta.employee_code} />
              <Field label="Department" value={meta.department} />
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
              <Input label="Phone" value={empForm.phone} onChange={onChangeEmp("phone")} disabled={!isEditingEmp} />
              <Input label="NIK" value={empForm.nik} onChange={onChangeEmp("nik")} disabled={!isEditingEmp} />
              <Input label="NPWP" value={empForm.npwp} onChange={onChangeEmp("npwp")} disabled={!isEditingEmp} />
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
                onChange={onChangeEmp("bank_account_number")}
                disabled={!isEditingEmp}
              />
              <Textarea label="Address" value={empForm.address} onChange={onChangeEmp("address")} disabled={!isEditingEmp} />
            </div>
          </div>
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

function Input({ label, ...props }) {
  return (
    <div className="space-y-2">
      <div className="text-xs font-semibold text-slate-700">{label}</div>
      <input
        {...props}
        className={[
          "w-full rounded border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition",
          "focus:border-sky-300 focus:ring-4 focus:ring-sky-200/40",
          props.disabled ? "opacity-70 cursor-not-allowed bg-slate-50" : "",
        ].join(" ")}
      />
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
