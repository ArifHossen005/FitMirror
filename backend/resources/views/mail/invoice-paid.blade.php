<x-mail::message>
# Payment received

Thanks — we've received your payment for **{{ $invoice->number }}**.

<x-mail::table>
| | |
|---|---:|
| Amount | {{ number_format($invoice->total) }} {{ $invoice->currency }} |
| Paid | {{ optional($invoice->paid_at)->format('Y-m-d') }} |
</x-mail::table>

Your invoice PDF is attached to this email for your records.

Thanks,<br>
The FitMirror Team
</x-mail::message>
