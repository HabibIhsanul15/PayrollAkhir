import { useState, useEffect } from "react";
import { X, User, Calendar, Loader2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { fetchEmployeesLite } from "@/lib/payrollsApi";
import { api } from "@/lib/api";

export default function PayrollCreateModal({ open, onClose, onSuccess }) {
  const [loadingEmp, setLoadingEmp] = useState(false);
  const [employees, setEmployees] = useState([]);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Form states
  const [form, setForm] = useState({
    employee_id: "",
    periode: "",
  });

  // Fetch employees
  useEffect(() => {
    if (open) {
      setForm({
        employee_id: "",
        periode: new Date().toISOString().substring(0, 7),
      });
      const load = async () => {
        setLoadingEmp(true);
        try {
          const res = await fetchEmployeesLite("active");
          setEmployees(Array.isArray(res) ? res : []);
        } catch (e) {
          console.error(e);
        } finally {
          setLoadingEmp(false);
        }
      };
      if (employees.length === 0) load();
    }
  }, [open, employees.length]);

  if (!open) return null;

  const handleSubmit = async () => {
    if (!form.employee_id) return alert("Pilih Karyawan dulu!");
    if (!form.periode) return alert("Periode wajib diisi!");
    
    setIsSubmitting(true);
    try {
      // Panggil Engine Otomatis Phase 2
      const payload = {
        employee_id: Number(form.employee_id),
        period_month: form.periode
      };

      await api("/payrolls/auto", {
        method: "POST",
        body: payload
      });
      
      if (onSuccess) onSuccess();
      onClose();
    } catch (e) {
      alert(e?.message || "Gagal menggenerate payroll otomatis.");
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/40 backdrop-blur-[2px] p-4 animate-in fade-in duration-200">
      <div className="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col">
        
        {/* Header */}
        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <div>
            <h2 className="text-lg font-bold text-slate-800">Generate Payroll Baru</h2>
            <p className="text-xs text-slate-500 mt-1">
              Sistem akan menghitung komponen gaji otomatis berdasarkan Grade.
            </p>
          </div>
          <button onClick={onClose} className="p-2 -mr-2 text-slate-400 hover:text-slate-600 rounded-full hover:bg-slate-50 transition-colors">
            <X size={18} />
          </button>
        </div>

        {/* Content */}
        <div className="px-6 py-6 space-y-5 bg-slate-50/50">
          
          {/* Karyawan */}
          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-2">Karyawan</label>
            <div className="relative">
              <User size={16} className="absolute left-3.5 top-3 text-slate-400" />
              <select 
                value={form.employee_id} 
                onChange={(e) => setForm({...form, employee_id: e.target.value})}
                className="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-lg text-sm text-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all outline-none"
                disabled={isSubmitting || loadingEmp}
              >
                <option value="">{loadingEmp ? "Memuat..." : "Pilih karyawan..."}</option>
                {employees.map(emp => (
                  <option key={emp.id} value={emp.id}>{emp.employee_code} - {emp.name}</option>
                ))}
              </select>
            </div>
          </div>

          {/* Periode */}
          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-2">Periode Penggajian</label>
            <div className="relative">
              <Calendar size={16} className="absolute left-3.5 top-3 text-slate-400" />
              <input 
                type="month" 
                value={form.periode}
                onChange={(e) => setForm({...form, periode: e.target.value})}
                className="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-lg text-sm text-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all outline-none"
                disabled={isSubmitting}
              />
            </div>
          </div>

        </div>

        {/* Footer */}
        <div className="px-6 py-4 bg-white border-t border-slate-100 flex items-center justify-end gap-3">
          <Button 
            variant="outline" 
            onClick={onClose} 
            disabled={isSubmitting}
            className="text-slate-600 rounded-lg border-slate-200 hover:bg-slate-50"
          >
            Batal
          </Button>
          <Button 
            onClick={handleSubmit} 
            disabled={isSubmitting}
            className="bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow-sm flex items-center gap-2"
          >
            {isSubmitting && <Loader2 size={16} className="animate-spin" />}
            {isSubmitting ? "Generating..." : "Generate Otomatis"}
          </Button>
        </div>

      </div>
    </div>
  );
}
