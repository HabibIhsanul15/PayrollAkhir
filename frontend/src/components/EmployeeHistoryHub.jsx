import { useState, useEffect } from "react";
import { Link } from "react-router-dom";
import { Card, CardHeader, CardTitle, CardContent } from "./ui/card";
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer
} from "recharts";
import { api } from "../lib/api";
import { formatRupiah } from "../lib/utils";

export default function EmployeeHistoryHub({ employeeId, role }) {
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");
  const [salaryProfiles, setSalaryProfiles] = useState([]);
  const [payrolls, setPayrolls] = useState([]);

  const isHCGA = String(role || "").toLowerCase() === "hcga";

  useEffect(() => {
    let active = true;
    const fetchData = async () => {
      try {
        setLoading(true);
        setErr("");

        const [dataSp, dataPr] = await Promise.all([
          api(`/employees/${employeeId}/salary-profiles`),
          api(`/payrolls?employee_id=${employeeId}`)
        ]);

        if (active) {
          setSalaryProfiles(dataSp || []);
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

  // 1. Data Pertumbuhan Gaji Pokok (dari salaryProfiles)
  // Recharts butuh array dari yang terlama ke terbaru
  const spData = [...salaryProfiles].reverse().map(sp => ({
    name: new Date(sp.effective_from).toLocaleDateString('id-ID', { month: 'short', year: '2-digit' }),
    "Tunjangan Jabatan": parseFloat(sp.position_allowance) || 0,
    "Posisi": sp.position || "-"
  }));

  // 2. Data Gaji Bulanan (Take Home Pay) dari payrolls
  const prData = [...payrolls].reverse().map(pr => ({
    name: new Date(pr.periode).toLocaleDateString('id-ID', { month: 'short', year: '2-digit' }),
    "Take Home Pay": parseFloat(pr.total) || 0,
    "Gaji Pokok": parseFloat(pr.gaji_pokok) || 0
  }));

  const CustomTooltip = ({ active, payload, label }) => {
    if (active && payload && payload.length) {
      return (
        <div className="bg-white border border-slate-200 p-3 rounded shadow-lg text-sm">
          <p className="font-bold text-slate-800 mb-1">{label}</p>
          {payload.map((entry, index) => (
            <p key={`item-${index}`} style={{ color: entry.color }} className="font-medium">
              {entry.name}: {formatRupiah(entry.value)}
            </p>
          ))}
          {/* Tampilkan Posisi jika ada (khusus chart Jabatan) */}
          {payload[0]?.payload?.Posisi && (
            <p className="mt-1 text-xs text-slate-500 bg-slate-50 p-1 rounded">
              Posisi: <span className="font-semibold text-slate-700">{payload[0].payload.Posisi}</span>
            </p>
          )}
        </div>
      );
    }
    return null;
  };

  return (
    <div className="space-y-6 mt-6">
      
      {/* Chart Section */}
      {!isHCGA && (
        <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
          {/* Grafik Take Home Pay Bulanan */}
          <Card className="bg-white border border-border shadow-sm">
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-bold text-slate-800">Tren Slip Gaji (Take-Home Pay)</CardTitle>
            </CardHeader>
            <CardContent>
              {prData.length === 0 ? (
                <p className="text-xs text-slate-500 py-10 text-center">Belum ada riwayat slip gaji bulanan.</p>
              ) : (
                <div className="h-[250px] w-full">
                  <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={prData} margin={{ top: 10, right: 10, left: 20, bottom: 0 }}>
                      <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e2e8f0" />
                      <XAxis dataKey="name" axisLine={false} tickLine={false} tick={{ fontSize: 11, fill: '#64748b' }} dy={10} />
                      <YAxis 
                        axisLine={false} 
                        tickLine={false} 
                        tick={{ fontSize: 11, fill: '#64748b' }} 
                        tickFormatter={(val) => `Rp${(val/1000000).toFixed(1)}Jt`}
                      />
                      <Tooltip content={<CustomTooltip />} />
                      <Legend iconType="circle" wrapperStyle={{ fontSize: '12px', marginTop: '10px' }}/>
                      <Line 
                        type="monotone" 
                        dataKey="Take Home Pay" 
                        stroke="#0ea5e9" 
                        strokeWidth={3}
                        activeDot={{ r: 6 }} 
                      />
                    </LineChart>
                  </ResponsiveContainer>
                </div>
              )}
            </CardContent>
          </Card>

          {/* Grafik Karir (Tunjangan Jabatan) */}
          <Card className="bg-white border border-border shadow-sm">
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-bold text-slate-800">Pertumbuhan Karir & Tunjangan Jabatan</CardTitle>
            </CardHeader>
            <CardContent>
              {spData.length === 0 ? (
                <p className="text-xs text-slate-500 py-10 text-center">Belum ada riwayat jabatan tersimpan.</p>
              ) : (
                <div className="h-[250px] w-full">
                  <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={spData} margin={{ top: 10, right: 10, left: 20, bottom: 0 }}>
                      <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e2e8f0" />
                      <XAxis dataKey="name" axisLine={false} tickLine={false} tick={{ fontSize: 11, fill: '#64748b' }} dy={10} />
                      <YAxis 
                        axisLine={false} 
                        tickLine={false} 
                        tick={{ fontSize: 11, fill: '#64748b' }} 
                        tickFormatter={(val) => `Rp${(val/1000000).toFixed(1)}Jt`}
                      />
                      <Tooltip content={<CustomTooltip />} />
                      <Legend iconType="circle" wrapperStyle={{ fontSize: '12px', marginTop: '10px' }}/>
                      <Line 
                        type="stepAfter" 
                        dataKey="Tunjangan Jabatan" 
                        stroke="#8b5cf6" 
                        strokeWidth={3}
                        activeDot={{ r: 6 }} 
                      />
                    </LineChart>
                  </ResponsiveContainer>
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      )}

      {/* Tables Section */}
      <div className="grid grid-cols-1 gap-6">
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
                        {new Date(pr.periode).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' })}
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

        {/* Table Riwayat Jabatan */}
        <Card className="bg-white border border-border shadow-sm">
          <CardHeader>
            <CardTitle className="text-sm font-bold text-slate-800">Riwayat Jabatan {isHCGA ? "" : "& Komponen"}</CardTitle>
          </CardHeader>
          <CardContent className="p-0 overflow-x-auto">
            <table className="w-full text-sm text-left border-collapse">
              <thead className="bg-slate-50 text-slate-500 text-[11px] uppercase tracking-wider font-bold border-b border-slate-200">
                <tr>
                  <th className="px-4 py-3">Berlaku Sejak</th>
                  <th className="px-4 py-3">Posisi</th>
                  {!isHCGA && <th className="px-4 py-3 text-right">Tunjangan Jabatan</th>}
                  {!isHCGA && <th className="px-4 py-3 text-right">Tunjangan Tetap</th>}
                  {!isHCGA && <th className="px-4 py-3 text-right">Potongan Tetap</th>}
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {salaryProfiles.length === 0 ? (
                  <tr>
                    <td colSpan={isHCGA ? "2" : "5"} className="text-center py-8 text-slate-400 font-medium">Belum ada profil jabatan</td>
                  </tr>
                ) : (
                  salaryProfiles.map((sp) => (
                    <tr key={sp.id} className="text-slate-700 hover:bg-slate-50/50 transition-colors">
                      <td className="px-4 py-3 font-medium text-slate-800">
                        {new Date(sp.effective_from).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })}
                      </td>
                      <td className="px-4 py-3 font-medium text-slate-700">
                        {sp.position || "-"}
                      </td>
                      {!isHCGA && (
                        <>
                          <td className="px-4 py-3 text-right font-mono text-purple-600 font-medium">
                            {sp.position_allowance != null ? formatRupiah(sp.position_allowance) : <span className="text-slate-300 italic">-</span>}
                          </td>
                          <td className="px-4 py-3 text-right font-mono text-teal-600">
                            {sp.allowance_fixed != null && sp.allowance_fixed > 0 ? formatRupiah(sp.allowance_fixed) : "-"}
                          </td>
                          <td className="px-4 py-3 text-right font-mono text-rose-600">
                            {sp.deduction_fixed != null && sp.deduction_fixed > 0 ? formatRupiah(sp.deduction_fixed) : "-"}
                          </td>
                        </>
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
