import { Button } from "@/components/ui/button";

export default function PayrollPreviewModal({ isOpen, onClose, data, onSave, isSaving }) {
  if (!isOpen || !data) return null;

  const {
    employee_name,
    period_month,
    period_from,
    period_to,
    gaji_pokok,
    allowances,
    total_allowances,
    total_deductions,
    total_nett,
    is_calculable,
    blocking_warnings,
    non_blocking_warnings,
    message,
  } = data;

  const formatIDR = (v) => new Intl.NumberFormat("id-ID").format(v || 0);

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div className="p-6 border-b">
          <h2 className="text-xl font-bold">Preview Auto Calculation</h2>
          <p className="text-sm text-gray-500">
            {employee_name} | {period_month} ({period_from} s.d {period_to})
          </p>
        </div>

        <div className="p-6 space-y-6">
          {blocking_warnings?.length > 0 && (
            <div className="bg-red-50 border-l-4 border-red-500 p-4 rounded text-red-700">
              <p className="font-bold">Kalkulasi diblokir (Prerequisite gagal):</p>
              <ul className="list-disc ml-5 mt-2 space-y-1 text-sm">
                {blocking_warnings.map((w, i) => (
                  <li key={i}>{w}</li>
                ))}
              </ul>
            </div>
          )}

          {non_blocking_warnings?.length > 0 && (
            <div className="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded text-yellow-800">
              <p className="font-bold">Peringatan (Non-blocking):</p>
              <ul className="list-disc ml-5 mt-2 space-y-1 text-sm">
                {non_blocking_warnings.map((w, i) => (
                  <li key={i}>{w}</li>
                ))}
              </ul>
            </div>
          )}

          <div className="grid grid-cols-2 gap-4">
            <div className="bg-gray-50 p-4 rounded border">
              <span className="text-gray-500 text-sm block">Gaji Pokok</span>
              <span className="font-bold text-lg">Rp {formatIDR(gaji_pokok)}</span>
            </div>
            <div className="bg-gray-50 p-4 rounded border">
              <span className="text-gray-500 text-sm block">Total Nett</span>
              <span className="font-bold text-xl text-blue-600">Rp {formatIDR(total_nett)}</span>
            </div>
          </div>

          <div>
            <h3 className="font-bold mb-3">Rincian Tunjangan (Allowances)</h3>
            <div className="border rounded overflow-x-auto">
              <table className="w-full text-sm text-left">
                <thead className="bg-gray-50 border-b">
                  <tr>
                    <th className="p-3">Tipe</th>
                    <th className="p-3 text-right">Rate</th>
                    <th className="p-3 text-right">Mandays</th>
                    <th className="p-3 text-right">Amount</th>
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {allowances?.length > 0 ? (
                    allowances.map((al, idx) => (
                      <tr key={idx} className="hover:bg-gray-50">
                        <td className="p-3">
                          <span className="font-medium">{al.allowance_type}</span>
                          <div className="text-xs text-gray-400 mt-1">
                            {JSON.stringify(al.calculation_detail)}
                          </div>
                        </td>
                        <td className="p-3 text-right text-gray-600">
                          {al.rate_amount !== null ? formatIDR(al.rate_amount) : "-"}
                        </td>
                        <td className="p-3 text-right text-gray-600">
                          {al.mandays !== null ? al.mandays : "-"}
                        </td>
                        <td className="p-3 text-right font-medium">Rp {formatIDR(al.amount)}</td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={4} className="p-4 text-center text-gray-500">
                        Tidak ada tunjangan.
                      </td>
                    </tr>
                  )}
                  <tr className="bg-gray-50 font-bold">
                    <td colSpan={3} className="p-3 text-right">Total Tunjangan</td>
                    <td className="p-3 text-right">Rp {formatIDR(total_allowances)}</td>
                  </tr>
                  <tr className="bg-gray-50 font-bold text-red-600">
                    <td colSpan={3} className="p-3 text-right">Total Potongan</td>
                    <td className="p-3 text-right">Rp {formatIDR(total_deductions)}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div className="text-sm text-gray-500 italic text-center">
            {message || "PPh 21 dan BPJS belum dihitung (Masuk Phase 5)."}
          </div>
        </div>

        <div className="p-6 border-t flex justify-end gap-3 bg-gray-50">
          <Button variant="outline" onClick={onClose} disabled={isSaving}>
            Cancel
          </Button>
          <Button 
            onClick={onSave} 
            disabled={!is_calculable || isSaving}
            className={!is_calculable ? "opacity-50 cursor-not-allowed" : ""}
          >
            {isSaving ? "Saving..." : "Calculate and Save"}
          </Button>
        </div>
      </div>
    </div>
  );
}
