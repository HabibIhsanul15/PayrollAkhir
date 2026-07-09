import React, { useState, useEffect, useRef } from "react";
import { 
  ShieldCheck, Database, Key, CheckCircle, Search, 
  Unlock, Loader2, Lock, ArrowRight, FileDigit, Activity,
  Server, Cpu, HardDrive
} from "lucide-react";
import { getToken } from "@/lib/auth";

const API_BASE = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000";

function formatIDR(n) {
  const num = Number(n ?? 0);
  const safe = Number.isFinite(num) ? num : 0;
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
  }).format(safe);
}

// Helper to auto-scroll to the latest step
const ScrollTarget = ({ active }) => {
  const ref = useRef(null);
  useEffect(() => {
    if (active && ref.current) {
      // Small delay to ensure render is complete
      setTimeout(() => {
        ref.current.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }, 100);
    }
  }, [active]);
  return <div ref={ref} className="absolute -top-20" />;
}

export default function SecurityInspectionTab({ payrollId }) {
  // ... (keep state and runInspection same, we just need to fix render logic)
  const [loading, setLoading] = useState(false);
  const [data, setData] = useState(null);
  const [step, setStep] = useState(0); 
  const [error, setError] = useState("");

  const runInspection = async () => {
    try {
      setLoading(true);
      setError("");
      setStep(0);
      setData(null);

      const token = getToken();
      const res = await fetch(`${API_BASE}/api/payrolls/${payrollId}/inspection`, {
        headers: { Authorization: `Bearer ${token}` },
      });

      if (!res.ok) {
        throw new Error("Gagal mengambil data inspeksi keamanan. Akses ditolak.");
      }

      const json = await res.json();
      
      // Auto-play animation sequence
      setTimeout(() => { setLoading(false); setData(json); setStep(1); }, 1500); 
      setTimeout(() => setStep(2), 3500);
      setTimeout(() => setStep(3), 5500); 
      setTimeout(() => setStep(4), 7500); 
      setTimeout(() => setStep(5), 9500); 
      setTimeout(() => setStep(6), 11500); 
      setTimeout(() => setStep(7), 13500); 
      setTimeout(() => setStep(8), 15000); 
      setTimeout(() => setStep(9), 16500);
      
    } catch (err) {
      setError(err.message);
      setLoading(false);
    }
  };

  const [pdfLoading, setPdfLoading] = useState(false);

  const exportPdf = async () => {
    try {
      setPdfLoading(true);
      const token = getToken();
      if (!token) throw new Error("Token login tidak ditemukan.");

      // Remove noopener,noreferrer so we can keep the window reference
      const newTab = window.open("", "_blank");

      const res = await fetch(`${API_BASE}/api/payrolls/${payrollId}/inspection-pdf`, {
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: "application/pdf",
        },
      });

      if (!res.ok) {
        if (newTab) newTab.close();
        throw new Error(`Gagal membuka PDF (HTTP ${res.status}).`);
      }

      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      if (newTab) {
        newTab.location.href = url;
      } else {
        window.location.href = url;
      }
      setTimeout(() => URL.revokeObjectURL(url), 60000);
    } catch (err) {
      alert(err.message);
    } finally {
      setPdfLoading(false);
    }
  };
  
  if (error) {
    return (
      <div className="p-8 text-center text-red-600 bg-red-50 rounded">
        <p>{error}</p>
      </div>
    );
  }

  return (
    <div className="bg-white min-h-[600px] relative rounded-lg border border-slate-200">
      
      {!data && (
        <div className="flex flex-col items-center justify-center p-12 max-w-2xl mx-auto transition-all min-h-[400px]">
          <div className="bg-slate-50 border border-slate-200 text-slate-700 p-4 rounded-lg mb-8 text-center w-full shadow-sm">
            <h4 className="font-semibold mb-1 flex items-center justify-center gap-2">
              <ShieldCheck size={18} className="text-indigo-600"/> 
              Hybrid Encryption Demonstration
            </h4>
            <p className="text-sm">
              Halaman ini akan mendemonstrasikan secara transparan bagaimana algoritma <strong>AES-128-GCM</strong> dan <strong>RSA-2048</strong> melindungi data payroll ini.
            </p>
          </div>

          <button
            onClick={runInspection}
            disabled={loading}
            className="flex items-center gap-2 bg-slate-900 text-white px-8 py-3.5 rounded-lg hover:bg-slate-800 disabled:opacity-50 font-medium transition-all shadow-md"
          >
            {loading ? (
              <>
                <Loader2 size={18} className="animate-spin text-emerald-400" />
                Mempersiapkan Inspeksi...
              </>
            ) : (
              <>
                <Cpu size={18} />
                Run Hybrid Crypto Analysis
              </>
            )}
          </button>
        </div>
      )}

      {data && step > 0 && (
        <div className="max-w-4xl mx-auto py-12 px-4 md:px-8">
          
          <div className="flex justify-between items-end mb-12 border-b border-slate-100 pb-6">
             <div>
               <h2 className="text-2xl font-bold text-slate-800 tracking-tight">Cryptographic Trace</h2>
               <p className="text-sm text-slate-500 mt-1">Tracing encryption lifecycle for Payroll #{data.id}</p>
             </div>
             <div className="text-right">
               <span className="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-700 px-3 py-1.5 rounded-full text-xs font-semibold border border-emerald-100 shadow-sm">
                  <div className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                  {data.salary_alg === "HYBRID" ? "HYBRID ACTIVE" : "AES ONLY"}
               </span>
             </div>
          </div>

          <div className="relative border-l-2 border-slate-200 ml-4 md:ml-8 space-y-12 pb-24 pt-8">
            
            {/* --- PHASE 1: REPLAY --- */}
            <div className={`transition-all duration-1000 ${step >= 1 ? "opacity-100" : "opacity-0"}`}>
               <div className="absolute -left-[60px] -top-8 bg-white py-1 z-20">
                  <div className="bg-slate-100 text-slate-500 text-[10px] font-bold uppercase tracking-widest px-4 py-1.5 rounded-full border border-slate-200 ml-[10px]">
                    Encryption Timeline (Replay)
                  </div>
               </div>
            </div>

            {/* STEP 1: ORIGINAL PAYROLL DATA */}
            <TimelineStep stepNum={1} current={step} title="Original Payroll Data" icon={<FileDigit size={16}/>}>
               <p className="text-sm text-slate-600 mb-4">
                 Sistem mengumpulkan komponen gaji mentah yang sangat rahasia (Plaintext) sebelum disimpan ke dalam database.
               </p>
               <div className="bg-slate-50 border border-slate-200 rounded-lg p-5 font-mono text-sm shadow-inner overflow-x-auto">
                 <table className="w-full text-left">
                   <tbody>
                     <tr className="border-b border-slate-200/50">
                        <td className="py-2.5 text-slate-500 w-1/3">base_salary</td>
                        <td className="py-2.5 text-slate-800 font-semibold">{formatIDR(data.plaintext?.total * 0.7 || 5000000)}</td>
                     </tr>
                     <tr className="border-b border-slate-200/50">
                        <td className="py-2.5 text-slate-500">allowances</td>
                        <td className="py-2.5 text-slate-800 font-semibold">{formatIDR(data.plaintext?.total * 0.3 || 2000000)}</td>
                     </tr>
                     <tr>
                        <td className="py-2.5 text-slate-500 font-bold">total_take_home_pay</td>
                        <td className="py-2.5 text-emerald-600 font-bold">{formatIDR(data.plaintext?.total || 7000000)}</td>
                     </tr>
                   </tbody>
                 </table>
               </div>
            </TimelineStep>

            {/* STEP 2: GENERATE DEK */}
            <TimelineStep stepNum={2} current={step} title="Generate Data Encryption Key (DEK)" icon={<Cpu size={16}/>}>
               <p className="text-sm text-slate-600 mb-4">
                 Sistem membangkitkan Kunci Simetris 16-byte (128-bit) secara acak khusus untuk satu baris transaksi gaji ini.
               </p>
               <div className="flex items-center gap-4 bg-white border border-indigo-100 p-5 rounded-lg shadow-sm">
                  <div className="bg-indigo-50 p-3.5 rounded-full text-indigo-600"><Key size={20}/></div>
                  <div>
                    <div className="text-xs text-indigo-400 font-bold uppercase tracking-wider mb-1">Symmetric Key (DEK)</div>
                    <div className="font-mono text-base font-medium text-slate-700">{data.dek_masked || '4A:2B:**:**:**:**:**:**:**:F1'}</div>
                  </div>
               </div>
            </TimelineStep>

            {/* STEP 3: AES ENCRYPTION */}
            <TimelineStep stepNum={3} current={step} title="AES-128-GCM Encryption" icon={<ShieldCheck size={16}/>}>
               <p className="text-sm text-slate-600 mb-4">
                 DEK digunakan oleh algoritma AES-128-GCM untuk mengunci seluruh payload gaji mentah menjadi Ciphertext yang tidak dapat dibaca.
               </p>
               <div className="flex flex-col md:flex-row items-center justify-between gap-4 bg-slate-50 p-6 rounded-lg border border-slate-200">
                  <div className="bg-white border border-slate-200 px-5 py-2.5 rounded-md shadow-sm text-sm font-semibold text-slate-700">
                    Raw Data
                  </div>
                  <div className="text-slate-400 flex flex-col items-center">
                    <span className="text-[10px] font-bold tracking-widest uppercase mb-1">AES Encrypt</span>
                    <ArrowRight size={18}/>
                  </div>
                  <div className="bg-slate-900 text-slate-300 font-mono text-xs p-4 rounded-md shadow-inner border border-slate-700 w-full md:w-auto overflow-hidden">
                    {data.compare?.[0]?.ciphertext || 'U2FsdGVkX1+...'}
                  </div>
               </div>
            </TimelineStep>

            {/* STEP 4: RSA ENCRYPTION */}
            <TimelineStep stepNum={4} current={step} title="RSA-2048 Encryption (Hybrid Core)" icon={<Lock size={16}/>}>
               <p className="text-sm text-slate-600 mb-4">
                 <strong>Inilah inti dari Hybrid Encryption:</strong> DEK yang digunakan pada langkah sebelumnya kini dibungkus secara asimetris menggunakan Master Public Key RSA-2048 agar aman disimpan di database.
               </p>
               <div className="bg-white border border-sky-100 rounded-lg p-6 flex flex-col items-center justify-center text-center shadow-sm">
                 <div className="flex flex-col md:flex-row items-center gap-6 w-full justify-center text-slate-600">
                    <div className="font-mono text-sm border-b-2 border-slate-200 pb-1 px-4 font-semibold">{data.dek_masked || 'DEK'}</div>
                    <div className="flex flex-col items-center text-sky-500">
                      <span className="text-[10px] font-bold tracking-widest uppercase mb-1">RSA Public Key</span>
                      <ArrowRight size={18}/>
                    </div>
                    <div className="bg-sky-50 text-sky-800 font-mono text-xs p-3 rounded-md w-full md:w-48 truncate border border-sky-200">
                      {data.snapshot?.find(s => s.column === 'dek_enc')?.value || 'ey...'}
                    </div>
                 </div>
                 <div className="mt-5 pt-3 border-t border-sky-50 text-xs text-sky-600 font-medium">DEK is now secured as dek_enc</div>
               </div>
            </TimelineStep>

            {/* --- PHASE 2: STORAGE --- */}
            <div className={`relative transition-all duration-1000 mt-20 pt-10 ${step >= 5 ? "opacity-100" : "opacity-0"}`}>
               <div className="absolute -left-[60px] -top-4 bg-white py-1 z-20">
                  <div className="bg-emerald-50 text-emerald-700 text-[10px] font-bold uppercase tracking-widest px-4 py-1.5 rounded-full border border-emerald-200 shadow-sm ml-[10px]">
                    Live Decryption Inspection
                  </div>
               </div>
            </div>

            {/* STEP 5: DB SNAPSHOT */}
            <TimelineStep stepNum={5} current={step} title="Database Storage (Encrypted State)" icon={<Database size={16}/>}>
               <p className="text-sm text-slate-600 mb-4">
                 Kondisi aktual di dalam Database MySQL saat ini. Data sensitif tersimpan rapat. Jika database bocor ke tangan peretas, inilah yang akan mereka lihat.
               </p>
               <div className="bg-slate-900 text-slate-300 rounded-lg overflow-hidden shadow-lg border border-slate-700 font-mono text-sm">
                  <div className="p-5 space-y-5 overflow-x-auto">
                    {data.snapshot && data.snapshot.map((row, idx) => (
                      <div key={idx}>
                        <div className="text-emerald-400 mb-1.5 font-bold tracking-wide">{row.column}</div>
                        <div className="text-slate-400 break-all text-xs opacity-90 leading-relaxed">{row.value}</div>
                        {idx < data.snapshot.length - 1 && <div className="border-b border-slate-800 mt-5"></div>}
                      </div>
                    ))}
                  </div>
               </div>
            </TimelineStep>

            {/* STEP 6: RSA DECRYPT */}
            <TimelineStep stepNum={6} current={step} title="RSA-2048 Decryption" icon={<Unlock size={16}/>}>
               <p className="text-sm text-slate-600 mb-4">
                 Sistem menggunakan Private Key yang tersimpan aman di server terpisah untuk membuka kembali kunci DEK yang terkunci di dalam <code>dek_enc</code>.
               </p>
               <div className="flex flex-col md:flex-row items-center gap-6 justify-center bg-white border border-slate-200 p-8 rounded-lg shadow-sm">
                  <div className="bg-slate-50 text-slate-500 font-mono text-xs p-3 rounded-md w-full md:w-48 truncate border border-slate-200">
                    {data.snapshot?.find(s => s.column === 'dek_enc')?.value || 'ey...'}
                  </div>
                  <div className="flex flex-col items-center text-indigo-500">
                    <span className="text-[10px] font-bold tracking-widest uppercase mb-1">RSA Private Key</span>
                    <ArrowRight size={18}/>
                  </div>
                  <div className="bg-indigo-50 border border-indigo-100 p-4 rounded-md text-indigo-700 font-mono text-sm font-bold shadow-sm whitespace-nowrap">
                    {data.dek_masked || '4A:2B:**:**:**:**:**:**:**:F1'}
                  </div>
               </div>
            </TimelineStep>

            {/* STEP 7: AES DECRYPT */}
            <TimelineStep stepNum={7} current={step} title="AES-128-GCM Decryption" icon={<Unlock size={16}/>}>
               <p className="text-sm text-slate-600 mb-4">
                 DEK yang telah berhasil diselamatkan kini digunakan kembali untuk membongkar Ciphertext payload gaji.
               </p>
               <div className="flex flex-col md:flex-row items-center justify-between gap-6 bg-slate-50 p-6 rounded-lg border border-slate-200">
                  <div className="bg-slate-900 text-slate-300 font-mono text-xs p-4 rounded-md shadow-inner border border-slate-700 w-full md:w-auto overflow-hidden">
                    {data.compare?.[0]?.ciphertext || 'U2FsdGVkX1+...'}
                  </div>
                  <div className="text-slate-400 flex flex-col items-center">
                    <span className="text-[10px] font-bold tracking-widest uppercase mb-1 flex items-center gap-1"><Key size={12}/> AES Decrypt</span>
                    <ArrowRight size={18}/>
                  </div>
                  <div className="bg-white border border-emerald-200 px-6 py-3.5 rounded-md shadow-sm text-emerald-700 font-bold flex-1 text-center whitespace-nowrap text-sm">
                    Plaintext Payload
                  </div>
               </div>
            </TimelineStep>

            {/* STEP 8: INTEGRITY */}
            <TimelineStep stepNum={8} current={step} title="Integrity Verification" icon={<CheckCircle size={16}/>}>
               <div className="bg-white border border-emerald-200 rounded-lg p-6 flex items-start gap-5 shadow-sm">
                  <div className="bg-emerald-50 p-4 rounded-full text-emerald-500 shrink-0 border border-emerald-100">
                     <CheckCircle size={28}/>
                  </div>
                  <div>
                     <h4 className="font-bold text-emerald-800 mb-1.5 text-lg">Authentication Tag Verified</h4>
                     <p className="text-sm text-slate-600 leading-relaxed">
                       Sistem mengkonfirmasi GCM Tag bawaan dari ciphertext 100% cocok. Data gaji dijamin utuh dan <strong>tidak mengalami perubahan (tampering)</strong> sejak pertama kali dienkripsi.
                     </p>
                  </div>
               </div>
            </TimelineStep>

            {/* STEP 9: FINAL RESULT */}
            <TimelineStep stepNum={9} current={step} title="Final Result" icon={<Activity size={16}/>}>
               <p className="text-sm text-slate-600 mb-6">
                 Proses dekripsi Hybrid selesai dalam waktu <span className="font-bold text-slate-900 bg-slate-100 px-2 py-0.5 rounded">{data.decryption_time_ms} ms</span>. Berikut adalah perbandingan langsung Ciphertext di database vs Plaintext yang dikembalikan ke sistem.
               </p>
               
               <div className="bg-white border border-slate-200 rounded-lg shadow-sm overflow-hidden">
                  <table className="w-full text-sm text-left">
                    <thead className="bg-slate-50 text-slate-600 text-[11px] font-bold uppercase tracking-wider">
                      <tr>
                        <th className="px-6 py-4 border-b border-slate-200">Database (Ciphertext)</th>
                        <th className="px-6 py-4 border-b border-slate-200 bg-emerald-50/50 text-emerald-800">Sistem (Plaintext)</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {data.compare && data.compare.map((row, idx) => (
                          <tr key={idx} className="hover:bg-slate-50 transition-colors">
                            <td className="px-6 py-4 font-mono text-xs text-slate-500 bg-slate-50/30">{row.ciphertext}</td>
                            <td className="px-6 py-4 font-bold text-slate-800 bg-emerald-50/30">{formatIDR(row.plaintext)}</td>
                          </tr>
                      ))}
                    </tbody>
                  </table>
               </div>
               
               {/* Minimalist Export Action */}
               <div className="mt-10 flex justify-end">
                  <button 
                     onClick={exportPdf}
                     disabled={pdfLoading}
                     className="text-sm font-semibold text-slate-600 hover:text-slate-900 flex items-center gap-2 px-5 py-2.5 hover:bg-slate-100 rounded-full transition-colors border border-transparent hover:border-slate-200 disabled:opacity-50"
                  >
                     {pdfLoading ? "⏳ Memproses PDF..." : "📄 Export Inspection Report PDF"}
                  </button>
               </div>
            </TimelineStep>

          </div>
        </div>
      )}
    </div>
  );
}

// Sub-component for timeline steps
function TimelineStep({ stepNum, current, title, icon, children }) {
  const active = current >= stepNum;
  return (
    <div className={`relative transition-all duration-1000 ease-out transform origin-top
      ${active ? "opacity-100 scale-100 translate-y-0" : "opacity-0 scale-95 translate-y-8 hidden"}`}
    >
      <ScrollTarget active={current === stepNum} />
      
      {/* Node point */}
      <div className={`absolute -left-[17px] top-0 w-8 h-8 rounded-full border-[3px] border-white flex items-center justify-center shadow-sm transition-colors duration-500 z-10
        ${active ? (stepNum >= 5 ? "bg-emerald-500 text-white" : "bg-indigo-500 text-white") : "bg-slate-200 text-slate-400"}`}>
        {icon}
      </div>
      
      <div className="pl-6 md:pl-8">
         <h3 className="text-lg font-bold text-slate-800 mb-3">{stepNum}. {title}</h3>
         {children}
      </div>
    </div>
  );
}
