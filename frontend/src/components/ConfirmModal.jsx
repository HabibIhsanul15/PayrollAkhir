import { AlertTriangle, CheckCircle, X, XCircle } from "lucide-react";

export default function ConfirmModal({
  isOpen,
  title = "Konfirmasi",
  message,
  confirmLabel = "Ya, lanjutkan",
  cancelLabel = "Batal",
  tone = "primary",
  loading = false,
  onConfirm,
  onCancel,
}) {
  if (!isOpen) return null;

  const isDanger = tone === "danger";
  const Icon = isDanger ? XCircle : tone === "success" ? CheckCircle : AlertTriangle;
  const iconClasses = isDanger
    ? "bg-rose-100 text-rose-600"
    : tone === "success"
      ? "bg-emerald-100 text-emerald-600"
      : "bg-amber-100 text-amber-600";
  const buttonClasses = isDanger
    ? "bg-rose-600 hover:bg-rose-700 focus:ring-rose-500/30"
    : tone === "success"
      ? "bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500/30"
      : "bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500/30";

  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-sm">
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="confirm-modal-title"
        className="w-full max-w-md rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden"
      >
        <div className="p-6">
          <div className="flex items-start gap-4">
            <div className={`shrink-0 rounded-full p-3 ${iconClasses}`}>
              <Icon size={22} />
            </div>
            <div className="min-w-0 flex-1">
              <div className="flex items-start justify-between gap-3">
                <h3 id="confirm-modal-title" className="text-lg font-semibold text-slate-900">
                  {title}
                </h3>
                <button
                  type="button"
                  onClick={onCancel}
                  disabled={loading}
                  className="text-slate-400 hover:text-slate-600 disabled:opacity-50"
                  aria-label="Tutup"
                >
                  <X size={20} />
                </button>
              </div>
              <p className="mt-2 text-sm leading-6 text-slate-600">{message}</p>
            </div>
          </div>
        </div>
        <div className="flex justify-end gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4">
          <button
            type="button"
            onClick={onCancel}
            disabled={loading}
            className="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-200 disabled:opacity-50"
          >
            {cancelLabel}
          </button>
          <button
            type="button"
            onClick={onConfirm}
            disabled={loading}
            className={`rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-sm transition focus:ring-4 disabled:cursor-not-allowed disabled:opacity-50 ${buttonClasses}`}
          >
            {loading ? "Memproses..." : confirmLabel}
          </button>
        </div>
      </div>
    </div>
  );
}
