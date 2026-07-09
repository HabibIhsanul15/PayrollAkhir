import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { api } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";

function formatIDR(v) {
  const n = Number(v ?? 0);
  const safe = Number.isFinite(n) ? n : 0;
  try {
    return new Intl.NumberFormat("id-ID").format(safe);
  } catch {
    return String(safe);
  }
}

function periodKey(value) {
  const s = String(value || "").trim();
  if (!s) return "";
  if (/^\d{4}-\d{2}$/.test(s)) return s; // YYYY-MM
  if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s.slice(0, 7); // YYYY-MM-DD -> YYYY-MM
  return s.length >= 7 ? s.slice(0, 7) : s;
}

function monthLabel(yyyyMM) {
  if (!/^\d{4}-\d{2}$/.test(yyyyMM)) return yyyyMM || "-";
  const [y, m] = yyyyMM.split("-");
  const map = {
    "01": "Jan",
    "02": "Feb",
    "03": "Mar",
    "04": "Apr",
    "05": "Mei",
    "06": "Jun",
    "07": "Jul",
    "08": "Agu",
    "09": "Sep",
    "10": "Okt",
    "11": "Nov",
    "12": "Des",
  };
  return `${map[m] || m} ${y}`;
}

function Field({ label, value }) {
  return (
    <div className="bg-white border border-border rounded shadow-sm px-4 py-3">
      <div className="text-[11px] text-slate-500">{label}</div>
      <div className="mt-0.5 text-sm font-medium text-foreground">
        {value ?? "-"}
      </div>
    </div>
  );
}

function StatusBadge({ masked }) {
  if (masked) {
    return (
      <Badge className="rounded-full border border-slate-200 bg-slate-50 text-slate-700">
        MASKED
      </Badge>
    );
  }
  return (
    <Badge className="rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700">
      OK
    </Badge>
  );
}

export default function PayrollDetail() {
  const { id } = useParams();
  const navigate = useNavigate();

  const [data, setData] = useState(null);
  const [err, setErr] = useState("");
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let mounted = true;
    setLoading(true);
    setErr("");

    // ✅ pakai base api helper kamu (yang otomatis /api)
    api(`/payrolls/${id}`)
      .then((res) => mounted && setData(res?.data ?? res))
      .catch((e) => mounted && setErr(e?.message || "Gagal memuat data payroll."))
      .finally(() => mounted && setLoading(false));

    return () => {
      mounted = false;
    };
  }, [id]);

  const view = useMemo(() => {
    const p = data || {};
    const periodeLabel = monthLabel(periodKey(p.periode));
    const total =
      p.total ??
      (Number(p.gaji_pokok ?? 0) + Number(p.tunjangan ?? 0) - Number(p.potongan ?? 0));

    return {
      title:
        p.employee_name ||
        p.user_name ||
        p.name ||
        "Payroll Detail",
      employeeCode: p.employee_code || p.employee?.employee_code || null,
      periodeLabel,
      catatan: p.catatan || "-",
      gaji_pokok: `Rp ${formatIDR(p.gaji_pokok)}`,
      tunjangan: `Rp ${formatIDR(p.tunjangan)}`,
      potongan: `Rp ${formatIDR(p.potongan)}`,
      total: `Rp ${formatIDR(total)}`,
      masked: !!p.masked,
    };
  }, [data]);

  return (
    <div>
      {/* soft background selaras halaman lain */}
      <div className="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
        <div className="absolute -top-40 -left-40 h-[520px] w-[520px] rounded-full bg-sky-200/50 blur-3xl" />
        <div className="absolute -bottom-44 -right-44 h-[620px] w-[620px] rounded-full bg-indigo-200/45 blur-3xl" />
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_10%,rgba(14,165,233,0.10),transparent_45%),radial-gradient(circle_at_80%_18%,rgba(99,102,241,0.10),transparent_48%)]" />
      </div>

      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <div className="hidden">
              <span className="h-2 w-2 rounded-full bg-sky-500" />
              <span className="text-[10px] font-semibold text-muted-foreground">
                Human Plus Institute
              </span>
            </div>

            <div className="mt-4 flex items-center gap-3">
              <h1 className="text-lg font-semibold text-foreground">
                Payroll Detail
              </h1>
              {!loading && data && <StatusBadge masked={view.masked} />}
            </div>

            <p className="mt-1 text-sm text-slate-600">
              Detail slip gaji untuk payroll ID: <span className="font-semibold">{id}</span>
            </p>
          </div>

          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              onClick={() => navigate(-1)}
              className="bg-white border border-border rounded text-xs text-muted-foreground hover:text-foreground transition-colors"
            >
              Back
            </Button>
          </div>
        </div>

        {/* Loading */}
        {loading && (
          <div className="bg-white border border-border rounded shadow-sm px-6 py-6 text-xs text-muted-foreground">
            Loading...
          </div>
        )}

        {/* Error */}
        {!loading && err && (
          <div className="bg-white border border-border rounded shadow-sm p-4 my-4">
            <div className="font-bold text-rose-800 mb-1">Tidak bisa akses data</div>
            {err}
          </div>
        )}

        {/* Content */}
        {!loading && data && (
          <div className="bg-white border border-border rounded shadow-sm overflow-hidden">
            <div className="px-4 py-3 border-b border-slate-200/70 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <div className="text-sm font-medium text-foreground">
                  {view.title}
                </div>
                <div className="text-xs text-slate-500">
                  {view.employeeCode ? `Employee Code: ${view.employeeCode}` : "—"}
                </div>
              </div>

              <div className="text-xs text-slate-500">
                Periode: <span className="font-semibold text-slate-800">{view.periodeLabel}</span>
              </div>
            </div>

            <div className="p-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Field label="Periode" value={view.periodeLabel} />
                <Field label="Catatan" value={view.catatan} />

                <Field label="Gaji Pokok" value={view.gaji_pokok} />
                <Field label="Tunjangan" value={view.tunjangan} />

                <Field label="Potongan" value={view.potongan} />

                <div className="rounded border border-slate-200 bg-gradient-to-r from-sky-50 to-indigo-50 px-4 py-3">
                  <div className="text-[11px] text-slate-500">Total</div>
                  <div className="mt-0.5 text-base font-extrabold text-slate-900">
                    {view.total}
                  </div>
                  <div className="text-[11px] text-[10px] text-muted-foreground mt-0.5">
                    Total = gaji pokok + tunjangan − potongan
                  </div>
                </div>
              </div>
            </div>

            <div className="px-6 py-4 border-t border-slate-200/70 text-[11px] text-slate-500 flex items-center justify-between">
              <span>© {new Date().getFullYear()} Human Plus Institute</span>
              <span>Payroll Internal System</span>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
