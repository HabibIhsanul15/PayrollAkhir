import { Navigate, useLocation } from "react-router-dom";
import { isAuthed, getUser } from "@/lib/auth";

export default function RoleRoute({ allow = [], children }) {
  const loc = useLocation();

  if (!isAuthed() || !getUser()) {
    return <Navigate to="/login" replace state={{ from: loc.pathname }} />;
  }

  const role = String(getUser()?.role || "").toLowerCase();
  const allowed = allow.map((x) => String(x).toLowerCase());

  if (allowed.length > 0 && !allowed.includes(role)) {
    return <Navigate to="/payrolls" replace />;
  }

  return children;
}
