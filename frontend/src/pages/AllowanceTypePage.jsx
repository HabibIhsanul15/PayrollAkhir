import { useEffect, useState } from "react";
import useSWR from "swr";
import { getUser } from "@/lib/auth";
import { api } from "@/lib/api";
import { Button } from "@/components/ui/button";
import AlertMessage from "@/components/AlertMessage";
import ConfirmModal from "@/components/ConfirmModal";
import { Badge } from "@/components/ui/badge";
import {
  Table,
  TableHeader,
  TableRow,
  TableHead,
  TableBody,
  TableCell,
} from "@/components/ui/table";

const CALC_OPTIONS = {
  per_mandays: "Per Kehadiran (Mandays)",
  per_trip: "Per Perjalanan (Trip)",
  flat: "Tetap Bulanan",
  per_toddler: "Per Balita (Toddler)",
};

const inputSourceLabels = {
  total_mandays: "Total hari dibayar",
  training_days: "Hari training",
  out_of_town_days: "Hari luar kota",
  wfo_days: "Hari WFO",
  wfh_days: "Hari WFH",
  business_trips: "Jumlah perjalanan dinas",
};

const inputSourceOptions = {
  per_mandays: [
    ["total_mandays", "Total hari dibayar"],
    ["training_days", "Hari training"],
    ["out_of_town_days", "Hari luar kota"],
    ["wfo_days", "Hari WFO"],
    ["wfh_days", "Hari WFH"],
  ],
  per_trip: [["business_trips", "Jumlah perjalanan dinas"]],
};

function sortByDisplayOrder(rows) {
  return [...rows].sort((a, b) => Number(a.display_order || 0) - Number(b.display_order || 0));
}

function makeAllowanceCode(name) {
  return String(name || "")
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "_")
    .replace(/_+/g, "_")
    .replace(/^_+|_+$/g, "")
    .slice(0, 50);
}

function calculationText(row) {
  if (row.calculation_type === "per_mandays") {
    return "Rekap Kehadiran";
  }

  if (row.calculation_type === "per_trip") {
    return "Per perjalanan dinas";
  }

  if (row.calculation_type === "flat") {
    return "Tetap bulanan";
  }

  return CALC_OPTIONS[row.calculation_type] || row.calculation_type;
}

function defaultInputSource(calculationType) {
  if (calculationType === "per_trip") return "business_trips";
  if (calculationType === "per_mandays") return "total_mandays";
  return "";
}

function normalizeFormByCalculation(form, calculationType) {
  return {
    ...form,
    calculation_type: calculationType,
    input_source: defaultInputSource(calculationType),
  };
}

