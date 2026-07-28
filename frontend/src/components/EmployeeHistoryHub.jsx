import { useState } from "react";
import useSWR from "swr";
import { Link } from "react-router-dom";
import { Card, CardHeader, CardTitle, CardContent } from "./ui/card";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "./ui/dialog";
import { api } from "../lib/api";
import { formatRupiah } from "../lib/utils";

function formatDate(value, options) {
  const datePart = String(value || "").slice(0, 10);
  if (!/^\d{4}-\d{2}-\d{2}$/.test(datePart)) return "-";

  const date = new Date(`${datePart}T00:00:00`);
  return Number.isNaN(date.getTime()) ? "-" : date.toLocaleDateString("id-ID", options);
}

function parseDate(value) {
  const datePart = String(value || "").slice(0, 10);
  if (!/^\d{4}-\d{2}-\d{2}$/.test(datePart)) return null;

  const date = new Date(`${datePart}T00:00:00`);
  return Number.isNaN(date.getTime()) ? null : date;
}

function formatProfileAmount(value, zeroLabel) {
  if (value === null || value === undefined || value === "") return "Belum diatur";
  const amount = Number(value);
  return Number.isFinite(amount) && amount > 0 ? formatRupiah(amount) : zeroLabel;
}

function formatHistoryNote(value) {
  const note = String(value || "").trim();

  // Riwayat lama menyimpan alasan kosong sebagai ": -". Jangan tampilkan
  // placeholder itu kepada pengguna.
  return note.replace(/:\s*-\s*\(Approved by Dir\)/i, " disetujui Direktur");
}

function rateUnitLabel(rate) {
  if (rate?.code === "position") return "Tetap bulanan";

  return {
    flat: "Tetap bulanan",
    per_mandays: "Per total hari dibayar",
    per_trip: "Per perjalanan dinas",
    per_toddler: "Per balita",
  }[rate?.calculation_type] || "Tarif sesuai aturan tunjangan";
}

