/**
 * AlertMessage – Komponen notifikasi error/success.
 *
 * Props:
 *   type    – "error" | "success"
 *   message – string pesan (jika kosong/null, tidak render apa-apa)
 *   className – className tambahan (opsional)
 */
export default function AlertMessage({ type = "error", message, className = "" }) {
  if (!message) return null;

  const styles = {
    error: "bg-rose-50 text-rose-600 border-rose-100",
    success: "bg-emerald-50 text-emerald-600 border-emerald-100",
  };
  const cls = styles[type] || styles.error;

  return (
    <div className={`rounded px-3 py-2 text-xs border ${cls} ${className}`}>
      {message}
    </div>
  );
}
