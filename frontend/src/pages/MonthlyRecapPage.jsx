import { useMemo, useState } from "react";
import useSWR from "swr";
import { api } from "@/lib/api";
import { getUser } from "@/lib/auth";
import { Table } from "@/components/ui/table";
import { Button } from "@/components/ui/button";
import { useConfirm } from "@/components/ConfirmProvider";
import { currentPayrollMonth, monthLabel } from "@/lib/utils";
import PeriodDisplay from "@/components/PeriodDisplay";

function emptyRecap(salaryProfileId = "") {
  return {
    salary_profile_id: salaryProfileId,
    wfo_days: 0,
    wfh_days: 0,
    out_of_town_days: 0,
    business_trips: 0,
    training_days: 0,
    overtime_hours: 0,
    late_count: 0,
  };
}

function cleanNumber(value) {
  if (value === null || value === undefined || value === "") return "0";
  const number = Number(value);
  if (!Number.isFinite(number)) return "0";
  return Number.isInteger(number)
    ? String(number)
    : number.toLocaleString("id-ID", { maximumFractionDigits: 2 });
}

function wholeInputValue(value) {
  if (value === null || value === undefined || value === "") return "0";
  const number = Math.trunc(Number(value) || 0);
  return String(Math.max(number, 0));
}

function recapPaidDays(recap) {
  return (
    Number(recap.wfo_days || 0) +
    Number(recap.wfh_days || 0) +
    Number(recap.out_of_town_days || 0) +
    Number(recap.training_days || 0)
  );
}

