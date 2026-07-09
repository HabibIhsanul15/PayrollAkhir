import { useEffect, useState } from "react";
import { getUser } from "@/lib/auth";
import { api } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import {
  Table,
  TableHeader,
  TableRow,
  TableHead,
  TableBody,
  TableCell,
} from "@/components/ui/table";

export default function GradeManagementPage() {
  const user = getUser();
  const role = String(user?.role || "").toLowerCase();
  const isHCGA = role === "hcga";

  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");
  const [success, setSuccess] = useState("");

  // Form State
  const [modalOpen, setModalOpen] = useState(false);
  const [isEdit, setIsEdit] = useState(false);
  const [editId, setEditId] = useState(null);
  const [form, setForm] = useState({
    code: "",
    name: "",
    level: 1,
    description: "",
    is_active: true,
    default_mandays_rate: "",
  });

  async function load() {
    setErr("");
    setLoading(true);
    try {
      const data = await api("/master/grades");
      setRows(Array.isArray(data) ? data : []);
    } catch (e) {
      setErr(e?.message || "Gagal load data grade");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    if (isHCGA) {
      load();
    }
  }, []); // eslint-disable-line

  const openAddModal = () => {
    setForm({
      code: "",
      name: "",
      level: rows.length > 0 ? Math.max(...rows.map((r) => r.level)) + 1 : 1,
      description: "",
      is_active: true,
      default_mandays_rate: "",
    });
    setIsEdit(false);
    setModalOpen(true);
  };

  const openEditModal = (r) => {
    setForm({
      code: r.code,
      name: r.name,
      level: r.level,
      description: r.description || "",
      is_active: r.is_active,
      default_mandays_rate: r.default_mandays_rate !== null && r.default_mandays_rate !== undefined ? String(r.default_mandays_rate) : "",
    });
    setEditId(r.id);
    setIsEdit(true);
    setModalOpen(true);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setErr("");
    setSuccess("");
    const payload = {
      ...form,
      default_mandays_rate: form.default_mandays_rate !== "" ? Number(form.default_mandays_rate) : null,
    };
    try {
      if (isEdit) {
        const updated = await api(`/master/grades/${editId}`, {
          method: "PUT",
          body: payload,
        });
        setRows((prev) => prev.map((x) => (x.id === editId ? updated : x)));
        setSuccess("Grade berhasil diperbarui");
      } else {
        const created = await api("/master/grades", {
          method: "POST",
          body: payload,
        });
        setRows((prev) => [...prev, created].sort((a, b) => a.level - b.level));
        setSuccess("Grade baru berhasil dibuat");
      }
      setModalOpen(false);
    } catch (err) {
      setErr(err?.message || "Gagal menyimpan grade");
    }
  };

  const onDelete = async (id) => {
    const ok = confirm("Apakah Anda yakin ingin menghapus grade ini?");
    if (!ok) return;
    setErr("");
    setSuccess("");
    try {
      await api(`/master/grades/${id}`, { method: "DELETE" });
      setRows((prev) => prev.filter((x) => x.id !== id));
      setSuccess("Grade berhasil dihapus");
    } catch (err) {
      setErr(err?.message || "Gagal menghapus grade");
    }
  };

  if (!isHCGA) {
    return (
      <div className="rounded bg-rose-50 px-3 py-2 text-xs text-rose-600 border border-rose-100">
        Forbidden: Anda tidak memiliki akses ke halaman ini. Halaman ini hanya untuk HCGA.
      </div>
    );
  }

  return (
    <div>
      {/* Background gradients */}
      <div className="space-y-6">
        <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <div className="hidden">
              <span className="h-2 w-2 rounded-full bg-sky-500" />
              <span className="text-[10px] font-semibold text-muted-foreground">Master Data</span>
            </div>

            <h1 className="mt-4 text-lg font-semibold text-foreground">
              Master Jabatan
            </h1>
            <p className="mt-1 text-sm text-slate-600">
              Kelola level jabatan karyawan (BOD, PM, PD, dll) beserta tingkatan level hierarkinya.
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <Button
              variant="outline"
              onClick={load}
              disabled={loading}
              className="bg-white border border-border rounded text-xs text-muted-foreground hover:text-foreground transition-colors"
            >
              {loading ? "Refreshing..." : "Refresh"}
            </Button>
            <Button
              onClick={openAddModal}
              className="px-4 py-1.5 bg-blue-600 rounded text-xs font-medium text-white hover:bg-blue-700 transition-colors"
            >
              + Add Grade
            </Button>
          </div>
        </div>

        {err && (
          <div className="rounded bg-rose-50 px-3 py-2 text-xs text-rose-600 border border-rose-100">
            {err}
          </div>
        )}

        {success && (
          <div className="rounded bg-emerald-50 px-3 py-2 text-xs text-emerald-600 border border-emerald-100">
            {success}
          </div>
        )}

        {/* Grades Table */}
          <div className="bg-white border border-border rounded shadow-sm overflow-hidden">
          <div className="px-4 py-3 border-b border-slate-200/70 flex items-center justify-between">
            <span className="text-sm font-medium text-foreground">Daftar Jabatan</span>
            <span className="text-xs text-slate-500">
              {loading ? "Memuat..." : `${rows.length} jabatan`}
            </span>
          </div>

          <div className="overflow-x-auto">
            <div className="px-8">
              <Table className="min-w-[900px]">
                <TableHeader>
                  <TableRow className="bg-slate-50/80">
                    <TableHead className="text-slate-700 pl-6 w-[120px]">Code</TableHead>
                    <TableHead className="text-slate-700 w-[180px]">Name</TableHead>
                    <TableHead className="text-slate-700 w-[90px]">Level</TableHead>
                    <TableHead className="text-slate-700 w-[130px]">Default Harian</TableHead>
                    <TableHead className="text-slate-700 w-[200px]">Description</TableHead>
                    <TableHead className="text-slate-700 w-[100px]">Status</TableHead>
                    <TableHead className="text-center text-slate-700 w-[160px] pr-6">Action</TableHead>
                  </TableRow>
                </TableHeader>

                <TableBody>
                  {loading && (
                    <TableRow>
                      <TableCell colSpan={7} className="py-12 text-center text-slate-500">
                        Loading data...
                      </TableCell>
                    </TableRow>
                  )}

                  {!loading && rows.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={7} className="py-12 text-center text-slate-500">
                        Belum ada jabatan yang terdaftar.
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
                          {r.name}
                        </TableCell>
                        <TableCell className="font-semibold text-indigo-700 py-4">
                          Level {r.level}
                        </TableCell>
                        <TableCell className="font-semibold text-amber-700 py-4">
                          {r.default_mandays_rate ? `Rp ${Number(r.default_mandays_rate).toLocaleString("id-ID")}` : "-"}
                        </TableCell>
                        <TableCell className="text-slate-600 py-4 max-w-[200px] truncate">
                          {r.description || "-"}
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
                {isEdit ? "Edit Grade" : "Add New Grade"}
              </h2>

              <form onSubmit={handleSubmit} className="space-y-4">
                <div>
                  <label className="block text-xs font-semibold text-slate-800 mb-1">
                    Grade Code
                  </label>
                  <input
                    value={form.code}
                    onChange={(e) => setForm({ ...form, code: e.target.value })}
                    disabled={isEdit}
                    placeholder="e.g. staff, pm, pd"
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
                    placeholder="e.g. Project Manager"
                    required
                    className="w-full border border-border rounded bg-white px-3 py-1.5 text-xs focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-800 mb-1">
                    Hierarchy Level
                  </label>
                  <input
                    type="number"
                    min="1"
                    value={form.level}
                    onChange={(e) => setForm({ ...form, level: parseInt(e.target.value) || 1 })}
                    required
                    className="w-full border border-border rounded bg-white px-3 py-1.5 text-xs focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all"
                  />
                  <p className="mt-1 text-[11px] text-slate-500">
                    Nilai terkecil (1) mewakili tingkatan tertinggi (e.g. BOD).
                  </p>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-800 mb-1">
                    Gaji Harian Default
                  </label>
                  <input
                    type="number"
                    placeholder="e.g. 150000"
                    value={form.default_mandays_rate}
                    onChange={(e) => setForm({ ...form, default_mandays_rate: e.target.value })}
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
                    placeholder="Keterangan tambahan..."
                    rows="3"
                    className="w-full border border-border rounded bg-white px-3 py-1.5 text-xs focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all"
                  />
                </div>

                <div className="flex items-center gap-2 py-2">
                  <input
                    type="checkbox"
                    id="is_active"
                    checked={form.is_active}
                    onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                    className="h-4 w-4 rounded border-slate-200 text-sky-600 focus:ring-sky-500/40"
                  />
                  <label htmlFor="is_active" className="text-xs font-semibold text-slate-800 cursor-pointer select-none">
                    Grade is Active
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
                    Save Grade
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
