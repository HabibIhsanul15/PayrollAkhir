import { useEffect, useState } from "react";
import useSWR from "swr";
import { getUser } from "@/lib/auth";
import { api } from "@/lib/api";
import { formatRupiah } from "@/lib/utils";
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

export default function GradeRatePage() {
  const user = getUser();
  const role = String(user?.role || "").toLowerCase();
  const isHCGA = role === "hcga";

  const { data: rawGrades, error: errGrades, isLoading: loadGrades } = useSWR(isHCGA ? "/master/grades" : null);
  const { data: rawAllowances, error: errAllowances, isLoading: loadAllowances } = useSWR(isHCGA ? "/master/allowance-types" : null);
  const { data: rawRates, error: errRates, isLoading: loadRates, mutate } = useSWR(isHCGA ? "/master/grade-allowance-rates" : null);

  const loading = loadGrades || loadAllowances || loadRates;
  const err = errGrades?.message || errAllowances?.message || errRates?.message || "";
  const [success, setSuccess] = useState("");

  const grades = Array.isArray(rawGrades) ? rawGrades : [];
  const allowances = Array.isArray(rawAllowances) ? rawAllowances : [];
  const rates = Array.isArray(rawRates) ? rawRates : [];

  function loadAll() {
    mutate();
  }

  // Modal / Form state
  const [modalOpen, setModalOpen] = useState(false);
  const [isEdit, setIsEdit] = useState(false);
  const [editId, setEditId] = useState(null);
  const [form, setForm] = useState({
    grade_id: "",
    allowance_type_id: "",
    rate_amount: "",
    rate_multiplier: "",
    rate_formula: "",
    requires_condition: "",
    effective_from: "2026-01-01",
    effective_to: "",
    is_active: true,
  });



  // Create lookup map: garMap[grade_id][allowance_type_id] = RateObject
  const garMap = {};
  rates.forEach((rate) => {
    const gId = rate.grade_id;
    const aId = rate.allowance_type_id;
    if (!garMap[gId]) garMap[gId] = {};
    garMap[gId][aId] = rate;
  });

  const handleCellClick = (grade, allowance) => {
    const existing = garMap[grade.id]?.[allowance.id];

    if (existing) {
      setForm({
        grade_id: existing.grade_id,
        allowance_type_id: existing.allowance_type_id,
        rate_amount: existing.rate_amount !== null ? String(existing.rate_amount) : "",
        rate_multiplier: existing.rate_multiplier !== null ? String(existing.rate_multiplier) : "",
        rate_formula: existing.rate_formula || "",
        requires_condition: existing.requires_condition || "",
        effective_from: existing.effective_from ? existing.effective_from.split("T")[0] : "2026-01-01",
        effective_to: existing.effective_to ? existing.effective_to.split("T")[0] : "",
        is_active: existing.is_active,
      });
      setEditId(existing.id);
      setIsEdit(true);
    } else {
      setForm({
        grade_id: grade.id,
        allowance_type_id: allowance.id,
        rate_amount: "",
        rate_multiplier: "",
        rate_formula: "",
        requires_condition: "",
        effective_from: "2026-01-01",
        effective_to: "",
        is_active: true,
      });
      setIsEdit(false);
    }
    setModalOpen(true);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setErr("");
    setSuccess("");

    // Prepare body
    const body = {
      grade_id: parseInt(form.grade_id),
      allowance_type_id: parseInt(form.allowance_type_id),
      rate_amount: form.rate_amount !== "" ? parseFloat(form.rate_amount) : null,
      rate_multiplier: form.rate_multiplier !== "" ? parseFloat(form.rate_multiplier) : null,
      rate_formula: form.rate_formula || null,
      requires_condition: form.requires_condition || null,
      effective_from: form.effective_from,
      effective_to: form.effective_to || null,
      is_active: form.is_active,
    };

    try {
      if (isEdit) {
        const updated = await api(`/master/grade-allowance-rates/${editId}`, {
          method: "PUT",
          body,
        });
        setRates((prev) => prev.map((x) => (x.id === editId ? updated : x)));
        setSuccess("Matrix rate berhasil diperbarui");
      } else {
        const created = await api("/master/grade-allowance-rates", {
          method: "POST",
          body,
        });
        setRates((prev) => [...prev, created]);
        setSuccess("Matrix rate baru berhasil dibuat");
      }
      setModalOpen(false);
    } catch (err) {
      setErr(err?.message || "Gagal menyimpan rate");
    }
  };

  const handleDelete = async () => {
    if (!editId) return;
    const ok = confirm("Yakin ingin menghapus rate ini dari matrix?");
    if (!ok) return;

    setErr("");
    setSuccess("");
    try {
      await api(`/master/grade-allowance-rates/${editId}`, { method: "DELETE" });
      mutate();
      setSuccess("Rate dihapus.");
      setModalOpen(false);
    } catch (err) {
      setErr(err?.message || "Gagal menghapus rate");
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
              Tarif Tunjangan Jabatan
            </h1>
            <p className="mt-1 text-sm text-slate-600">
              Pengaturan nominal tarif tunjangan untuk kombinasi tingkatan Jabatan dan Jenis Tunjangan. Klik pada sel untuk melakukan pengisian atau perubahan.
            </p>
          </div>

          <div>
            <Button
              variant="outline"
              onClick={loadAll}
              disabled={loading}
              className="bg-white border border-border rounded text-xs text-muted-foreground hover:text-foreground transition-colors"
            >
              {loading ? "Refreshing..." : "Refresh Tarif"}
            </Button>
          </div>
        </div>

        <AlertMessage type="error" message={err} />

        <AlertMessage type="success" message={success} />

        {/* Matrix Table */}
        <div className="bg-white border border-border rounded shadow-sm overflow-hidden">
          <div className="px-4 py-3 border-b border-slate-200/70 flex items-center justify-between">
            <span className="text-sm font-medium text-foreground">Matriks Tarif Tunjangan Jabatan</span>
            <span className="text-xs text-slate-500">
              {loading ? "Memuat..." : "Klik sel untuk isi/edit rate"}
            </span>
          </div>

          <div className="overflow-x-auto">
            <div className="px-8">
              <Table className="min-w-[1000px] border-collapse">
                <TableHeader>
                  <TableRow className="bg-slate-50/80">
                    <TableHead className="text-slate-700 pl-6 w-[180px] font-bold border-r border-slate-200/50">
                      Jabatan / Level
                    </TableHead>
                    {allowances.map((allowance) => (
                      <TableHead key={allowance.id} className="text-slate-700 text-center font-bold px-2 py-3 text-xs w-[140px]">
                        <div>{allowance.name}</div>
                        <div className="text-[10px] text-slate-400 font-normal">({allowance.code})</div>
                      </TableHead>
                    ))}
                  </TableRow>
                </TableHeader>

                <TableBody>
                  {loading && (
                    <TableRow>
                      <TableCell colSpan={allowances.length + 1} className="py-12 text-center text-slate-500">
                        Loading Matrix data...
                      </TableCell>
                    </TableRow>
                  )}

                  {!loading && grades.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={allowances.length + 1} className="py-12 text-center text-slate-500">
                        Belum ada grade yang terdaftar.
                      </TableCell>
                    </TableRow>
                  )}

                  {!loading &&
                    grades.map((grade, idx) => (
                      <TableRow
                        key={grade.id}
                        className={[
                          "transition align-middle border-b border-slate-100",
                          idx % 2 === 0 ? "bg-white/40" : "bg-white/20",
                          "hover:bg-slate-50/80",
                        ].join(" ")}
                      >
                        {/* Row Header: Grade */}
                        <TableCell className="pl-6 py-4 font-bold text-slate-900 border-r border-slate-200/50">
                          <div className="uppercase text-sky-700 text-sm">{grade.code}</div>
                          <div className="text-[11px] text-slate-500 font-normal">{grade.name}</div>
                        </TableCell>

                        {/* Cells: Allowance Rates */}
                        {allowances.map((allowance) => {
                          const rateObj = garMap[grade.id]?.[allowance.id];
                          const hasRate = !!rateObj;

                          return (
                            <TableCell
                              key={allowance.id}
                              onClick={() => handleCellClick(grade, allowance)}
                              className={[
                                "p-2 text-center text-xs transition cursor-pointer hover:bg-sky-100/40 select-none",
                                hasRate ? "font-bold text-slate-900" : "text-slate-400",
                              ].join(" ")}
                            >
                              {hasRate ? (
                                <div className="space-y-1">
                                  {rateObj.rate_amount !== null && (
                                    <div className="text-[13px] text-slate-900">
                                      {formatRupiah(rateObj.rate_amount)}
                                    </div>
                                  )}
                                  {rateObj.rate_multiplier !== null && (
                                    <div className="text-[11px] text-indigo-700">
                                      Multiplier: {rateObj.rate_multiplier}x
                                    </div>
                                  )}
                                  {rateObj.requires_condition && (
                                    <Badge variant="outline" className="text-[9px] px-1 py-0.5 border-orange-200 bg-orange-50 text-orange-700 scale-90">
                                      {rateObj.requires_condition}
                                    </Badge>
                                  )}
                                </div>
                              ) : (
                                <span className="text-slate-300 font-light">-</span>
                              )}
                            </TableCell>
                          );
                        })}
                      </TableRow>
                    ))}
                </TableBody>
              </Table>
            </div>
          </div>
        </div>

        {/* Modal Editor */}
        {modalOpen && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-in fade-in duration-200">
            <div className="bg-white border border-border rounded shadow-sm p-4 my-4">
              <h2 className="text-xl font-black text-slate-900 mb-4">
                {isEdit ? "Update Matrix Rate" : "Set Matrix Rate"}
              </h2>

              <div className="mb-4 p-3 bg-slate-50 rounded border border-slate-100 text-xs text-slate-700 space-y-1">
                <div>
                  <strong>Grade:</strong> {grades.find((g) => g.id === form.grade_id)?.name} ({grades.find((g) => g.id === form.grade_id)?.code.toUpperCase()})
                </div>
                <div>
                  <strong>Allowance:</strong> {allowances.find((a) => a.id === form.allowance_type_id)?.name}
                </div>
              </div>

              <form onSubmit={handleSubmit} className="space-y-4">
                <div>
                  <label className="block text-xs font-semibold text-slate-800 mb-1">
                    Rate Amount (Rp)
                  </label>
                  <input
                    type="number"
                    min="0"
                    value={form.rate_amount}
                    onChange={(e) => setForm({ ...form, rate_amount: e.target.value })}
                    placeholder="e.g. 25000 (biarkan kosong jika pakai multiplier/formula)"
                    className="w-full border border-border rounded bg-white px-3 py-1.5 text-xs focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-800 mb-1">
                    Rate Multiplier
                  </label>
                  <input
                    type="number"
                    step="0.0001"
                    min="0"
                    value={form.rate_multiplier}
                    onChange={(e) => setForm({ ...form, rate_multiplier: e.target.value })}
                    placeholder="e.g. 1.5 (opsional)"
                    className="w-full border border-border rounded bg-white px-3 py-1.5 text-xs focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-800 mb-1">
                    Formula Description
                  </label>
                  <input
                    value={form.rate_formula}
                    onChange={(e) => setForm({ ...form, rate_formula: e.target.value })}
                    placeholder="e.g. 1.5 * mandays_rate (opsional)"
                    className="w-full border border-border rounded bg-white px-3 py-1.5 text-xs focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-800 mb-1">
                    Requires Condition
                  </label>
                  <input
                    value={form.requires_condition}
                    onChange={(e) => setForm({ ...form, requires_condition: e.target.value })}
                    placeholder="e.g. num_toddlers>=3, is_trainer=1 (opsional)"
                    className="w-full border border-border rounded bg-white px-3 py-1.5 text-xs focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-800 mb-1">
                    Effective From
                  </label>
                  <input
                    type="date"
                    value={form.effective_from}
                    onChange={(e) => setForm({ ...form, effective_from: e.target.value })}
                    required
                    className="w-full border border-border rounded bg-white px-3 py-1.5 text-xs focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-800 mb-1">
                    Effective To
                  </label>
                  <input
                    type="date"
                    value={form.effective_to}
                    onChange={(e) => setForm({ ...form, effective_to: e.target.value })}
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
                    Rate is Active
                  </label>
                </div>

                <div className="flex items-center justify-between pt-2 border-t border-slate-100">
                  <div>
                    {isEdit && (
                      <Button
                        type="button"
                        variant="destructive"
                        onClick={handleDelete}
                        className="rounded"
                      >
                        Delete
                      </Button>
                    )}
                  </div>

                  <div className="flex items-center gap-2">
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
                      Save Rate
                    </Button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