export default function MonthlyRecapPage() {
  const { confirm } = useConfirm();
  const user = getUser();
  const role = String(user?.role || "").toLowerCase();
  const isHCGA = role === "hcga";
  const [period, setPeriod] = useState(() => currentPayrollMonth());
  const [showModal, setShowModal] = useState(false);
  const [notice, setNotice] = useState(null);
  const [formRecaps, setFormRecaps] = useState([emptyRecap()]);
  const [employeeProfiles, setEmployeeProfiles] = useState([]);
  const [selectedEmployeeId, setSelectedEmployeeId] = useState("");
  const [detailGroup, setDetailGroup] = useState(null);

  const { data: rawRecaps, mutate: mutateRecaps } = useSWR(`/monthly-recaps?period_month=${period}`);
  const { data: rawEmployees } = useSWR("/employees");

  const recaps = useMemo(() => (Array.isArray(rawRecaps) ? rawRecaps : []), [rawRecaps]);
  const groupedRecaps = useMemo(() => {
    const map = new Map();

    for (const recap of recaps) {
      const employeeId = recap.employee_id || recap.employee?.id || "unknown";
      const key = `${employeeId}-${recap.period_month}`;
      const existing = map.get(key) || {
        key,
        employee_id: employeeId,
        employee: recap.employee,
        period_month: recap.period_month,
        items: [],
        wfo_days: 0,
        wfh_days: 0,
        out_of_town_days: 0,
        training_days: 0,
        business_trips: 0,
        overtime_hours: 0,
        late_count: 0,
        total_mandays: 0,
        total_attendance: 0,
      };

      existing.items.push(recap);
      existing.employee = existing.employee || recap.employee;
      existing.wfo_days += Number(recap.wfo_days || 0);
      existing.wfh_days += Number(recap.wfh_days || 0);
      existing.out_of_town_days += Number(recap.out_of_town_days || 0);
      existing.training_days += Number(recap.training_days || 0);
      existing.business_trips += Number(recap.business_trips || 0);
      existing.overtime_hours += Number(recap.overtime_hours || 0);
      existing.late_count += Number(recap.late_count || 0);
      existing.total_mandays += Number(recap.total_mandays || 0);
      existing.total_attendance += recapPaidDays(recap);
      map.set(key, existing);
    }

    return Array.from(map.values()).map((group) => ({
      ...group,
      isSubmitted: group.items.length > 0 && group.items.every((item) => item.is_finalized),
    }));
  }, [recaps]);
  const summary = useMemo(() => groupedRecaps.reduce(
    (acc, group) => ({
      employees: acc.employees + 1,
      total_mandays: acc.total_mandays + Number(group.total_mandays || 0),
      total_attendance: acc.total_attendance + Number(group.total_attendance || 0),
      business_trips: acc.business_trips + Number(group.business_trips || 0),
      overtime_hours: acc.overtime_hours + Number(group.overtime_hours || 0),
      late_count: acc.late_count + Number(group.late_count || 0),
    }),
    {
      employees: 0,
      total_mandays: 0,
      total_attendance: 0,
      business_trips: 0,
      overtime_hours: 0,
      late_count: 0,
    }
  ), [groupedRecaps]);
  const employees = Array.isArray(rawEmployees) ? rawEmployees : (rawEmployees?.data || []);
  const selectedEmployee = employees.find((employee) => String(employee.id) === String(selectedEmployeeId));
  const maxDays = useMemo(() => {
    if (!period) return 0;
    const [yearStr, monthStr] = period.split("-");
    const year = Number(yearStr);
    const month = Number(monthStr);
    const periodEnd = new Date(year, month - 1, 27);
    const periodStart = new Date(year, month - 2, 28);
    
    let effectiveStart = periodStart;
    
    if (selectedEmployee && selectedEmployee.join_date) {
      const joinDate = new Date(selectedEmployee.join_date);
      joinDate.setHours(0, 0, 0, 0);
      if (joinDate > periodEnd) return 0;
      if (joinDate > periodStart) {
        effectiveStart = joinDate;
      }
    }
    
    const diffTime = periodEnd.getTime() - effectiveStart.getTime();
    return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
  }, [period, selectedEmployee]);
  const totalPaidDays = useMemo(
    () => formRecaps.reduce((total, recap) => total + recapPaidDays(recap), 0),
    [formRecaps]
  );

  // Per-segment validation warnings
  const segmentWarnings = useMemo(() => {
    return formRecaps.map((recap) => {
      const warnings = [];
      const wfo = Number(recap.wfo_days || 0);
      const wfh = Number(recap.wfh_days || 0);
      const lk = Number(recap.out_of_town_days || 0);
      const training = Number(recap.training_days || 0);
      const attendanceDays = wfo + wfh + lk; // hari yang "hadir" fisik/remote
      const lateCount = Number(recap.late_count || 0);
      const businessTrips = Number(recap.business_trips || 0);

      // 1. Terlambat tidak boleh melebihi hari hadir (WFO + WFH + Luar Kota)
      if (lateCount > attendanceDays) {
        warnings.push(`Jumlah terlambat (${lateCount}) melebihi total hari hadir (${attendanceDays}). Karyawan hanya bisa terlambat pada hari dimana dia hadir.`);
      }

      // 2. Perjalanan dinas tidak boleh melebihi hari luar kota
      if (businessTrips > lk) {
        warnings.push(`Jumlah perjalanan dinas (${businessTrips}) melebihi hari luar kota (${lk}). Perjalanan dinas hanya terjadi saat berada di luar kota.`);
      }

      // 4. Training days seharusnya 0 jika bukan trainer (TODO: kalau ada flag trainer)
      // 5. Semua field individual tidak boleh melebihi maxDays
      if (wfo > maxDays) {
        warnings.push(`Hari WFO (${wfo}) melebihi maksimal hari periode (${maxDays}).`);
      }
      if (wfh > maxDays) {
        warnings.push(`Hari WFH (${wfh}) melebihi maksimal hari periode (${maxDays}).`);
      }
      if (lk > maxDays) {
        warnings.push(`Hari Luar Kota (${lk}) melebihi maksimal hari periode (${maxDays}).`);
      }
      if (training > maxDays) {
        warnings.push(`Hari Training (${training}) melebihi maksimal hari periode (${maxDays}).`);
      }

      return warnings;
    });
  }, [formRecaps, maxDays]);

  const hasAnyWarning = useMemo(() => segmentWarnings.some(w => w.length > 0), [segmentWarnings]);

  const fetchRecaps = () => mutateRecaps();

  const fillRecapForm = (profiles, existingRecaps = null) => {
    if (existingRecaps?.length) {
      setFormRecaps(existingRecaps.map((recap) => ({
        salary_profile_id: recap.salary_profile_id || "",
        wfo_days: wholeInputValue(recap.wfo_days),
        wfh_days: wholeInputValue(recap.wfh_days),
        out_of_town_days: wholeInputValue(recap.out_of_town_days),
        business_trips: wholeInputValue(recap.business_trips),
        training_days: wholeInputValue(recap.training_days),
        late_count: wholeInputValue(recap.late_count),
      })));
      return;
    }

    const relevantProfiles = [];
    for (const prof of profiles) {
      const endOfMonth = `${period}-31`;
      const startOfMonth = `${period}-01`;

      if (prof.effective_from <= endOfMonth) {
        relevantProfiles.push(prof);
        if (prof.effective_from < startOfMonth) {
          break;
        }
      }
    }

    relevantProfiles.reverse();

    if (relevantProfiles.length > 0) {
      setFormRecaps(relevantProfiles.map((prof) => emptyRecap(prof.id)));
    } else {
      setFormRecaps([emptyRecap()]);
    }
  };

  const loadEmployeeProfiles = async (empId, existingRecaps = null) => {
    if (!empId) {
      setEmployeeProfiles([]);
      setFormRecaps([emptyRecap()]);
      return;
    }

    try {
      const res = await api(`/employees/${empId}/salary-profiles`);
      const profiles = Array.isArray(res) ? res : [];
      setEmployeeProfiles(profiles);
      fillRecapForm(profiles, existingRecaps);
    } catch (err) {
      setNotice({
        type: "error",
        title: "Gagal Memuat Profil",
        message: err?.message || "Profil gaji karyawan tidak dapat dimuat.",
      });
    }
  };

  const handleEmployeeChange = async (empId) => {
    setSelectedEmployeeId(empId);
    await loadEmployeeProfiles(empId);
  };

  const openCreateModal = () => {
    setSelectedEmployeeId("");
    setEmployeeProfiles([]);
    setFormRecaps([emptyRecap()]);
    setShowModal(true);
  };

  const openEditModal = async (recap) => {
    const employeeId = recap.employee_id || recap.employee?.id;
    const employeeRecaps = recaps.filter((item) =>
      String(item.employee_id || item.employee?.id) === String(employeeId)
    ).filter((item) => !item.is_finalized);

    if (employeeRecaps.length === 0) return;

    setSelectedEmployeeId(String(employeeId));
    await loadEmployeeProfiles(employeeId, employeeRecaps);
    setShowModal(true);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (totalPaidDays > maxDays) {
      setNotice({
        type: "error",
        title: "Total Hari Tidak Valid",
        message: `Total hari dibayar (${totalPaidDays}) melebihi jumlah maksimal hari di bulan ini (${maxDays} hari).`,
      });
      return;
    }

    if (hasAnyWarning) {
      setNotice({
        type: "error",
        title: "Data Tidak Konsisten",
        message: segmentWarnings.flat().join(" "),
      });
      return;
    }

    try {
      await api("/monthly-recaps", { 
        method: "POST", 
        body: {
          employee_id: selectedEmployeeId,
          period_month: period,
          recaps: formRecaps
        }
      });
      setNotice({
        type: "success",
        title: "Rekap Tersimpan",
        message: "Rekap bulanan berhasil disimpan.",
      });
      setShowModal(false);
      fetchRecaps();
    } catch (err) {
      setNotice({
        type: "error",
        title: "Gagal Menyimpan Rekap",
        message: err.message || "Gagal menyimpan rekap.",
      });
    }
  };

  const handleSubmitToFinance = async (group) => {
    const ok = await confirm("Kirim rekap ini ke Finance? Setelah dikirim, data tidak bisa diedit lagi oleh HCGA.");
    if (!ok) return;
    try {
      await api("/monthly-recaps/submit-to-finance", {
        method: "POST",
        body: {
          employee_id: group.employee_id,
          period_month: group.period_month,
        },
      });
      setNotice({
        type: "success",
        title: "Rekap Terkirim",
        message: "Rekap sudah dikirim ke Finance dan dikunci dari perubahan.",
      });
      fetchRecaps();
    } catch (err) {
      setNotice({
        type: "error",
        title: "Gagal Mengirim Rekap",
        message: err?.message || "Gagal mengirim rekap ke Finance.",
      });
    }
  };

  const handleDeleteGroup = async (group) => {
    const ok = await confirm("Hapus draft rekap ini?");
    if (!ok) return;

    try {
      const draftItems = group.items.filter((item) => !item.is_finalized);
      await Promise.all(draftItems.map((item) => api(`/monthly-recaps/${item.id}`, { method: "DELETE" })));
      setNotice({
        type: "success",
        title: "Draft Dihapus",
        message: "Draft rekap bulanan berhasil dihapus.",
      });
      fetchRecaps();
    } catch (err) {
      setNotice({
        type: "error",
        title: "Gagal Menghapus",
        message: err?.message || "Gagal menghapus draft rekap.",
      });
    }
  };
  
  const handleRecapChange = (index, field, value) => {
    const newRecaps = [...formRecaps];
    newRecaps[index][field] = value.replace(/\D/g, "");

    if (field === "out_of_town_days") {
      newRecaps[index]["business_trips"] = value.replace(/\D/g, "");
    }

    setFormRecaps(newRecaps);
  };
  
  const removeRecapRow = (index) => {
    const newRecaps = [...formRecaps];
    newRecaps.splice(index, 1);
    setFormRecaps(newRecaps);
  };

  return (
    <div className="p-6">
      <div className="flex items-center justify-between mb-4">
        <h1 className="text-2xl font-bold">Rekap Bulanan Karyawan</h1>
        <div className="flex gap-4">
          <input
            type="month"
            value={period}
            onChange={(e) => setPeriod(e.target.value)}
            className="border p-2 rounded"
          />
          {isHCGA ? <Button onClick={openCreateModal}>Input Rekap</Button> : null}
        </div>
      </div>

      <div className="mb-4 grid gap-3 md:grid-cols-3">
        <SummaryBox label="Karyawan Direkap" value={cleanNumber(summary.employees)} />
        <SummaryBox label="Total Perjalanan" value={`${cleanNumber(summary.business_trips)} kali`} />
        <SummaryBox label="Terlambat" value={`${cleanNumber(summary.late_count)} kali`} />
      </div>

      <div className="bg-white rounded-lg shadow overflow-x-auto">
        <Table>
          <thead>
            <tr>
              <th className="px-4 py-2 text-left">Nama Karyawan</th>
              <th className="px-4 py-2 text-left">Periode</th>
              <th className="px-4 py-2 text-right">Total Kehadiran</th>
              <th className="px-4 py-2 text-right">Total Perjalanan</th>
              <th className="px-4 py-2 text-right">Terlambat</th>
              <th className="px-4 py-2 text-center">Status</th>
              <th className="px-4 py-2 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            {groupedRecaps.map((r) => (
              <tr key={r.key} className="border-t align-top">
                <td className="px-4 py-3">{r.employee?.name}</td>
                <td className="px-4 py-3">{monthLabel(r.period_month)}</td>
                <td className="px-4 py-3 text-right text-sm text-slate-700">
                  <div className="font-semibold">{cleanNumber(r.total_attendance)} hari</div>
                </td>
                <td className="px-4 py-3 text-right text-sm text-slate-700">
                  <strong>{cleanNumber(r.business_trips)} kali</strong>
                </td>
                <td className="px-4 py-3 text-right font-semibold">{cleanNumber(r.late_count)} kali</td>
                <td className="px-4 py-2 text-center">
                  {r.isSubmitted ? (
                    <span className="px-2 py-1 bg-green-100 text-green-700 rounded text-sm">Terkirim</span>
                  ) : (
                    <span className="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-sm">Draft</span>
                  )}
                </td>
                <td className="px-4 py-3 text-center">
                  <Button size="sm" variant="outline" className="mr-2" onClick={() => setDetailGroup(r)}>
                    Detail
                  </Button>
                  {isHCGA && !r.isSubmitted ? (
                    <>
                      <Button size="sm" variant="outline" className="mr-2" onClick={() => openEditModal(r)}>
                        Edit
                      </Button>
                      <Button size="sm" variant="outline" className="mr-2" onClick={() => handleSubmitToFinance(r)}>
                        Kirim ke Finance
                      </Button>
                      <Button size="sm" variant="destructive" onClick={() => handleDeleteGroup(r)}>
                        Hapus
                      </Button>
                    </>
                  ) : (
                    <span className="text-xs text-slate-400">
                      {r.isSubmitted ? "Terkunci" : "Menunggu HCGA"}
                    </span>
                  )}
                </td>
              </tr>
            ))}
            {groupedRecaps.length === 0 && (
                <tr>
                  <td colSpan={7} className="px-4 py-4 text-center text-gray-500">
                    Belum ada data rekap untuk periode <PeriodDisplay period={period} />.
                  </td>
                </tr>
            )}
          </tbody>
          {groupedRecaps.length > 0 && (
            <tfoot>
              <tr className="border-t bg-slate-50 font-semibold">
                <td colSpan={2} className="px-4 py-3 text-right">Total Keseluruhan</td>
                <td className="px-4 py-3 text-right">{cleanNumber(summary.total_attendance)} hari</td>
                <td className="px-4 py-3 text-right">{cleanNumber(summary.business_trips)} kali</td>
                <td className="px-4 py-3 text-right">{cleanNumber(summary.late_count)} kali</td>
                <td colSpan={2}></td>
              </tr>
            </tfoot>
          )}
        </Table>
      </div>

      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" onClick={() => setShowModal(false)} />
          <div className="bg-white p-6 rounded-lg shadow-xl z-10 w-[600px] max-w-full max-h-[90vh] overflow-y-auto relative">
            <h2 className="text-lg font-semibold mb-1">Input Rekap Bulanan</h2>
            <p className="mb-4 text-xs text-slate-500">
              Rekap ini mencatat hari kerja dan aktivitas bulanan. Perhitungan gaji tetap mengikuti basis gaji pada profil karyawan.
            </p>
            <form onSubmit={handleSubmit} className="space-y-6">
              <div>
                <label className="block text-sm font-medium mb-1">Karyawan</label>
                <select
                  required
                  className="w-full border p-2 rounded"
                  value={selectedEmployeeId}
                  onChange={(e) => handleEmployeeChange(e.target.value)}
                >
                  <option value="">Pilih Karyawan</option>
                  {employees.map((emp) => (
                    <option key={emp.id} value={emp.id}>
                      {emp.name}
                    </option>
                  ))}
                </select>
              </div>

              {selectedEmployeeId && (
                <div className="rounded border border-blue-100 bg-blue-50 px-4 py-3 text-xs text-blue-900">
                  <div className="font-semibold text-blue-950">{selectedEmployee?.name || "Karyawan terpilih"}</div>
                  <div className="mt-1">
                    {selectedEmployee?.join_date && new Date(selectedEmployee.join_date) > new Date(Number(period.split("-")[0]), Number(period.split("-")[1]) - 2, 28)
                      ? <>Periode <PeriodDisplay period={period} /> (masuk {new Date(selectedEmployee.join_date).toLocaleDateString("id-ID", { day: "numeric", month: "short", year: "numeric" })}) memiliki maksimal </> 
                      : <>Periode <PeriodDisplay period={period} /> memiliki maksimal </>}
                    <strong>{maxDays} hari</strong>.
                    Total hari dibayar yang sedang diinput: <strong>{totalPaidDays} hari</strong>.
                  </div>
                  <div className="mt-1 text-blue-800">
                    Rekap ini menjadi dasar perhitungan payroll untuk gaji pokok, tunjangan berbasis rekap, lembur, dan potongan keterlambatan.
                  </div>
                </div>
              )}

              {selectedEmployeeId && formRecaps.map((recap, index) => (
                <div key={index} className="bg-slate-50 p-4 rounded-lg border border-slate-200 space-y-4 relative">
                  {(() => {
                    const prof = employeeProfiles.find(p => String(p.id) === String(recap.salary_profile_id));
                    if (!prof) return null;
                    return (
                      <div className="rounded border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">
                        <div className="font-semibold text-slate-900">{prof.position_name || "Jabatan"}</div>
                        <div className="mt-1 flex flex-wrap gap-x-3 gap-y-1">
                          <span>Mulai: {prof.effective_from}</span>
                          <span>Segmen jabatan untuk rekap payroll</span>
                        </div>
                      </div>
                    );
                  })()}

                  {formRecaps.length > 1 && (
                    <button type="button" onClick={() => removeRecapRow(index)} className="absolute top-4 right-4 text-rose-500 text-sm font-semibold hover:text-rose-700">
                      Hapus
                    </button>
                  )}
                  {formRecaps.length > 1 && (
                    <div className="mb-2">
                      <div className="inline-flex items-center px-3 py-1 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold border border-indigo-200">
                        Segmen Promosi/Demosi: {(() => {
                          const prof = employeeProfiles.find(p => String(p.id) === String(recap.salary_profile_id));
                          return prof ? `Mulai ${prof.effective_from} (Jabatan: ${prof.position_name})` : "Profil Tidak Ditemukan";
                        })()}
                      </div>
                    </div>
                  )}
                  
                  <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <div>
                      <label className="block text-xs font-medium mb-1">Hari WFO</label>
                      <input
                        type="text" inputMode="numeric" required
                        className="w-full border p-2 rounded text-sm"
                        value={recap.wfo_days}
                        onChange={(e) => handleRecapChange(index, "wfo_days", e.target.value)}
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-medium mb-1">Hari WFH</label>
                      <input
                        type="text" inputMode="numeric" required
                        className="w-full border p-2 rounded text-sm"
                        value={recap.wfh_days}
                        onChange={(e) => handleRecapChange(index, "wfh_days", e.target.value)}
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-medium mb-1">Hari Luar Kota</label>
                      <input
                        type="text" inputMode="numeric" required
                        className="w-full border p-2 rounded text-sm"
                        value={recap.out_of_town_days}
                        onChange={(e) => handleRecapChange(index, "out_of_town_days", e.target.value)}
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-medium mb-1">Hari Training</label>
                      <input
                        type="text" inputMode="numeric" required
                        className="w-full border p-2 rounded text-sm"
                        value={recap.training_days}
                        onChange={(e) => handleRecapChange(index, "training_days", e.target.value)}
                      />
                    </div>


                    <div>
                      <label className="block text-xs font-medium mb-1">Jumlah Terlambat</label>
                      <input
                        type="text" inputMode="numeric" required
                        className={`w-full border p-2 rounded text-sm ${Number(recap.late_count || 0) > (Number(recap.wfo_days || 0) + Number(recap.wfh_days || 0) + Number(recap.out_of_town_days || 0)) ? 'border-rose-400 bg-rose-50' : ''}`}
                        value={recap.late_count}
                        onChange={(e) => handleRecapChange(index, "late_count", e.target.value)}
                      />
                      <p className="mt-1 text-[11px] text-slate-500">
                        Maks: {Number(recap.wfo_days || 0) + Number(recap.wfh_days || 0) + Number(recap.out_of_town_days || 0)} (= hari hadir WFO+WFH+LK).
                        Dipakai Finance sebagai acuan potongan keterlambatan.
                      </p>
                    </div>
                  </div>

                  {segmentWarnings[index]?.length > 0 && (
                    <div className="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                      <strong className="block mb-1">⚠ Peringatan:</strong>
                      <ul className="list-disc pl-4 space-y-0.5">
                        {segmentWarnings[index].map((w, wi) => <li key={wi}>{w}</li>)}
                      </ul>
                    </div>
                  )}

                  <div className="rounded bg-white px-3 py-2 text-xs text-slate-600">
                    Total hari dibayar segmen ini: <strong>{recapPaidDays(recap)} hari</strong>
                  </div>
                </div>
              ))}

              {selectedEmployeeId && (
                <div className={`rounded px-4 py-3 text-xs ${
                  totalPaidDays > maxDays
                    ? "border border-rose-200 bg-rose-50 text-rose-700"
                    : "border border-emerald-200 bg-emerald-50 text-emerald-700"
                }`}>
                  Total hari dibayar: <strong>{totalPaidDays}</strong> dari maksimal <strong>{maxDays}</strong> hari.
                </div>
              )}

              <div className="flex justify-end pt-4 border-t border-slate-100">
                <Button type="button" variant="outline" className="mr-2" onClick={() => setShowModal(false)}>
                  Batal
                </Button>
                <Button type="submit" disabled={totalPaidDays > maxDays || hasAnyWarning}>Simpan Rekap</Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {notice && (
        <NoticeModal
          type={notice.type}
          title={notice.title}
          message={notice.message}
          onClose={() => setNotice(null)}
        />
      )}

      {detailGroup && (
        <RecapDetailModal
          group={detailGroup}
          onClose={() => setDetailGroup(null)}
        />
      )}

    </div>
  );
}

