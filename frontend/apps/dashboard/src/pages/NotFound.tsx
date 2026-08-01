import { Button } from '@fitmirror/ui';
import { Link } from 'react-router-dom';

export function NotFound() {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-4 p-6 text-center">
      <h1 className="text-3xl font-semibold text-neutral-900">404</h1>
      <p className="text-neutral-500">This page doesn't exist.</p>
      <Link to="/">
        <Button variant="outline">Back to dashboard</Button>
      </Link>
    </div>
  );
}