export default function EmployeeHistoryHub({ employeeId, role }) {
  const [expandedHistoryIds, setExpandedHistoryIds] = useState({});
  const [positionModal, setPositionModal] = useState({
    open: false,
    profile: null,
  });

  const { data, error, isLoading } = useSWR(
    employeeId ? `employee-history-${employeeId}` : null,
    async () => {
      const [salaryProfiles, jobHistories, payrolls] = await Promise.all([
        api(`/employees/${employeeId}/salary-profiles`),
        api(`/employees/${employeeId}/job-histories`),
        api(`/payrolls?employee_id=${employeeId}`),
      ]);

      return {
        salaryProfiles: salaryProfiles || [],
        jobHistories: Array.isArray(jobHistories) ? jobHistories : [],
        payrolls: payrolls?.data || payrolls || [],
      };
    },
  );

  const salaryProfiles = data?.salaryProfiles || [];
  const jobHistories = data?.jobHistories || [];
  const payrolls = data?.payrolls || [];
  const err = error?.message || "";

  const isEmployee = ["staff", "employee"].includes(String(role || "").toLowerCase());

  const closePositionModal = () => {
    setPositionModal({ open: false, profile: null });
  };

  const openPositionModal = (profile) => {
    setPositionModal({ open: true, profile });
  };

  if (isLoading && !data) {
    return (
      <Card className="bg-white border border-border shadow-sm mt-6">
        <CardContent className="p-8 text-center text-slate-500">
          Memuat data riwayat...
        </CardContent>
      </Card>
    );
  }

  if (err) {
    return (
      <Card className="bg-white border-rose-200 shadow-sm mt-6">
        <CardContent className="p-8 text-center text-rose-500 font-medium">
          {err}
        </CardContent>
      </Card>
    );
  }

  const historyRows = (() => {
    const usedProfileIds = new Set();
    const rows = jobHistories.map((history) => {
      const startDate = String(history.start_date || "").slice(0, 10);
      const profile = salaryProfiles.find((item) => {
        if (usedProfileIds.has(item.id)) return false;
        const profileDate = String(item.effective_from || "").slice(0, 10);
        return profileDate === startDate
          && history.position_id
          && item.position_id
          && Number(history.position_id) === Number(item.position_id);
      }) || salaryProfiles.find((item) => {
        if (usedProfileIds.has(item.id)) return false;
        return history.position_id
          && item.position_id
          && Number(history.position_id) === Number(item.position_id);
      });

      if (profile) usedProfileIds.add(profile.id);

      return {
        ...(profile || {}),
        id: `job-${history.id}`,
        profile_id: profile?.id ?? null,
        position: typeof history.position === "string"
          ? history.position
          : history.position?.name || profile?.position || "-",
        effective_from: startDate || profile?.effective_from,
        end_date: history.end_date,
        status: history.status,
        notes: history.notes,
        history_created_at: history.created_at,
      };
    });

    salaryProfiles.forEach((profile) => {
      if (!usedProfileIds.has(profile.id) && !rows.some((row) => row.effective_from === profile.effective_from)) {
        rows.push({ ...profile, profile_id: profile.id });
      }
    });

    return rows.sort((a, b) => {
      const dateOrder = String(b.effective_from || "").localeCompare(String(a.effective_from || ""));
      if (dateOrder !== 0) return dateOrder;

      return String(b.history_created_at || b.created_at || "")
        .localeCompare(String(a.history_created_at || a.created_at || ""));
    });
  })();

  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const isActiveAtToday = (row) => {
    const start = parseDate(row.effective_from);
    const end = parseDate(row.end_date);

    return start && start <= today && (!end || end >= today);
  };
  const currentIndex = historyRows.findIndex((row) => row.status === "active" && isActiveAtToday(row));
  const visibleCurrentIndex = currentIndex >= 0
    ? currentIndex
    : historyRows.findIndex(isActiveAtToday);

  return (
    <div className="space-y-6 mt-6">

      {/* Tables & Timeline Section */}
      <div className="grid grid-cols-1 gap-6">
        
        {/* Timeline Riwayat Jabatan */}
        <Card className="bg-white border border-border shadow-sm">
          <CardHeader className="pb-4 border-b border-slate-100">
            <CardTitle className="text-sm font-bold text-slate-800">Perjalanan Karir & Jabatan</CardTitle>
          </CardHeader>
          <CardContent className="p-6">
            {historyRows.length === 0 ? (
              <div className="text-center py-8 text-slate-400 font-medium">Belum ada riwayat jabatan</div>
            ) : (
              <div className="relative border-l-2 border-indigo-100 ml-3 md:ml-6 space-y-8">
                {historyRows.map((sp, index) => {
                  const start = parseDate(sp.effective_from);
                  const isCurrent = index === visibleCurrentIndex;
                  const isUpcoming = Boolean(start && start > today);
                  const isExpanded = expandedHistoryIds[sp.id] ?? isCurrent;
                  const statusLabel = isCurrent ? "Posisi Saat Ini" : isUpcoming ? "Akan Berlaku" : "Riwayat";
                  const isHighlighted = isCurrent || isUpcoming;

                  return (
                    <div key={sp.id} className="relative pl-6 md:pl-8">
                      {/* Timeline Dot */}
                      <div className={`absolute -left-[9px] top-1.5 h-4 w-4 rounded-full border-2 bg-white ${
                        isCurrent ? 'border-indigo-600 ring-4 ring-indigo-50' : isUpcoming ? 'border-amber-500 ring-4 ring-amber-50' : 'border-slate-300'
                      }`} />
                      
                      {/* Content Card */}
                      <div className={`rounded-xl border p-4 transition-all ${
                        isCurrent ? 'bg-indigo-50/50 border-indigo-200 shadow-sm' : isUpcoming ? 'bg-amber-50/40 border-amber-200 shadow-sm' : 'bg-white border-slate-200 hover:border-indigo-100 hover:shadow-sm'
                      }`}>
                        <div>
                          <button
                            type="button"
                            onClick={() => setExpandedHistoryIds((current) => ({
                              ...current,
                              [sp.id]: !isExpanded,
                            }))}
                            aria-expanded={isExpanded}
                            className="flex w-full flex-col gap-3 text-left md:flex-row md:items-start md:justify-between"
                          >
                          <div>
                            <div className="flex items-center gap-2 mb-1">
                              <h4 className={`text-base font-bold ${isCurrent ? 'text-indigo-900' : 'text-slate-800'}`}>
                                {sp.position || "-"}
                              </h4>
                              {isHighlighted && (
                                <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider ${
                                  isCurrent ? 'bg-indigo-600 text-white' : 'bg-amber-100 text-amber-800'
                                }`}>
                                  {statusLabel}
                                </span>
                              )}
                            </div>
                            <p className="text-xs font-medium text-slate-500 flex items-center gap-1.5">
                              <span className={`w-2 h-2 rounded-full ${isCurrent ? 'bg-emerald-400' : isUpcoming ? 'bg-amber-400' : 'bg-slate-300'}`}></span>
                              Berlaku sejak: {formatDate(sp.effective_from, { day: 'numeric', month: 'long', year: 'numeric' })}
                              {sp.end_date && ` · sampai ${formatDate(sp.end_date, { day: 'numeric', month: 'long', year: 'numeric' })}`}
                            </p>
                            {sp.notes && <p className="mt-1 text-[11px] text-slate-400">{formatHistoryNote(sp.notes)}</p>}
                          </div>
                          {isEmployee && (
                            <span className="shrink-0 text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                              {isExpanded ? "Sembunyikan rincian" : "Lihat rincian gaji"}
                            </span>
                          )}
                          </button>

                          {isEmployee && isExpanded && (
                            <div className="mt-4">
                              {sp.profile_id ? (
                                <button
                                  type="button"
                                  onClick={() => openPositionModal(sp)}
                                  className="rounded-md border border-indigo-200 bg-white px-3 py-2 text-xs font-bold text-indigo-700 transition-colors hover:bg-indigo-50"
                                >
                                  Lihat komponen gaji jabatan
                                </button>
                              ) : (
                                <p className="text-xs text-slate-500">
                                  Detail gaji tidak tersedia karena profil gaji untuk riwayat jabatan ini belum tersimpan.
                                </p>
                              )}
                            </div>
                          )}
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </CardContent>
        </Card>
        {/* Table Gaji Bulanan */}
        <Card className="bg-white border border-border shadow-sm">
          <CardHeader>
            <CardTitle className="text-sm font-bold text-slate-800">Riwayat Slip Gaji Bulanan</CardTitle>
          </CardHeader>
          <CardContent className="p-0 overflow-x-auto">
            <table className="w-full text-sm text-left border-collapse">
              <thead className="bg-slate-50 text-slate-500 text-[11px] uppercase tracking-wider font-bold border-b border-slate-200">
                <tr>
                  <th className="px-4 py-3">Periode</th>
                  {isEmployee && <th className="px-4 py-3 text-right">Gaji Pokok</th>}
                  {isEmployee && <th className="px-4 py-3 text-right">Take Home Pay</th>}
                  <th className="px-4 py-3 text-center">Status</th>
                  {isEmployee && <th className="px-4 py-3 text-center">Aksi</th>}
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {payrolls.length === 0 ? (
                  <tr>
                    <td colSpan={isEmployee ? "5" : "2"} className="text-center py-8 text-slate-400 font-medium">Belum ada riwayat gaji</td>
                  </tr>
                ) : (
                  payrolls.map((pr) => (
                    <tr key={pr.id} className="text-slate-700 hover:bg-slate-50/50 transition-colors">
                      <td className="px-4 py-3 font-medium text-slate-800">
                        {formatDate(pr.periode, { month: 'long', year: 'numeric' })}
                      </td>
                      {isEmployee && (
                        <>
                          <td className="px-4 py-3 text-right font-mono">
                            {pr.gaji_pokok != null ? formatRupiah(pr.gaji_pokok) : <span className="text-slate-300 italic">-</span>}
                          </td>
                          <td className="px-4 py-3 text-right font-mono font-bold text-sky-600">
                            {pr.total != null ? formatRupiah(pr.total) : <span className="text-slate-300 italic">-</span>}
                          </td>
                        </>
                      )}
                      <td className="px-4 py-3 text-center">
                        <span className={`inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider
                          ${pr.status === 'paid' ? 'bg-teal-100 text-teal-700' :
                            pr.status === 'approved' ? 'bg-indigo-100 text-indigo-700' :
                            pr.status === 'pending' ? 'bg-amber-100 text-amber-700' :
                            'bg-slate-100 text-slate-700'
                          }`}>
                          {pr.status || "DRAFT"}
                        </span>
                      </td>
                      {isEmployee && (
                        <td className="px-4 py-3 text-center">
                          <Link to={`/payrolls/${pr.id}`} className="text-xs font-bold text-sky-600 hover:text-sky-800 bg-sky-50 px-2 py-1 rounded transition-colors border border-sky-100 hover:bg-sky-100">
                            Lihat Detail
                          </Link>
                        </td>
                      )}
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </CardContent>
        </Card>

      </div>

      <Dialog open={positionModal.open} onOpenChange={(open) => !open && closePositionModal()}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>Komponen Gaji Jabatan</DialogTitle>
            <DialogDescription>
              {positionModal.profile
                ? `${positionModal.profile.position || "Jabatan"} · berlaku sejak ${formatDate(positionModal.profile.effective_from, { day: "numeric", month: "long", year: "numeric" })}`
                : ""}
            </DialogDescription>
          </DialogHeader>

          {positionModal.profile && (
            <div className="space-y-3">
              <div className="flex items-center justify-between rounded-lg border border-sky-100 bg-sky-50/50 p-3 text-sm">
                <div>
                  <p className="font-semibold text-slate-800">Gaji Pokok</p>
                  <p className="mt-0.5 text-xs text-slate-500">Tarif per hari kerja</p>
                </div>
                <span className="font-mono font-bold text-sky-700">{formatProfileAmount(positionModal.profile.base_salary_amount, "Belum diatur")}</span>
              </div>
              <div className="rounded-lg border border-indigo-100 bg-indigo-50/40 p-3">
                <p className="text-sm font-semibold text-slate-800">Tunjangan Berdasarkan Jabatan</p>
                {positionModal.profile.position_allowance_rates?.length > 0 ? (
                  <div className="mt-2 divide-y divide-indigo-100">
                    {positionModal.profile.position_allowance_rates.map((rate) => (
                      <div key={rate.id} className="flex items-center justify-between gap-3 py-2 text-sm">
                        <div>
                          <p className="font-medium text-slate-700">{rate.name}</p>
                          <p className="text-xs text-slate-500">{rateUnitLabel(rate)}</p>
                        </div>
                        <span className="shrink-0 font-mono font-semibold text-indigo-700">{formatRupiah(rate.rate_amount)}</span>
                      </div>
                    ))}
                  </div>
                ) : (
                  <p className="mt-2 text-sm text-slate-500">Belum ada tarif tunjangan untuk jabatan ini.</p>
                )}
              </div>
              <p className="rounded-lg bg-slate-50 p-3 text-xs leading-relaxed text-slate-500">
                Tunjangan Makan, Transport, atau Anak baru menghasilkan nominal akhir saat payroll dihitung sesuai hari kerja, perjalanan dinas, atau jumlah balita pegawai.
              </p>
            </div>
          )}

          <DialogFooter>
            <DialogClose asChild>
              <button type="button" className="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Tutup
              </button>
            </DialogClose>
          </DialogFooter>
        </DialogContent>
      </Dialog>

    </div>
  );
}
