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

export default function AllowanceTypePage() {
  const user = getUser();
  const role = String(user?.role || "").toLowerCase();
  const isHCGA = role === "hcga";

  const [success, setSuccess] = useState("");

  const { data, error, isLoading, mutate } = useSWR(isHCGA ? "/master/allowance-types" : null);

  const loading = isLoading;
  const err = error?.message;

  const rawData = Array.isArray(data) ? data : data?.data ?? [];
  const [localRows, setLocalRows] = useState([]);

  useEffect(() => {
    if (rawData) setLocalRows(rawData);
  }, [rawData]);

  const rows = localRows;

  function load() {
    mutate();
  }

  // Form State
  const [modalOpen, setModalOpen] = useState(false);
  const [isEdit, setIsEdit] = useState(false);
  const [editId, setEditId] = useState(null);
  const [form, setForm] = useState({
    code: "",
    name: "",
    calculation_type: "per_mandays",
    applies_to: "all",
    display_order: 0,
    description: "",
    is_active: true,
  });



  const openAddModal = () => {
    setForm({
      code: "",
      name: "",
      calculation_type: "per_mandays",
      applies_to: "all",
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
      applies_to: r.applies_to,
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
    try {
      if (isEdit) {
        const updated = await api(`/master/allowance-types/${editId}`, {
          method: "PUT",
          body: form,
        });
        setRows((prev) => prev.map((x) => (x.id === editId ? updated : x)).sort((a, b) => a.display_order - b.display_order));
        setSuccess("Jenis tunjangan berhasil diperbarui");
      } else {
        const created = await api("/master/allowance-types", {
          method: "POST",
          body: form,
        });
        setRows((prev) => [...prev, created].sort((a, b) => a.display_order - b.display_order));
        setSuccess("Jenis tunjangan baru berhasil dibuat");
      }
      setModalOpen(false);
    } catch (err) {
      setErr(err?.message || "Gagal menyimpan jenis tunjangan");
    }
  };

  const onDelete = async (id) => {
    const ok = confirm("Apakah Anda yakin ingin menghapus jenis tunjangan ini?");
    if (!ok) return;
    setErr("");
    setSuccess("");
    try {
      await api(`/master/allowance-types/${id}`, { method: "DELETE" });
      mutate();
      setSuccess("Tunjangan dihapus.");
    } catch (err) {
      setErr(err?.message || "Gagal menghapus jenis tunjangan");
    }
  };

  if (!isHCGA) {
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
              Kelola master jenis tunjangan beserta metode perhitungan dan sub-tipe partner yang berhak menerimanya.
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <Button
              variant="outline"
              onClick={load}
              disabled={loading}
              className="bg-white border border-border rounded text-xs text-muted-foreground hover:text-foreground transition-colors"
            >
              {loading ? "Menyegarkan..." : "Segarkan"}
            </Button>
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
                    <TableHead className="text-slate-700 pl-6 w-[150px]">Kode</TableHead>
                    <TableHead className="text-slate-700 w-[200px]">Nama</TableHead>
                    <TableHead className="text-slate-700 w-[150px]">Dasar Perhitungan</TableHead>
                    <TableHead className="text-slate-700 w-[120px]">Berlaku Untuk</TableHead>
                    <TableHead className="text-slate-700 w-[80px]">Urutan</TableHead>
                    <TableHead className="text-slate-700 w-[100px]">Status</TableHead>
                    <TableHead className="text-center text-slate-700 w-[160px] pr-6">Aksi</TableHead>
                  </TableRow>
                </TableHeader>

                <TableBody>
                  {loading && (
                    <TableRow>
                      <TableCell colSpan={7} className="py-12 text-center text-slate-500">
                        Memuat data...
                      </TableCell>
                    </TableRow>
                  )}

                  {!loading && rows.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={7} className="py-12 text-center text-slate-500">
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
                        <TableCell className="pl-6 py-4 font-bold text-sky-700 uppercase">
                          {r.code}
                        </TableCell>
                        <TableCell className="font-medium text-foreground py-4">
                          <div className="font-semibold">{r.name}</div>
                          <div className="text-[11px] text-slate-500 font-normal">{r.description || "-"}</div>
                        </TableCell>
                        <TableCell className="py-4">
                          <Badge variant="outline" className="text-indigo-700 border-indigo-200 bg-indigo-50">
                            {r.calculation_type}
                          </Badge>
                        </TableCell>
                        <TableCell className="py-4">
                          <Badge variant="outline" className="text-sky-700 border-sky-200 bg-sky-50">
                            {r.applies_to}
                          </Badge>
                        </TableCell>
                        <TableCell className="font-semibold text-slate-700 py-4">
                          {r.display_order}
                        </TableCell>
                        <TableCell className="py-4">
                          {r.is_active ? (
                            <Badge className="rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700">
                              Active
                            </Badge>
                          ) : (
                            <Badge className="rounded-full border border-slate-200 bg-slate-50 text-slate-700">
                              Inactive
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
                              onClick={() => onDelete(r.id)}
                            >
                              Delete
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
                {isEdit ? "Edit Allowance Type" : "Add Allowance Type"}
              </h2>

              <form onSubmit={handleSubmit} className="space-y-4">
                <div>
                  <label className="block text-xs font-semibold text-slate-800 mb-1">
                    Allowance Code
                  </label>
                  <input
                    value={form.code}
                    onChange={(e) => setForm({ ...form, code: e.target.value })}
                    disabled={isEdit}
                    placeholder="e.g. meal, transport_trip"
                    required
                    className="w-full border border-border rounded bg-white px-3 py-1.5 text-xs focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-800 mb-1">
                    Name
                  </label>
                  <input
                    value={form.name}
                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                    placeholder="e.g. Tunjangan Makan"
                    required
                    className="w-full border border-border rounded bg-white px-3 py-1.5 text-xs focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-800 mb-1">
                    Calculation Type
                  </label>
                  <select
                    value={form.calculation_type}
                    onChange={(e) => setForm({ ...form, calculation_type: e.target.value })}
                    className="w-full border border-border rounded bg-white px-3 py-1.5 text-xs focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all"
                  >
                    <option value="per_mandays">per_mandays</option>
                    <option value="per_trip">per_trip</option>
                    <option value="flat">flat</option>
                    <option value="formula">formula</option>
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-800 mb-1">
                    Applies To (Partner Type)
                  </label>
                  <select
                    value={form.applies_to}
                    onChange={(e) => setForm({ ...form, applies_to: e.target.value })}
                    className="w-full border border-border rounded bg-white px-3 py-1.5 text-xs focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all"
                  >
                    <option value="all">all</option>
                    <option value="project_only">project_only</option>
                    <option value="fix_rate_only">fix_rate_only</option>
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-800 mb-1">
                    Display Order
                  </label>
                  <input
                    type="number"
                    min="0"
                    value={form.display_order}
                    onChange={(e) => setForm({ ...form, display_order: parseInt(e.target.value) || 0 })}
                    required
                    className="w-full border border-border rounded bg-white px-3 py-1.5 text-xs focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-800 mb-1">
                    Description
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
                    Allowance is Active
                  </label>
                </div>

                <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => setModalOpen(false)}
                    className="px-4 py-1.5 bg-white border border-border rounded text-xs text-muted-foreground hover:text-foreground transition-colors"
                  >
                    Cancel
                  </Button>
                  <Button
                    type="submit"
                    className="px-4 py-1.5 bg-blue-600 rounded text-xs font-medium text-white hover:bg-blue-700 transition-colors"
                  >
                    Save
                  </Button>
                </div>
              </form>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
