<x-mail::message>
# Background removal failed

We couldn't produce an AR-ready image for **{{ $product->name }}** after several attempts.

<x-mail::table>
| | |
|---|---|
| Product | {{ $product->name }} ({{ $product->sku }}) |
| Image | {{ basename($image->path) }} |
| Error | {{ $image->background_removal_error ?? 'Unknown error' }} |
</x-mail::table>

You can re-upload the AR-ready image manually from the product's photo gallery, or retry the automatic removal from the same screen.

Thanks,<br>
The FitMirror Team
</x-mail::message>
