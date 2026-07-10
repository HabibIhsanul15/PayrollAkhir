import { Badge } from "@/components/ui/badge";

/**
 * StatusBadge – Komponen badge status universal.
 *
 * Props:
 *   status  – string status (active, inactive, draft, requested, approved, paid, rejected)
 *   variant – "badge" (default, pakai Badge) | "text" (hanya teks berwarna)
 *
 * Contoh penggunaan:
 *   <StatusBadge status="active" />
 *   <StatusBadge status="paid" variant="text" />
 */
export default function StatusBadge({ status, variant = "badge" }) {
  const s = String(status || "").toLowerCase();

  // ── Variant "text" (dipakai di PayrollReportPage, PayrollList, EmployeesPage) ──
  if (variant === "text") {
    const colorMap = {
      active: "text-emerald-600",
      inactive: "text-slate-500",
      draft: "text-slate-500",
      requested: "text-amber-600",
      approved: "text-sky-600",
      paid: "text-emerald-600",
      rejected: "text-rose-600",
    };
    const cls = colorMap[s] || "text-slate-500";
    return (
      <span className={`text-[10px] font-semibold uppercase tracking-wider ${cls}`}>
        {s || "—"}
      </span>
    );
  }

  // ── Variant "badge" (default, dipakai di EmployeeDetailPage, PayrollDetail) ──
  const badgeMap = {
    active: "border-emerald-200 bg-emerald-50 text-emerald-700",
    paid: "border-emerald-200 bg-emerald-50 text-emerald-700",
    approved: "border-sky-200 bg-sky-50 text-sky-700",
    requested: "border-amber-200 bg-amber-50 text-amber-700",
    rejected: "border-rose-200 bg-rose-50 text-rose-700",
    draft: "border-slate-200 bg-slate-50 text-slate-700",
    inactive: "border-slate-200 bg-slate-50 text-slate-700",
    masked: "border-slate-200 bg-slate-50 text-slate-700",
    ok: "border-emerald-200 bg-emerald-50 text-emerald-700",
  };
  const cls = badgeMap[s] || "border-slate-200 bg-slate-50 text-slate-700";

  return (
    <Badge className={`rounded-full border ${cls}`}>
      {s || "-"}
    </Badge>
  );
}
