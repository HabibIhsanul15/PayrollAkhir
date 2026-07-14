export function EmployeePageHeader({ title, description, actions }) {
  return (
    <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div>
        <h1 className="text-lg font-semibold text-foreground">{title}</h1>
        {description ? (
          <p className="mt-1 text-sm text-slate-600">{description}</p>
        ) : null}
      </div>
      {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
    </div>
  );
}

export function EmployeeSectionCard({
  title,
  description,
  actions,
  children,
  className = "",
  bodyClassName = "",
}) {
  return (
    <section className={`overflow-hidden rounded-xl border border-border bg-white shadow-sm ${className}`.trim()}>
      {(title || description || actions) && (
        <div className="flex flex-col gap-3 border-b border-slate-200/70 px-5 py-4 md:flex-row md:items-start md:justify-between">
          <div>
            {title ? <h2 className="text-sm font-semibold text-slate-900">{title}</h2> : null}
            {description ? <p className="mt-1 text-xs text-slate-500">{description}</p> : null}
          </div>
          {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
        </div>
      )}
      <div className={`p-5 ${bodyClassName}`.trim()}>{children}</div>
    </section>
  );
}

export function EmployeeNotice({ children, tone = "neutral", className = "" }) {
  const toneClass =
    tone === "info"
      ? "border-sky-200 bg-sky-50 text-sky-800"
      : tone === "warning"
        ? "border-amber-200 bg-amber-50 text-amber-800"
        : "border-slate-200 bg-slate-50 text-slate-700";

  return (
    <div className={`rounded-xl border px-4 py-3 text-xs ${toneClass} ${className}`.trim()}>
      {children}
    </div>
  );
}

export function EmployeeDisplayField({
  label,
  value,
  full = false,
  mono = false,
  helper,
}) {
  return (
    <div className={full ? "space-y-1.5 md:col-span-2" : "space-y-1.5"}>
      <div className="text-xs font-medium text-slate-600">{label}</div>
      <div
        className={[
          "rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900",
          mono ? "font-mono" : "font-medium",
        ].join(" ")}
      >
        {value ?? "-"}
      </div>
      {helper ? <div className="text-[11px] text-slate-500">{helper}</div> : null}
    </div>
  );
}
