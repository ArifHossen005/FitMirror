import '@testing-library/jest-dom/vitest';

import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';

// vitest.config.ts doesn't set `test.globals: true`, so @testing-library/
// react's own auto-cleanup (which detects the global `afterEach`) never
// registers — without this, a multi-test file accumulates every previous
// test's rendered DOM, and `getByText` starts failing with "multiple
// elements found" on the second test onward. Single-test files (the only
// kind that existed before Phase 2.C) never surfaced this.
afterEach(() => {
  cleanup();
});
