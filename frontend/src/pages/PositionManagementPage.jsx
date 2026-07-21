import { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import useSWR from "swr";
import { Edit3, Plus, SlidersHorizontal, Trash2 } from "lucide-react";
import { getUser } from "@/lib/auth";
import { api } from "@/lib/api";
import { formatRupiah } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { CurrencyInput } from "@/components/ui/CurrencyInput";
import { Badge } from "@/components/ui/badge";
import { useConfirm } from "@/components/ConfirmProvider";
import AlertMessage from "@/components/AlertMessage";
import {
  Table,
  TableHeader,
  TableRow,
  TableHead,
  TableBody,
  TableCell,
} from "@/components/ui/table";

const inputClass =
  "w-full rounded border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500";

function digitsOnly(value, maxLength = 30) {
  return String(value ?? "").replace(/\D/g, "").slice(0, maxLength);
}

function makeJobCode(name) {
  const words = String(name || "")
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9\s]/g, " ")
    .split(/\s+/)
    .filter(Boolean);

  if (words.length === 0) return "";
  if (words.length > 1) return words.map((word) => word[0]).join("").slice(0, 20);
  return words[0].slice(0, 30);
}

function makeUniqueJobCode(name, rows, ignoreId = null) {
  const base = makeJobCode(name);
  if (!base) return "";

  const usedCodes = new Set(
    rows
      .filter((row) => (ignoreId ? row.id !== ignoreId : true))
      .map((row) => String(row.code || "").toLowerCase())
  );
  if (!usedCodes.has(base)) return base;

  let index = 2;
  let candidate = `${base}-${index}`;
  while (usedCodes.has(candidate)) {
    index += 1;
    candidate = `${base}-${index}`;
  }
  return candidate;
}

function normalizeName(name) {
  return String(name || "").trim().replace(/\s+/g, " ").toLowerCase();
}

