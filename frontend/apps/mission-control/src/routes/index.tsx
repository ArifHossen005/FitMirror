import { Route, Routes } from 'react-router-dom';

import { MissionHome } from '../pages/MissionHome';
import { MissionLogin } from '../pages/MissionLogin';
import { NotFound } from '../pages/NotFound';
import { ProtectedLayout } from './ProtectedLayout';

/**
 * /login stands alone (no MissionShell chrome, no auth guard). Every other
 * route nests under ProtectedLayout, which redirects to /login when there
 * is no session and otherwise wraps the page in MissionShell — see
 * routes/ProtectedLayout.tsx.
 */
export function AppRoutes() {
  return (
    <Routes>
      <Route path="/login" element={<MissionLogin />} />
      <Route element={<ProtectedLayout />}>
        <Route path="/" element={<MissionHome />} />
      </Route>
      <Route path="*" element={<NotFound />} />
    </Routes>
  );
}
