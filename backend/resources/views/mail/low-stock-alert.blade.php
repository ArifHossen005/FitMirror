<x-mail::message>
# Low stock alert

The following product variants are at or below their configured low-stock threshold:

<x-mail::table>
| Product | SKU | Stock | Threshold |
|---|---|---:|---:|
@foreach ($variants as $variant)
| {{ $variant->product->name ?? '—' }} | {{ $variant->sku }} | {{ $variant->stock }} | {{ $variant->low_stock_threshold }} |
@endforeach
</x-mail::table>

Restock these soon to avoid running out.

Thanks,<br>
The FitMirror Team
</x-mail::message>
