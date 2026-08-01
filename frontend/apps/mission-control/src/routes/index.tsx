import { Route, Routes } from 'react-router-dom';

import { MissionHome } from '../pages/MissionHome';
import { NotFound } from '../pages/NotFound';

export function AppRoutes() {
  return (
    <Routes>
      <Route path="/" element={<MissionHome />} />
      <Route path="*" element={<NotFound />} />
    </Routes>
  );
}
