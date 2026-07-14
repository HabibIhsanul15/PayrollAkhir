import { getAllowanceConditionStatus } from "@/lib/payrollConditionPreview";

function formatNum(num) {
  return new Intl.NumberFormat("id-ID").format(num);
}

function formatBaseSalaryHint(basis) {
  return basis === "monthly"
    ? "Mengikuti nominal bulanan default pada master jabatan."
    : "Mengikuti tarif harian default pada master jabatan.";
}

function getInfoBadge(type) {
  switch (type) {
    case "per_trip":
      return (
        <span className="rounded border border-sky-200 bg-sky-100 px-2 py-0.5 text-[10px] font-bold text-sky-700">
          Dibayar per Perjalanan
        </span>
      );
    case "per_mandays":
      return (
        <span className="rounded border border-amber-200 bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700">
          Dibayar per Hari Hadir
        </span>
      );
    case "formula":
      return (
        <span className="rounded border border-emerald-200 bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
          Dikalikan Syarat
        </span>
      );
    case "fixed":
    case "flat":
      return (
        <span className="rounded border border-purple-200 bg-purple-100 px-2 py-0.5 text-[10px] font-bold text-purple-700">
          Tetap Setiap Bulan
        </span>
      );
    default:
      return (
        <span className="rounded border border-slate-200 bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase text-slate-700">
          {type}
        </span>
      );
  }
}

export default function EmployeePayrollPreview({ grade, form }) {
  if (!grade) return null;

  return (
    <div className="overflow-hidden rounded-xl border border-slate-200 bg-slate-50/50">
      <div className="border-b border-slate-200 bg-slate-100/50 px-4 py-3">
        <h3 className="text-sm font-semibold text-slate-800">Standar Jabatan: {grade.name}</h3>
        <p className="mt-0.5 text-[11px] text-slate-500">
          Nilai ini menjadi acuan payroll. Basis gaji pokok dan nominal default diatur dari menu Jabatan.
        </p>
      </div>

      <div className="space-y-5 p-4">
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <label className="mb-1 block text-[11px] font-medium uppercase tracking-wider text-slate-500">
              Tunjangan Jabatan
            </label>
            <div className="relative">
              <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-slate-500">
                Rp
              </span>
              <input
                type="number"
                min="0"
                value={form.position_allowance}
                readOnly
                className="w-full rounded-xl border border-slate-200 bg-slate-50 py-2 pl-10 pr-3 text-sm font-semibold text-slate-900"
              />
            </div>
            <p className="mt-1 text-[10px] text-slate-400">Mengikuti tarif aktif pada master jabatan.</p>
          </div>

          <div>
            <label className="mb-1 block text-[11px] font-medium uppercase tracking-wider text-slate-500">
              Gaji Pokok Default
            </label>
            <div className="relative">
              <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-slate-500">
                Rp
              </span>
              <input
                type="number"
                min="0"
                value={form.base_salary_amount}
                readOnly
                className="w-full rounded-xl border border-slate-200 bg-slate-50 py-2 pl-10 pr-3 text-sm font-semibold text-slate-900"
              />
            </div>
            <p className="mt-1 text-[10px] text-slate-400">
              {formatBaseSalaryHint(form.base_salary_basis)} Ubah basis dan nominalnya dari menu Jabatan.
            </p>
          </div>
        </div>

        {grade.allowance_rates?.length > 0 ? (
          <div>
            <div className="mb-2 text-[11px] font-medium uppercase tracking-wider text-slate-500">
              Daftar Tunjangan
            </div>
            <div className="overflow-hidden rounded-lg border border-slate-200">
              <table className="w-full text-left text-xs text-slate-700">
                <thead className="border-b border-slate-200 bg-slate-50 text-slate-500">
                  <tr>
                    <th className="px-3 py-2.5 font-medium">Nama Tunjangan</th>
                    <th className="px-3 py-2.5 font-medium">Aturan Pencairan</th>
                    <th className="px-3 py-2.5 text-right font-medium">Tarif Dasar</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 bg-white">
                  {grade.allowance_rates.map((rate, index) => {
                    const conditionStatus = getAllowanceConditionStatus(rate.allowance_type, form);

                    return (
                      <tr key={index} className="transition-colors hover:bg-slate-50/50">
                        <td className="px-3 py-2.5 font-medium text-slate-800">
                          {rate.allowance_type?.name}
                          {!conditionStatus.eligible ? (
                            <span
                              className="ml-2 rounded border border-slate-200 bg-slate-100 px-1 py-0.5 text-[9px] text-slate-600"
                              title={conditionStatus.helperText}
                            >
                              Syarat belum terpenuhi
                            </span>
                          ) : null}
                        </td>
                        <td className="px-3 py-2.5">{getInfoBadge(rate.allowance_type?.calculation_type)}</td>
                        <td className="px-3 py-2.5 text-right font-semibold text-slate-900">
                          Rp {formatNum(rate.rate_amount || 0)}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>
        ) : null}
      </div>
    </div>
  );
}
