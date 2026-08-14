import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { NotFound } from './NotFound';

describe('NotFound', () => {
  it('renders a 404 message', () => {
    render(<NotFound />);

    expect(screen.getByText('404')).toBeInTheDocument();
    expect(screen.getByText(/this screen doesn't exist/i)).toBeInTheDocument();
  });
});
