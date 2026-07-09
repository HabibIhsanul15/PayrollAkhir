import { useState, useEffect } from "react";
import { api } from "@/lib/api";
import { Table } from "@/components/ui/table";
import { Button } from "@/components/ui/button";

export default function MonthlyRecapPage() {
  const [recaps, setRecaps] = useState([]);
  const [employees, setEmployees] = useState([]);
  const [period, setPeriod] = useState(new Date().toISOString().substring(0, 7));
  const [showModal, setShowModal] = useState(false);
  const [formRecaps, setFormRecaps] = useState([
    {
      salary_profile_id: "",
      wfo_days: 0,
      wfh_days: 0,
      out_of_town_days: 0,
      business_trips: 0,
      training_days: 0,
      overtime_hours: 0,
    },
  ]);
  const [employeeProfiles, setEmployeeProfiles] = useState([]);
  const [selectedEmployeeId, setSelectedEmployeeId] = useState("");

  const fetchRecaps = async () => {
    try {
      const res = await api(`/monthly-recaps?period_month=${period}`);
      setRecaps(res);
    } catch (err) {
      alert("Gagal mengambil data rekap bulanan.");
    }
  };

  const fetchEmployees = async () => {
    try {
      const res = await api("/employees");
      setEmployees(Array.isArray(res) ? res : (res.data || []));
    } catch (err) {
      console.error(err);
    }
  };

  useEffect(() => {
    fetchRecaps();
    fetchEmployees();
  }, [period]);

  const handleEmployeeChange = async (empId) => {
    setSelectedEmployeeId(empId);
    if (!empId) {
      setEmployeeProfiles([]);
      setFormRecaps([{ salary_profile_id: "", wfo_days: 0, wfh_days: 0, out_of_town_days: 0, business_trips: 0, training_days: 0, overtime_hours: 0 }]);
      return;
    }
    
    try {
      const res = await api(`/employees/${empId}/salary-profiles`);
      const profiles = Array.isArray(res) ? res : [];
      setEmployeeProfiles(profiles);
      
      // Auto-detect profiles relevant to the selected period
      const relevantProfiles = [];
      for (const prof of profiles) {
        // Assume period is YYYY-MM
        const endOfMonth = `${period}-31`; // string comparison works for ISO dates
        const startOfMonth = `${period}-01`;
        
        if (prof.effective_from <= endOfMonth) {
          relevantProfiles.push(prof);
          if (prof.effective_from < startOfMonth) {
            break; // found the base profile active at the start of the month
          }
        }
      }
      
      relevantProfiles.reverse(); // oldest to newest
      
      if (relevantProfiles.length > 0) {
        setFormRecaps(relevantProfiles.map(prof => ({
          salary_profile_id: prof.id,
          wfo_days: 0,
          wfh_days: 0,
          out_of_town_days: 0,
          business_trips: 0,
          training_days: 0,
          overtime_hours: 0
        })));
      } else {
        setFormRecaps([{ salary_profile_id: "", wfo_days: 0, wfh_days: 0, out_of_town_days: 0, business_trips: 0, training_days: 0, overtime_hours: 0 }]);
      }
    } catch (err) {
      console.error(err);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    // Validasi maksimal hari
    const getDaysInMonth = (yearMonth) => {
      const [year, month] = yearMonth.split('-');
      return new Date(year, month, 0).getDate();
    };
    
    const maxDays = getDaysInMonth(period);
    let totalMandays = 0;
    
    formRecaps.forEach(r => {
      totalMandays += parseFloat(r.wfo_days || 0) + parseFloat(r.wfh_days || 0) + parseFloat(r.out_of_town_days || 0) + parseFloat(r.training_days || 0);
    });
    
    if (totalMandays > maxDays) {
      alert(`Total hari kehadiran (${totalMandays}) melebihi jumlah maksimal hari di bulan ini (${maxDays} hari).`);
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
      alert("Rekap bulanan berhasil disimpan.");
      setShowModal(false);
      fetchRecaps();
    } catch (err) {
      alert(err.message || "Gagal menyimpan rekap.");
    }
  };

  const handleFinalize = async (id) => {
    if (!confirm("Yakin ingin memfinalisasi rekap ini? Data tidak bisa diubah setelah difinalisasi.")) return;
    try {
      await api(`/monthly-recaps/${id}/finalize`, { method: "POST" });
      alert("Rekap difinalisasi.");
      fetchRecaps();
    } catch (err) {
      alert("Gagal finalisasi rekap.");
    }
  };

  const handleDelete = async (id) => {
    if (!confirm("Hapus rekap ini?")) return;
    try {
      await api(`/monthly-recaps/${id}`, { method: "DELETE" });
      alert("Rekap dihapus.");
      fetchRecaps();
    } catch (err) {
      alert("Gagal menghapus rekap.");
    }
  };
  
  const handleRecapChange = (index, field, value) => {
    const newRecaps = [...formRecaps];
    newRecaps[index][field] = value;
    setFormRecaps(newRecaps);
  };
  
  const addRecapRow = () => {
    setFormRecaps([...formRecaps, { salary_profile_id: "", wfo_days: 0, wfh_days: 0, out_of_town_days: 0, business_trips: 0, training_days: 0, overtime_hours: 0 }]);
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
          <Button onClick={() => {
            setSelectedEmployeeId("");
            setFormRecaps([{ salary_profile_id: "", wfo_days: 0, wfh_days: 0, out_of_town_days: 0, business_trips: 0, training_days: 0, overtime_hours: 0 }]);
            setShowModal(true);
          }}>Input Rekap</Button>
        </div>
      </div>

      <div className="bg-white rounded-lg shadow overflow-x-auto">
        <Table>
          <thead>
            <tr>
              <th className="px-4 py-2 text-left">Nama</th>
              <th className="px-4 py-2 text-left">Bulan</th>
              <th className="px-4 py-2 text-right">WFO</th>
              <th className="px-4 py-2 text-right">WFH</th>
              <th className="px-4 py-2 text-right">Luar Kota</th>
              <th className="px-4 py-2 text-right">Training</th>
              <th className="px-4 py-2 text-right">Total Mandays</th>
              <th className="px-4 py-2 text-center">Status</th>
              <th className="px-4 py-2 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            {recaps.map((r) => (
              <tr key={r.id} className="border-t">
                <td className="px-4 py-2">{r.employee?.name}</td>
                <td className="px-4 py-2">{r.period_month}</td>
                <td className="px-4 py-2 text-right">{r.wfo_days}</td>
                <td className="px-4 py-2 text-right">{r.wfh_days}</td>
                <td className="px-4 py-2 text-right">{r.out_of_town_days}</td>
                <td className="px-4 py-2 text-right">{r.training_days}</td>
                <td className="px-4 py-2 text-right font-semibold">{r.total_mandays}</td>
                <td className="px-4 py-2 text-center">
                  {r.is_finalized ? (
                    <span className="px-2 py-1 bg-green-100 text-green-700 rounded text-sm">Final</span>
                  ) : (
                    <span className="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-sm">Draft</span>
                  )}
                </td>
                <td className="px-4 py-2 text-center">
                  {!r.is_finalized && (
                    <>
                      <Button size="sm" variant="outline" className="mr-2" onClick={() => handleFinalize(r.id)}>
                        Finalize
                      </Button>
                      <Button size="sm" variant="destructive" onClick={() => handleDelete(r.id)}>
                        Hapus
                      </Button>
                    </>
                  )}
                </td>
              </tr>
            ))}
            {recaps.length === 0 && (
              <tr>
                <td colSpan={9} className="px-4 py-4 text-center text-gray-500">
                  Belum ada data rekap bulan {period}.
                </td>
              </tr>
            )}
          </tbody>
        </Table>
      </div>

      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" onClick={() => setShowModal(false)} />
          <div className="bg-white p-6 rounded-lg shadow-xl z-10 w-[600px] max-w-full max-h-[90vh] overflow-y-auto relative">
            <h2 className="text-lg font-semibold mb-4">Input Rekap Bulanan</h2>
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

              {selectedEmployeeId && formRecaps.map((recap, index) => (
                <div key={index} className="bg-slate-50 p-4 rounded-lg border border-slate-200 space-y-4 relative">
                  {formRecaps.length > 1 && (
                    <button type="button" onClick={() => removeRecapRow(index)} className="absolute top-4 right-4 text-rose-500 text-sm font-semibold hover:text-rose-700">
                      Hapus
                    </button>
                  )}
                  {formRecaps.length > 1 && (
                    <div className="mb-2">
                      <div className="inline-flex items-center px-3 py-1 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold border border-indigo-200">
                        Segmen Mutasi: {(() => {
                          const prof = employeeProfiles.find(p => String(p.id) === String(recap.salary_profile_id));
                          return prof ? `Mulai ${prof.effective_from} (Jabatan: ${prof.grade_name})` : "Profil Tidak Ditemukan";
                        })()}
                      </div>
                    </div>
                  )}
                  
                  <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <div>
                      <label className="block text-xs font-medium mb-1">WFO Days</label>
                      <input
                        type="number" step="0.5" min="0" required
                        className="w-full border p-2 rounded text-sm"
                        value={recap.wfo_days}
                        onChange={(e) => handleRecapChange(index, "wfo_days", e.target.value)}
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-medium mb-1">WFH Days</label>
                      <input
                        type="number" step="0.5" min="0" required
                        className="w-full border p-2 rounded text-sm"
                        value={recap.wfh_days}
                        onChange={(e) => handleRecapChange(index, "wfh_days", e.target.value)}
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-medium mb-1">Luar Kota Days</label>
                      <input
                        type="number" step="0.5" min="0" required
                        className="w-full border p-2 rounded text-sm"
                        value={recap.out_of_town_days}
                        onChange={(e) => handleRecapChange(index, "out_of_town_days", e.target.value)}
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-medium mb-1">Training Days</label>
                      <input
                        type="number" step="0.5" min="0" required
                        className="w-full border p-2 rounded text-sm"
                        value={recap.training_days}
                        onChange={(e) => handleRecapChange(index, "training_days", e.target.value)}
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-medium mb-1">Jml Perjalanan Dinas</label>
                      <input
                        type="number" min="0" required
                        className="w-full border p-2 rounded text-sm"
                        value={recap.business_trips}
                        onChange={(e) => handleRecapChange(index, "business_trips", e.target.value)}
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-medium mb-1">Overtime (Jam)</label>
                      <input
                        type="number" step="0.5" min="0" required
                        className="w-full border p-2 rounded text-sm"
                        value={recap.overtime_hours}
                        onChange={(e) => handleRecapChange(index, "overtime_hours", e.target.value)}
                      />
                    </div>
                  </div>
                </div>
              ))}


              <div className="flex justify-end pt-4 border-t border-slate-100">
                <Button type="button" variant="outline" className="mr-2" onClick={() => setShowModal(false)}>
                  Batal
                </Button>
                <Button type="submit">Simpan Rekap</Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
