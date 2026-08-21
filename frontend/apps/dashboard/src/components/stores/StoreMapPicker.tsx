import { Button, Input } from '@fitmirror/ui';
import { useState } from 'react';

export interface StoreMapPickerValue {
  lat: string;
  lng: string;
  mapUrl: string;
}

export interface StoreMapPickerProps {
  lat: string;
  lng: string;
  mapUrl: string;
  disabled?: boolean;
  latError?: string | undefined;
  lngError?: string | undefined;
  mapUrlError?: string | undefined;
  onChange: (value: StoreMapPickerValue) => void;
}

/**
 * Coordinate picker for a branch.
 *
 * Deliberately not an embedded interactive map. A Google/Mapbox tile layer
 * would need a paid API key FitMirror does not have, and would be a
 * third-party script on a page a shop owner uses daily — a hard
 * dependency for what is, in practice, "paste the pin you already have".
 * Instead this offers the two things a shop owner actually does: paste the
 * Google Maps link they copied from their phone, or use the browser's own
 * geolocation while standing in the shop.
 *
 * Pasting a link extracts the coordinates from it where possible, so the
 * lat/lng the kiosk and the "get directions" button use stay in step with
 * the link itself rather than being two independent facts that can drift.
 */
export function StoreMapPicker({
  lat,
  lng,
  mapUrl,
  disabled = false,
  latError,
  lngError,
  mapUrlError,
  onChange,
}: StoreMapPickerProps) {
  const [locating, setLocating] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);

  const emit = (next: Partial<StoreMapPickerValue>) =>
    onChange({ lat, lng, mapUrl, ...next });

  const handleMapUrlChange = (value: string) => {
    const extracted = extractCoordinates(value);

    if (extracted) {
      setNotice('Coordinates read from the link.');
      emit({ mapUrl: value, lat: extracted.lat, lng: extracted.lng });
      return;
    }

    setNotice(null);
    emit({ mapUrl: value });
  };

  const useMyLocation = () => {
    if (!('geolocation' in navigator)) {
      setNotice('This browser cannot share a location.');
      return;
    }

    setLocating(true);
    setNotice(null);

    navigator.geolocation.getCurrentPosition(
      (position) => {
        setLocating(false);
        emit({
          lat: position.coords.latitude.toFixed(7),
          lng: position.coords.longitude.toFixed(7),
        });
        setNotice('Using your current position.');
      },
      () => {
        setLocating(false);
        setNotice('Could not read your location. Enter the coordinates manually.');
      },
      { enableHighAccuracy: true, timeout: 10000 },
    );
  };

  const previewUrl =
    lat.trim() !== '' && lng.trim() !== ''
      ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${lat},${lng}`)}`
      : mapUrl.trim() !== ''
        ? mapUrl
        : null;

  return (
    <fieldset className="flex flex-col gap-4">
      <legend className="text-sm font-medium text-neutral-700">Map location</legend>

      <Input
        label="Google Maps link"
        type="url"
        placeholder="https://maps.google.com/…"
        value={mapUrl}
        onChange={(event) => handleMapUrlChange(event.target.value)}
        error={mapUrlError}
        hint="Paste the share link from Google Maps. Coordinates are filled in automatically when the link contains them."
        disabled={disabled}
      />

      <div className="grid items-end gap-4 sm:grid-cols-3">
        <Input
          label="Latitude"
          inputMode="decimal"
          placeholder="23.7808875"
          value={lat}
          onChange={(event) => emit({ lat: event.target.value })}
          error={latError}
          disabled={disabled}
        />
        <Input
          label="Longitude"
          inputMode="decimal"
          placeholder="90.2792371"
          value={lng}
          onChange={(event) => emit({ lng: event.target.value })}
          error={lngError}
          disabled={disabled}
        />
        <Button
          type="button"
          variant="outline"
          onClick={useMyLocation}
          isLoading={locating}
          disabled={disabled}
        >
          Use my location
        </Button>
      </div>

      {notice && <p className="text-xs text-neutral-500">{notice}</p>}

      {previewUrl && (
        <a
          href={previewUrl}
          target="_blank"
          rel="noreferrer"
          className="text-brand-600 text-sm hover:underline"
        >
          Open this pin in Google Maps ↗
        </a>
      )}
    </fieldset>
  );
}

/**
 * Pulls a lat/lng pair out of a Google Maps URL. Two shapes cover almost
 * every link a shop owner pastes:
 *
 *   - `.../@23.78,90.27,17z`  — the map's own viewport centre
 *   - `?q=23.78,90.27` / `?query=23.78,90.27` — a search or share link
 *
 * A shortened `maps.app.goo.gl` link contains no coordinates at all and
 * cannot be expanded without following the redirect, which is a
 * cross-origin request the browser will not allow. Those simply return
 * null and the owner fills the fields in by hand — better than silently
 * doing nothing while looking like it worked.
 */
function extractCoordinates(url: string): { lat: string; lng: string } | null {
  const atMatch = url.match(/@(-?\d+\.\d+),(-?\d+\.\d+)/);

  if (atMatch?.[1] && atMatch[2]) {
    return { lat: atMatch[1], lng: atMatch[2] };
  }

  const queryMatch = url.match(/[?&](?:q|query|ll)=(-?\d+\.\d+),(-?\d+\.\d+)/);

  if (queryMatch?.[1] && queryMatch[2]) {
    return { lat: queryMatch[1], lng: queryMatch[2] };
  }

  return null;
}
