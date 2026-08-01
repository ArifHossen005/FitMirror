import { Route, Routes } from 'react-router-dom';

import { KioskHome } from '../pages/KioskHome';
import { NotFound } from '../pages/NotFound';

export function AppRoutes() {
  return (
    <Routes>
      <Route path="/" element={<KioskHome />} />
      <Route path="*" element={<NotFound />} />
    </Routes>
  );
}
