import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';

import { useDashboardAuthStore } from '../stores/dashboardAuthStore';
import { Can } from './usePermissions';

describe('Can', () => {
  beforeEach(() => {
    useDashboardAuthStore.setState({ roles: ['manager'], permissions: ['staff.view', 'staff.invite'] });
  });

  it('renders children when the permission is granted', () => {
    render(
      <Can permission="staff.view">
        <span>Visible</span>
      </Can>,
    );

    expect(screen.getByText('Visible')).toBeInTheDocument();
  });

  it('renders nothing when the permission is missing', () => {
    render(
      <Can permission="billing.manage">
        <span>Hidden</span>
      </Can>,
    );

    expect(screen.queryByText('Hidden')).not.toBeInTheDocument();
  });

  it('renders the fallback when provided and the permission is missing', () => {
    render(
      <Can permission="billing.manage" fallback={<span>Fallback</span>}>
        <span>Hidden</span>
      </Can>,
    );

    expect(screen.getByText('Fallback')).toBeInTheDocument();
  });

  it('mode="any" renders when at least one permission in the list matches', () => {
    render(
      <Can permission={['billing.manage', 'staff.view']} mode="any">
        <span>Visible</span>
      </Can>,
    );

    expect(screen.getByText('Visible')).toBeInTheDocument();
  });

  it('mode="all" (default) requires every permission in the list', () => {
    render(
      <Can permission={['staff.view', 'billing.manage']}>
        <span>Hidden</span>
      </Can>,
    );

    expect(screen.queryByText('Hidden')).not.toBeInTheDocument();
  });
});
