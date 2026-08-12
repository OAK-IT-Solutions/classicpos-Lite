<!DOCTYPE html>
<html>
<head>
    <title>Invoice {{ $sale->invoice_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
        .header { text-align: center; padding: 20px 0; border-bottom: 2px solid #eee; }
        .header h1 { margin: 0; font-size: 24px; }
        .details { margin: 20px 0; }
        .details p { margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; }
        .total-row td { font-weight: bold; border-top: 2px solid #333; }
        .footer { text-align: center; padding: 20px 0; color: #888; font-size: 12px; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Invoice</h1>
        <p>{{ $sale->invoice_number }}</p>
    </div>

    <div class="details">
        <p><strong>Date:</strong> {{ $sale->created_at->format('Y-m-d H:i') }}</p>
        @if ($sale->customer)
            <p><strong>Customer:</strong> {{ $sale->customer->name }}</p>
            <p><strong>Email:</strong> {{ $sale->customer->email }}</p>
        @endif
        <p><strong>Payment Method:</strong> {{ ucfirst($sale->payment_method) }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $item)
                <tr>
                    <td>{{ $item->product->name ?? $item->product_id }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ number_format($item->price, 2) }}</td>
                    <td>{{ number_format($item->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            @if ($sale->discount > 0)
                <tr>
                    <td colspan="3" style="text-align: right;">Discount</td>
                    <td>-{{ number_format($sale->discount, 2) }}</td>
                </tr>
            @endif
            @if ($sale->tax_amount > 0)
                <tr>
                    <td colspan="3" style="text-align: right;">Tax</td>
                    <td>{{ number_format($sale->tax_amount, 2) }}</td>
                </tr>
            @endif
            <tr class="total-row">
                <td colspan="3" style="text-align: right;">Total</td>
                <td>{{ number_format($sale->total_amount, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <p>Thank you for your business!</p>
        <p>ClassicPOS</p>
    </div>
</body>
</html>