export default function AllowanceTypePage() {
  const user = getUser();
  const role = String(user?.role || "").toLowerCase();
  const isHcga = role === "hcga";

  const [success, setSuccess] = useState("");
  const [localErr, setErr] = useState("");

  const { data, error, isLoading, mutate } = useSWR(isHcga ? "/master/allowance-types" : null);

  const loading = isLoading;
  const err = localErr || error?.message;

  const [localRows, setLocalRows] = useState([]);
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [isDeleting, setIsDeleting] = useState(false);

  useEffect(() => {
    setLocalRows(Array.isArray(data) ? data : data?.data ?? []);
  }, [data]);

  const rows = localRows;

  // Form State
  const [modalOpen, setModalOpen] = useState(false);
  const [isEdit, setIsEdit] = useState(false);
  const [editId, setEditId] = useState(null);
  const [form, setForm] = useState({
    code: "",
    name: "",
    calculation_type: "per_mandays",
    input_source: "total_mandays",
    display_order: 0,
    description: "",
    is_active: true,
  });



  const openAddModal = () => {
    setForm({
      code: "",
      name: "",
      calculation_type: "per_mandays",
      input_source: "total_mandays",
      display_order: rows.length,
      description: "",
      is_active: true,
    });
    setIsEdit(false);
    setModalOpen(true);
  };

  const openEditModal = (r) => {
    setForm({
      code: r.code,
      name: r.name,
      calculation_type: r.calculation_type,
      input_source: r.input_source || defaultInputSource(r.calculation_type),
      display_order: r.display_order,
      description: r.description || "",
      is_active: r.is_active,
    });
    setEditId(r.id);
    setIsEdit(true);
    setModalOpen(true);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setErr("");
    setSuccess("");
    const payload = { ...form };

    try {
      if (isEdit) {
        const updated = await api(`/master/allowance-types/${editId}`, {
          method: "PUT",
          body: payload,
        });
        setLocalRows((prev) => sortByDisplayOrder(prev.map((x) => (x.id === editId ? updated : x))));
        setSuccess("Jenis tunjangan berhasil diperbarui");
      } else {
        const created = await api("/master/allowance-types", {
          method: "POST",
          body: payload,
        });
        setLocalRows((prev) => sortByDisplayOrder([...prev, created]));
        setSuccess("Jenis tunjangan baru berhasil dibuat");
      }
      setModalOpen(false);
      mutate();
    } catch (err) {
      setErr(err?.message || "Gagal menyimpan jenis tunjangan");
    }
  };

  const openDeleteModal = (row) => {
    setErr("");
    setSuccess("");
    setDeleteTarget(row);
  };

  const onDelete = async () => {
    if (!deleteTarget || isDeleting) return;

    const id = deleteTarget.id;
    setErr("");
    setSuccess("");
    setIsDeleting(true);

    try {
      await api(`/master/allowance-types/${id}`, { method: "DELETE" });
      setLocalRows((prev) => prev.filter((row) => row.id !== id));
      mutate();
      setSuccess("Jenis tunjangan dihapus.");
      setDeleteTarget(null);
    } catch (err) {
      setErr(err?.message || "Gagal menghapus jenis tunjangan");
    } finally {
      setIsDeleting(false);
    }
  };

  if (!isHcga) {
    return (
      <AlertMessage type="error" message="Forbidden: Anda tidak memiliki akses ke halaman ini. Halaman ini hanya untuk HCGA." />
    );
  }

  return (
    <div>
      <div className="space-y-6">
        <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <div className="hidden">
              <span className="h-2 w-2 rounded-full bg-sky-500" />
              <span className="text-[10px] font-semibold text-muted-foreground">Master Data</span>
            </div>

            <h1 className="mt-4 text-lg font-semibold text-foreground">
              Jenis Tunjangan
            </h1>
            <p className="mt-1 text-sm text-slate-600">
              Kelola komponen tunjangan dan indikator rekap yang dipakai dalam perhitungan payroll.
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <Button
              onClick={openAddModal}
              className="px-4 py-1.5 bg-blue-600 rounded text-xs font-medium text-white hover:bg-blue-700 transition-colors"
            >
              + Tambah Tunjangan
            </Button>
          </div>
        </div>

        <AlertMessage type="error" message={err} />

        <AlertMessage type="success" message={success} />

        {/* Table list */}
        <div className="bg-white border border-border rounded shadow-sm overflow-hidden">
          <div className="px-4 py-3 border-b border-slate-200/70 flex items-center justify-between">
            <span className="text-sm font-medium text-foreground">Daftar Jenis Tunjangan</span>
            <span className="text-xs text-slate-500">
              {loading ? "Memuat..." : `${rows.length} jenis tunjangan`}
            </span>
          </div>

          <div className="overflow-x-auto">
            <div className="px-8">
              <Table className="min-w-[800px]">
                <TableHeader>
                  <TableRow className="bg-slate-50/80">
                    <TableHead className="text-slate-700 pl-6 w-[260px]">Jenis Tunjangan</TableHead>
                    <TableHead className="text-slate-700 w-[220px]">Cara Hitung</TableHead>
                    <TableHead className="text-slate-700 w-[100px]">Status</TableHead>
                    <TableHead className="text-center text-slate-700 w-[160px] pr-6">Aksi</TableHead>
                  </TableRow>
                </TableHeader>

                <TableBody>
                  {loading && (
                    <TableRow>
                      <TableCell colSpan={4} className="py-12 text-center text-slate-500">
                        Memuat data...
                      </TableCell>
                    </TableRow>
                  )}

                  {!loading && rows.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={4} className="py-12 text-center text-slate-500">
                        Belum ada jenis tunjangan yang terdaftar.
                      </TableCell>
                    </TableRow>
                  )}

                  {!loading &&
                    rows.map((r, idx) => (
                      <TableRow
                        key={r.id}
                        className={[
                          "transition align-middle",
                          idx % 2 === 0 ? "bg-white/40" : "bg-white/20",
                          "hover:bg-slate-50/80",
                        ].join(" ")}
                      >
                        <TableCell className="font-medium text-foreground py-4 pl-6">
                          <div className="font-semibold">{r.name}</div>
                          <div className="text-[11px] text-slate-500 font-normal">{r.description || "-"}</div>
                        </TableCell>
                        <TableCell className="py-4">
                          <Badge variant="outline" className="text-indigo-700 border-indigo-200 bg-indigo-50">
                            {calculationText(r)}
                          </Badge>
                          {r.input_source && (
                            <div className="mt-1 text-[11px] text-slate-500">
                              Pemicu: {inputSourceLabels[r.input_source] || r.input_source}
                            </div>
                          )}
                        </TableCell>
                        <TableCell className="py-4">
                          {r.is_active ? (
                            <Badge className="rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700">
                              Aktif
                            </Badge>
                          ) : (
                            <Badge className="rounded-full border border-slate-200 bg-slate-50 text-slate-700">
                              Nonaktif
                            </Badge>
                          )}
                        </TableCell>
                        <TableCell className="py-4 pr-6">
                          <div className="flex justify-center gap-2">
                            <Button
                              size="sm"
                              variant="outline"
                              className="rounded-xl border-slate-200 bg-white hover:bg-slate-50"
                              onClick={() => openEditModal(r)}
                            >
                              Edit
                            </Button>
                            <Button
                              size="sm"
                              variant="destructive"
                              className="rounded-xl"
                              onClick={() => openDeleteModal(r)}
                            >
                              Hapus
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                </TableBody>
              </Table>
            </div>
          </div>
        </div>

        {/* Modal Add/Edit */}
        {modalOpen && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-in fade-in duration-200">
            <div className="bg-white border border-border rounded shadow-sm p-4 my-4">
              <h2 className="text-xl font-black text-slate-900 mb-4">
                {isEdit ? "Edit Jenis Tunjangan" : "Tambah Jenis Tunjangan"}
              </h2>

              <form onSubmit={handleSubmit} className="space-y-4">
                <div>
                  <label className="block text-xs font-semibold text-slate-800 mb-1">
                    Nama Jenis Tunjangan
                  </label>
                  <input
                    value={form.name}
                    onChange={(e) =>
                      setForm({
                        ...form,
                        name: e.target.value,
                        code: isEdit ? form.code : makeAllowanceCode(e.target.value),
                      })
                    }
                    placeholder="Contoh: Tunjangan Makan"
                    required
                    className="w-full border border-border rounded bg-white px-3 py-1.5 text-xs focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-800 mb-1">
                    Cara Hitung
                  </label>
                  <select
                    value={form.calculation_type}
                    onChange={(e) => setForm((prev) => normalizeFormByCalculation(prev, e.target.value))}
                    className="w-full border border-border rounded bg-white px-3 py-1.5 text-xs focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all"
                  >
                    {Object.entries(CALC_OPTIONS).map(([val, label]) => (
                      <option key={val} value={val}>{label}</option>
                    ))}
                  </select>
                </div>

                {!["flat", "per_toddler"].includes(form.calculation_type) && (
                  <div>
                    <label className="block text-xs font-semibold text-slate-800 mb-1">
                      Sumber Pemicu
                    </label>
                    <select
                      value={form.input_source}
                      onChange={(e) => setForm((prev) => ({ ...prev, input_source: e.target.value }))}
                      required
                      className="w-full border border-border rounded bg-white px-3 py-1.5 text-xs focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all"
                    >
                      {(inputSourceOptions[form.calculation_type] || []).map(([value, label]) => (
                        <option key={value} value={value}>{label}</option>
                      ))}
                    </select>
                    <p className="mt-1 text-[11px] text-slate-500">
                      Nilai kehadiran pada sumber ini akan menjadi pemicu perhitungan tunjangan.
                    </p>
                  </div>
                )}

                <div className="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                  Dasar perhitungan: <strong>
                    {form.calculation_type === "flat"
                      ? "Nominal tetap per bulan"
                      : form.calculation_type === "per_toddler"
                      ? "Jumlah anak/balita dari profil pegawai"
                      : inputSourceLabels[form.input_source] || "Sumber kehadiran"}
                  </strong>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-800 mb-1">
                    Keterangan
                  </label>
                  <textarea
                    value={form.description}
                    onChange={(e) => setForm({ ...form, description: e.target.value })}
                    placeholder="Deskripsi jenis tunjangan..."
                    rows="2"
                    className="w-full border border-border rounded bg-white px-3 py-1.5 text-xs focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all"
                  />
                </div>

                <div className="flex items-center gap-2 py-1">
                  <input
                    type="checkbox"
                    id="is_active"
                    checked={form.is_active}
                    onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                    className="h-4 w-4 rounded border-slate-200 text-sky-600 focus:ring-sky-500/40"
                  />
                  <label htmlFor="is_active" className="text-xs font-semibold text-slate-800 cursor-pointer select-none">
                    Jenis tunjangan aktif
                  </label>
                </div>

                <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => setModalOpen(false)}
                    className="px-4 py-1.5 bg-white border border-border rounded text-xs text-muted-foreground hover:text-foreground transition-colors"
                  >
                    Batal
                  </Button>
                  <Button
                    type="submit"
                    className="px-4 py-1.5 bg-blue-600 rounded text-xs font-medium text-white hover:bg-blue-700 transition-colors"
                  >
                    Simpan
                  </Button>
                </div>
              </form>
            </div>
          </div>
        )}

        <ConfirmModal
          isOpen={!!deleteTarget}
          title="Hapus jenis tunjangan?"
          message={`Jenis tunjangan \"${deleteTarget?.name || "ini"}\" akan dihapus dan tidak dapat dikembalikan.`}
          confirmLabel="Ya, hapus"
          tone="danger"
          loading={isDeleting}
          onCancel={() => {
            if (!isDeleting) setDeleteTarget(null);
          }}
          onConfirm={onDelete}
        />
      </div>
    </div>
  );
}
