import React, { useState, useEffect, useCallback } from "react";
import { X, AlertCircle, Trash2 } from "lucide-react";
import { api } from "@/lib/api";
import { formatRupiah } from "@/lib/utils";
import { specialDeductionsApi } from "@/lib/specialDeductionsApi";

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
  const [description, setDescription] = useState("");
  const [savingDeduction, setSavingDeduction] = useState(false);

  const loadPreview = useCallback(async () => {
    setLoading(true);
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
      setLoading(false);
    }
  }, [employeeId, payrollId, periodMonth]);

  useEffect(() => {
    if (isOpen && employeeId && periodMonth) {
      loadPreview();
    }
  }, [isOpen, employeeId, periodMonth, loadPreview]);

  const handleSaveDeduction = async (e) => {
    e.preventDefault();
    setSavingDeduction(true);
    try {
      await specialDeductionsApi.create({
        employee_id: employeeId,
        period_month: periodMonth,
        type: "manual",
        amount: Number(amount),
        description: description
      });
      setAmount("");
      setDescription("");
      await loadPreview();
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
      await loadPreview();
      if (onDeductionSaved) onDeductionSaved();
    } catch (err) {
      alert(err?.message || "Gagal menghapus potongan");
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
      <div className="bg-white border border-border rounded-xl shadow-xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0">
          <h3 className="text-lg font-bold text-slate-800">
            Detail Slip Gaji <span className="text-sm font-normal text-slate-500 ml-2">(Simulasi)</span>
          </h3>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-600">
            <X size={20} />
          </button>
        </div>

        <div className="flex-1 overflow-y-auto p-6">
          {loading && <div className="text-center py-8 text-slate-500">Memuat detail...</div>}
          {error && (
            <div className="bg-rose-50 text-rose-600 p-4 rounded text-sm mb-4">
              {error}
            </div>
          )}

          {!loading && !error && data && (
            <div className="space-y-6">
              <div className="grid grid-cols-2 gap-4 text-sm bg-slate-50 p-4 rounded-lg">
                <div>
                  <span className="text-slate-500 block mb-1">Karyawan</span>
                  <strong className="text-slate-800">{data.employee_name}</strong>
                </div>
                <div>
                  <span className="text-slate-500 block mb-1">Periode</span>
                  <strong className="text-slate-800">{data.period_month}</strong>
                </div>
              </div>

              {/* Rincian */}
              <div>
                <h4 className="font-semibold text-slate-800 mb-3 pb-2 border-b">Rincian Pendapatan</h4>
                <div className="space-y-2 text-sm">
                  <div className="flex justify-between">
                    <span className="text-slate-600">Gaji Pokok</span>
                    <span className="font-medium">{formatRupiah(data.gaji_pokok)}</span>
                  </div>
                  {data.allowances?.map((al, i) => (
                    <div key={i} className="flex justify-between">
                      <span className="text-slate-600">{al.allowance_label} {al.mandays ? `(${al.mandays} Hari)` : ''}</span>
                      <span className="font-medium">{formatRupiah(al.amount)}</span>
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
                    <div key={i} className="flex justify-between items-center text-red-600 group">
                      <span>{d.deduction_label}</span>
                      <div className="flex items-center gap-3">
                        <span className="font-medium">-{formatRupiah(d.amount)}</span>
                        {isFAT && canEditDeductions && d.calculation_detail?.special_deduction_id && (
                          <button 
                            onClick={() => handleDeleteDeduction(d.calculation_detail.special_deduction_id)}
                            className="opacity-0 group-hover:opacity-100 text-red-400 hover:text-red-700 transition-opacity"
                            title="Hapus Potongan"
                          >
                            <Trash2 size={14} />
                          </button>
                        )}
                      </div>
                    </div>
                  ))}
                  {data.deductions?.length > 0 && (
                    <div className="flex justify-between pt-2 border-t mt-2 text-red-700">
                      <span className="font-semibold">Total Potongan</span>
                      <span className="font-semibold">-{formatRupiah(data.total_deductions)}</span>
                    </div>
                  )}
                </div>

                {isFAT && canEditDeductions && (
                  <form onSubmit={handleSaveDeduction} className="bg-red-50 p-4 rounded-lg border border-red-100">
                    <h5 className="text-sm font-semibold text-red-800 mb-3 flex items-center gap-2">
                      <AlertCircle size={14} /> Tambah Potongan Khusus
                    </h5>
                    <div className="flex flex-col md:flex-row gap-3">
                      <input
                        type="text"
                        required
                        value={description}
                        onChange={(e) => setDescription(e.target.value)}
                        placeholder="Keterangan (Cth: Denda)"
                        className="flex-1 border rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-red-200 bg-white"
                      />
                      <input
                        type="number"
                        required
                        min="0"
                        value={amount}
                        onChange={(e) => setAmount(e.target.value)}
                        placeholder="Nominal (Rp)"
                        className="w-full md:w-32 border rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-red-200 bg-white"
                      />
                      <button
                        type="submit"
                        disabled={savingDeduction}
                        className="bg-red-600 text-white px-4 py-1.5 rounded text-sm font-medium hover:bg-red-700 disabled:opacity-50 whitespace-nowrap"
                      >
                        {savingDeduction ? "Menyimpan..." : "Tambahkan"}
                      </button>
                    </div>
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
