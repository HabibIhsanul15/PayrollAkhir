import React, { useState, useEffect, useCallback, useMemo } from "react";
import { X, AlertCircle, Trash2 } from "lucide-react";
import { api } from "@/lib/api";
import { formatRupiah, monthLabel } from "@/lib/utils";
import { specialDeductionsApi } from "@/lib/specialDeductionsApi";
import { CurrencyInput } from "@/components/ui/CurrencyInput";
import PeriodDisplay from "@/components/PeriodDisplay";

export default function PayrollPreviewModal({
  isOpen,
  onClose,
  employeeId,
  payrollId,
  periodMonth,
  isFAT,
  canEditDeductions = false,
  onDeductionSaved,
}) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const [amount, setAmount] = useState("");
  const [deductionTypeId, setDeductionTypeId] = useState("");
  const [deductionTypes, setDeductionTypes] = useState([]);
  const [description, setDescription] = useState("");
  const [savingDeduction, setSavingDeduction] = useState(false);

  const loadPreview = useCallback(async (showLoading = true) => {
    if (showLoading) setLoading(true);
    setError("");
    try {
      const res = await api("/payrolls/preview-calculation", {
        method: "POST",
        body: {
          employee_id: employeeId,
          payroll_id: payrollId,
          period_month: periodMonth,
        }
      });
      setData(res);
    } catch (err) {
      setError(err?.message || "Gagal memuat detail simulasi");
    } finally {
      if (showLoading) setLoading(false);
    }
  }, [employeeId, payrollId, periodMonth]);

  useEffect(() => {
    if (isOpen && employeeId && periodMonth) {
      loadPreview();
    }
  }, [isOpen, employeeId, periodMonth, loadPreview]);

  useEffect(() => {
    if (!isOpen || !isFAT) return;

    api("/master/deduction-types?active_only=1")
      .then((response) => {
        const rows = Array.isArray(response) ? response : response?.data || [];
        setDeductionTypes(rows);
        setDeductionTypeId((current) => (
          rows.some((row) => String(row.id) === String(current))
            ? current
            : String(rows[0]?.id || "")
        ));
      })
      .catch(() => setDeductionTypes([]));
  }, [isOpen, isFAT]);

  const usedDeductionTypeIds = useMemo(() => new Set(
    (data?.deductions || [])
      .filter((item) => item.calculation_detail?.special_deduction_id && item.deduction_type_id)
      .map((item) => String(item.deduction_type_id))
  ), [data]);

  const usedDeductionTypeCodes = useMemo(() => new Set(
    (data?.deductions || [])
      .filter((item) => item.calculation_detail?.special_deduction_id && item.deduction_type)
      .map((item) => String(item.deduction_type).toLowerCase())
  ), [data]);

  const availableDeductionTypes = useMemo(
    () => deductionTypes.filter((type) => (
      !usedDeductionTypeIds.has(String(type.id))
      && !usedDeductionTypeCodes.has(String(type.code).toLowerCase())
    )),
    [deductionTypes, usedDeductionTypeIds, usedDeductionTypeCodes]
  );

  useEffect(() => {
    setDeductionTypeId((current) => {
      if (current && availableDeductionTypes.some((type) => String(type.id) === String(current))) {
        return current;
      }
      return String(availableDeductionTypes[0]?.id || "");
    });
  }, [availableDeductionTypes]);

  const handleSaveDeduction = async (e) => {
    e.preventDefault();
    setSavingDeduction(true);
    try {
      await specialDeductionsApi.create({
        employee_id: employeeId,
        period_month: periodMonth,
        deduction_type_id: deductionTypeId,
        amount: Number(amount),
        description: description
      });
      setAmount("");
      setDescription("");
      setDeductionTypeId("");
      await loadPreview(false);
      if (onDeductionSaved) onDeductionSaved();
    } catch (err) {
      alert(err?.message || "Gagal menyimpan potongan");
    } finally {
      setSavingDeduction(false);
    }
  };

  const handleDeleteDeduction = async (id) => {
    if (!confirm("Yakin ingin menghapus potongan ini?")) return;
    try {
      await specialDeductionsApi.delete(id);
      await loadPreview(false);
      if (onDeductionSaved) onDeductionSaved();
    } catch (err) {
      alert(err?.message || "Gagal menghapus potongan");
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto overflow-x-hidden p-4 sm:p-6 bg-slate-950/50 backdrop-blur-sm">
      <div className="bg-white border border-slate-200 rounded-2xl shadow-2xl w-full max-w-4xl 2xl:max-w-5xl overflow-hidden flex flex-col max-h-[92vh] min-w-0">
        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0">
          <h3 className="text-lg font-bold text-slate-800">
            Detail Slip Gaji
          </h3>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-600">
            <X size={20} />
          </button>
        </div>

        <div className="flex-1 min-w-0 overflow-y-auto overflow-x-hidden p-5 sm:p-7">
          {loading && <div className="text-center py-8 text-slate-500">Memuat detail...</div>}
          {error && (
            <div className="bg-rose-50 text-rose-600 p-4 rounded text-sm mb-4">
              {error}
            </div>
          )}

          {!loading && !error && data && data.is_calculable === false && (
            <div className="bg-amber-50 text-amber-700 p-4 rounded text-sm mb-4">
              <h4 className="font-bold mb-1">Simulasi tidak dapat dilanjutkan:</h4>
              <ul className="list-disc pl-5">
                {data.blocking_warnings?.map((w, i) => (
                  <li key={i}>{w}</li>
                ))}
              </ul>
            </div>
          )}

          {!loading && !error && data && data.is_calculable !== false && (
            <div className="space-y-6 min-w-0">
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm bg-slate-50 p-4 rounded-lg">
                <div>
                  <span className="text-slate-500 block mb-1">Karyawan</span>
                  <strong className="text-slate-800">{data.employee_name}</strong>
                </div>
                <div>
                  <span className="text-slate-500 block mb-1">Jabatan</span>
                  <strong className="text-slate-800">{data.position_name || "-"}</strong>
                </div>
                <div>
                  <span className="text-slate-500 block mb-1">Periode</span>
                  <strong className="text-slate-800"><PeriodDisplay period={data.period_month} /></strong>
                </div>
                <div>
                  <span className="text-slate-500 block mb-1">Kehadiran (Dibayar)</span>
                  <strong className="text-slate-800">{Math.round(data.total_mandays || 0)} Hari</strong>
                  {data.recaps && data.recaps.length > 0 && (
                    <div className="text-[10px] text-slate-500 mt-1 space-y-0.5">
                      {data.recaps.map((r, idx) => (
                        <div key={idx} className="leading-tight">
                          {Number(r.wfo_days) > 0 && <span>WFO: {Math.round(r.wfo_days)} </span>}
                          {Number(r.wfh_days) > 0 && <span>WFH: {Math.round(r.wfh_days)} </span>}
                          {Number(r.out_of_town_days) > 0 && <span>LK: {Math.round(r.out_of_town_days)} </span>}
                          {Number(r.training_days) > 0 && <span>Trn: {Math.round(r.training_days)} </span>}
                          {Number(r.late_count) > 0 && <span>Terlambat: {Math.round(r.late_count)}× </span>}
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </div>

              {/* Rincian */}
              <div>
                <h4 className="font-semibold text-slate-800 mb-3 pb-2 border-b">Rincian Pendapatan</h4>
                <div className="space-y-2 text-sm">
                  {data.base_salary_segments && data.base_salary_segments.length > 0 ? (
                    <div className="mb-2">
                      <div className="text-slate-600 font-medium">Gaji Pokok</div>
                      <div className="pl-4 space-y-1 mt-1 text-xs">
                        {data.base_salary_segments.map((seg, i) => (
                          <div key={i} className="flex min-w-0 justify-between gap-4 text-slate-500">
                            <span className="min-w-0 break-words">- {seg.position} {seg.mandays ? `(${seg.mandays} Hari)` : ''}</span>
                            <span className="shrink-0">{formatRupiah(seg.amount)}</span>
                          </div>
                        ))}
                      </div>
                      <div className="flex justify-between text-sm font-medium border-t border-slate-100 mt-1 pt-1">
                        <span className="text-slate-600">Total Gaji Pokok</span>
                        <span>{formatRupiah(data.gaji_pokok)}</span>
                      </div>
                    </div>
                  ) : (
                    <div className="flex justify-between mb-2">
                      <span className="text-slate-600">Gaji Pokok</span>
                      <span className="font-medium">{formatRupiah(data.gaji_pokok)}</span>
                    </div>
                  )}

                  {data.allowances?.map((al, i) => (
                    <div key={i} className="mb-2">
                      {al.calculation_detail?.is_prorated && al.calculation_detail?.segments?.length > 0 ? (
                        <>
                          <div className="text-slate-600 font-medium">{al.allowance_label || al.allowance_type}</div>
                          <div className="pl-4 space-y-1 mt-1 text-xs">
                            {al.calculation_detail.segments.map((seg, idx) => (
                              <div key={idx} className="flex min-w-0 justify-between gap-4 text-slate-500">
                                <span className="min-w-0 break-words">- {seg.position} {seg.mandays ? `(${seg.mandays} Hari)` : ''}</span>
                                <span className="shrink-0">{formatRupiah(seg.amount)}</span>
                              </div>
                            ))}
                          </div>
                          <div className="flex justify-between text-sm font-medium border-t border-slate-100 mt-1 pt-1">
                            <span className="text-slate-600">Total {al.allowance_label || al.allowance_type}</span>
                            <span>{formatRupiah(al.amount)}</span>
                          </div>
                        </>
                      ) : (
                        <div className="flex min-w-0 justify-between gap-4">
                          <span className="min-w-0 break-words text-slate-600">{al.allowance_label || al.allowance_type} {al.mandays ? `(${al.mandays} Hari)` : ''}</span>
                          <span className="shrink-0 font-medium">{formatRupiah(al.amount)}</span>
                        </div>
                      )}
                    </div>
                  ))}
                  <div className="flex justify-between pt-2 border-t mt-2">
                    <span className="font-semibold text-slate-800">Total Pendapatan</span>
                    <span className="font-semibold text-slate-800">
                      {formatRupiah(data.gaji_pokok + data.total_allowances)}
                    </span>
                  </div>
                </div>
              </div>

              <div>
                <h4 className="font-semibold text-slate-800 mb-3 pb-2 border-b">Rincian Potongan</h4>
                <div className="space-y-2 text-sm mb-4">
                  {data.deductions?.length === 0 && (
                    <div className="text-slate-400 italic">Belum ada potongan.</div>
                  )}
                  {data.deductions?.map((d, i) => (
                    <div key={i} className="flex min-w-0 justify-between items-center gap-4 text-red-600 group">
                      <span className="min-w-0 break-words">{d.deduction_label}</span>
                      <div className="flex shrink-0 items-center gap-3">
                        <span className="font-medium whitespace-nowrap">-{formatRupiah(d.amount)}</span>
                        {isFAT && canEditDeductions && d.calculation_detail?.special_deduction_id && (
                          <button 
                            type="button"
                            onClick={() => handleDeleteDeduction(d.calculation_detail.special_deduction_id)}
                            className="text-red-400 hover:text-red-700 transition-colors"
                            title="Hapus Potongan"
                            aria-label={`Hapus ${d.deduction_label}`}
                          >
                            <Trash2 size={14} />
                          </button>
                        )}
                      </div>
                    </div>
                  ))}
                  {data.deductions?.length > 0 && (
                    <div className="flex justify-between gap-4 pt-2 border-t mt-2 text-red-700">
                      <span className="font-semibold">Total Potongan</span>
                      <span className="font-semibold">-{formatRupiah(data.total_deductions)}</span>
                    </div>
                  )}
                </div>

                {isFAT && canEditDeductions && (
                  <form onSubmit={handleSaveDeduction} className="bg-red-50 p-4 rounded-lg border border-red-100">
                    <h5 className="text-sm font-semibold text-red-800 mb-3 flex items-center gap-2">
                      <AlertCircle size={14} /> Tambah Potongan
                    </h5>
                    <div className="grid grid-cols-1 md:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)_minmax(150px,180px)_auto] gap-3 items-end">
                      <select
                        value={deductionTypeId}
                        onChange={(e) => setDeductionTypeId(e.target.value)}
                        required
                        disabled={availableDeductionTypes.length === 0}
                        className="w-full min-w-0 border rounded px-3 py-2 text-sm focus:ring-1 focus:ring-red-200 bg-white disabled:bg-slate-100"
                      >
                        <option value="">-- Pilih Jenis Potongan --</option>
                        {deductionTypes.map((type) => {
                          const isUsed = usedDeductionTypeIds.has(String(type.id))
                            || usedDeductionTypeCodes.has(String(type.code).toLowerCase());

                          return (
                            <option key={type.id} value={type.id} disabled={isUsed}>
                              {type.name}{isUsed ? " (sudah ditambahkan)" : ""}
                            </option>
                          );
                        })}
                      </select>
                      <input
                        type="text"
                        value={description}
                        onChange={(e) => setDescription(e.target.value)}
                        placeholder="Catatan (opsional)"
                        className="w-full min-w-0 border rounded px-3 py-2 text-sm focus:ring-1 focus:ring-red-200 bg-white"
                      />
                        <CurrencyInput
                          value={amount}
                          onChange={(value) => setAmount(value)}
                          placeholder="Nominal (Rp)"
                          className="w-full min-w-0 border rounded px-3 py-2 text-sm focus:ring-1 focus:ring-red-200 bg-white"
                          required
                        />
                      <button
                        type="submit"
                        disabled={savingDeduction || !deductionTypeId || availableDeductionTypes.length === 0}
                        className="w-full md:w-auto bg-red-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-red-700 disabled:opacity-50 whitespace-nowrap"
                      >
                        {savingDeduction ? "Menyimpan..." : "Tambahkan"}
                      </button>
                    </div>
                    {availableDeductionTypes.length === 0 && (
                      <p className="mt-2 text-xs text-red-700">
                        Semua jenis potongan yang tersedia sudah ditambahkan pada periode ini.
                      </p>
                    )}
                  </form>
                )}
                {isFAT && !canEditDeductions && (
                  <div className="bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm text-slate-600">
                    Payroll sudah diajukan/diproses, sehingga potongan tidak bisa diubah dari detail ini.
                  </div>
                )}
              </div>
              
              <div className="bg-blue-50 p-4 rounded-lg flex justify-between items-center mt-6">
                <span className="font-bold text-blue-900">Total Nett Diterima</span>
                <span className="text-xl font-bold text-blue-700">{formatRupiah(data.total_nett)}</span>
              </div>
            </div>
          )}
        </div>
        
        <div className="px-6 py-4 border-t border-slate-100 flex justify-end shrink-0 bg-slate-50">
          <button onClick={onClose} className="px-5 py-2 rounded bg-white border font-medium text-slate-700 hover:bg-slate-50 text-sm shadow-sm">
            Tutup
          </button>
        </div>
      </div>
    </div>
  );
}
