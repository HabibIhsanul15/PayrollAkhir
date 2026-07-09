import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { Button } from "@/components/ui/button";

export default function CoaManagementPage() {
  const [accounts, setAccounts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  
  // Modal states
  const [modalOpen, setModalOpen] = useState(false);
  const [isEdit, setIsEdit] = useState(false);
  const [selectedId, setSelectedId] = useState(null);
  
  // Form states
  const [code, setCode] = useState("");
  const [name, setName] = useState("");
  const [group, setGroup] = useState("Aset");
  const [normalBalance, setNormalBalance] = useState("debit");
  const [description, setDescription] = useState("");
  const [isActive, setIsActive] = useState(true);
  const [submitting, setSubmitting] = useState(false);

  const fetchAccounts = async () => {
    try {
      setLoading(true);
      const data = await api("/accounting/coa");
      setAccounts(data || []);
      setError(null);
    } catch (err) {
      setError(err.message || "Gagal mengambil data COA");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchAccounts();
  }, []);

  const openAddModal = () => {
    setIsEdit(false);
    setSelectedId(null);
    setCode("");
    setName("");
    setGroup("Aset");
    setNormalBalance("debit");
    setDescription("");
    setIsActive(true);
    setModalOpen(true);
  };

  const openEditModal = (acc) => {
    setIsEdit(true);
    setSelectedId(acc.id);
    setCode(acc.code);
    setName(acc.name);
    setGroup(acc.group);
    setNormalBalance(acc.normal_balance);
    setDescription(acc.description || "");
    setIsActive(acc.is_active);
    setModalOpen(true);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      const payload = {
        code,
        name,
        group,
        normal_balance: normalBalance,
        description,
        is_active: isActive,
      };

      if (isEdit) {
        await api(`/accounting/coa/${selectedId}`, {
          method: "PUT",
          body: payload,
        });
      } else {
        await api("/accounting/coa", {
          method: "POST",
          body: payload,
        });
      }
      
      setModalOpen(false);
      fetchAccounts();
    } catch (err) {
      alert(err.message || "Gagal menyimpan akun.");
    } finally {
      setSubmitting(false);
    }
  };

  const handleDelete = async (id) => {
    if (!confirm("Apakah Anda yakin ingin menghapus akun ini?")) return;
    try {
      await api(`/accounting/coa/${id}`, {
        method: "DELETE",
      });
      fetchAccounts();
    } catch (err) {
      alert(err.message || "Gagal menghapus akun.");
    }
  };

  return (
    <div className="max-w-6xl mx-auto px-4 py-8">
      {/* Header Panel */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8 bg-gradient-to-r from-slate-900 to-slate-800 p-4 rounded text-white shadow-lg">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">Chart of Accounts (COA)</h1>
          <p className="text-slate-300 text-sm mt-1">
            Kelola daftar akun akuntansi dasar untuk pencatatan beban dan pembayaran payroll.
          </p>
        </div>
        <Button
          onClick={openAddModal}
          className="bg-sky-500 hover:bg-sky-600 text-white font-medium px-5 py-2.5 rounded-xl transition duration-250 self-start md:self-auto"
        >
          + Tambah Akun Baru
        </Button>
      </div>

      {/* Main Content */}
      {loading ? (
        <div className="flex justify-center items-center py-20">
          <div className="animate-spin rounded-full h-10 w-10 border-b-2 border-sky-500"></div>
        </div>
      ) : error ? (
        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl">
          {error}
        </div>
      ) : (
        <div className="bg-white rounded border border-slate-200 shadow-sm overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="bg-slate-50 border-b border-slate-100 text-slate-500 text-xs font-semibold uppercase tracking-wider">
                  <th className="px-6 py-4">Kode</th>
                  <th className="px-6 py-4">Nama Akun</th>
                  <th className="px-6 py-4">Kelompok</th>
                  <th className="px-6 py-4">Saldo Normal</th>
                  <th className="px-6 py-4">Status</th>
                  <th className="px-6 py-4">Keterangan</th>
                  <th className="px-6 py-4 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 text-sm">
                {accounts.map((acc) => (
                  <tr key={acc.id} className="hover:bg-slate-50 transition duration-150">
                    <td className="px-6 py-4 font-mono font-medium text-foreground">
                      {acc.code}
                    </td>
                    <td className="px-6 py-4 font-medium text-slate-800">
                      {acc.name}
                    </td>
                    <td className="px-6 py-4 text-slate-600">
                      <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                        acc.group === 'Aset' ? 'bg-emerald-50 text-emerald-700' :
                        acc.group === 'Liabilitas' ? 'bg-amber-50 text-amber-700' :
                        acc.group === 'Ekuitas' ? 'bg-indigo-50 text-indigo-700' :
                        acc.group === 'Beban' ? 'bg-red-50 text-red-700' : 'bg-slate-100 text-slate-700'
                      }`}>
                        {acc.group}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-slate-600 capitalize">
                      {acc.normal_balance}
                    </td>
                    <td className="px-6 py-4">
                      {acc.is_active ? (
                        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700">
                          Aktif
                        </span>
                      ) : (
                        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">
                          Nonaktif
                        </span>
                      )}
                    </td>
                    <td className="px-6 py-4 text-slate-500 max-w-xs truncate">
                      {acc.description || "-"}
                    </td>
                    <td className="px-6 py-4 text-right space-x-2">
                      <button
                        onClick={() => openEditModal(acc)}
                        className="text-sky-600 hover:text-sky-800 font-medium text-xs transition"
                      >
                        Edit
                      </button>
                      {/* Sistem inti COA (10100, 20100, etc.) tidak boleh dihapus */}
                      {!['10100', '20100', '20200', '50100', '50200'].includes(acc.code) && (
                        <button
                          onClick={() => handleDelete(acc.id)}
                          className="text-red-500 hover:text-red-700 font-medium text-xs transition"
                        >
                          Hapus
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {accounts.length === 0 && (
            <div className="text-center py-12 text-slate-500">
              Belum ada data akun akuntansi.
            </div>
          )}
        </div>
      )}

      {/* Modal Dialog */}
      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
          <div className="bg-white rounded w-full max-w-lg shadow-xl overflow-hidden animate-in fade-in-50 zoom-in-95 duration-200">
            <div className="bg-slate-900 text-white px-6 py-4 flex items-center justify-between">
              <h2 className="text-lg font-bold">
                {isEdit ? "Edit Akun Akuntansi" : "Tambah Akun Akuntansi Baru"}
              </h2>
              <button
                onClick={() => setModalOpen(false)}
                className="text-slate-400 hover:text-white transition text-lg"
              >
                ✕
              </button>
            </div>
            
            <form onSubmit={handleSubmit} className="p-4 space-y-4">
              <div>
                <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
                  Kode Akun
                </label>
                <input
                  type="text"
                  required
                  placeholder="Contoh: 10100"
                  value={code}
                  onChange={(e) => setCode(e.target.value)}
                  className="w-full px-4 py-2 border.border-slate-200 rounded-xl focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition font-mono outline-none"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
                  Nama Akun
                </label>
                <input
                  type="text"
                  required
                  placeholder="Contoh: Kas & Bank"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  className="w-full px-4 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition outline-none"
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
                    Kelompok Akun
                  </label>
                  <select
                    value={group}
                    onChange={(e) => setGroup(e.target.value)}
                    className="w-full px-4 py-2 border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition outline-none"
                  >
                    <option value="Aset">Aset</option>
                    <option value="Liabilitas">Liabilitas</option>
                    <option value="Ekuitas">Ekuitas</option>
                    <option value="Pendapatan">Pendapatan</option>
                    <option value="Beban">Beban</option>
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
                    Saldo Normal
                  </label>
                  <select
                    value={normalBalance}
                    onChange={(e) => setNormalBalance(e.target.value)}
                    className="w-full px-4 py-2 border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition outline-none"
                  >
                    <option value="debit">Debit</option>
                    <option value="credit">Kredit</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
                  Keterangan
                </label>
                <textarea
                  placeholder="Keterangan mengenai fungsi akun..."
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  className="w-full px-4 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition outline-none h-20 resize-none"
                />
              </div>

              <div className="flex items-center gap-2 pt-2">
                <input
                  type="checkbox"
                  id="isActiveCheck"
                  checked={isActive}
                  onChange={(e) => setIsActive(e.target.checked)}
                  className="h-4 w-4 rounded text-sky-600 focus:ring-sky-500"
                />
                <label htmlFor="isActiveCheck" className="text-sm text-slate-700 font-medium cursor-pointer">
                  Status Aktif
                </label>
              </div>

              <div className="flex justify-end gap-3 pt-4 border-t border-slate-100">
                <Button
                  type="button"
                  onClick={() => setModalOpen(false)}
                  className="bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 px-4 py-2 rounded-xl transition"
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  disabled={submitting}
                  className="bg-sky-500 hover:bg-sky-600 text-white font-medium px-5 py-2 rounded-xl transition disabled:opacity-50"
                >
                  {submitting ? "Menyimpan..." : "Simpan Akun"}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
