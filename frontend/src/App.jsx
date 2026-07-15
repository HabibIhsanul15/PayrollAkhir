// App.jsx
import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import { useEffect, useState } from "react";
import { getUser, isAuthed } from "./lib/auth";

import ProtectedRoute from "./components/ProtectedRoute";
import AppLayout from "./components/AppLayout";
import RoleRoute from "@/components/RoleRoute";
import { ConfirmProvider } from "@/components/ConfirmProvider";

import Login from "./pages/Login";
import Register from "./pages/Register";
import DashboardPage from "./pages/DashboardPage";

import PayrollList from "./pages/PayrollList";

import PayrollDetailPage from "./pages/PayrollDetailPage";

import EmployeesPage from "./pages/EmployeesPage";
import EmployeeCreatePage from "./pages/EmployeeCreatePage";
import EmployeeEditPage from "./pages/EmployeeEditPage";

import EmployeeDetailPage from "./pages/EmployeeDetailPage";

import MyProfilePage from "./pages/MyProfilePage";
import AccountCreatePage from "./pages/AccountCreatePage";

// ✅ NEW: laporan payroll
import PayrollReportPage from "./pages/PayrollReportPage";

// Master data pages (Phase 1)
import PositionManagementPage from "./pages/PositionManagementPage";
import AllowanceTypePage from "./pages/AllowanceTypePage";
import DeductionTypePage from "./pages/DeductionTypePage";
import PositionRatePage from "./pages/PositionRatePage";

// Phase 3 UI
import MonthlyRecapPage from "./pages/MonthlyRecapPage";
import MutationApprovalPage from "./pages/MutationApprovalPage";

function getHomePath(user) {
  const role = String(user?.role || "").toLowerCase();

  if (role === "staff" || role === "employee") return "/payrolls";
  if (role === "fat" || role === "director" || role === "hcga") return "/dashboard";

  return "/my-profile";
}

export default function App() {
  const [authed, setAuthed] = useState(() => isAuthed());
  const [me, setMe] = useState(() => getUser());

  useEffect(() => {
    const sync = () => {
      setAuthed(isAuthed());
      setMe(getUser());
    };
    window.addEventListener("auth:changed", sync);
    window.addEventListener("storage", sync);
    return () => {
      window.removeEventListener("auth:changed", sync);
      window.removeEventListener("storage", sync);
    };
  }, []);

  const home = getHomePath(me);

  return (
    <ConfirmProvider>
      <BrowserRouter>
        <Routes>
        {/* ROOT */}
        <Route path="/" element={<Navigate to={authed ? home : "/login"} replace />} />

        {/* PUBLIC */}
        <Route path="/login" element={authed ? <Navigate to={home} replace /> : <Login />} />
        <Route path="/register" element={authed ? <Navigate to={home} replace /> : <Register />} />

        {/* PROTECTED + LAYOUT */}
        <Route
          element={
            <ProtectedRoute>
              <AppLayout />
            </ProtectedRoute>
          }
        >
          {/* DASHBOARD */}
          <Route
            path="/dashboard"
            element={
              <RoleRoute allow={["staff", "fat", "director", "hcga"]}>
                <DashboardPage />
              </RoleRoute>
            }
          />

          {/* PAYROLL: HCGA TIDAK BOLEH */}
          <Route
            path="/payrolls"
            element={
              <RoleRoute allow={["staff", "fat", "director"]}>
                <PayrollList />
              </RoleRoute>
            }
          />
          <Route
            path="/payrolls/:id"
            element={
              <RoleRoute allow={["staff", "fat", "director"]}>
                <PayrollDetailPage />
              </RoleRoute>
            }
          />




          {/* ✅ REPORT PAYROLL: FAT + DIRECTOR */}
          <Route
            path="/reports/payroll"
            element={
              <RoleRoute allow={["fat", "director"]}>
                <PayrollReportPage />
              </RoleRoute>
            }
          />

          {/* EMPLOYEES */}
          <Route
            path="/employees"
            element={
              <RoleRoute allow={["hcga", "director"]}>
                <EmployeesPage />
              </RoleRoute>
            }
          />
          <Route
            path="/employees/:id"
            element={
              <RoleRoute allow={["hcga", "director"]}>
                <EmployeeDetailPage />
              </RoleRoute>
            }
          />

          {/* EMPLOYEE CREATE/EDIT/SET SALARY: HCGA saja */}
          <Route
            path="/employees/new"
            element={
              <RoleRoute allow={["hcga"]}>
                <EmployeeCreatePage />
              </RoleRoute>
            }
          />
          <Route
            path="/employees/:id/edit"
            element={
              <RoleRoute allow={["hcga"]}>
                <EmployeeEditPage />
              </RoleRoute>
            }
          />


          {/* MASTER DATA (Phase 1) */}
          <Route
            path="/master/positions"
            element={
              <RoleRoute allow={["hcga", "fat"]}>
                <PositionManagementPage />
              </RoleRoute>
            }
          />
          <Route
            path="/master/allowance-types"
            element={
              <RoleRoute allow={["fat"]}>
                <AllowanceTypePage />
              </RoleRoute>
            }
          />
          <Route
            path="/master/deduction-types"
            element={
              <RoleRoute allow={["fat"]}>
                <DeductionTypePage />
              </RoleRoute>
            }
          />
          <Route
            path="/master/position-rates"
            element={
              <RoleRoute allow={["fat"]}>
                <PositionRatePage />
              </RoleRoute>
            }
          />

          {/* CREATE ACCOUNT: HCGA saja */}
          <Route
            path="/accounts/create"
            element={
              <RoleRoute allow={["hcga"]}>
                <AccountCreatePage />
              </RoleRoute>
            }
          />

          {/* PHASE 3 UI: Rekap Bulanan */}
          <Route
            path="/monthly-recaps"
            element={
              <RoleRoute allow={["hcga", "fat", "director"]}>
                <MonthlyRecapPage />
              </RoleRoute>
            }
          />
          <Route
            path="/mutation-approvals"
            element={
              <RoleRoute allow={["director", "hcga"]}>
                <MutationApprovalPage />
              </RoleRoute>
            }
          />

          {/* MY PROFILE */}
          <Route
            path="/my-profile"
            element={
              <RoleRoute allow={["staff", "fat", "director", "hcga"]}>
                <MyProfilePage />
              </RoleRoute>
            }
          />
        </Route>

        {/* FALLBACK */}
        <Route path="*" element={<Navigate to={authed ? home : "/login"} replace />} />
      </Routes>
    </BrowserRouter>
    </ConfirmProvider>
  );
}
