import { Button } from '@fitmirror/ui';
import { type PointerEvent as ReactPointerEvent, useCallback, useEffect, useRef, useState } from 'react';

export interface ImageCropperProps {
  file: File;
  /** Width divided by height. 1 for a square logo, 3 for a wide banner. */
  aspectRatio: number;
  /** Pixel width of the produced image; height follows from aspectRatio. */
  outputWidth: number;
  onCancel: () => void;
  onCropped: (blob: Blob) => void;
}

const MIN_ZOOM = 1;
const MAX_ZOOM = 4;

/**
 * Drag-and-zoom cropper, built on a canvas rather than pulled in as a
 * dependency.
 *
 * The crop is applied here and the *result* is uploaded, so the server
 * never receives the original: a 12 MP phone photo becomes a few hundred
 * kilobytes before it ever touches the tenant's storage quota. That is
 * also why the backend's own validation only has to bound what it accepts
 * rather than resize anything (see StoreBrandingRequest).
 *
 * State is two numbers and an offset: the visible frame is fixed, the
 * image moves behind it. Pointer events (not mouse events) so the same
 * code works on the tablets shop owners actually use.
 */
export function ImageCropper({
  file,
  aspectRatio,
  outputWidth,
  onCancel,
  onCropped,
}: ImageCropperProps) {
  const [imageUrl, setImageUrl] = useState<string | null>(null);
  const [image, setImage] = useState<HTMLImageElement | null>(null);
  const [zoom, setZoom] = useState(1);
  const [offset, setOffset] = useState({ x: 0, y: 0 });
  const [isRendering, setIsRendering] = useState(false);
  const dragStart = useRef<{ x: number; y: number; originX: number; originY: number } | null>(null);
  const frameRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const url = URL.createObjectURL(file);
    setImageUrl(url);

    const element = new Image();
    element.onload = () => setImage(element);
    element.src = url;

    return () => URL.revokeObjectURL(url);
  }, [file]);

  // A new file resets the framing — carrying over the previous image's pan
  // would open the cropper on an arbitrary corner of the new one.
  useEffect(() => {
    setZoom(1);
    setOffset({ x: 0, y: 0 });
  }, [file]);

  const handlePointerDown = (event: ReactPointerEvent<HTMLDivElement>) => {
    event.currentTarget.setPointerCapture(event.pointerId);
    dragStart.current = { x: event.clientX, y: event.clientY, originX: offset.x, originY: offset.y };
  };

  const handlePointerMove = (event: ReactPointerEvent<HTMLDivElement>) => {
    const start = dragStart.current;
    if (!start) return;

    setOffset({
      x: start.originX + (event.clientX - start.x),
      y: start.originY + (event.clientY - start.y),
    });
  };

  const handlePointerUp = (event: ReactPointerEvent<HTMLDivElement>) => {
    event.currentTarget.releasePointerCapture(event.pointerId);
    dragStart.current = null;
  };

  const crop = useCallback(() => {
    const frame = frameRef.current;
    if (!image || !frame) return;

    setIsRendering(true);

    const frameWidth = frame.clientWidth;
    const frameHeight = frame.clientHeight;
    const outputHeight = Math.round(outputWidth / aspectRatio);

    const canvas = document.createElement('canvas');
    canvas.width = outputWidth;
    canvas.height = outputHeight;

    const context = canvas.getContext('2d');

    if (!context) {
      setIsRendering(false);
      return;
    }

    // The preview uses `object-fit: cover` plus a CSS transform, so the
    // same cover scale has to be recomputed here — otherwise the exported
    // crop would not match the rectangle the user framed.
    const coverScale = Math.max(frameWidth / image.width, frameHeight / image.height) * zoom;
    const drawWidth = image.width * coverScale;
    const drawHeight = image.height * coverScale;
    const drawX = (frameWidth - drawWidth) / 2 + offset.x;
    const drawY = (frameHeight - drawHeight) / 2 + offset.y;

    const exportScale = outputWidth / frameWidth;

    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(
      image,
      drawX * exportScale,
      drawY * exportScale,
      drawWidth * exportScale,
      drawHeight * exportScale,
    );

    canvas.toBlob(
      (blob) => {
        setIsRendering(false);
        if (blob) onCropped(blob);
      },
      'image/png',
      0.92,
    );
  }, [image, zoom, offset, aspectRatio, outputWidth, onCropped]);

  return (
    <div className="flex flex-col gap-4">
      <div
        ref={frameRef}
        className="relative w-full cursor-grab overflow-hidden rounded-lg bg-neutral-100 active:cursor-grabbing"
        style={{ aspectRatio: String(aspectRatio) }}
        onPointerDown={handlePointerDown}
        onPointerMove={handlePointerMove}
        onPointerUp={handlePointerUp}
        onPointerCancel={handlePointerUp}
      >
        {imageUrl && (
          <img
            src={imageUrl}
            alt="Crop preview"
            draggable={false}
            className="pointer-events-none absolute inset-0 h-full w-full object-cover"
            style={{ transform: `translate(${offset.x}px, ${offset.y}px) scale(${zoom})` }}
          />
        )}
      </div>

      <label className="flex items-center gap-3 text-sm text-neutral-600">
        Zoom
        <input
          type="range"
          min={MIN_ZOOM}
          max={MAX_ZOOM}
          step={0.05}
          value={zoom}
          onChange={(event) => setZoom(Number(event.target.value))}
          className="flex-1"
          aria-label="Zoom"
        />
      </label>

      <p className="text-xs text-neutral-500">Drag the image to reposition it inside the frame.</p>

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={onCancel}>
          Cancel
        </Button>
        <Button type="button" onClick={crop} isLoading={isRendering} disabled={!image}>
          Use this crop
        </Button>
      </div>
    </div>
  );
}
