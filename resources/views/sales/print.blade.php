@php
    $isMaterialUsage = ($context ?? 'sale') === 'material_usage';
    $canViewUsageValue = !$isMaterialUsage || ((auth()->user()?->canViewInventoryValue() ?? false) || (auth()->user()?->canAccessFinance() ?? false));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $isMaterialUsage ? 'Material Usage Slip' : 'Legacy Sales Invoice' }} #{{ $sale->display_transaction_number }}</title>
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
        .summary,
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
        .meta p,
        .footer p {
            margin: 4px 0;
        }

        .meta {
            min-width: 280px;
        }

        .summary {
            margin-bottom: 18px;
        }

        .card {
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

        .muted {
            color: #6b7280;
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

        .footer {
            margin-top: 28px;
            align-items: flex-end;
        }

        .signature {
            width: 240px;
            border-top: 1px solid #9ca3af;
            padding-top: 8px;
            text-align: center;
        }

        @media print {
            .no-print {
                display: none;
            }
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
                <p class="muted">{{ $isMaterialUsage ? 'Raw material issuance slip' : 'Legacy sales invoice / receipt' }}</p>
            </div>

            <div class="meta">
                <p><strong>Document</strong>: {{ $isMaterialUsage ? 'Material Usage Slip' : 'Legacy Sales Invoice' }}</p>
                <p><strong>Transaction Number</strong>: {{ $sale->display_transaction_number }}</p>
                <p><strong>Reference</strong>: {{ $sale->reference_number ?? '-' }}</p>
                <p><strong>Date</strong>: {{ optional($sale->usage_date ?? $sale->sale_date)?->format('d M Y') }}</p>
                <p><strong>Status</strong>: {{ $sale->status->label() }}</p>
                <p><strong>{{ $isMaterialUsage ? 'Issued By' : 'Customer' }}</strong>:
                    {{ $isMaterialUsage ? ($sale->issuer->name ?? $sale->creator->name ?? '-') : ($sale->customer->name ?? 'Guest') }}
                </p>
            </div>
        </div>

        <div class="summary">
            <div class="card">
                <p><strong>{{ $isMaterialUsage ? 'Purpose' : 'Payment Method' }}</strong></p>
                <p>{{ $isMaterialUsage ? ($sale->purpose ?? '-') : strtoupper($sale->payment_method->value) }}</p>
                @if($isMaterialUsage)
                    <p class="muted">Formula: {{ $sale->formula ?? '-' }}</p>
                    <p class="muted">Team: {{ $sale->team?->name ?? $sale->project ?? '-' }}</p>
                @else
                    <p class="muted">Issued by {{ $sale->issuer->name ?? $sale->creator->name ?? '-' }}</p>
                @endif
            </div>
            <div class="card">
                <p><strong>Notes</strong></p>
                <p>{{ $sale->notes ?: 'No additional notes.' }}</p>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 12%">SKU</th>
                    <th style="width: 16%">Item Code</th>
                    <th style="width: 24%">{{ $isMaterialUsage ? 'Raw Material' : 'Product' }}</th>
                    <th style="width: 10%">Unit</th>
                    <th style="width: 10%" class="text-right">Qty</th>
                    <th style="width: 18%">{{ $isMaterialUsage ? 'Batch / Expiry' : 'Unit Price' }}</th>
                    <th style="width: 10%" class="text-right">{{ $isMaterialUsage ? ($canViewUsageValue ? 'Cost' : 'Visibility') : 'Discount' }}</th>
                    <th style="width: 10%" class="text-right">{{ $isMaterialUsage ? ($canViewUsageValue ? 'Allocated Qty' : 'Visibility') : 'Line Total' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->items as $item)
                    <tr>
                        <td>{{ $item->product->sku_display }}</td>
                        <td>{{ $item->product->item_code_ierp_display }}</td>
                        <td>{{ $item->product->name }}</td>
                        <td>{{ $item->product->unit->symbol ?? $item->product->unit->name ?? '-' }}</td>
                        <td class="text-right">{{ number_format($item->quantity) }}</td>
                        <td>
                            @if($isMaterialUsage)
                                {{ $item->saleItemBatches->pluck('batch.batch_number')->filter()->implode(', ') ?: '-' }}
                                <div class="muted">
                                    {{ $item->saleItemBatches->map(fn($allocation) => $allocation->batch?->expiry_date?->format('d/m/Y') ?? 'No expiry')->implode(', ') ?: 'No expiry' }}
                                </div>
                            @else
                                {{ format_money($item->unit_price) }}
                            @endif
                        </td>
                        <td class="text-right">
                            @if($isMaterialUsage)
                                {{ $canViewUsageValue ? format_money($item->total_cost) : 'Restricted' }}
                            @else
                                {{ $item->discount > 0 ? format_money($item->discount) : '-' }}
                            @endif
                        </td>
                        <td class="text-right">
                            {{ $isMaterialUsage ? ($canViewUsageValue ? number_format($item->saleItemBatches->sum('quantity')) : 'Restricted') : format_money($item->subtotal) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if(! $isMaterialUsage)
            <table class="totals">
                <tr>
                    <td>Subtotal</td>
                    <td class="text-right">{{ format_money($sale->subtotal) }}</td>
                </tr>
                <tr>
                    <td>Total Discount</td>
                    <td class="text-right">{{ format_money($sale->total_discount) }}</td>
                </tr>
                <tr class="total">
                    <td>Total</td>
                    <td class="text-right">{{ format_money($sale->total) }}</td>
                </tr>
                <tr>
                    <td>Cash Received</td>
                    <td class="text-right">{{ format_money($sale->cash_received) }}</td>
                </tr>
                <tr>
                    <td>Change</td>
                    <td class="text-right">{{ format_money($sale->change) }}</td>
                </tr>
            </table>
        @endif

        <div class="footer">
            <div>
                @if($isMaterialUsage)
                    <p><strong>Requested By:</strong> {{ $sale->requested_by ?? '-' }}</p>
                    <p><strong>Team:</strong> {{ $sale->team?->name ?? $sale->project ?? '-' }}</p>
                @else
                    <p><strong>Customer:</strong> {{ $sale->customer->name ?? 'Guest' }}</p>
                    <p><strong>Payment:</strong> {{ strtoupper($sale->payment_method->value) }}</p>
                @endif
            </div>
            <div class="signature">
                {{ $isMaterialUsage ? 'Issued By' : 'Authorized Signature' }}
            </div>
        </div>
    </div>
</body>
</html>