export default function PositionManagementPage() {
  const navigate = useNavigate();
  const user = getUser();
  const role = String(user?.role || "").toLowerCase();
  const isHCGA = role === "hcga";
  const isFinance = role === "fat";
  const canAccessPage = isHCGA || isFinance;
  const { confirm } = useConfirm();

  const { data, error, isLoading, mutate } = useSWR(canAccessPage ? "/master/positions" : null);
  const [rows, setRows] = useState([]);
  const [localErr, setErr] = useState("");
  const [success, setSuccess] = useState("");
  const [modalOpen, setModalOpen] = useState(false);
  const [isEdit, setIsEdit] = useState(false);
  const [editId, setEditId] = useState(null);
  const [form, setForm] = useState({
    code: "",
    name: "",
    level: "",
    default_base_salary_amount: "",
    default_late_penalty_amount: "",
    description: "",
    is_active: true,
  });

  useEffect(() => {
    setRows(Array.isArray(data) ? data : data?.data ?? []);
  }, [data]);

  const summary = useMemo(() => {
    const total = rows.length;
    const active = rows.filter((row) => row.is_active).length;
    const inactive = total - active;
    return { total, active, inactive };
  }, [rows]);

  const err = localErr || error?.message || "";
  const duplicateName = useMemo(() => {
    const currentName = normalizeName(form.name);
    if (!currentName) return false;

    return rows.some((row) => {
      if (isEdit && row.id === editId) return false;
      return normalizeName(row.name) === currentName;
    });
  }, [rows, form.name, isEdit, editId]);

  function clearMessage() {
    setErr("");
    setSuccess("");
  }

  function openAddModal() {
    clearMessage();
    setForm({
      code: "",
      name: "",
      level: rows.length > 0 ? String(Math.max(...rows.map((row) => Number(row.level) || 1)) + 1) : "1",
      default_base_salary_amount: "",
      default_late_penalty_amount: "",
      description: "",
      is_active: true,
    });
    setEditId(null);
    setIsEdit(false);
    setModalOpen(true);
  }

  function openEditModal(row) {
    clearMessage();
    setForm({
      code: row.code || "",
      name: row.name || "",
      level: row.level ? String(row.level) : "1",
      default_base_salary_amount:
        row.default_base_salary_amount !== null && row.default_base_salary_amount !== undefined
          ? String(Math.trunc(Number(row.default_base_salary_amount)))
          : "",
      default_late_penalty_amount:
        row.default_late_penalty_amount !== null && row.default_late_penalty_amount !== undefined
          ? String(Math.trunc(Number(row.default_late_penalty_amount)))
          : "",
      description: row.description || "",
      is_active: !!row.is_active,
    });
    setEditId(row.id);
    setIsEdit(true);
    setModalOpen(true);
  }

  function updateName(value) {
    setForm((current) => ({
      ...current,
      name: value,
      code: makeUniqueJobCode(value, rows, isEdit ? editId : null),
    }));
  }

  async function handleSubmit(event) {
    event.preventDefault();
    clearMessage();

    const level = Number(form.level || 0);
    const salary = Number(form.default_base_salary_amount || 0);
    const penalty = Number(form.default_late_penalty_amount || 0);

    if (!form.name.trim()) {
      setErr("Nama jabatan wajib diisi.");
      return;
    }
    if (duplicateName) {
      setErr("Nama jabatan sudah ada. Gunakan nama jabatan lain agar data payroll tidak ambigu.");
      return;
    }
    if (!Number.isInteger(level) || level < 1) {
      setErr("Level harus angka bulat minimal 1.");
      return;
    }
    if (!Number.isInteger(salary) || salary < 0) {
      setErr("Nominal gaji pokok harian harus angka dan tidak boleh minus.");
      return;
    }
    if (!Number.isFinite(penalty) || penalty < 0) {
      setErr("Nominal denda keterlambatan harus angka dan tidak boleh minus.");
      return;
    }

    const payload = {
      code: form.code || makeUniqueJobCode(form.name, rows, isEdit ? editId : null),
      name: form.name.trim().replace(/\s+/g, " "),
      level,
      description: form.description || null,
      is_active: form.is_active,
      default_base_salary_amount: salary,
      default_late_penalty_amount: penalty,
    };

    try {
      if (isEdit) {
        const updated = await api(`/master/positions/${editId}`, { method: "PUT", body: payload });
        setRows((prev) =>
          prev.map((row) =>
            row.id === editId
              ? { ...row, ...updated, allowance_rates: updated.allowance_rates ?? row.allowance_rates }
              : row
          )
        );
        setSuccess("Jabatan berhasil diperbarui.");
      } else {
        const created = await api("/master/positions", { method: "POST", body: payload });
        setRows((prev) => [...prev, created].sort((a, b) => a.level - b.level));
        setSuccess("Jabatan baru berhasil dibuat.");
      }
      setModalOpen(false);
    } catch (submitErr) {
      setErr(submitErr?.message || "Gagal menyimpan jabatan.");
    }
  }

  async function onDelete(id) {
    const ok = await confirm("Apakah Anda yakin ingin menghapus jabatan ini?");
    if (!ok) return;

    clearMessage();
    try {
      await api(`/master/positions/${id}`, { method: "DELETE" });
      await mutate();
      setSuccess("Jabatan dihapus.");
    } catch (deleteErr) {
      setErr(deleteErr?.message || "Gagal menghapus jabatan.");
    }
  }

  if (!canAccessPage) {
    return (
      <AlertMessage type="error" message="Forbidden: Anda tidak memiliki akses ke halaman ini. Halaman ini hanya untuk HCGA." />
    );
  }

  return (
    <div className="space-y-5">
      <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
          <h1 className="text-lg font-semibold text-foreground">
            {isFinance ? "Gaji Jabatan" : "Master Jabatan"}
          </h1>
          <p className="mt-1 text-sm text-slate-600">
            {isFinance
              ? "Tentukan nominal gaji pokok harian untuk jabatan yang sudah dibuat HCGA."
              : "Kelola nama jabatan, level, status, dan struktur jabatan karyawan."}
          </p>
          <div className="mt-2 flex items-center gap-3 text-[10px] text-muted-foreground">
            <span>Total: <strong className="text-foreground">{summary.total}</strong></span>
            <span className="text-border">|</span>
            <span>Aktif: <strong className="text-foreground">{summary.active}</strong></span>
            <span className="text-border">|</span>
            <span>Nonaktif: <strong className="text-foreground">{summary.inactive}</strong></span>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-2">
            <Button
              variant="outline"
              onClick={() => navigate("/master/position-rates")}
              className="rounded border-slate-200 bg-white text-xs text-slate-700 hover:bg-slate-50"
            >
              <SlidersHorizontal size={13} />
              Tarif Tunjangan
            </Button>
          {isHCGA ? (
            <Button
              onClick={openAddModal}
              className="rounded bg-blue-600 px-4 py-1.5 text-xs font-medium text-white hover:bg-blue-700"
            >
              <Plus size={13} />
              Tambah Jabatan
            </Button>
          ) : null}
        </div>
      </div>

      <AlertMessage type="error" message={err} />
      <AlertMessage type="success" message={success} />

      <div className="overflow-hidden rounded border border-border bg-white shadow-sm">
        <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
          <span className="text-sm font-medium text-foreground">Daftar Jabatan</span>
          <span className="text-xs text-slate-500">{isLoading ? "Memuat..." : `${rows.length} jabatan`}</span>
        </div>

        <div className="overflow-x-auto">
          <Table className={isFinance ? "min-w-[760px]" : "min-w-[640px]"}>
            <TableHeader>
              <TableRow className="bg-slate-50">
                <TableHead className="pl-5 text-slate-700">Jabatan</TableHead>
                <TableHead className="w-[120px] text-slate-700">Level</TableHead>
                <TableHead className="w-[190px] text-slate-700">Gaji Pokok Harian</TableHead>
                <TableHead className="w-[170px] text-slate-700">Denda Terlambat</TableHead>
                <TableHead className="w-[110px] text-slate-700">Status</TableHead>
                <TableHead className="w-[180px] pr-5 text-right text-slate-700">Aksi</TableHead>
              </TableRow>
            </TableHeader>

            <TableBody>
              {isLoading ? (
                <TableRow>
                  <TableCell colSpan={6} className="py-10 text-center text-slate-500">
                    Memuat data jabatan...
                  </TableCell>
                </TableRow>
              ) : null}

              {!isLoading && rows.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={6} className="py-10 text-center text-slate-500">
                    Belum ada jabatan yang terdaftar.
                  </TableCell>
                </TableRow>
              ) : null}

              {!isLoading
                ? rows.map((row) => (
                    <TableRow key={row.id} className="align-middle hover:bg-slate-50/70">
                      <TableCell className="py-4 pl-5">
                        <div className="font-semibold text-slate-900">{row.name}</div>
                        <div className="mt-0.5 text-[11px] text-slate-500">
                          Kode sistem: <span className="font-mono uppercase">{row.code}</span>
                        </div>
                        {row.description ? (
                          <div className="mt-1 max-w-[360px] truncate text-[11px] text-slate-500">
                            {row.description}
                          </div>
                        ) : null}
                      </TableCell>

                      <TableCell className="py-4">
                        <Badge className="rounded-full border border-indigo-200 bg-indigo-50 text-indigo-700">
                          Level {row.level}
                        </Badge>
                      </TableCell>

                      <TableCell className="py-4 font-semibold text-slate-900">
                        {formatRupiah(row.default_base_salary_amount)}
                      </TableCell>
                      <TableCell className="py-4 font-semibold text-rose-600">
                        {formatRupiah(row.default_late_penalty_amount)}
                      </TableCell>

                      <TableCell className="py-4">
                        {row.is_active ? (
                          <Badge className="rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700">
                            Aktif
                          </Badge>
                        ) : (
                          <Badge className="rounded-full border border-slate-200 bg-slate-50 text-slate-700">
                            Nonaktif
                          </Badge>
                        )}
                      </TableCell>

                      <TableCell className="py-4 pr-5">
                        <div className="flex items-center gap-1 justify-end">
                          <Button
                            size="sm"
                            variant="outline"
                            className="rounded border-blue-200 bg-blue-50 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-100"
                            onClick={() => openEditModal(row)}
                            title="Edit jabatan"
                          >
                            <Edit3 size={13} />
                            Edit
                          </Button>
                          {isHCGA ? (
                            <Button
                              size="sm"
                              variant="destructive"
                              className="rounded px-3 text-xs font-semibold"
                              onClick={() => onDelete(row.id)}
                              title="Hapus jabatan"
                            >
                              <Trash2 size={13} />
                              Hapus
                            </Button>
                          ) : null}
                        </div>
                      </TableCell>
                    </TableRow>
                  ))
                : null}
            </TableBody>
          </Table>
        </div>
      </div>

      {modalOpen ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
          <div className="flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded bg-white shadow-xl">
            <div className="border-b border-slate-200 px-5 py-4">
              <h2 className="text-base font-semibold text-slate-900">
                {isEdit ? "Edit Jabatan" : "Tambah Jabatan"}
              </h2>
              <p className="mt-1 text-xs text-slate-500">
                Kode sistem dibuat otomatis dari nama jabatan dan harus unik.
              </p>
            </div>

            <form onSubmit={handleSubmit} className="space-y-5 overflow-y-auto p-5">
                <section className="space-y-4">
                  <SectionTitle title="Informasi Jabatan" description="Dipakai untuk struktur jabatan dan alur promosi/demosi." />
                  <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <Field label="Nama Jabatan" required>
                      <input
                        value={form.name}
                        onChange={(event) => updateName(event.target.value)}
                        placeholder="Contoh: Staff"
                        required
                        className={`${inputClass} ${duplicateName ? "border-rose-300 focus:border-rose-400 focus:ring-rose-100" : ""}`}
                      />
                      {duplicateName ? (
                        <p className="mt-1 text-[11px] text-rose-600">
                          Nama jabatan ini sudah ada. Jabatan harus unik.
                        </p>
                      ) : null}
                      {form.code ? (
                        <p className="mt-1 text-[11px] text-slate-500">
                          Kode otomatis: <span className="font-mono uppercase">{form.code}</span>
                        </p>
                      ) : null}
                    </Field>

                    <Field label="Level" helper="Level 1 adalah jabatan tertinggi.">
                      <input
                        type="text"
                        inputMode="numeric"
                        value={form.level}
                        onChange={(event) => setForm({ ...form, level: digitsOnly(event.target.value, 3) || "1" })}
                        required
                        className={inputClass}
                      />
                    </Field>

                    <Field label="Status" full>
                      <label className="flex min-h-[38px] items-center gap-2 rounded border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-800">
                        <input
                          type="checkbox"
                          checked={form.is_active}
                          onChange={(event) => setForm({ ...form, is_active: event.target.checked })}
                          className="h-4 w-4 rounded border-slate-200 text-sky-600 focus:ring-sky-500/40"
                        />
                        Jabatan aktif
                      </label>
                    </Field>

                    <Field label="Keterangan" full>
                      <textarea
                        value={form.description}
                        onChange={(event) => setForm({ ...form, description: event.target.value })}
                        placeholder="Opsional"
                        rows={3}
                        className={`${inputClass} min-h-[86px]`}
                      />
                    </Field>
                  </div>
                </section>
                
                <section className="space-y-4 mt-6 pt-6 border-t border-slate-100">
                  <SectionTitle
                    title="Nominal Gaji Jabatan"
                    description="Nominal ini dipakai sebagai gaji pokok harian saat payroll dihitung."
                  />
                  <div className="rounded border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">
                    <div className="font-semibold text-slate-900">{form.name}</div>
                    <div className="mt-1">Kode sistem: <span className="font-mono uppercase">{form.code || "-"}</span></div>
                    <div>Level: {form.level || "-"}</div>
                  </div>
                  <Field label="Nominal Gaji Pokok Harian" helper="Hanya angka, tidak boleh minus." required>
                    <CurrencyInput
                      value={form.default_base_salary_amount}
                      onChange={(value) =>
                        setForm({ ...form, default_base_salary_amount: value })
                      }
                      placeholder="Contoh: 75000"
                      required
                      className={inputClass}
                    />
                  </Field>
                  <Field label="Denda per Keterlambatan (Rp)" helper="Potongan otomatis per kali terlambat." required>
                    <CurrencyInput
                      value={form.default_late_penalty_amount}
                      onChange={(value) =>
                        setForm({ ...form, default_late_penalty_amount: value })
                      }
                      placeholder="Contoh: 50000"
                      required
                      className={inputClass}
                    />
                  </Field>
                </section>

              <div className="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setModalOpen(false)}
                  className="rounded border-slate-200 bg-white text-xs hover:bg-slate-50"
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  disabled={duplicateName}
                  className="rounded bg-blue-600 px-4 py-1.5 text-xs font-medium text-white hover:bg-blue-700"
                >
                  Simpan Jabatan
                </Button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </div>
  );
}

function SectionTitle({ title, description }) {
  return (
    <div>
      <h3 className="text-sm font-semibold text-slate-900">{title}</h3>
      {description ? <p className="mt-1 text-xs text-slate-500">{description}</p> : null}
    </div>
  );
}

function Field({ label, helper, full, required, children }) {
  return (
    <div className={full ? "space-y-1.5 md:col-span-2" : "space-y-1.5"}>
      <label className="text-xs font-medium text-slate-600">
        {label}
        {required ? <span className="text-rose-500"> *</span> : null}
      </label>
      {children}
      {helper ? <p className="text-[11px] text-slate-500">{helper}</p> : null}
    </div>
  );
}