function SummaryBox({ label, value }) {
  return (
    <div className="rounded border border-slate-200 bg-white px-4 py-3 shadow-sm">
      <div className="text-xs font-medium text-slate-500">{label}</div>
      <div className="mt-1 text-lg font-semibold text-slate-900">{value}</div>
    </div>
  );
}

function RecapDetailModal({ group, onClose }) {
  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" onClick={onClose} />
      <div className="relative w-full max-w-2xl rounded bg-white shadow-xl">
        <div className="border-b border-slate-200 px-5 py-4">
          <h2 className="text-base font-semibold text-slate-900">Detail Rekap Bulanan</h2>
          <p className="mt-1 text-xs text-slate-500">
            {group.employee?.name || "-"} • <PeriodDisplay period={group.period_month} />
          </p>
        </div>

        <div className="space-y-4 p-5">
          <div className="grid gap-3 md:grid-cols-3">
            <SummaryBox label="Total Kehadiran" value={`${cleanNumber(group.total_attendance)} hari`} />
            <SummaryBox label="Perjalanan Dinas" value={`${cleanNumber(group.business_trips)} kali`} />
            <SummaryBox label="Terlambat" value={`${cleanNumber(group.late_count)} kali`} />
          </div>

          <div className="rounded border border-slate-200 overflow-hidden">
            <table className="w-full text-sm">
              <thead className="bg-slate-50 text-xs text-slate-500">
                <tr>
                  <th className="px-3 py-2 text-left">Segmen</th>
                  <th className="px-3 py-2 text-right">WFO</th>
                  <th className="px-3 py-2 text-right">WFH</th>
                  <th className="px-3 py-2 text-right">Luar Kota</th>
                  <th className="px-3 py-2 text-right">Training</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {group.items.map((item, index) => (
                  <tr key={item.id || index}>
                    <td className="px-3 py-2">
                      <div className="font-medium text-slate-900">{item.salary_profile?.position_name || item.employee?.position?.name || `Segmen ${index + 1}`}</div>
                      <div className="text-xs text-slate-500">{item.salary_profile?.effective_from ? `Mulai ${item.salary_profile.effective_from}` : "Rekap utama"}</div>
                    </td>
                    <td className="px-3 py-2 text-right">{cleanNumber(item.wfo_days)}</td>
                    <td className="px-3 py-2 text-right">{cleanNumber(item.wfh_days)}</td>
                    <td className="px-3 py-2 text-right">{cleanNumber(item.out_of_town_days)}</td>
                    <td className="px-3 py-2 text-right">{cleanNumber(item.training_days)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="grid gap-3 text-sm md:grid-cols-2">
            <div className="rounded border border-slate-200 px-3 py-2">
              <div className="text-xs text-slate-500">Jumlah Perjalanan</div>
              <div className="font-semibold">{cleanNumber(group.business_trips)} kali</div>
            </div>
            <div className="rounded border border-slate-200 px-3 py-2">
              <div className="text-xs text-slate-500">Terlambat</div>
              <div className="font-semibold">{cleanNumber(group.late_count)} kali</div>
            </div>
          </div>
        </div>

        <div className="flex justify-end border-t border-slate-200 bg-slate-50 px-5 py-3">
          <Button onClick={onClose}>Tutup</Button>
        </div>
      </div>
    </div>
  );
}

function NoticeModal({ type, title, message, onClose }) {
  const isSuccess = type === "success";

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" onClick={onClose} />
      <div className="relative w-full max-w-sm rounded bg-white p-5 shadow-xl">
        <div className={`mb-3 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${
          isSuccess ? "bg-emerald-50 text-emerald-700" : "bg-rose-50 text-rose-700"
        }`}>
          {isSuccess ? "Berhasil" : "Perlu Dicek"}
        </div>
        <h2 className="text-base font-semibold text-slate-900">{title}</h2>
        <p className="mt-2 text-sm text-slate-600">{message}</p>
        <div className="mt-5 flex justify-end">
          <Button onClick={onClose}>OK</Button>
        </div>
      </div>
    </div>
  );
}
