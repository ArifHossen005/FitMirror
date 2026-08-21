import { Button, Select } from '@fitmirror/ui';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { type DragEvent, useMemo, useState } from 'react';

import { ShiftEditorModal } from '../../components/stores/ShiftEditorModal';
import { Can } from '../../hooks/usePermissions';
import { fetchStaff, type StaffMember } from '../../lib/staff';
import {
  ApiError,
  cancelShift,
  deleteShift,
  fetchSchedule,
  fetchStores,
  type Shift,
  updateShift,
} from '../../lib/stores';

/** Sunday-first, matching Carbon::dayOfWeek and the store-hours editor. */
const DAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function toIsoDate(date: Date): string {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

/** The Sunday on or before `date`, so a week always starts on the same column. */
function startOfWeek(date: Date): Date {
  const start = new Date(date);
  start.setDate(start.getDate() - start.getDay());
  start.setHours(0, 0, 0, 0);
  return start;
}

function addDays(date: Date, days: number): Date {
  const next = new Date(date);
  next.setDate(next.getDate() + days);
  return next;
}

/**
 * Weekly roster grid — one column per day, one row per staff member.
 *
 * Drag-and-drop moves a shift between cells, which is a change of both
 * date and staff member in one gesture. Built on the HTML drag-and-drop
 * API rather than a library: the interaction is "pick up one card, drop it
 * on one cell", and a drag library would be a dependency carrying its own
 * sensor/collision machinery for a grid that has neither sorting nor
 * nesting.
 *
 * The backend rejects an overlapping move (ShiftService::assertNoOverlap),
 * so an invalid drop surfaces as an error and the grid refetches to its
 * true state rather than being validated twice, differently, in two places.
 */
export function ShiftSchedulerPage() {
  const queryClient = useQueryClient();
  const [weekStart, setWeekStart] = useState(() => startOfWeek(new Date()));
  const [storeFilter, setStoreFilter] = useState<string>('');
  const [editing, setEditing] = useState<{ shift?: Shift; date?: string; userId?: number } | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [dragShiftId, setDragShiftId] = useState<number | null>(null);

  const weekDays = useMemo(
    () => Array.from({ length: 7 }, (_, index) => addDays(weekStart, index)),
    [weekStart],
  );
  const from = toIsoDate(weekDays[0] as Date);
  const to = toIsoDate(weekDays[6] as Date);

  const storesQuery = useQuery({ queryKey: ['stores', 1], queryFn: () => fetchStores(1) });
  const staffQuery = useQuery({ queryKey: ['staff', 1], queryFn: () => fetchStaff(1) });

  const scheduleQuery = useQuery({
    queryKey: ['shifts', from, to, storeFilter],
    queryFn: () =>
      fetchSchedule({ from, to, storeId: storeFilter === '' ? undefined : Number(storeFilter) }),
  });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['shifts'] });

  const moveMutation = useMutation({
    mutationFn: ({ shiftId, date, userId }: { shiftId: number; date: string; userId: number }) =>
      updateShift(shiftId, { shift_date: date, user_id: userId }),
    onSuccess: () => {
      setError(null);
      void invalidate();
    },
    onError: (mutationError) => {
      setError(mutationError instanceof ApiError ? mutationError.message : 'Unable to move that shift.');
      void invalidate();
    },
  });

  const cancelMutation = useMutation({ mutationFn: cancelShift, onSuccess: invalidate });
  const deleteMutation = useMutation({ mutationFn: deleteShift, onSuccess: invalidate });

  const staff: StaffMember[] = staffQuery.data?.data ?? [];
  const shifts = scheduleQuery.data?.shifts ?? [];

  const shiftsFor = (userId: number, date: string) =>
    shifts.filter((shift) => shift.user_id === userId && shift.shift_date === date);

  const handleDrop = (event: DragEvent<HTMLDivElement>, userId: number, date: string) => {
    event.preventDefault();

    if (dragShiftId === null) return;

    const shift = shifts.find((candidate) => candidate.id === dragShiftId);
    setDragShiftId(null);

    if (!shift || (shift.user_id === userId && shift.shift_date === date)) return;

    moveMutation.mutate({ shiftId: shift.id, date, userId });
  };

  const defaultStoreId = storeFilter !== ''
    ? Number(storeFilter)
    : storesQuery.data?.stores[0]?.id;

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-lg font-semibold text-neutral-900">Shift schedule</h1>
          <p className="mt-1 text-sm text-neutral-500">
            Drag a shift onto another day or staff member to move it.
          </p>
        </div>
        <div className="flex items-end gap-2">
          <Select
            label="Branch"
            value={storeFilter}
            onChange={(event) => setStoreFilter(event.target.value)}
            options={[
              { value: '', label: 'All branches' },
              ...(storesQuery.data?.stores ?? []).map((store) => ({
                value: String(store.id),
                label: store.name,
              })),
            ]}
            className="h-9"
          />
          <Button variant="outline" size="sm" onClick={() => setWeekStart(addDays(weekStart, -7))}>
            ← Previous
          </Button>
          <Button variant="outline" size="sm" onClick={() => setWeekStart(startOfWeek(new Date()))}>
            This week
          </Button>
          <Button variant="outline" size="sm" onClick={() => setWeekStart(addDays(weekStart, 7))}>
            Next →
          </Button>
        </div>
      </div>

      {error && (
        <p role="alert" className="bg-danger-50 text-danger-700 rounded-lg px-4 py-3 text-sm">
          {error}
        </p>
      )}

      <div className="overflow-x-auto">
        <div className="min-w-[900px]">
          <div className="grid grid-cols-[180px_repeat(7,1fr)] gap-px rounded-t-lg bg-neutral-200">
            <div className="bg-neutral-50 px-3 py-2 text-xs font-medium uppercase tracking-wide text-neutral-400">
              Staff
            </div>
            {weekDays.map((day, index) => (
              <div key={toIsoDate(day)} className="bg-neutral-50 px-3 py-2 text-center">
                <p className="text-xs font-medium uppercase tracking-wide text-neutral-400">
                  {DAY_LABELS[index]}
                </p>
                <p className="text-sm font-medium text-neutral-700">{day.getDate()}</p>
              </div>
            ))}
          </div>

          <div className="grid grid-cols-[180px_repeat(7,1fr)] gap-px bg-neutral-200">
            {staff.map((member) => (
              <ScheduleRow
                key={member.id}
                member={member}
                weekDays={weekDays}
                shiftsFor={shiftsFor}
                onDragStart={setDragShiftId}
                onDrop={handleDrop}
                onOpenShift={(shift) => setEditing({ shift })}
                onAddShift={(date) => setEditing({ date, userId: member.id })}
                onCancelShift={(shiftId) => cancelMutation.mutate(shiftId)}
                onDeleteShift={(shiftId) => deleteMutation.mutate(shiftId)}
              />
            ))}
          </div>

          {staff.length === 0 && !staffQuery.isLoading && (
            <p className="rounded-b-lg bg-neutral-50 px-4 py-6 text-center text-sm text-neutral-500">
              Invite staff members before building a roster.
            </p>
          )}
        </div>
      </div>

      {editing && defaultStoreId !== undefined && (
        <ShiftEditorModal
          storeId={defaultStoreId}
          stores={(storesQuery.data?.stores ?? []).map((store) => ({
            value: String(store.id),
            label: store.name,
          }))}
          staff={staff}
          {...(editing.shift ? { shift: editing.shift } : {})}
          {...(editing.date ? { date: editing.date } : {})}
          {...(editing.userId ? { userId: editing.userId } : {})}
          onClose={() => setEditing(null)}
          onSaved={() => {
            setEditing(null);
            void invalidate();
          }}
        />
      )}
    </div>
  );
}

