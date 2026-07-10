import { clsx } from "clsx";
import { twMerge } from "tailwind-merge"

export function cn(...inputs) {
  return twMerge(clsx(inputs));
}

/**
 * Format angka ke format Rupiah Indonesia.
 * @param {number|string} val
 * @param {object} options - { decimals: 0 }
 * @returns {string} contoh: "Rp 1.500.000"
 */
export function formatRupiah(val, { decimals = 0 } = {}) {
  if (val === null || val === undefined || val === "") return "-";
  return "Rp " + Number(val).toLocaleString("id-ID", {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  });
}

/**
 * Ubah format "YYYY-MM" menjadi label bulan Indonesia.
 * @param {string} ym - contoh: "2026-07"
 * @returns {string} contoh: "Juli 2026"
 */
export function monthLabel(ym) {
  if (!ym || !/^\d{4}-\d{2}$/.test(ym)) return ym || "—";
  const [y, m] = ym.split("-");
  const date = new Date(Number(y), Number(m) - 1, 1);
  return date.toLocaleString("id-ID", { month: "long", year: "numeric" });
}

/**
 * Ambil 2 huruf inisial dari nama.
 * @param {string} name
 * @returns {string} contoh: "AB"
 */
export function initials(name) {
  const s = String(name || "").trim();
  return s ? s.slice(0, 2).toUpperCase() : "N";
}
