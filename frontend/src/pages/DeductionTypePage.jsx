import { useEffect, useState } from "react";
import useSWR from "swr";
import { getUser } from "@/lib/auth";
import { api } from "@/lib/api";
import { Button } from "@/components/ui/button";
import AlertMessage from "@/components/AlertMessage";
import { Badge } from "@/components/ui/badge";
import {
  Table,
  TableHeader,
  TableRow,
  TableHead,
  TableBody,
  TableCell,
} from "@/components/ui/table";

function makeCode(name) {
  return String(name || "")
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "_")
    .replace(/_+/g, "_")
    .replace(/^_+|_+$/g, "")
    .slice(0, 50);
}

function sortRows(rows) {
  return [...rows].sort((a, b) => Number(a.display_order || 0) - Number(b.display_order || 0));
}

export default function DeductionTypePage() {
  const user = getUser();
  const isFinance = String(user?.role || "").toLowerCase() === "fat";
  const { data, error, isLoading, mutate } = useSWR(isFinance ? "/master/deduction-types" : null);

  const [rows, setRows] = useState([]);
  const [modalOpen, setModalOpen] = useState(false);
  const [editId, setEditId] = useState(null);
  const [form, setForm] = useState({
    code: "",
    name: "",
    display_order: 0,
    description: "",
    is_active: true,
  });
  const [localError, setLocalError] = useState("");
  const [success, setSuccess] = useState("");

  useEffect(() => {
    setRows(sortRows(Array.isArray(data) ? data : data?.data || []));
  }, [data]);

  const openAdd = () => {
    setForm({ code: "", name: "", display_order: rows.length + 1, description: "", is_active: true });
    setEditId(null);
    setLocalError("");
    setModalOpen(true);
  };

  const openEdit = (row) => {
    setForm({
      code: row.code,
      name: row.name,
      display_order: row.display_order || 0,
      description: row.description || "",
      is_active: Boolean(row.is_active),
    });
    setEditId(row.id);
    setLocalError("");
    setModalOpen(true);
  };

  const submit = async (event) => {
    event.preventDefault();
    setLocalError("");
    setSuccess("");

    try {
      const payload = { ...form, code: editId ? form.code : makeCode(form.name) };
      const url = editId ? `/master/deduction-types/${editId}` : "/master/deduction-types";
      const saved = await api(url, { method: editId ? "PUT" : "POST", body: payload });

      setRows((current) => sortRows(editId
        ? current.map((row) => row.id === editId ? saved : row)
        : [...current, saved]));
      setModalOpen(false);
      setSuccess(editId ? "Jenis potongan berhasil diperbarui." : "Jenis potongan berhasil ditambahkan.");
      mutate();
    } catch (saveError) {
      setLocalError(saveError?.message || "Gagal menyimpan jenis potongan.");
    }
  };

  const remove = async (id) => {
    if (!confirm("Hapus jenis potongan ini?")) return;
    setLocalError("");
    setSuccess("");

    try {
      await api(`/master/deduction-types/${id}`, { method: "DELETE" });
      setRows((current) => current.filter((row) => row.id !== id));
      setSuccess("Jenis potongan berhasil dihapus.");
      mutate();
    } catch (removeError) {
      setLocalError(removeError?.message || "Jenis potongan sudah digunakan dan tidak dapat dihapus.");
    }
  };

  if (!isFinance) {
    return <AlertMessage type="error" message="Halaman ini hanya dapat diakses oleh Finance." />;
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
          <h1 className="mt-4 text-lg font-semibold text-foreground">Jenis Potongan</h1>
          <p className="mt-1 text-sm text-slate-600">
            Kelola pilihan potongan manual yang dapat ditambahkan ke payroll.
          </p>
        </div>
          <div className="flex flex-wrap items-center gap-2">
          <Button
            variant="outline"
            onClick={() => mutate()}
            disabled={isLoading}
            className="bg-white border border-border rounded text-xs text-muted-foreground hover:text-foreground transition-colors"
          >
            {isLoading ? "Menyegarkan..." : "Segarkan"}
          </Button>
          <Button
            onClick={openAdd}
            className="px-4 py-1.5 bg-blue-600 rounded text-xs font-medium text-white hover:bg-blue-700 transition-colors"
          >
            + Tambah Potongan
          </Button>
        </div>
      </div>

      <AlertMessage type="error" message={localError || error?.message} />
      <AlertMessage type="success" message={success} />

      <div className="bg-white border border-border rounded shadow-sm overflow-hidden">
        <div className="px-4 py-3 border-b border-slate-200/70 flex justify-between">
          <span className="text-sm font-medium">Daftar Jenis Potongan</span>
          <span className="text-xs text-slate-500">{isLoading ? "Memuat..." : `${rows.length} jenis`}</span>
        </div>
        <div className="overflow-x-auto">
          <div className="px-8">
          <Table className="min-w-[800px]">
            <TableHeader>
              <TableRow className="bg-slate-50/80">
                <TableHead className="text-slate-700 pl-6 w-[300px]">Jenis Potongan</TableHead>
                <TableHead className="text-slate-700 w-[220px]">Cara Input</TableHead>
                <TableHead className="text-slate-700 w-[100px]">Status</TableHead>
                <TableHead className="text-center text-slate-700 w-[160px] pr-6">Aksi</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {!isLoading && rows.length === 0 && (
                <TableRow><TableCell colSpan={4} className="py-10 text-center text-slate-500">Belum ada data.</TableCell></TableRow>
              )}
              {rows.map((row) => (
                <TableRow key={row.id}>
                  <TableCell className="font-medium text-foreground py-4 pl-6">
                    <div className="font-semibold">{row.name}</div>
                    <div className="text-[11px] text-slate-500 font-normal">{row.description || "-"}</div>
                  </TableCell>
                  <TableCell className="py-4">
                    <Badge variant="outline" className="text-indigo-700 border-indigo-200 bg-indigo-50">
                      Input manual
                    </Badge>
                  </TableCell>
                  <TableCell className="py-4">
                    <Badge className={row.is_active
                      ? "rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700"
                      : "rounded-full border border-slate-200 bg-slate-50 text-slate-700"}>
                      {row.is_active ? "Aktif" : "Nonaktif"}
                    </Badge>
                  </TableCell>
                  <TableCell className="py-4 pr-6">
                    <div className="flex justify-center gap-2">
                      <Button size="sm" variant="outline" onClick={() => openEdit(row)}>Edit</Button>
                      <Button size="sm" variant="destructive" onClick={() => remove(row.id)}>Hapus</Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
          </div>
        </div>
      </div>

      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
          <div className="w-full max-w-md bg-white border border-border rounded shadow-sm p-4 my-4">
            <h2 className="text-lg font-bold text-slate-900 mb-4">
              {editId ? "Edit Jenis Potongan" : "Tambah Jenis Potongan"}
            </h2>
            <form onSubmit={submit} className="space-y-4">
              <div>
                <label className="block text-xs font-semibold mb-1">Nama Jenis Potongan</label>
                <input
                  value={form.name}
                  onChange={(event) => setForm((current) => ({
                    ...current,
                    name: event.target.value,
                    code: editId ? current.code : makeCode(event.target.value),
                  }))}
                  placeholder="Contoh: BPJS Kesehatan"
                  required
                  className="w-full border rounded px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold mb-1">Keterangan</label>
                <textarea
                  value={form.description}
                  onChange={(event) => setForm((current) => ({ ...current, description: event.target.value }))}
                  rows={2}
                  className="w-full border rounded px-3 py-2 text-sm"
                />
              </div>
              <label className="flex items-center gap-2 text-sm font-medium">
                <input
                  type="checkbox"
                  checked={form.is_active}
                  onChange={(event) => setForm((current) => ({ ...current, is_active: event.target.checked }))}
                />
                Jenis potongan aktif
              </label>
              <div className="flex justify-end gap-2 pt-3 border-t">
                <Button type="button" variant="outline" onClick={() => setModalOpen(false)}>Batal</Button>
                <Button type="submit">Simpan</Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
