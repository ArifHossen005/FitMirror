<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->number }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1f2937; }
        .header { display: table; width: 100%; margin-bottom: 24px; }
        .header .brand { display: table-cell; width: 50%; vertical-align: top; }
        .header .meta { display: table-cell; width: 50%; text-align: right; vertical-align: top; }
        .brand h1 { font-size: 20px; margin: 0 0 4px; color: #111827; }
        .brand p { margin: 0; color: #6b7280; }
        .meta h2 { font-size: 16px; margin: 0 0 8px; }
        .meta table { margin-left: auto; }
        .meta td { padding: 2px 0 2px 12px; text-align: right; }
        .meta td.label { color: #6b7280; }
        .bill-to { margin-bottom: 24px; }
        .bill-to h3 { font-size: 11px; text-transform: uppercase; color: #6b7280; margin: 0 0 4px; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.items th { text-align: left; border-bottom: 2px solid #111827; padding: 6px 4px; font-size: 11px; text-transform: uppercase; }
        table.items td { border-bottom: 1px solid #e5e7eb; padding: 8px 4px; }
        table.items th.num, table.items td.num { text-align: right; }
        .totals { width: 40%; margin-left: 60%; }
        .totals td { padding: 4px 0; }
        .totals td.label { color: #6b7280; }
        .totals td.value { text-align: right; }
        .totals tr.total td { border-top: 2px solid #111827; font-weight: bold; font-size: 14px; padding-top: 8px; }
        .footer { margin-top: 40px; color: #9ca3af; font-size: 10px; border-top: 1px solid #e5e7eb; padding-top: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">
            <h1>FitMirror</h1>
            <p>Virtual Try-On SaaS Platform</p>
            <p>invoices@fitmirror.com</p>
        </div>
        <div class="meta">
            <h2>INVOICE</h2>
            <table>
                <tr><td class="label">Invoice #</td><td>{{ $invoice->number }}</td></tr>
                <tr><td class="label">Issued</td><td>{{ optional($invoice->issued_at)->format('Y-m-d') }}</td></tr>
                <tr><td class="label">Status</td><td>{{ strtoupper($invoice->status->value) }}</td></tr>
                @if($invoice->paid_at)
                    <tr><td class="label">Paid</td><td>{{ $invoice->paid_at->format('Y-m-d') }}</td></tr>
                @endif
            </table>
        </div>
    </div>

    <div class="bill-to">
        <h3>Billed To</h3>
        <p><strong>{{ $invoice->tenant->name }}</strong></p>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                <th class="num">Qty</th>
                <th class="num">Unit Price</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="num">{{ $item->qty }}</td>
                    <td class="num">{{ number_format($item->unit_price) }} {{ $invoice->currency }}</td>
                    <td class="num">{{ number_format($item->total) }} {{ $invoice->currency }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="label">Subtotal</td><td class="value">{{ number_format($invoice->subtotal) }} {{ $invoice->currency }}</td></tr>
        @if($invoice->discount > 0)
            <tr><td class="label">Discount</td><td class="value">-{{ number_format($invoice->discount) }} {{ $invoice->currency }}</td></tr>
        @endif
        <tr><td class="label">VAT</td><td class="value">{{ number_format($invoice->vat) }} {{ $invoice->currency }}</td></tr>
        <tr class="total"><td>Total</td><td class="value">{{ number_format($invoice->total) }} {{ $invoice->currency }}</td></tr>
    </table>

    <div class="footer">
        <p>This is a system-generated invoice from FitMirror. For billing questions, contact support@fitmirror.com.</p>
    </div>
</body>
</html>
