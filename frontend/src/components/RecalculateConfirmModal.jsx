import { useState } from "react";
import { Button } from "@/components/ui/button";

export default function RecalculateConfirmModal({ isOpen, onClose, message, onConfirm, isSaving }) {
  const [force, setForce] = useState(false);

  if (!isOpen) return null;

  const handleSubmit = (e) => {
    e.preventDefault();
    onConfirm(force);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="bg-white rounded-lg shadow-xl w-full max-w-md">
        <div className="p-5 border-b">
          <h2 className="text-lg font-bold text-red-600">Peringatan Recalculate</h2>
        </div>

        <form onSubmit={handleSubmit} className="p-5 space-y-4">
          <div className="text-sm text-gray-700 bg-yellow-50 p-3 rounded border border-yellow-200">
            {message || "Terdapat nilai manual override pada payroll ini."}
          </div>

          <label className="flex items-start gap-2 mt-4 cursor-pointer text-sm">
            <input
              type="checkbox"
              checked={force}
              onChange={(e) => setForce(e.target.checked)}
              className="mt-1"
            />
            <span className="text-gray-700 font-medium">
              Ya, hapus semua override manual dan kembalikan ke perhitungan auto (Force Recalculate).
            </span>
          </label>

          <div className="pt-4 flex justify-end gap-2">
            <Button type="button" variant="outline" onClick={onClose} disabled={isSaving}>
              Batal
            </Button>
            <Button type="submit" disabled={!force || isSaving} className={!force ? "opacity-50" : ""}>
              {isSaving ? "Processing..." : "Force Recalculate"}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
