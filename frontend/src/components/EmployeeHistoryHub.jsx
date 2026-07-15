import { useState, useEffect } from "react";
import { Link } from "react-router-dom";
import { Card, CardHeader, CardTitle, CardContent } from "./ui/card";
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

export default function EmployeeHistoryHub({ employeeId, role }) {
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");
  const [salaryProfiles, setSalaryProfiles] = useState([]);
  const [jobHistories, setJobHistories] = useState([]);
  const [payrolls, setPayrolls] = useState([]);

  const isHCGA = String(role || "").toLowerCase() === "hcga";

  useEffect(() => {
    let active = true;
    const fetchData = async () => {
      try {
        setLoading(true);
        setErr("");

        const [dataSp, dataJobs, dataPr] = await Promise.all([
          api(`/employees/${employeeId}/salary-profiles`),
          api(`/employees/${employeeId}/job-histories`),
          api(`/payrolls?employee_id=${employeeId}`)
        ]);

        if (active) {
          setSalaryProfiles(dataSp || []);
          setJobHistories(Array.isArray(dataJobs) ? dataJobs : []);
          setPayrolls(dataPr?.data || dataPr || []);
        }
      } catch (error) {
        if (active) setErr(error.message);
      } finally {
        if (active) setLoading(false);
      }
    };
    fetchData();
    return () => { active = false; };
  }, [employeeId]);

  if (loading) {
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
          || (history.position_id && item.position_id && Number(history.position_id) === Number(item.position_id));
      });

      if (profile) usedProfileIds.add(profile.id);

      return {
        ...(profile || {}),
        id: `job-${history.id}`,
        position: typeof history.position === "string"
          ? history.position
          : history.position?.name || profile?.position || "-",
        effective_from: startDate || profile?.effective_from,
        end_date: history.end_date,
        status: history.status,
        notes: history.notes,
      };
    });

    salaryProfiles.forEach((profile) => {
      if (!usedProfileIds.has(profile.id) && !rows.some((row) => row.effective_from === profile.effective_from)) {
        rows.push(profile);
      }
    });

    return rows.sort((a, b) => String(b.effective_from || "").localeCompare(String(a.effective_from || "")));
  })();

  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const currentIndex = historyRows.findIndex((row) => {
    const start = parseDate(row.effective_from);
    const end = parseDate(row.end_date);

    return start && start <= today && (!end || end >= today);
  });

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
                  const isCurrent = index === currentIndex;
                  const isUpcoming = Boolean(start && start > today);
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
                        <div className="flex flex-col md:flex-row md:items-start justify-between gap-4">
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
                            {sp.notes && <p className="mt-1 text-[11px] text-slate-400">{sp.notes}</p>}
                          </div>

                          {!isHCGA && (
                            <div className="flex flex-wrap gap-3">
                              <div className="bg-white px-3 py-2 rounded-lg border border-slate-200 shadow-sm min-w-[140px]">
                                <div className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Gaji Pokok</div>
                                <div className="text-sm font-mono font-bold text-sky-600">
                                  {formatProfileAmount(sp.base_salary_amount, "Belum diatur")}
                                </div>
                                <div className="mt-1 text-[10px] text-slate-400">
                                  {sp.base_salary_basis === "monthly" ? "Bulanan" : "Harian"}
                                </div>
                              </div>
                              <div className="bg-white px-3 py-2 rounded-lg border border-slate-200 shadow-sm min-w-[140px]">
                                <div className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Tunj. Jabatan</div>
                                <div className="text-sm font-mono font-bold text-indigo-600">
                                  {formatProfileAmount(sp.position_allowance, "Tidak ada")}
                                </div>
                              </div>
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
                  {!isHCGA && <th className="px-4 py-3 text-right">Gaji Pokok</th>}
                  {!isHCGA && <th className="px-4 py-3 text-right">Take Home Pay</th>}
                  <th className="px-4 py-3 text-center">Status</th>
                  {!isHCGA && <th className="px-4 py-3 text-center">Aksi</th>}
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {payrolls.length === 0 ? (
                  <tr>
                    <td colSpan={isHCGA ? "2" : "5"} className="text-center py-8 text-slate-400 font-medium">Belum ada riwayat gaji</td>
                  </tr>
                ) : (
                  payrolls.map((pr) => (
                    <tr key={pr.id} className="text-slate-700 hover:bg-slate-50/50 transition-colors">
                      <td className="px-4 py-3 font-medium text-slate-800">
                        {formatDate(pr.periode, { month: 'long', year: 'numeric' })}
                      </td>
                      {!isHCGA && (
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
                      {!isHCGA && (
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

    </div>
  );
}
