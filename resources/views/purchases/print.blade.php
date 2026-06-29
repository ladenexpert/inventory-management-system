<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Legacy Purchase Receipt #{{ $purchase->display_transaction_number }}</title>
    <style>
        @page {
            size: A4;
            margin: 12mm;
        }

        body {
            font-family: Arial, sans-serif;
            color: #111827;
            font-size: 12px;
            margin: 0;
        }

        .page {
            max-width: 1000px;
            margin: 0 auto;
        }

        .header,
        .meta-grid,
        .footer {
            display: flex;
            justify-content: space-between;
            gap: 24px;
        }

        .header {
            border-bottom: 2px solid #111827;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }

        .brand h1 {
            margin: 0 0 6px;
            font-size: 22px;
        }

        .brand p,
        .meta-card p {
            margin: 4px 0;
        }

        .meta-grid {
            margin-bottom: 18px;
        }

        .meta-card {
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 12px 14px;
            flex: 1;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 10px;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
            text-align: left;
        }

        .text-right {
            text-align: right;
        }

        .totals {
            margin-left: auto;
            width: 320px;
        }

        .totals td {
            border: none;
            padding: 6px 0;
        }

        .totals tr.total td {
            border-top: 2px solid #111827;
            font-weight: bold;
            padding-top: 10px;
        }

        .signature {
            width: 240px;
            border-top: 1px solid #9ca3af;
            padding-top: 8px;
            text-align: center;
            margin-top: 28px;
            margin-left: auto;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="brand">
                <h1>{{ \App\Models\Setting::get('store_name', config('app.name')) }}</h1>
                <p>{{ \App\Models\Setting::get('store_address', 'Jl. Default No. 1') }}</p>
                <p>Phone: {{ \App\Models\Setting::get('store_phone', '-') }}</p>
            </div>
            <div>
                <p><strong>Document</strong>: Legacy Purchase Receipt</p>
                <p><strong>Transaction Number</strong>: {{ $purchase->display_transaction_number }}</p>
                <p><strong>Reference</strong>: {{ $purchase->reference_number ?? '-' }}</p>
                <p><strong>Date</strong>: {{ $purchase->purchase_date->format('d M Y') }}</p>
                <p><strong>Status</strong>: {{ $purchase->status->label() }}</p>
            </div>
        </div>

        <div class="meta-grid">
            <div class="meta-card">
                <p><strong>Supplier</strong></p>
                <p>{{ $purchase->supplier?->name ?? '-' }}</p>
                <p>{{ $purchase->supplier?->phone ?? '-' }}</p>
            </div>
            <div class="meta-card">
                <p><strong>Storage Summary</strong></p>
                <p>{{ $purchase->items->pluck('storageLocation.display_label')->filter()->unique()->implode(', ') ?: $purchase->items->pluck('storage_location')->filter()->unique()->implode(', ') ?: '-' }}</p>
            </div>
            <div class="meta-card">
                <p><strong>Notes</strong></p>
                <p>{{ $purchase->notes ?: 'No additional notes.' }}</p>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 18%">Transaction Number</th>
                    <th style="width: 24%">Item</th>
                    <th style="width: 10%">Qty</th>
                    <th style="width: 14%">Cost</th>
                    <th style="width: 18%">Storage Location</th>
                    <th style="width: 16%">Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach($purchase->items as $item)
                    <tr>
                        <td>{{ $purchase->display_transaction_number }}</td>
                        <td>
                            <strong>{{ $item->product->name }}</strong>
                            <div>SKU: {{ $item->product->sku_display }}</div>
                            <div>Item Code: {{ $item->product->item_code_ierp_display }}</div>
                            <div>Batch: {{ $item->batch?->batch_number ?? $item->batch_number ?? '-' }}</div>
                        </td>
                        <td>{{ number_format($item->quantity) }} {{ $item->product->unit->symbol ?? $item->product->unit->name ?? '' }}</td>
                        <td class="text-right">{{ format_money($item->unit_price) }}</td>
                        <td>{{ $item->batch?->resolved_storage_location ?? $item->storageLocation?->display_label ?? $item->storage_location ?? '-' }}</td>
                        <td>{{ $purchase->notes ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals">
            <tr class="total">
                <td>Total</td>
                <td class="text-right">{{ format_money($purchase->total) }}</td>
            </tr>
        </table>

        <div class="footer">
            <div>
                <p><strong>Prepared By:</strong> {{ $purchase->creator->name ?? '-' }}</p>
                <p><strong>Supplier:</strong> {{ $purchase->supplier?->name ?? '-' }}</p>
            </div>
            <div class="signature">Authorized Signature</div>
        </div>
    </div>
</body>
</html>
