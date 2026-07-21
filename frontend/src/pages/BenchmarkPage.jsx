import { useState } from "react";
import { api } from "@/lib/api";

export default function BenchmarkPage() {
  const [count, setCount] = useState(100);
  const [loading, setLoading] = useState(false);
  const [results, setResults] = useState(null);

  const runBenchmark = async () => {
    setLoading(true);
    setResults(null);
    try {
      const res = await api(`/benchmark?count=${count}`);
      setResults(res.results);
    } catch (err) {
      alert("Error running benchmark");
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="p-8 max-w-5xl mx-auto min-h-screen bg-gray-50/50">
      <div className="bg-white rounded-2xl shadow-xl border border-gray-100 p-8">
        <div className="mb-8 border-b border-gray-100 pb-6">
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-indigo-600 to-blue-500 mb-2">
            Crypto Benchmark Tool
          </h1>
          <p className="text-gray-500">
            Alat pengujian performa enkripsi (AES-128, RSA-2048, Hybrid RSA-AES) untuk demo Sidang TA.
          </p>
        </div>

        <div className="flex items-end gap-4 mb-8">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Jumlah Data (Iterations)
            </label>
            <input
              type="number"
              value={count}
              onChange={(e) => setCount(e.target.value)}
              className="w-32 px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
            />
          </div>
          <button
            onClick={runBenchmark}
            disabled={loading}
            className="px-6 py-2 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 text-white font-medium rounded-lg shadow-md shadow-indigo-200 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          >
            {loading ? (
              <>
                <svg className="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Menjalankan...
              </>
            ) : (
              "Jalankan Benchmark"
            )}
          </button>
        </div>

        {results && (
          <div className="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
            <table className="w-full text-left text-sm">
              <thead className="bg-gray-50 text-gray-600 font-medium border-b border-gray-200">
                <tr>
                  <th className="px-6 py-4">Metode Enkripsi</th>
                  <th className="px-6 py-4">Jumlah Data</th>
                  <th className="px-6 py-4">CREATE - Enkripsi (ms)</th>
                  <th className="px-6 py-4">READ_DETAIL - Dekripsi (ms)</th>
                  <th className="px-6 py-4">REPORT - Dekripsi (ms)</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {results.map((r, i) => (
                  <tr key={i} className="hover:bg-gray-50/50 transition-colors">
                    <td className="px-6 py-4 font-semibold text-gray-800">{r.alg}</td>
                    <td className="px-6 py-4 text-gray-600">{count} data</td>
                    <td className="px-6 py-4 font-mono text-indigo-600">{r.create_ms.toFixed(3)}</td>
                    <td className="px-6 py-4 font-mono text-indigo-600">{r.read_detail_ms.toFixed(3)}</td>
                    <td className="px-6 py-4 font-mono text-indigo-600">{r.report_ms.toFixed(3)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {results && (
          <div className="mt-8 p-4 bg-green-50 text-green-800 border border-green-200 rounded-lg text-sm">
            <p className="font-semibold flex items-center gap-2">
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
              Selesai!
            </p>
            <p className="mt-1">
              Hasil di atas sudah otomatis mensimulasikan proses enkripsi dan dekripsi yang terjadi pada sistem penggajian untuk algoritma AES-128, RSA-2048, dan Hybrid RSA-AES.
            </p>
          </div>
        )}
      </div>
    </div>
  );
}
