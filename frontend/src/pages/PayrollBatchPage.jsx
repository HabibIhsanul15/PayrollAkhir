import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { api } from "@/lib/api";
import { Button } from "@/components/ui/button";
import AlertMessage from "@/components/AlertMessage";
import { Calendar, Play, CheckCircle2, XCircle, ChevronRight } from "lucide-react";

export default function PayrollBatchPage() {
  const navigate = useNavigate();
  const [periodMonth, setPeriodMonth] = useState("");
  const [isLoading, setIsLoading] = useState(false);
  const [results, setResults] = useState(null);
  const [error, setError] = useState("");

  const handleGenerate = async (e) => {
    e.preventDefault();
    if (!periodMonth) {
      setError("Period month harus diisi");
      return;
    }

    setIsLoading(true);
    setError("");
    setResults(null);

    try {
      const data = await api("/payrolls/batch-generate", {
        method: "POST",
        body: { period_month: periodMonth },
      });
      setResults(data);
    } catch (err) {
      const msg = err.data?.message || err.message || "Terjadi kesalahan";
      setError(msg);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div>
      <div className="flex items-start justify-between mb-5">
        <div>
          <h1 className="text-lg font-semibold text-foreground">Batch Generate Auto Payroll</h1>
          <p className="text-xs text-muted-foreground mt-0.5">
            Hitung massal payroll karyawan mode auto untuk periode tertentu.
          </p>
        </div>
      </div>

      <div className="bg-white border border-border rounded p-4 mb-4" style={{ boxShadow: "0 1px 4px 0 rgba(0,0,0,0.04)" }}>
        <form onSubmit={handleGenerate} className="flex flex-col md:flex-row md:items-end gap-3 max-w-2xl">
          <div className="flex-1">
            <label className="block text-[10px] font-medium text-muted-foreground mb-1.5">
              Period Month (YYYY-MM) <span className="text-red-500">*</span>
            </label>
            <div>
              <Calendar size={12} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground" />
              <input
                type="month"
                value={periodMonth}
                onChange={(e) => setPeriodMonth(e.target.value)}
                className="w-full pl-8 pr-3 py-1.5 text-xs border border-border rounded bg-white focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all placeholder:text-muted-foreground"
                required
              />
            </div>
          </div>
          <button 
            type="submit" 
            disabled={isLoading}
            className="flex items-center justify-center gap-1.5 px-4 py-1.5 bg-blue-600 rounded text-xs font-medium text-white hover:bg-blue-700 transition-colors disabled:opacity-50 whitespace-nowrap"
          >
            <Play size={11} fill="currentColor" />
            {isLoading ? "Generating..." : "Generate Batch"}
          </button>
        </form>
        <AlertMessage type="error" message={error} className="mt-4 max-w-2xl" />
      </div>

      {results && (
        <div className="space-y-4 max-w-2xl">
          <div className="grid grid-cols-2 gap-4">
            <div className="bg-white border border-border rounded p-4 flex flex-col items-center justify-center text-center relative overflow-hidden" style={{ boxShadow: "0 1px 4px 0 rgba(0,0,0,0.04)" }}>
              <div className="absolute top-0 left-0 w-full h-0.5 bg-emerald-500"></div>
              <div className="text-[10px] font-medium text-muted-foreground uppercase tracking-wider mb-1">Total Success</div>
              <div className="text-2xl font-semibold text-emerald-600">{results.total_success}</div>
            </div>
            <div className="bg-white border border-border rounded p-4 flex flex-col items-center justify-center text-center relative overflow-hidden" style={{ boxShadow: "0 1px 4px 0 rgba(0,0,0,0.04)" }}>
              <div className="absolute top-0 left-0 w-full h-0.5 bg-red-500"></div>
              <div className="text-[10px] font-medium text-muted-foreground uppercase tracking-wider mb-1">Total Failed</div>
              <div className="text-2xl font-semibold text-red-600">{results.total_failed}</div>
            </div>
          </div>

          <div className="bg-white border border-border rounded overflow-hidden" style={{ boxShadow: "0 1px 4px 0 rgba(0,0,0,0.04)" }}>
            <div className="px-5 py-3 border-b border-border flex items-center justify-between">
              <span className="text-sm font-medium text-foreground">Batch Details</span>
              <span className="text-[10px] text-muted-foreground">
                Total {results.details?.length || 0} record processed
              </span>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full border-collapse">
                <thead>
                  <tr className="border-b border-border bg-slate-50/50">
                    <th className="text-left px-5 py-2.5 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Employee ID</th>
                    <th className="text-left px-4 py-2.5 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Status</th>
                    <th className="text-left px-4 py-2.5 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Message</th>
                  </tr>
                </thead>
                <tbody>
                  {results.details?.length > 0 ? (
                    results.details.map((detail, idx) => (
                      <tr key={idx} className="border-b border-border last:border-0 hover:bg-slate-50/70 transition-colors">
                        <td className="px-5 py-3 text-[11px] font-medium text-foreground">
                          EMP-{detail.employee_id}
                        </td>
                        <td className="px-4 py-3">
                          {detail.status === "success" ? (
                            <span className="inline-flex items-center gap-1 text-[10px] font-medium text-emerald-600 uppercase tracking-wider">
                              <CheckCircle2 size={10} /> Success
                            </span>
                          ) : (
                            <span className="inline-flex items-center gap-1 text-[10px] font-medium text-red-600 uppercase tracking-wider">
                              <XCircle size={10} /> Failed
                            </span>
                          )}
                        </td>
                        <td className="px-4 py-3 text-xs text-muted-foreground">
                          {detail.status === "success" ? (
                            <button
                              onClick={() => navigate(`/payrolls/${detail.payroll_id}`)}
                              className="flex items-center gap-1 text-blue-600 hover:text-blue-700 font-medium transition-colors"
                            >
                              View Payroll <ChevronRight size={12} />
                            </button>
                          ) : (
                            <span className="text-red-500">{detail.error || detail.message || "-"}</span>
                          )}
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={3} className="px-5 py-8 text-center text-xs text-muted-foreground">
                        Tidak ada rincian batch.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
