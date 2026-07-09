import { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { getUser } from "@/lib/auth";
import { api } from "@/lib/api";
import { fetchEmployees } from "@/lib/employeesApi";

import { Search, ChevronDown, RefreshCw, Plus, Pencil, Trash2, Eye, FileText } from "lucide-react";

function AvatarInitial({ letters }) {
  return (
    <div className="w-7 h-7 rounded flex items-center justify-center text-[10px] font-semibold bg-blue-50 text-blue-600 flex-shrink-0 border border-blue-100">
      {letters}
    </div>
  );
}

export default function EmployeesPage() {
  const nav = useNavigate();
  const user = getUser();
  const role = String(user?.role || "").toLowerCase();

  const isHCGA = role === "hcga";
  const canView = ["hcga", "fat", "director"].includes(role);

  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");

  const [q, setQ] = useState("");
  const [status, setStatus] = useState("all");

  const [page, setPage] = useState(1);
  const PAGE_SIZE = 10;

  async function load() {
    setErr("");
    setLoading(true);
    try {
      const data = await fetchEmployees();
      setRows(Array.isArray(data) ? data : data?.data ?? []);
    } catch (e) {
      setErr(e?.message || "Gagal load employees");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    if (!canView) return;
    load();
  }, []); // eslint-disable-line

  const onDelete = async (id) => {
    if (!isHCGA) return;

    const ok = confirm("Yakin mau hapus employee ini?");
    if (!ok) return;

    try {
      await api(`/employees/${id}`, { method: "DELETE" });
      setRows((prev) => prev.filter((x) => x.id !== id));
    } catch (e) {
      alert(e?.message || "Gagal menghapus employee.");
    }
  };

  const filtered = useMemo(() => {
    const qq = q.trim().toLowerCase();
    return rows.filter((r) => {
      const code = String(r?.employee_code ?? "").toLowerCase();
      const name = String(r?.name ?? "").toLowerCase();
      const dep = String(r?.department ?? "").toLowerCase();
      const pos = String(r?.position ?? "").toLowerCase();
      const st = String(r?.status ?? "").toLowerCase();

      const matchQ =
        !qq ||
        code.includes(qq) ||
        name.includes(qq) ||
        dep.includes(qq) ||
        pos.includes(qq);

      const matchStatus = status === "all" || st === status;

      return matchQ && matchStatus;
    });
  }, [rows, q, status]);

  const summary = useMemo(() => {
    const total = filtered.length;
    const active = filtered.filter(
      (x) => String(x?.status).toLowerCase() === "active"
    ).length;
    const inactive = total - active;
    return { total, active, inactive };
  }, [filtered]);

  const resetFilters = () => {
    setQ("");
    setStatus("all");
  };

  useEffect(() => {
    setPage(1);
  }, [q, status]);

  const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  const safePage = Math.min(page, totalPages);
  const start = (safePage - 1) * PAGE_SIZE;
  const end = start + PAGE_SIZE;
  const paged = filtered.slice(start, end);

  if (!canView) {
    return (
      <div className="rounded bg-rose-50 px-4 py-3 text-xs text-rose-600 border border-rose-100">
        Forbidden: role kamu tidak boleh mengakses halaman Employees.
      </div>
    );
  }

  const initials = (name) => {
    const s = String(name || "").trim();
    return s ? s.slice(0, 2).toUpperCase() : "N";
  };

  return (
    <div>
      {/* Title + actions */}
      <div className="flex items-start justify-between mb-5">
        <div>
          <h1 className="text-lg font-semibold text-foreground">Employees</h1>
          <p className="text-xs text-muted-foreground mt-0.5">
            Kelola data karyawan, departemen, dan posisi secara terpusat.
          </p>
          <div className="flex items-center gap-3 mt-2 text-[10px] text-muted-foreground">
            <span>Total: <strong className="text-foreground">{summary.total}</strong></span>
            <span className="text-border">·</span>
            <span>Active: <strong className="text-foreground">{summary.active}</strong></span>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <button 
            onClick={load}
            disabled={loading}
            className="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-border rounded text-xs text-muted-foreground hover:text-foreground transition-colors disabled:opacity-50"
          >
            <RefreshCw size={11} className={loading ? "animate-spin" : ""} />
            Refresh
          </button>
          {isHCGA && (
            <button 
              onClick={() => nav("/employees/new")}
              className="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 rounded text-xs font-medium text-white hover:bg-blue-700 transition-colors"
            >
              <Plus size={11} />
              Add Employee
            </button>
          )}
        </div>
      </div>

      {err && (
        <div className="mb-4 rounded bg-rose-50 px-4 py-3 text-xs text-rose-600 border border-rose-100">
          {err}
        </div>
      )}

      {/* Filters */}
      <div className="bg-white border border-border rounded p-4 mb-4" style={{ boxShadow: "0 1px 4px 0 rgba(0,0,0,0.04)" }}>
        <div className="flex flex-col md:flex-row md:items-end gap-3">
          <div className="flex-1">
            <label className="block text-[10px] font-medium text-muted-foreground mb-1.5">Cari Karyawan / Dept / Posisi</label>
            <div>
              <Search size={12} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground" />
              <input
                type="text"
                placeholder="Ketik kata kunci pencarian..."
                value={q}
                onChange={(e) => setQ(e.target.value)}
                className="w-full pl-8 pr-3 py-1.5 text-xs border border-border rounded bg-white focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all placeholder:text-muted-foreground"
              />
            </div>
          </div>
          <div className="w-full md:w-52">
            <label className="block text-[10px] font-medium text-muted-foreground mb-1.5">Status</label>
            <div>
              <select
                value={status}
                onChange={(e) => setStatus(e.target.value)}
                className="w-full appearance-none pl-3 pr-7 py-1.5 text-xs border border-border rounded bg-white focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all"
              >
                <option value="all">Semua Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="terminated">Terminated</option>
              </select>
              <ChevronDown size={11} className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" />
            </div>
          </div>
          <button
            onClick={resetFilters}
            className="px-3 py-1.5 text-xs text-muted-foreground border border-border rounded bg-white hover:bg-muted transition-colors whitespace-nowrap"
          >
            Reset
          </button>
        </div>
      </div>

      {/* Table */}
      <div className="bg-white border border-border rounded overflow-hidden" style={{ boxShadow: "0 1px 4px 0 rgba(0,0,0,0.04)" }}>
        <div className="px-5 py-3 border-b border-border flex items-center justify-between">
          <span className="text-sm font-medium text-foreground">Employee Master</span>
          <span className="text-[10px] text-muted-foreground">
            Menampilkan {paged.length} dari {summary.total} record
          </span>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full border-collapse min-w-[700px]">
            <thead>
              <tr className="border-b border-border bg-slate-50/50">
                <th className="text-left px-5 py-2.5 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Karyawan</th>
                <th className="text-left px-4 py-2.5 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Dept & Posisi</th>
                <th className="text-left px-4 py-2.5 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Join Date</th>
                <th className="text-left px-4 py-2.5 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Status</th>
                <th className="text-right px-5 py-2.5 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody>
              {loading && (
                <tr>
                  <td colSpan={5} className="py-8 text-center text-xs text-muted-foreground">
                    Memuat data...
                  </td>
                </tr>
              )}
              {!loading && paged.length === 0 && (
                <tr>
                  <td colSpan={5} className="py-8 text-center">
                    <div className="flex flex-col items-center justify-center gap-2">
                      <FileText size={14} className="text-slate-300" />
                      <p className="text-xs text-muted-foreground">Tidak ada data karyawan yang cocok.</p>
                    </div>
                  </td>
                </tr>
              )}
              {!loading && paged.map((row) => (
                <tr
                  key={row.id}
                  className="border-b border-border last:border-0 hover:bg-slate-50/70 transition-colors cursor-pointer"
                  onClick={() => nav(`/employees/${row.id}`)}
                >
                  <td className="px-5 py-3">
                    <div className="flex items-center gap-2.5">
                      <AvatarInitial letters={initials(row.name)} />
                      <div>
                        <div className="text-xs font-medium text-foreground">{row.name || "-"}</div>
                        <div className="text-[10px] text-muted-foreground">{row.employee_code || "-"}</div>
                      </div>
                    </div>
                  </td>
                  <td className="px-4 py-3">
                    <div className="text-xs text-foreground">{row.position || "-"}</div>
                    <div className="text-[10px] text-muted-foreground">{row.department || "-"}</div>
                  </td>
                  <td className="px-4 py-3 text-xs text-muted-foreground">
                    {row.join_date || "-"}
                  </td>
                  <td className="px-4 py-3">
                    <span className={`text-[10px] font-semibold uppercase tracking-wider ${
                      String(row.status || "").toLowerCase() === "active" 
                        ? "text-emerald-600" 
                        : "text-slate-500"
                    }`}>
                      {row.status || "-"}
                    </span>
                  </td>
                  
                  <td className="px-5 py-3" onClick={(e) => e.stopPropagation()}>
                    <div className="flex items-center justify-end gap-1.5 flex-wrap">
                      <button
                        onClick={() => nav(`/employees/${row.id}`)}
                        className="flex items-center gap-1 px-2 py-1 text-[10px] font-medium text-blue-600 border border-blue-200 bg-blue-50 rounded hover:bg-blue-100 transition-colors"
                      >
                        <Eye size={10} /> Detail
                      </button>
                      
                      {isHCGA && (
                        <>
                          <button
                            onClick={() => nav(`/employees/${row.id}/edit`)}
                            className="flex items-center gap-1 px-2 py-1 text-[10px] font-medium text-muted-foreground border border-border rounded hover:bg-muted hover:text-foreground transition-colors"
                          >
                            <Pencil size={9} /> Edit
                          </button>
                          <button
                            onClick={() => onDelete(row.id)}
                            className="flex items-center gap-1 px-2 py-1 text-[10px] font-medium text-red-600 border border-red-200 rounded hover:bg-red-50 transition-colors"
                          >
                            <Trash2 size={9} />
                          </button>
                        </>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        
        {/* Pagination Details */}
        <div className="px-5 py-3 border-t border-border flex items-center justify-between">
          <div className="text-[10px] text-muted-foreground">
            Halaman {safePage} dari {totalPages}
          </div>
          <div className="flex gap-1">
            <button
              onClick={() => setPage(p => Math.max(1, p - 1))}
              disabled={safePage <= 1}
              className="px-2 py-1 border border-border rounded text-[10px] disabled:opacity-50 hover:bg-slate-50 transition-colors"
            >
              Prev
            </button>
            <button
              onClick={() => setPage(p => Math.min(totalPages, p + 1))}
              disabled={safePage >= totalPages}
              className="px-2 py-1 border border-border rounded text-[10px] disabled:opacity-50 hover:bg-slate-50 transition-colors"
            >
              Next
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
