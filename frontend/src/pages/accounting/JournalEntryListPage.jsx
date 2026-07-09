import { useEffect, useState, useMemo } from "react";
import { api } from "@/lib/api";
import { Button } from "@/components/ui/button";

export default function JournalEntryListPage() {
  const [journals, setJournals] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Filter states: Start Month & End Month (YYYY-MM)
  const [startMonth, setStartMonth] = useState(() => {
    const d = new Date();
    const mm = String(d.getMonth() + 1).padStart(2, "0");
    return `${d.getFullYear()}-${mm}`;
  });
  const [endMonth, setEndMonth] = useState(() => {
    const d = new Date();
    const mm = String(d.getMonth() + 1).padStart(2, "0");
    return `${d.getFullYear()}-${mm}`;
  });

  // Create Manual Modal state
  const [createModalOpen, setCreateModalOpen] = useState(false);
  const [transactionDate, setTransactionDate] = useState("");
  const [description, setDescription] = useState("");
  const [items, setItems] = useState([
    { account_id: "", debit: 0, credit: 0, description: "" },
    { account_id: "", debit: 0, credit: 0, description: "" },
  ]);
  const [coas, setCoas] = useState([]);
  const [submitting, setSubmitting] = useState(false);

  // Calculate start and end dates based on startMonth and endMonth
  const { startDate, endDate } = useMemo(() => {
    const start = `${startMonth}-01`;
    const [yr, mo] = endMonth.split("-");
    const lastDay = new Date(parseInt(yr), parseInt(mo), 0).getDate();
    const end = `${endMonth}-${String(lastDay).padStart(2, "0")}`;
    return { startDate: start, endDate: end };
  }, [startMonth, endMonth]);

  const fetchJournals = async () => {
    try {
      setLoading(true);
      const data = await api(`/accounting/journals?start_date=${startDate}&end_date=${endDate}`);
      setJournals(data.data || []);
      setError(null);
    } catch (err) {
      setError(err.message || "Gagal mengambil data Jurnal Umum");
    } finally {
      setLoading(false);
    }
  };

  const fetchCoas = async () => {
    try {
      const data = await api("/accounting/coa");
      setCoas(data || []);
    } catch (err) {
      console.error("Gagal mengambil master COA", err);
    }
  };

  useEffect(() => {
    fetchJournals();
  }, [startDate, endDate]);

  useEffect(() => {
    fetchCoas();
  }, []);

  const openCreate = () => {
    setTransactionDate(new Date().toISOString().split("T")[0]);
    setDescription("");
    setItems([
      { account_id: "", debit: 0, credit: 0, description: "" },
      { account_id: "", debit: 0, credit: 0, description: "" },
    ]);
    setCreateModalOpen(true);
  };

  const handleItemChange = (index, field, value) => {
    const updated = [...items];
    if (field === "debit" || field === "credit") {
      updated[index][field] = parseFloat(value) || 0;
    } else {
      updated[index][field] = value;
    }
    setItems(updated);
  };

  const addLine = () => {
    setItems([...items, { account_id: "", debit: 0, credit: 0, description: "" }]);
  };

  const removeLine = (index) => {
    if (items.length <= 2) return;
    const updated = items.filter((_, i) => i !== index);
    setItems(updated);
  };

  const totalDebit = items.reduce((sum, item) => sum + item.debit, 0);
  const totalCredit = items.reduce((sum, item) => sum + item.credit, 0);
  const isBalanced = Math.abs(totalDebit - totalCredit) < 0.01 && totalDebit > 0;

  const handleSubmitJournal = async (e) => {
    e.preventDefault();
    if (!isBalanced) {
      alert("Jurnal harus seimbang (Total Debit = Total Kredit) dan nominal lebih besar dari Rp 0.");
      return;
    }
    
    setSubmitting(true);
    try {
      await api("/accounting/journals", {
        method: "POST",
        body: {
          transaction_date: transactionDate,
          description,
          items: items.map(it => ({
            account_id: parseInt(it.account_id),
            debit: it.debit,
            credit: it.credit,
            description: it.description || null
          }))
        }
      });
      setCreateModalOpen(false);
      fetchJournals();
    } catch (err) {
      alert(err.message || "Gagal menyimpan jurnal umum.");
    } finally {
      setSubmitting(false);
    }
  };

  const handleDeleteJournal = async (id) => {
    if (!confirm("Apakah Anda yakin ingin menghapus jurnal penyesuaian manual ini?")) return;
    try {
      await api(`/accounting/journals/${id}`, {
        method: "DELETE",
      });
      fetchJournals();
    } catch (err) {
      alert(err.message || "Gagal menghapus jurnal.");
    }
  };

  const formatRupiah = (val) => {
    if (val === undefined || val === null || val === 0) return "";
    return "Rp " + val.toLocaleString("id-ID", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  const classicRows = useMemo(() => {
    const rows = [];
    let sumDebit = 0;
    let sumCredit = 0;

    const sortedJournals = [...journals].sort((a, b) => {
      return new Date(a.transaction_date) - new Date(b.transaction_date);
    });

    sortedJournals.forEach((entry) => {
      const debits = (entry.items || []).filter(it => (it.debit || 0) > 0);
      const credits = (entry.items || []).filter(it => (it.credit || 0) > 0);

      const combined = [...debits, ...credits];

      combined.forEach((item, index) => {
        const isDebit = (item.debit || 0) > 0;
        const displayDate = index === 0 
          ? new Date(entry.transaction_date).toLocaleDateString("id-ID", { day: "2-digit", month: "short" })
          : "";

        rows.push({
          key: `${entry.id}-${item.id}-${index}`,
          id: entry.id,
          date: displayDate,
          accountName: item.account?.name || "-",
          accountCode: item.account?.code || "-",
          isDebit: isDebit,
          debit: isDebit ? (item.debit || 0) : 0,
          credit: !isDebit ? (item.credit || 0) : 0,
          entryType: entry.journal_type,
          showAction: index === 0 && entry.journal_type === "ADJUSTMENT",
        });

        sumDebit += isDebit ? (item.debit || 0) : 0;
        sumCredit += !isDebit ? (item.credit || 0) : 0;
      });
    });

    return { rows, sumDebit, sumCredit };
  }, [journals]);

  const periodLabel = useMemo(() => {
    const startObj = new Date(startDate);
    const endObj = new Date(endDate);
    
    const startText = startObj.toLocaleDateString("id-ID", { month: "long", year: "numeric" });
    const endText = endObj.toLocaleDateString("id-ID", { month: "long", year: "numeric" });

    if (startMonth === endMonth) {
      return startText;
    }
    return `${startText} s/d ${endText}`;
  }, [startDate, endDate, startMonth, endMonth]);

  return (
    <div className="max-w-4xl mx-auto px-4 py-8">
      
      {/* Filters & Actions Panel */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
        {/* Date Filters: Dari Bulan s/d Sampai Bulan */}
        <div className="flex flex-wrap items-center gap-3 text-sm">
          <div className="flex items-center gap-1.5">
            <span className="text-xs font-bold text-slate-400 uppercase tracking-wider">Dari:</span>
            <input
              type="month"
              value={startMonth}
              onChange={(e) => setStartMonth(e.target.value)}
              className="px-3 py-1.5 border border-slate-300 rounded-xl outline-none text-[10px] font-semibold text-muted-foreground bg-white hover:bg-slate-50 focus:border-sky-500 transition"
            />
          </div>
          <div className="flex items-center gap-1.5">
            <span className="text-xs font-bold text-slate-400 uppercase tracking-wider">Sampai:</span>
            <input
              type="month"
              value={endMonth}
              onChange={(e) => setEndMonth(e.target.value)}
              className="px-3 py-1.5 border border-slate-300 rounded-xl outline-none text-[10px] font-semibold text-muted-foreground bg-white hover:bg-slate-50 focus:border-sky-500 transition"
            />
          </div>
        </div>

        {/* Action Button */}
        <Button
          onClick={openCreate}
          className="bg-sky-500 hover:bg-sky-600 text-white font-semibold px-4 py-2.5 text-xs rounded-xl transition duration-200"
        >
          + Buat Jurnal Penyesuaian
        </Button>
      </div>

      {/* Main Journal Sheet */}
      {loading ? (
        <div className="flex justify-center items-center py-20 bg-white border border-slate-200 rounded-xl shadow-sm">
          <div className="animate-spin rounded-full h-10 w-10 border-b-2 border-sky-500"></div>
        </div>
      ) : error ? (
        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6">
          {error}
        </div>
      ) : (
        <div className="bg-white border border-slate-300 rounded-xl shadow-lg p-8 overflow-x-auto">
          
          {/* Header Block without PT Fashion Jakarta */}
          <div className="text-center mb-8 border-b border-slate-300 pb-6">
            <h2 className="text-2xl font-bold uppercase tracking-wider text-slate-900">Jurnal Umum</h2>
            <p className="text-slate-500 text-sm mt-1 font-semibold">Periode {periodLabel}</p>
          </div>

          <table className="w-full text-sm text-left border-collapse">
            <thead>
              <tr className="bg-slate-50 text-slate-500 text-[11px] uppercase tracking-wider font-bold border-b border-slate-200">
                <th className="px-4 py-3 w-28 text-center">Tanggal</th>
                <th className="px-4 py-3">Nama Akun</th>
                <th className="px-4 py-3 w-28 text-center">Referensi</th>
                <th className="px-4 py-3 w-44 text-right">Debet</th>
                <th className="px-4 py-3 w-44 text-right">Kredit</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {classicRows.rows.map((row) => (
                <tr key={row.key} className="text-slate-700 hover:bg-slate-50/50 relative group transition-colors">
                  {/* Tanggal */}
                  <td className="px-4 py-3 text-center font-medium text-slate-500">
                    <div className="flex items-center justify-center gap-1">
                      {row.date}
                      {row.showAction && (
                        <button
                          type="button"
                          onClick={() => handleDeleteJournal(row.id)}
                          className="opacity-0 group-hover:opacity-100 absolute left-2 text-rose-500 hover:text-rose-700 text-xs font-bold transition ml-1"
                          title="Hapus Jurnal Penyesuaian"
                        >
                          ✕
                        </button>
                      )}
                    </div>
                  </td>
                  {/* Nama Akun (Kredit indented) */}
                  <td className={`px-4 py-3 ${!row.isDebit ? 'pl-10 text-slate-500' : 'text-slate-800 font-medium'}`}>
                    {row.accountName}
                  </td>
                  {/* Referensi (COA Code) */}
                  <td className="px-4 py-3 text-center font-mono text-xs text-slate-400">
                    {row.accountCode}
                  </td>
                  {/* Debet */}
                  <td className="px-4 py-3 text-right font-mono font-medium text-slate-700">
                    {row.debit > 0 ? formatRupiah(row.debit) : ""}
                  </td>
                  {/* Kredit */}
                  <td className="px-4 py-3 text-right font-mono font-medium text-slate-700">
                    {row.credit > 0 ? formatRupiah(row.credit) : ""}
                  </td>
                </tr>
              ))}

              {classicRows.rows.length === 0 && (
                <tr>
                  <td colSpan="5" className="text-center py-16 text-slate-400 font-medium">
                    Tidak ada jurnal tercatat untuk periode ini.
                  </td>
                </tr>
              )}

              {/* Total Row */}
              {classicRows.rows.length > 0 && (
                <tr className="bg-slate-50/80 font-bold text-slate-800 border-t border-slate-200">
                  <td colSpan="3" className="px-4 py-4 text-center uppercase tracking-widest text-xs">
                    Total
                  </td>
                  <td className="px-4 py-4 text-right font-mono text-sm border-b-4 border-double border-slate-300">
                    {formatRupiah(classicRows.sumDebit)}
                  </td>
                  <td className="px-4 py-4 text-right font-mono text-sm border-b-4 border-double border-slate-300">
                    {formatRupiah(classicRows.sumCredit)}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {/* Jurnal Penyesuaian Manual Modal */}
      {createModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 overflow-y-auto">
          <div className="bg-white rounded w-full max-w-2xl shadow-xl overflow-hidden animate-in fade-in-50 zoom-in-95 duration-200 my-8">
            <div className="bg-slate-900 text-white px-6 py-4 flex items-center justify-between">
              <h2 className="text-lg font-bold">Buat Jurnal Penyesuaian Manual</h2>
              <button
                onClick={() => setCreateModalOpen(false)}
                className="text-slate-400 hover:text-white transition text-lg"
              >
                ✕
              </button>
            </div>
            
            <form onSubmit={handleSubmitJournal} className="p-4 space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
                    Tanggal Transaksi
                  </label>
                  <input
                    type="date"
                    required
                    value={transactionDate}
                    onChange={(e) => setTransactionDate(e.target.value)}
                    className="w-full px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 outline-none text-xs"
                  />
                </div>
                <div className="md:col-span-2">
                  <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
                    Keterangan Jurnal
                  </label>
                  <input
                    type="text"
                    required
                    placeholder="Contoh: Koreksi Beban Gaji Staf Magang"
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                    className="w-full px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 outline-none text-xs"
                  />
                </div>
              </div>

              <div>
                <div className="flex items-center justify-between mb-2">
                  <h3 className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Baris Ledger Jurnal</h3>
                  <button
                    type="button"
                    onClick={addLine}
                    className="text-xs text-sky-600 hover:text-sky-800 font-semibold"
                  >
                    + Tambah Baris
                  </button>
                </div>
                
                <div className="space-y-3 max-h-60 overflow-y-auto border border-slate-100 p-2 rounded-xl bg-slate-50">
                  {items.map((item, index) => (
                    <div key={index} className="grid grid-cols-1 md:grid-cols-12 gap-2 bg-white p-3 rounded-xl border border-slate-200 items-end">
                      <div className="md:col-span-4">
                        <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Akun</label>
                        <select
                          required
                          value={item.account_id}
                          onChange={(e) => handleItemChange(index, "account_id", e.target.value)}
                          className="w-full px-2 py-1.5 border border-slate-200 rounded-lg text-xs outline-none bg-white"
                        >
                          <option value="">-- Pilih Akun --</option>
                          {coas.map(c => (
                            <option key={c.id} value={c.id}>[{c.code}] {c.name}</option>
                          ))}
                        </select>
                      </div>
                      <div className="md:col-span-4">
                        <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Deskripsi Baris</label>
                        <input
                          type="text"
                          placeholder="Deskripsi baris..."
                          value={item.description}
                          onChange={(e) => handleItemChange(index, "description", e.target.value)}
                          className="w-full px-2 py-1.5 border border-slate-200 rounded-lg text-xs outline-none"
                        />
                      </div>
                      <div className="md:col-span-2">
                        <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Debet</label>
                        <input
                          type="number"
                          min="0"
                          value={item.debit}
                          onChange={(e) => handleItemChange(index, "debit", e.target.value)}
                          className="w-full px-2 py-1.5 border border-slate-200 rounded-lg text-xs outline-none font-mono"
                        />
                      </div>
                      <div className="md:col-span-2">
                        <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Kredit</label>
                        <div className="flex items-center gap-2">
                          <input
                            type="number"
                            min="0"
                            value={item.credit}
                            onChange={(e) => handleItemChange(index, "credit", e.target.value)}
                            className="w-full px-2 py-1.5 border border-slate-200 rounded-lg text-xs outline-none font-mono"
                          />
                          {items.length > 2 && (
                            <button
                              type="button"
                              onClick={() => removeLine(index)}
                              className="text-red-500 hover:text-red-700 text-sm font-bold"
                            >
                              ✕
                            </button>
                          )}
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              {/* Balance Summary Card */}
              <div className="bg-slate-50 p-4 rounded-xl border border-slate-200 flex justify-between items-center text-sm">
                <div>
                  <span className="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Status Balance</span>
                  {isBalanced ? (
                    <span className="text-green-600 font-bold flex items-center gap-1">
                      ✓ Seimbang (Balanced)
                    </span>
                  ) : (
                    <span className="text-red-500 font-bold flex items-center gap-1">
                      ✕ Tidak Seimbang (Selisih: Rp {Math.abs(totalDebit - totalCredit).toLocaleString("id-ID")})
                    </span>
                  )}
                </div>
                <div className="text-right font-mono font-bold text-slate-800 space-y-1">
                  <div>Debit: Rp {totalDebit.toLocaleString("id-ID")}</div>
                  <div>Kredit: Rp {totalCredit.toLocaleString("id-ID")}</div>
                </div>
              </div>

              <div className="flex justify-end gap-3 pt-4 border-t border-slate-100">
                <Button
                  type="button"
                  onClick={() => setCreateModalOpen(false)}
                  className="bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 px-4 py-2 rounded-xl transition"
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  disabled={submitting || !isBalanced}
                  className="bg-sky-500 hover:bg-sky-600 text-white font-medium px-5 py-2 rounded-xl transition disabled:opacity-50"
                >
                  {submitting ? "Menyimpan..." : "Simpan Jurnal"}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