interface ScheduleRowProps {
  member: StaffMember;
  weekDays: Date[];
  shiftsFor: (userId: number, date: string) => Shift[];
  onDragStart: (shiftId: number) => void;
  onDrop: (event: DragEvent<HTMLDivElement>, userId: number, date: string) => void;
  onOpenShift: (shift: Shift) => void;
  onAddShift: (date: string) => void;
  onCancelShift: (shiftId: number) => void;
  onDeleteShift: (shiftId: number) => void;
}

function ScheduleRow({
  member,
  weekDays,
  shiftsFor,
  onDragStart,
  onDrop,
  onOpenShift,
  onAddShift,
  onCancelShift,
  onDeleteShift,
}: ScheduleRowProps) {
  return (
    <>
      <div className="bg-white px-3 py-3">
        <p className="text-sm font-medium text-neutral-900">{member.name}</p>
        <p className="text-xs text-neutral-400">{member.roles[0] ?? '—'}</p>
      </div>

      {weekDays.map((day) => {
        const date = toIsoDate(day);
        const cellShifts = shiftsFor(member.id, date);

        return (
          <div
            key={date}
            className="group min-h-[76px] bg-white p-1.5"
            onDragOver={(event) => event.preventDefault()}
            onDrop={(event) => onDrop(event, member.id, date)}
          >
            <div className="flex flex-col gap-1">
              {cellShifts.map((shift) => (
                <div
                  key={shift.id}
                  draggable={shift.status === 'scheduled'}
                  onDragStart={() => onDragStart(shift.id)}
                  className={
                    shift.status === 'cancelled'
                      ? 'rounded border border-neutral-200 bg-neutral-50 px-2 py-1 text-xs text-neutral-400 line-through'
                      : 'border-brand-200 bg-brand-50 text-brand-800 cursor-grab rounded border px-2 py-1 text-xs active:cursor-grabbing'
                  }
                >
                  <button
                    type="button"
                    className="block w-full text-left"
                    onClick={() => onOpenShift(shift)}
                  >
                    <span className="font-medium">
                      {shift.starts_at}–{shift.ends_at}
                    </span>
                    {shift.is_overnight && <span className="ml-1 text-[10px]">+1d</span>}
                    <span className="block truncate text-[10px] opacity-70">{shift.store_name}</span>
                  </button>

                  {shift.status === 'scheduled' && (
                    <div className="mt-1 hidden gap-2 group-hover:flex">
                      <button
                        type="button"
                        className="text-[10px] underline"
                        onClick={() => onCancelShift(shift.id)}
                      >
                        Cancel
                      </button>
                      <button
                        type="button"
                        className="text-danger-600 text-[10px] underline"
                        onClick={() => onDeleteShift(shift.id)}
                      >
                        Delete
                      </button>
                    </div>
                  )}
                </div>
              ))}

              <Can permission="staff.update">
                <button
                  type="button"
                  onClick={() => onAddShift(date)}
                  className="hover:text-brand-600 rounded border border-dashed border-neutral-200 py-1 text-xs text-neutral-300 opacity-0 transition group-hover:opacity-100"
                >
                  + Add
                </button>
              </Can>
            </div>
          </div>
        );
      })}
    </>
  );
}
