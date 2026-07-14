import { Navigate, useLocation } from "react-router-dom";
import { isAuthed, getUser } from "@/lib/auth";

function fallbackPath(role) {
  const r = String(role || "").toLowerCase();

  if (r === "hcga") return "/employees";
  if (r === "fat" || r === "director") return "/dashboard";
  if (r === "staff" || r === "employee") return "/payrolls";

  return "/my-profile";
}

export default function RoleRoute({ allow = [], children }) {
  const loc = useLocation();

  if (!isAuthed() || !getUser()) {
    return <Navigate to="/login" replace state={{ from: loc.pathname }} />;
  }

  const role = String(getUser()?.role || "").toLowerCase();
  const allowed = allow.map((x) => String(x).toLowerCase());

  if (allowed.length > 0 && !allowed.includes(role)) {
    return <Navigate to={fallbackPath(role)} replace />;
  }

  return children;
}
