import { Route, Routes } from 'react-router-dom';

import { EmailVerificationNoticePage } from '../pages/auth/EmailVerificationNoticePage';
import { ForgotPasswordPage } from '../pages/auth/ForgotPasswordPage';
import { InviteAcceptPage } from '../pages/auth/InviteAcceptPage';
import { LoginPage } from '../pages/auth/LoginPage';
import { PendingApprovalPage } from '../pages/auth/PendingApprovalPage';
import { RegisterPage } from '../pages/auth/RegisterPage';
import { ResetPasswordPage } from '../pages/auth/ResetPasswordPage';
import { TwoFactorChallengePage } from '../pages/auth/TwoFactorChallengePage';
import { DashboardHome } from '../pages/DashboardHome';
import { NotFound } from '../pages/NotFound';
import { ProfileSettingsPage } from '../pages/settings/ProfileSettingsPage';
import { SessionsPage } from '../pages/settings/SessionsPage';
import { TwoFactorSetupPage } from '../pages/settings/TwoFactorSetupPage';
import { AuditLogPage } from '../pages/staff/AuditLogPage';
import { StaffListPage } from '../pages/staff/StaffListPage';
import { ProtectedLayout } from './ProtectedLayout';

/**
 * Standalone auth routes (no AppShell chrome, no session required) live
 * outside ProtectedLayout; everything else nests under it. Grows through
 * Phase 3+ (billing), Phase 4 (stores), Phase 5 (catalog), etc. — see
 * apps/mission-control's identical structure for the pattern this follows.
 */
export function AppRoutes() {
  return (
    <Routes>
      <Route path="/register" element={<RegisterPage />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/2fa-challenge" element={<TwoFactorChallengePage />} />
      <Route path="/forgot-password" element={<ForgotPasswordPage />} />
      <Route path="/reset-password" element={<ResetPasswordPage />} />
      <Route path="/invite/accept" element={<InviteAcceptPage />} />
      {/*
        Reachable unauthenticated: RegisterPage navigates here right after
        registration, before any token exists (RegisterController issues
        none — see its own docblock). ProtectedLayout would otherwise
        bounce a signed-out visitor straight to /login before this page
        ever rendered.
      */}
      <Route path="/verify-email" element={<EmailVerificationNoticePage />} />

      <Route element={<ProtectedLayout />}>
        <Route path="/" element={<DashboardHome />} />
        <Route path="/pending-approval" element={<PendingApprovalPage />} />
        <Route path="/staff" element={<StaffListPage />} />
        <Route path="/audit-log" element={<AuditLogPage />} />
        <Route path="/settings" element={<ProfileSettingsPage />} />
        <Route path="/settings/two-factor" element={<TwoFactorSetupPage />} />
        <Route path="/settings/sessions" element={<SessionsPage />} />
      </Route>

      <Route path="*" element={<NotFound />} />
    </Routes>
  );
}
