import React from 'react';
import { monthLabel } from '@/lib/utils';

export default function PeriodDisplay({ period, className = "" }) {
  if (!period) return null;
  
  const label = monthLabel(period);
  const [y, m] = period.split("-");
  const year = Number(y);
  const month = Number(m);
  
  const start = new Date(year, month - 2, 28);
  const end = new Date(year, month - 1, 27);
  
  const startStr = start.toLocaleString("id-ID", { day: "numeric", month: "short", year: "numeric" });
  const endStr = end.toLocaleString("id-ID", { day: "numeric", month: "short", year: "numeric" });
  
  return (
    <span className={`inline-flex flex-wrap items-baseline gap-1.5 ${className}`}>
      <span>{label}</span>
      <span className="text-[0.85em] text-slate-500 font-normal">({startStr} - {endStr})</span>
    </span>
  );
}
