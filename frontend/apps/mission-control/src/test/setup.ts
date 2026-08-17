import '@testing-library/jest-dom/vitest';

import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';

// vitest.config.ts doesn't set `test.globals: true`, so @testing-library/
// react's own auto-cleanup (which detects the global `afterEach`) never
// registers — without this, a multi-test file accumulates every previous
// test's rendered DOM. See apps/dashboard's identical setup.ts for the
// feature test that caught this (Phase 2.C).
afterEach(() => {
  cleanup();
});
