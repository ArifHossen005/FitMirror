import { Button, Input, Modal, Select, type SelectOption } from '@fitmirror/ui';
import { useMutation } from '@tanstack/react-query';
import { type FormEvent, useState } from 'react';

import type { StaffMember } from '../../lib/staff';
import { ApiError, createShift, type Shift, updateShift } from '../../lib/stores';

export interface ShiftEditorModalProps {
  /** Branch a new shift is created against; ignored when editing. */
  storeId: number;
  stores: SelectOption[];
  staff: StaffMember[];
  shift?: Shift;
  date?: string;
  userId?: number;
  onClose: () => void;
  onSaved: () => void;
}

/**
 * Create or edit one rostered shift.
 *
 * `ends_at` is deliberately allowed to be earlier than `starts_at` — that
 * is how an overnight shift is expressed (22:00 to 06:00), and the backend
 * reads the pair as absolute instants rather than as two clock faces. The
 * hint below says so, because a form that silently accepts what looks like
 * a mistake needs to explain itself.
 */
export function ShiftEditorModal({
  storeId,
  stores,
  staff,
  shift,
  date,
  userId,
  onClose,
  onSaved,
}: ShiftEditorModalProps) {
  const isEditing = shift !== undefined;

  const [form, setForm] = useState({
    store_id: String(shift?.store_id ?? storeId),
    user_id: String(shift?.user_id ?? userId ?? staff[0]?.id ?? ''),
    shift_date: shift?.shift_date ?? date ?? '',
    starts_at: shift?.starts_at ?? '09:00',
    ends_at: shift?.ends_at ?? '17:00',
    note: shift?.note ?? '',
  });
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

  const mutation = useMutation({
    mutationFn: () => {
      const payload = {
        user_id: Number(form.user_id),
        shift_date: form.shift_date,
        starts_at: form.starts_at,
        ends_at: form.ends_at,
        ...(form.note.trim() !== '' ? { note: form.note.trim() } : {}),
      };

      return isEditing
        ? updateShift(shift.id, payload)
        : createShift(Number(form.store_id), payload);
    },
    onSuccess: onSaved,
    onError: (mutationError) => {
      if (mutationError instanceof ApiError) {
        setError(mutationError.message);
        setFieldErrors(mutationError.fieldErrors);
        return;
      }
      setError('Unable to save this shift.');
    },
  });

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault();
    setError(null);
    setFieldErrors({});
    mutation.mutate();
  };

  const fieldError = (field: string) => fieldErrors[field]?.[0];

  const isOvernight = form.ends_at <= form.starts_at;

  return (
    <Modal isOpen onClose={onClose} title={isEditing ? 'Edit shift' : 'Add a shift'}>
      <form className="flex flex-col gap-4" onSubmit={handleSubmit} noValidate>
        {!isEditing && (
          <Select
            label="Branch"
            value={form.store_id}
            onChange={(event) => setForm({ ...form, store_id: event.target.value })}
            options={stores}
            disabled={mutation.isPending}
          />
        )}

        <Select
          label="Staff member"
          value={form.user_id}
          onChange={(event) => setForm({ ...form, user_id: event.target.value })}
          options={staff.map((member) => ({ value: String(member.id), label: member.name }))}
          error={fieldError('user_id')}
          disabled={mutation.isPending}
        />

        <Input
          label="Date"
          type="date"
          required
          value={form.shift_date}
          onChange={(event) => setForm({ ...form, shift_date: event.target.value })}
          error={fieldError('shift_date')}
          disabled={mutation.isPending}
        />

        <div className="grid gap-4 sm:grid-cols-2">
          <Input
            label="Starts at"
            type="time"
            required
            value={form.starts_at}
            onChange={(event) => setForm({ ...form, starts_at: event.target.value })}
            error={fieldError('starts_at')}
            disabled={mutation.isPending}
          />
          <Input
            label="Ends at"
            type="time"
            required
            value={form.ends_at}
            onChange={(event) => setForm({ ...form, ends_at: event.target.value })}
            error={fieldError('ends_at')}
            {...(isOvernight ? { hint: 'Runs past midnight into the next day.' } : {})}
            disabled={mutation.isPending}
          />
        </div>

        <Input
          label="Note (optional)"
          value={form.note}
          onChange={(event) => setForm({ ...form, note: event.target.value })}
          error={fieldError('note')}
          disabled={mutation.isPending}
        />

        {error && (
          <p role="alert" className="text-danger-600 text-sm">
            {error}
          </p>
        )}

        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={onClose} disabled={mutation.isPending}>
            Cancel
          </Button>
          <Button type="submit" isLoading={mutation.isPending}>
            {isEditing ? 'Save shift' : 'Add shift'}
          </Button>
        </div>
      </form>
    </Modal>
  );
}
