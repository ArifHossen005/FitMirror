import { Route, Routes } from 'react-router-dom';

import { DashboardHome } from '../pages/DashboardHome';
import { NotFound } from '../pages/NotFound';

/**
 * Route tree for the shop-owner dashboard app. Grows through Phase 2
 * (auth pages), Phase 3 (billing), Phase 4 (stores/staff), Phase 5
 * (catalog), etc. — each phase adds its own <Route> here rather than a
 * separate router, so the whole app tree stays visible in one file.
 */
export function AppRoutes() {
  return (
    <Routes>
      <Route path="/" element={<DashboardHome />} />
      <Route path="*" element={<NotFound />} />
    </Routes>
  );
}
