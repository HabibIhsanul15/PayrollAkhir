import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { Button } from "@/components/ui/button";

export default function GeneralLedgerPage() {
  const [coas, setCoas] = useState([]);
  const [selectedAccountId, setSelectedAccountId] = useState("");
  
  // Date Filters
  const [startDate, setStartDate] = useState(() => {
    const d = new Date();
    return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().split("T")[0];
  });
  const [endDate, setEndDate] = useState(() => {
    const d = new Date();
    return new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().split("T")[0];
  });

  // Ledger state
  const [ledger, setLedger] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const fetchCoas = async () => {
    try {
      const data = await api("/accounting/coa");
      setCoas(data || []);
      if (data && data.length > 0) {
        setSelectedAccountId(data[0].id.toString());
      }
    } catch (err) {
      console.error("Gagal mengambil master COA", err);
    }
  };

  const loadLedger = async () => {
    if (!selectedAccountId) return;
    try {
      setLoading(true);
      setError(null);
      
      const query = `?account_id=${selectedAccountId}&start_date=${startDate}&end_date=${endDate}`;
      const data = await api(`/accounting/general-ledger${query}`);
      setLedger(data);
    } catch (err) {
      setError(err.message || "Gagal mengambil data Buku Besar");
      setLedger(null);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchCoas();
  }, []);

  // Auto load ledger when selected account changes after coas loaded
  useEffect(() => {
    if (selectedAccountId) {
      loadLedger();
    }
  }, [selectedAccountId]);

  const handleFilterSubmit = (e) => {
    e.preventDefault();
    loadLedger();
  };

  return (
    <div className="max-w-6xl mx-auto px-4 py-8">
      {/* Header Panel */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8 bg-gradient-to-r from-slate-900 to-slate-800 p-4 rounded text-white shadow-lg">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">Buku Besar (General Ledger)</h1>
          <p className="text-slate-300 text-sm mt-1">
            Pantau rincian mutasi debit/kredit dan saldo akhir dinamis dari setiap perkiraan akun.
          </p>
        </div>
      </div>

      {/* Filter Bar */}
      <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm mb-6">
        <form onSubmit={handleFilterSubmit} className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
          <div>
            <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
              Pilih Akun Perkiraan
            </label>
            <select
              value={selectedAccountId}
              onChange={(e) => setSelectedAccountId(e.target.value)}
              className="w-full px-3 py-2 border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 outline-none text-sm"
            >
              <option value="">-- Pilih Akun --</option>
              {coas.map(c => (
                <option key={c.id} value={c.id}>[{c.code}] {c.name}</option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
              Dari Tanggal
            </label>
            <input
              type="date"
              required
              value={startDate}
              onChange={(e) => setStartDate(e.target.value)}
              className="w-full px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 outline-none text-sm"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
              Sampai Tanggal
            </label>
            <input
              type="date"
              required
              value={endDate}
              onChange={(e) => setEndDate(e.target.value)}
              className="w-full px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 outline-none text-sm"
            />
          </div>

          <Button
            type="submit"
            className="bg-slate-800 hover:bg-slate-900 text-white font-medium py-2 rounded-xl text-sm"
          >
            Tampilkan Buku Besar
          </Button>
        </form>
      </div>

      {/* Ledger Report Display */}
      {loading ? (
        <div className="flex justify-center items-center py-20">
          <div className="animate-spin rounded-full h-10 w-10 border-b-2 border-sky-500"></div>
        </div>
      ) : error ? (
        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl">
          {error}
        </div>
      ) : ledger ? (
        <div className="space-y-6">
          {/* Account Summary Cards */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="bg-white p-5 border border-slate-200 rounded shadow-sm">
              <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Informasi Akun</span>
              <h2 className="text-lg font-bold text-slate-800 mt-1">{ledger.account.name}</h2>
              <div className="flex items-center gap-2 mt-2">
                <span className="font-mono text-xs font-semibold text-sky-600 bg-sky-50 px-2 py-0.5 rounded">
                  Code: {ledger.account.code}
                </span>
                <span className="text-xs text-slate-500 capitalize">
                  Saldo Normal: {ledger.account.normal_balance}
                </span>
              </div>
            </div>

            <div className="bg-white p-5 border border-slate-200 rounded shadow-sm">
              <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Saldo Awal Periode</span>
              <h2 className="text-xl font-bold text-slate-700 mt-1 font-mono">
                Rp {ledger.opening_balance.toLocaleString("id-ID")}
              </h2>
              <p className="text-xs text-slate-400 mt-1">Saldo akumulatif sebelum tanggal {startDate}</p>
            </div>

            <div className="bg-white p-5 border border-slate-200 rounded shadow-sm">
              <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Saldo Akhir Periode</span>
              <h2 className="text-xl font-bold text-sky-600 mt-1 font-mono">
                Rp {ledger.closing_balance.toLocaleString("id-ID")}
              </h2>
              <p className="text-xs text-slate-400 mt-1">Saldo mutasi akumulatif s.d. tanggal {endDate}</p>
            </div>
          </div>

          {/* Mutation Table */}
          <div className="bg-white rounded border border-slate-200 shadow-sm overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse text-sm">
                <thead>
                  <tr className="bg-slate-50 border-b border-slate-100 text-slate-500 text-xs font-semibold uppercase tracking-wider">
                    <th className="px-6 py-4">Tanggal</th>
                    <th className="px-6 py-4">Nomor Jurnal</th>
                    <th className="px-6 py-4">Keterangan Transaksi</th>
                    <th className="px-6 py-4 text-right">Debit</th>
                    <th className="px-6 py-4 text-right">Kredit</th>
                    <th className="px-6 py-4 text-right">Saldo Akhir</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {/* Row Saldo Awal */}
                  <tr className="bg-slate-50/50 text-xs text-slate-500 font-semibold">
                    <td className="px-6 py-3">
                      {new Date(startDate).toLocaleDateString("id-ID", {
                        day: "numeric",
                        month: "short",
                        year: "numeric"
                      })}
                    </td>
                    <td className="px-6 py-3 font-mono">-</td>
                    <td className="px-6 py-3 uppercase tracking-wider">Saldo Awal (Opening Balance)</td>
                    <td className="px-6 py-3 text-right">-</td>
                    <td className="px-6 py-3 text-right">-</td>
                    <td className="px-6 py-3 text-right font-mono">
                      Rp {ledger.opening_balance.toLocaleString("id-ID")}
                    </td>
                  </tr>

                  {/* Rows mutations */}
                  {ledger.mutations.map((mut) => (
                    <tr key={mut.id} className="hover:bg-slate-50 transition duration-100">
                      <td className="px-6 py-4 text-slate-650">
                        {new Date(mut.transaction_date).toLocaleDateString("id-ID", {
                          day: "numeric",
                          month: "short",
                          year: "numeric"
                        })}
                      </td>
                      <td className="px-6 py-4 font-mono font-semibold text-slate-700">
                        {mut.journal_number}
                      </td>
                      <td className="px-6 py-4 text-slate-800 font-medium">
                        {mut.description}
                      </td>
                      <td className="px-6 py-4 text-right font-mono text-slate-600">
                        {mut.debit > 0 ? `Rp ${mut.debit.toLocaleString("id-ID")}` : "-"}
                      </td>
                      <td className="px-6 py-4 text-right font-mono text-slate-600">
                        {mut.credit > 0 ? `Rp ${mut.credit.toLocaleString("id-ID")}` : "-"}
                      </td>
                      <td className="px-6 py-4 text-right font-mono font-bold text-slate-900">
                        Rp {mut.balance.toLocaleString("id-ID")}
                      </td>
                    </tr>
                  ))}
                  
                  {ledger.mutations.length === 0 && (
                    <tr>
                      <td colSpan="6" className="text-center py-8 text-slate-400 font-medium bg-slate-50/20 text-xs">
                        Tidak ada aktivitas mutasi untuk akun ini pada rentang periode terpilih.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      ) : (
        <div className="bg-slate-100 text-slate-500 rounded-xl p-8 text-center font-medium">
          Silakan pilih akun perkiraan dan rentang tanggal untuk menampilkan Buku Besar.
        </div>
      )}
    </div>
  );
}
