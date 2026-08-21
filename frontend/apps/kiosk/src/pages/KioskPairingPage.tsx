import { Button } from '@fitmirror/ui';
import { type ClipboardEvent, type KeyboardEvent, useEffect, useRef, useState } from 'react';

import { getDeviceFingerprint } from '../lib/deviceToken';
import {
  claimPairingCode,
  KioskApiError,
  normalisePairingCode,
  PAIRING_CODE_LENGTH,
} from '../lib/pairing';

export interface KioskPairingPageProps {
  onPaired: () => void;
}

/**
 * The first screen an unpaired kiosk shows: enter the code the shop owner
 * reads off their dashboard.
 *
 * One box per character rather than a single text field — a staff member
 * is typing eight characters on a tablet, standing up, and the segmented
 * layout makes both the expected length and their position in it obvious
 * at a glance. Paste is handled explicitly so copying the whole code from
 * a message still works.
 */
export function KioskPairingPage({ onPaired }: KioskPairingPageProps) {
  const [characters, setCharacters] = useState<string[]>(() =>
    Array.from({ length: PAIRING_CODE_LENGTH }, () => ''),
  );
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const inputsRef = useRef<Array<HTMLInputElement | null>>([]);

  useEffect(() => {
    inputsRef.current[0]?.focus();
  }, []);

  const code = characters.join('');
  const isComplete = code.length === PAIRING_CODE_LENGTH;

  const setCharacterAt = (index: number, value: string) => {
    setCharacters((current) => {
      const next = [...current];
      next[index] = value;
      return next;
    });
  };

  const handleChange = (index: number, rawValue: string) => {
    const cleaned = normalisePairingCode(rawValue);

    if (cleaned === '') {
      setCharacterAt(index, '');
      return;
    }

    // Typing into a box when the code was pasted character-by-character
    // still fills forward, so holding a key or a fast typist never drops
    // characters.
    setCharacters((current) => {
      const next = [...current];

      for (let offset = 0; offset < cleaned.length && index + offset < PAIRING_CODE_LENGTH; offset++) {
        next[index + offset] = cleaned[offset] as string;
      }

      return next;
    });

    const nextIndex = Math.min(index + cleaned.length, PAIRING_CODE_LENGTH - 1);
    inputsRef.current[nextIndex]?.focus();
  };

  const handleKeyDown = (index: number, event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'Backspace' && characters[index] === '' && index > 0) {
      event.preventDefault();
      setCharacterAt(index - 1, '');
      inputsRef.current[index - 1]?.focus();
      return;
    }

    if (event.key === 'ArrowLeft' && index > 0) {
      event.preventDefault();
      inputsRef.current[index - 1]?.focus();
      return;
    }

    if (event.key === 'ArrowRight' && index < PAIRING_CODE_LENGTH - 1) {
      event.preventDefault();
      inputsRef.current[index + 1]?.focus();
    }
  };

  const handlePaste = (event: ClipboardEvent<HTMLInputElement>) => {
    event.preventDefault();

    const pasted = normalisePairingCode(event.clipboardData.getData('text'));

    if (pasted === '') return;

    setCharacters(
      Array.from({ length: PAIRING_CODE_LENGTH }, (_, index) => pasted[index] ?? ''),
    );
    inputsRef.current[Math.min(pasted.length, PAIRING_CODE_LENGTH - 1)]?.focus();
  };

  const submit = async () => {
    if (!isComplete || isSubmitting) return;

    setIsSubmitting(true);
    setError(null);

    try {
      await claimPairingCode(code);
      onPaired();
    } catch (claimError) {
      setError(
        claimError instanceof KioskApiError
          ? claimError.message
          : 'Could not pair this kiosk. Check the code and try again.',
      );
      setCharacters(Array.from({ length: PAIRING_CODE_LENGTH }, () => ''));
      inputsRef.current[0]?.focus();
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="flex flex-1 flex-col items-center justify-center px-6 text-center">
      <h1 className="text-kiosk-2xl font-semibold">Pair this kiosk</h1>
      <p className="mt-3 max-w-md text-neutral-400">
        In your FitMirror dashboard, open <strong>Branches → Kiosks</strong> and choose{' '}
        <strong>Pair a device</strong>. Enter the code shown there.
      </p>

      <form
        className="mt-10 flex flex-col items-center gap-6"
        onSubmit={(event) => {
          event.preventDefault();
          void submit();
        }}
      >
        <div className="flex gap-2 sm:gap-3">
          {characters.map((character, index) => (
            <input
              // The boxes are a fixed-length positional array, never
              // reordered or filtered, so the index is a stable identity.
              key={index}
              ref={(element) => {
                inputsRef.current[index] = element;
              }}
              value={character}
              onChange={(event) => handleChange(index, event.target.value)}
              onKeyDown={(event) => handleKeyDown(index, event)}
              onPaste={handlePaste}
              onFocus={(event) => event.target.select()}
              inputMode="text"
              autoCapitalize="characters"
              autoComplete="off"
              spellCheck={false}
              maxLength={PAIRING_CODE_LENGTH}
              aria-label={`Pairing code character ${index + 1}`}
              disabled={isSubmitting}
              className="focus:border-brand-500 h-16 w-12 rounded-lg border-2 border-neutral-700 bg-neutral-900 text-center font-mono text-2xl uppercase text-white outline-none sm:h-20 sm:w-16 sm:text-3xl"
            />
          ))}
        </div>

        {error && (
          <p role="alert" className="text-danger-400 max-w-md text-sm">
            {error}
          </p>
        )}

        <Button type="submit" size="lg" isLoading={isSubmitting} disabled={!isComplete}>
          Pair kiosk
        </Button>
      </form>

      <p className="mt-10 text-xs text-neutral-600">
        Device ID {getDeviceFingerprint().slice(0, 8)} · App {__APP_VERSION__}
      </p>
    </div>
  );
}
