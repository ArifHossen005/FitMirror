import { Button } from '@fitmirror/ui';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { type FormEvent, useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';

import { Can } from '../../hooks/usePermissions';
import {
  ApiError,
  fetchStoreHours,
  type StoreHourPayloadDay,
  toInputTime,
  updateStoreHours,
} from '../../lib/stores';

interface DayState {
  day_of_week: number;
  day_name: string;
  is_closed: boolean;
  opens_at: string;
  closes_at: string;
  /** Empty means "same as the shop hours" — the backend reads null the same way. */
  kiosk_opens_at: string;
  kiosk_closes_at: string;
}

/**
 * Weekly opening hours plus the (optionally narrower) window the kiosk may
 * run in. The whole week is submitted at once because the API replaces it
 * atomically — per-day saves would let a half-applied week reach the kiosk
 * guard (see StoreHoursService::replaceWeek()).
 */
export function StoreHoursPage() {
  const params = useParams<{ storeId: string }>();
  const storeId = Number(params.storeId);
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [days, setDays] = useState<DayState[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  const hoursQuery = useQuery({
    queryKey: ['stores', storeId, 'hours'],
    queryFn: () => fetchStoreHours(storeId),
  });

  useEffect(() => {
    if (!hoursQuery.data) return;

    setDays(
      hoursQuery.data.days.map((day) => ({
        day_of_week: day.day_of_week,
        day_name: day.day_name,
        is_closed: day.is_closed,
        // An unconfigured branch comes back with every day marked closed
        // and no times; pre-filling a sensible default means toggling a day
        // open does not immediately fail validation for missing times.
        opens_at: toInputTime(day.opens_at) || '09:00',
        closes_at: toInputTime(day.closes_at) || '21:00',
        kiosk_opens_at: toInputTime(day.kiosk_opens_at),
        kiosk_closes_at: toInputTime(day.kiosk_closes_at),
      })),
    );
  }, [hoursQuery.data]);

  const mutation = useMutation({
    mutationFn: (payload: StoreHourPayloadDay[]) => updateStoreHours(storeId, payload),
    onSuccess: () => {
      setError(null);
      setSaved(true);
      void queryClient.invalidateQueries({ queryKey: ['stores', storeId, 'hours'] });
    },
    onError: (mutationError) => {
      setSaved(false);
      setError(
        mutationError instanceof ApiError ? mutationError.message : 'Unable to save opening hours.',
      );
    },
  });

  const updateDay = (index: number, patch: Partial<DayState>) =>
    setDays((current) => current.map((day, i) => (i === index ? { ...day, ...patch } : day)));

  const applyToAllDays = (index: number) => {
    const source = days[index];
    if (!source) return;

    setDays((current) =>
      current.map((day) => ({
        ...day,
        is_closed: source.is_closed,
        opens_at: source.opens_at,
        closes_at: source.closes_at,
        kiosk_opens_at: source.kiosk_opens_at,
        kiosk_closes_at: source.kiosk_closes_at,
      })),
    );
  };

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault();
    setSaved(false);

    mutation.mutate(
      days.map((day) => ({
        day_of_week: day.day_of_week,
        is_closed: day.is_closed,
        opens_at: day.is_closed ? null : day.opens_at,
        closes_at: day.is_closed ? null : day.closes_at,
        // Blank means "same as the shop hours", which the API expresses as
        // null — sending '' would fail its H:i format rule.
        kiosk_opens_at: day.is_closed || day.kiosk_opens_at === '' ? null : day.kiosk_opens_at,
        kiosk_closes_at: day.is_closed || day.kiosk_closes_at === '' ? null : day.kiosk_closes_at,
      })),
    );
  };

  const availability = hoursQuery.data?.kiosk_availability;

  if (hoursQuery.isLoading) {
    return <p className="text-sm text-neutral-500">Loading opening hours…</p>;
  }

  return (
    <div className="flex flex-col gap-8">
      <div>
        <button
          type="button"
          onClick={() => navigate('/stores')}
          className="text-sm text-neutral-500 hover:text-neutral-700"
        >
          ← Back to branches
        </button>
        <h1 className="mt-2 text-lg font-semibold text-neutral-900">Opening hours</h1>
        <p className="mt-1 text-sm text-neutral-500">
          Times are in this branch's own timezone ({hoursQuery.data?.timezone}). Leave the kiosk
          times blank to let it run whenever the shop is open.
        </p>
      </div>

      {availability && (
        <div
          className={
            availability.is_open
              ? 'bg-success-50 text-success-700 rounded-lg px-4 py-3 text-sm'
              : 'rounded-lg bg-neutral-100 px-4 py-3 text-sm text-neutral-600'
          }
        >
          {availability.is_open ? (
            <>Kiosks at this branch can run sessions right now.</>
          ) : (
            <>
              Kiosks at this branch are outside their active hours.
              {availability.next_opens_at && (
                <> Next opening: {new Date(availability.next_opens_at).toLocaleString()}.</>
              )}
            </>
          )}
        </div>
      )}

      <form className="flex flex-col gap-4" onSubmit={handleSubmit}>
        <div className="overflow-x-auto">
          <table className="w-full min-w-[720px] border-separate border-spacing-y-2 text-sm">
            <thead>
              <tr className="text-left text-xs font-medium uppercase tracking-wide text-neutral-400">
                <th className="px-3">Day</th>
                <th className="px-3">Open</th>
                <th className="px-3">Shop hours</th>
                <th className="px-3">Kiosk hours</th>
                <th className="px-3" />
              </tr>
            </thead>
            <tbody>
              {days.map((day, index) => (
                <tr key={day.day_of_week} className="rounded-lg bg-neutral-50">
                  <td className="rounded-l-lg px-3 py-3 font-medium text-neutral-800">{day.day_name}</td>
                  <td className="px-3 py-3">
                    <label className="flex items-center gap-2 text-neutral-600">
                      <input
                        type="checkbox"
                        checked={!day.is_closed}
                        onChange={(event) => updateDay(index, { is_closed: !event.target.checked })}
                        disabled={mutation.isPending}
                        aria-label={`${day.day_name} open`}
                      />
                      {day.is_closed ? 'Closed' : 'Open'}
                    </label>
                  </td>
                  <td className="px-3 py-3">
                    <div className="flex items-center gap-2">
                      <input
                        type="time"
                        value={day.opens_at}
                        onChange={(event) => updateDay(index, { opens_at: event.target.value })}
                        disabled={day.is_closed || mutation.isPending}
                        className="h-9 rounded-md border border-neutral-300 px-2 disabled:bg-neutral-100"
                        aria-label={`${day.day_name} opening time`}
                      />
                      <span className="text-neutral-400">–</span>
                      <input
                        type="time"
                        value={day.closes_at}
                        onChange={(event) => updateDay(index, { closes_at: event.target.value })}
                        disabled={day.is_closed || mutation.isPending}
                        className="h-9 rounded-md border border-neutral-300 px-2 disabled:bg-neutral-100"
                        aria-label={`${day.day_name} closing time`}
                      />
                    </div>
                  </td>
                  <td className="px-3 py-3">
                    <div className="flex items-center gap-2">
                      <input
                        type="time"
                        value={day.kiosk_opens_at}
                        onChange={(event) => updateDay(index, { kiosk_opens_at: event.target.value })}
                        disabled={day.is_closed || mutation.isPending}
                        className="h-9 rounded-md border border-neutral-300 px-2 disabled:bg-neutral-100"
                        aria-label={`${day.day_name} kiosk opening time`}
                      />
                      <span className="text-neutral-400">–</span>
                      <input
                        type="time"
                        value={day.kiosk_closes_at}
                        onChange={(event) => updateDay(index, { kiosk_closes_at: event.target.value })}
                        disabled={day.is_closed || mutation.isPending}
                        className="h-9 rounded-md border border-neutral-300 px-2 disabled:bg-neutral-100"
                        aria-label={`${day.day_name} kiosk closing time`}
                      />
                    </div>
                  </td>
                  <td className="rounded-r-lg px-3 py-3 text-right">
                    <button
                      type="button"
                      className="text-brand-600 text-xs hover:underline"
                      onClick={() => applyToAllDays(index)}
                      disabled={mutation.isPending}
                    >
                      Copy to all days
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {error && (
          <p role="alert" className="text-danger-600 text-sm">
            {error}
          </p>
        )}
        {saved && <p className="text-success-700 text-sm">Opening hours saved.</p>}

        <Can permission="stores.update">
          <div className="flex justify-end">
            <Button type="submit" isLoading={mutation.isPending}>
              Save opening hours
            </Button>
          </div>
        </Can>
      </form>
    </div>
  );
}
