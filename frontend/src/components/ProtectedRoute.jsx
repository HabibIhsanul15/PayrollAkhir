import { Navigate, useLocation } from "react-router-dom";
import { isAuthed, getUser } from "../lib/auth";

export default function ProtectedRoute({ children }) {
  const loc = useLocation();
  if (!isAuthed() || !getUser()) {
    return <Navigate to="/login" replace state={{ from: loc.pathname }} />;
  }
  return children;
}
