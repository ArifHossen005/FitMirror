import { Route, Routes } from 'react-router-dom';

import { NotFound } from '../pages/NotFound';
import { PortalHome } from '../pages/PortalHome';
import { TryOnLanding } from '../pages/TryOnLanding';

export function AppRoutes() {
  return (
    <Routes>
      <Route path="/" element={<PortalHome />} />
      <Route path="/try-on/:token" element={<TryOnLanding />} />
      <Route path="*" element={<NotFound />} />
    </Routes>
  );
}
