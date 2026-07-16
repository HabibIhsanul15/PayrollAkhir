import { useState, useEffect } from "react";
import { Button } from "@/components/ui/button";
import { formatRupiah } from "@/lib/utils";
import { CurrencyInput } from "@/components/ui/CurrencyInput";
export default function OverrideAllowanceModal({ isOpen, onClose, data, onSave, isSaving }) {
  const [amount, setAmount] = useState("");
  const [reason, setReason] = useState("");
  const [error, setError] = useState("");

  useEffect(() => {
    if (isOpen && data) {
      setAmount(data.amount || "0");
      setReason("");
      setError("");
    }
  }, [isOpen, data]);

  if (!isOpen || !data) return null;

  const handleSubmit = (e) => {
    e.preventDefault();
    if (Number(amount) < 0) {
      setError("Amount tidak boleh negatif");
      return;
    }
    if (!reason.trim()) {
      setError("Alasan (reason) wajib diisi");
      return;
    }
    onSave({ amount: Number(amount), override_reason: reason.trim() });
  };

  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/50 backdrop-blur-sm p-4">
      <div className="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-md overflow-hidden">
        <div className="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
          <h2 className="text-lg font-semibold text-slate-900">Override Allowance</h2>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-600 transition-colors text-2xl leading-none">
            &times;
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-5 space-y-4">
          <div>
            <label className="block text-sm font-semibold text-gray-700 mb-1">
              Allowance Type
            </label>
            <div className="px-3 py-2 bg-gray-100 rounded text-sm text-gray-600">
              {data.allowance_type}
            </div>
          </div>

          <div>
            <label className="block text-sm font-semibold text-gray-700 mb-1">
              New Amount (Rp) <span className="text-red-500">*</span>
            </label>
            <CurrencyInput
              value={amount}
              onChange={(value) => setAmount(value)}
              className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
              required
            />
          </div>

          <div>
            <label className="block text-sm font-semibold text-gray-700 mb-1">
              Override Reason <span className="text-red-500">*</span>
            </label>
            <textarea
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
              rows={3}
              required
              placeholder="Contoh: Kesepakatan khusus direksi"
            />
          </div>

          {error && <div className="text-sm text-red-600 bg-red-50 p-2 rounded">{error}</div>}

          <div className="pt-2 flex justify-end gap-2">
            <Button type="button" variant="outline" onClick={onClose} disabled={isSaving}>
              Cancel
            </Button>
            <Button type="submit" disabled={isSaving}>
              {isSaving ? "Saving..." : "Save Override"}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
