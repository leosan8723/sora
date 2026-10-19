import { Navigate, Route, Routes } from 'react-router'

import { AppShell } from './components/layout/AppShell.tsx'
import { AboutPage } from './pages/AboutPage.tsx'
import { AppointmentDetailPage } from './pages/AppointmentDetailPage.tsx'
import { AppointmentsPage } from './pages/AppointmentsPage.tsx'
import { ContactPage } from './pages/ContactPage.tsx'
import { ConsultantDashboardPage } from './pages/ConsultantDashboardPage.tsx'
import { ConsultantDetailPage } from './pages/ConsultantDetailPage.tsx'
import { ConsultantsPage } from './pages/ConsultantsPage.tsx'
import { FaqPage } from './pages/FaqPage.tsx'
import { HomePage } from './pages/HomePage.tsx'
import { LoginPage } from './pages/LoginPage.tsx'
import { PasswordSetupPage } from './pages/PasswordSetupPage.tsx'
import { VerifyEmailPage } from './pages/VerifyEmailPage.tsx'
import { ProfilePage } from './pages/ProfilePage.tsx'
import { RequireAuth } from './features/auth/RequireAuth.tsx'
import { RequireRole } from './features/auth/RequireRole.tsx'
import { ADMIN_BASE } from './lib/adminBase.ts'
import { AdminAppointmentsPage } from './pages/admin/AdminAppointmentsPage.tsx'
import { AdminConsultantsPage } from './pages/admin/AdminConsultantsPage.tsx'
import { AdminLayoutPage } from './pages/admin/AdminLayoutPage.tsx'
import { AdminMessagesPage } from './pages/admin/AdminMessagesPage.tsx'
import { AdminPricingPage } from './pages/admin/AdminPricingPage.tsx'
import { AdminReportsPage } from './pages/admin/AdminReportsPage.tsx'
import { AdminReviewsPage } from './pages/admin/AdminReviewsPage.tsx'
import { AdminSettingsPage } from './pages/admin/AdminSettingsPage.tsx'
import { AdminSummaryPage } from './pages/admin/AdminSummaryPage.tsx'
import { AdminUsersPage } from './pages/admin/AdminUsersPage.tsx'

function App() {
  return (
    <Routes>
      <Route element={<AppShell />}>
        <Route path="/" element={<HomePage />} />
        <Route path="/about" element={<AboutPage />} />
        <Route path="/faq" element={<FaqPage />} />
        <Route path="/contact" element={<ContactPage />} />
        <Route path="/consultants" element={<ConsultantsPage />} />
        <Route path="/consultants/:id" element={<ConsultantDetailPage />} />
        <Route
          path="/appointments/:id"
          element={
            <RequireAuth>
              <AppointmentDetailPage />
            </RequireAuth>
          }
        />
        <Route
          path="/appointments"
          element={
            <RequireAuth>
              <AppointmentsPage />
            </RequireAuth>
          }
        />
        <Route
          path="/profile"
          element={
            <RequireAuth>
              <ProfilePage />
            </RequireAuth>
          }
        />
        <Route
          path="/dashboard"
          element={
            <RequireRole role="consultant">
              <ConsultantDashboardPage />
            </RequireRole>
          }
        />
        {ADMIN_BASE !== null && (
          <Route
            path={ADMIN_BASE}
            element={
              <RequireRole role="admin">
                <AdminLayoutPage />
              </RequireRole>
            }
          >
            <Route index element={<AdminSummaryPage />} />
            <Route path="consultants" element={<AdminConsultantsPage />} />
            <Route path="users" element={<AdminUsersPage />} />
            <Route path="appointments" element={<AdminAppointmentsPage />} />
            <Route path="pricing" element={<AdminPricingPage />} />
            <Route path="reviews" element={<AdminReviewsPage />} />
            <Route path="reports" element={<AdminReportsPage />} />
            <Route path="messages" element={<AdminMessagesPage />} />
            <Route path="settings" element={<AdminSettingsPage />} />
          </Route>
        )}
      </Route>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/password-setup" element={<PasswordSetupPage />} />
      <Route path="/verify-email" element={<VerifyEmailPage />} />
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}

export default App
