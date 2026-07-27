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
export function monthLabel(ym, withRange = false) {
  if (!ym || !/^\d{4}-\d{2}$/.test(ym)) return ym || "-";
  const [y, m] = ym.split("-");
  const year = Number(y);
  const month = Number(m);
  const date = new Date(year, month - 1, 1);
  let label = date.toLocaleString("id-ID", { month: "long", year: "numeric" });

  if (withRange) {
    const start = new Date(year, month - 2, 28);
    const end = new Date(year, month - 1, 27);
    const startStr = start.toLocaleString("id-ID", { day: "numeric", month: "short", year: "numeric" });
    const endStr = end.toLocaleString("id-ID", { day: "numeric", month: "short", year: "numeric" });
    label += ` (${startStr} - ${endStr})`;
  }

  return label;
}

export function currentPayrollMonth(date = new Date()) {
  const value = new Date(date);
  if (value.getDate() >= 28) value.setMonth(value.getMonth() + 1);
  return `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, "0")}`;
}

function localYmd(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
}

/**
 * Menghitung rentang payroll tetap: tanggal 28 bulan sebelumnya--27 bulan ini.
 * Tidak membaca master periode dari API/database.
 */
export function payrollPeriodForMonth(periodMonth) {
  if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(periodMonth || "")) return null;

  const [year, month] = periodMonth.split("-").map(Number);
  const start = new Date(year, month - 2, 28);
  const end = new Date(year, month - 1, 27);

  return {
    period_month: periodMonth,
    name: `Periode Gaji ${monthLabel(periodMonth)}`,
    start_date: localYmd(start),
    end_date: localYmd(end),
  };
}

/**
 * Pilihan periode berjalan dan periode mendatang untuk form aplikasi.
 */
export function payrollPeriodOptions(months = 12, selectedMonth = "") {
  const firstMonth = currentPayrollMonth();
  const [year, month] = firstMonth.split("-").map(Number);
  const options = Array.from({ length: months }, (_, index) => {
    const date = new Date(year, month - 1 + index, 1);
    const periodMonth = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}`;
    return payrollPeriodForMonth(periodMonth);
  });

  const selected = payrollPeriodForMonth(selectedMonth);
  return selected && !options.some((option) => option.period_month === selected.period_month)
    ? [selected, ...options]
    : options;
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
