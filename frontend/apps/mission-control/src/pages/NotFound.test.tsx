import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it } from 'vitest';

import { NotFound } from './NotFound';

describe('NotFound', () => {
  it('renders a 404 message with a link back to Mission Control', () => {
    render(
      <MemoryRouter>
        <NotFound />
      </MemoryRouter>,
    );

    expect(screen.getByText('404')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /back to mission control/i })).toHaveAttribute(
      'href',
      '/',
    );
  });
});
