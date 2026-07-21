import { useEffect, useMemo, useState } from "react";
import useSWR from "swr";
import { getUser } from "@/lib/auth";
import { api } from "@/lib/api";
import { formatRupiah } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { CurrencyInput } from "@/components/ui/CurrencyInput";
import AlertMessage from "@/components/AlertMessage";
import { Badge } from "@/components/ui/badge";
import { useConfirm } from "@/components/ConfirmProvider";
import {
  Table,
  TableHeader,
  TableRow,
  TableHead,
  TableBody,
  TableCell,
} from "@/components/ui/table";

export default function PositionRatePage() {
  const user = getUser();
  const role = String(user?.role || "").toLowerCase();
  const isHcga = role === "hcga";
  const today = new Date().toISOString().slice(0, 10);

  const { data: rawPositions, error: errPositions, isLoading: loadPositions } = useSWR(isHcga ? "/master/positions" : null);
  const { data: rawAllowances, error: errAllowances, isLoading: loadAllowances } = useSWR(isHcga ? "/master/allowance-types" : null);
  const { data: rawRates, error: errRates, isLoading: loadRates, mutate } = useSWR(isHcga ? "/master/position-allowance-rates" : null);

  const { confirm } = useConfirm();

  const loading = loadPositions || loadAllowances || loadRates;
  const [localErr, setErr] = useState("");
  const err = localErr || errPositions?.message || errAllowances?.message || errRates?.message || "";
  const [success, setSuccess] = useState("");

  const positions = Array.isArray(rawPositions) ? rawPositions : rawPositions?.data ?? [];
  const allowances = Array.isArray(rawAllowances) ? rawAllowances : rawAllowances?.data ?? [];
  const [localRates, setLocalRates] = useState([]);

  useEffect(() => {
    setLocalRates(Array.isArray(rawRates) ? rawRates : rawRates?.data ?? []);
  }, [rawRates]);

  const rates = localRates;

  function loadAll() {
    setErr("");
    mutate();
  }

  // Modal / Form state
  const [modalOpen, setModalOpen] = useState(false);
  const [isEdit, setIsEdit] = useState(false);
  const [editId, setEditId] = useState(null);
  const [form, setForm] = useState({
    position_id: "",
    allowance_type_id: "",
    rate_amount: "",
  });

  // Create lookup map: garMap[position_id][allowance_type_id] = RateObject
  const garMap = useMemo(() => {
    const map = {};
    rates.forEach((rate) => {
      const gId = rate.position_id;
      const aId = rate.allowance_type_id;
      if (!map[gId]) map[gId] = {};
      if (!map[gId][aId]) {
        map[gId][aId] = rate;
      }
    });

    return map;
  }, [rates]);

  const handleCellClick = (position, allowance) => {
    const existing = garMap[position.id]?.[allowance.id];

    if (existing) {
      setForm({
        position_id: existing.position_id,
        allowance_type_id: existing.allowance_type_id,
        rate_amount: existing.rate_amount !== null ? String(existing.rate_amount) : "",
      });
      setEditId(existing.id);
      setIsEdit(true);
    } else {
      setForm({
        position_id: position.id,
        allowance_type_id: allowance.id,
        rate_amount: "",
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
      position_id: parseInt(form.position_id),
      allowance_type_id: parseInt(form.allowance_type_id),
      rate_amount: form.rate_amount !== "" ? parseFloat(form.rate_amount) : null,
    };

    try {
      if (isEdit) {
        const updated = await api(`/master/position-allowance-rates/${editId}`, {
          method: "PUT",
          body,
        });
        setLocalRates((prev) => prev.map((x) => (x.id === editId ? updated : x)));
        setSuccess("Matrix rate berhasil diperbarui");
      } else {
        const created = await api("/master/position-allowance-rates", {
          method: "POST",
          body,
        });
        setLocalRates((prev) => [created, ...prev]);
        setSuccess("Matrix rate baru berhasil dibuat");
      }
      setModalOpen(false);
    } catch (err) {
      setErr(err?.message || "Gagal menyimpan rate");
    }
  };

  const handleDelete = async () => {
    if (!editId) return;
    const ok = await confirm("Yakin ingin menghapus rate ini dari matrix?");
    if (!ok) return;

    setErr("");
    setSuccess("");
    try {
      await api(`/master/position-allowance-rates/${editId}`, { method: "DELETE" });
      mutate();
      setSuccess("Rate dihapus.");
      setModalOpen(false);
    } catch (err) {
      setErr(err?.message || "Gagal menghapus rate");
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

                  {!loading && positions.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={allowances.length + 1} className="py-12 text-center text-slate-500">
                        Belum ada position yang terdaftar.
                      </TableCell>
                    </TableRow>
                  )}

                  {!loading &&
                    positions.map((position, idx) => (
                      <TableRow
                        key={position.id}
                        className={[
                          "transition align-middle border-b border-slate-100",
                          idx % 2 === 0 ? "bg-white/40" : "bg-white/20",
                          "hover:bg-slate-50/80",
                        ].join(" ")}
                      >
                        {/* Row Header: position */}
                        <TableCell className="pl-6 py-4 font-bold text-slate-900 border-r border-slate-200/50">
                          <div className="uppercase text-sky-700 text-sm">{position.code}</div>
                          <div className="text-[11px] text-slate-500 font-normal">{position.name}</div>
                        </TableCell>

                        {/* Cells: Allowance Rates */}
                        {allowances.map((allowance) => {
                          const rateObj = garMap[position.id]?.[allowance.id];
                          const hasRate = !!rateObj;

                          return (
                            <TableCell
                              key={allowance.id}
                              onClick={() => handleCellClick(position, allowance)}
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
                  <strong>position:</strong> {positions.find((g) => g.id === form.position_id)?.name} ({positions.find((g) => g.id === form.position_id)?.code.toUpperCase()})
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
                  <CurrencyInput
                    value={form.rate_amount}
                    onChange={(value) => setForm({ ...form, rate_amount: value })}
                    placeholder="Contoh: 150000"
                    className="w-full border border-border rounded bg-white px-3 py-1.5 text-xs focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all"
                  />
                </div>

                <div className="flex items-center justify-between pt-2 border-t border-slate-100 mt-6">
                  <div>
                    {isEdit && (
                      <Button
                        type="button"
                        variant="destructive"
                        onClick={handleDelete}
                        className="rounded"
                      >
                        Hapus Tarif
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
