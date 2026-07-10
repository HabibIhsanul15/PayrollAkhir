/**
 * Pagination – Komponen paginasi sederhana.
 *
 * Props:
 *   page       – nomor halaman aktif (1-indexed)
 *   totalPages – jumlah total halaman
 *   onPageChange(newPage) – callback saat halaman berubah
 */
export default function Pagination({ page, totalPages, onPageChange }) {
  const safePage = Math.min(page, totalPages);

  return (
    <div className="px-5 py-3 border-t border-border flex items-center justify-between">
      <div className="text-[10px] text-muted-foreground">
        Halaman {safePage} dari {totalPages}
      </div>
      <div className="flex gap-1">
        <button
          onClick={() => onPageChange(Math.max(1, safePage - 1))}
          disabled={safePage <= 1}
          className="px-2 py-1 border border-border rounded text-[10px] disabled:opacity-50 hover:bg-slate-50 transition-colors"
        >
          Prev
        </button>
        <button
          onClick={() => onPageChange(Math.min(totalPages, safePage + 1))}
          disabled={safePage >= totalPages}
          className="px-2 py-1 border border-border rounded text-[10px] disabled:opacity-50 hover:bg-slate-50 transition-colors"
        >
          Next
        </button>
      </div>
    </div>
  );
}
