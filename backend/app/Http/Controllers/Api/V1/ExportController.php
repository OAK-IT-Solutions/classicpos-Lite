<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExportController extends Controller
{
    /**
     * Export sales to CSV
     */
    public function salesCsv(Request $request): StreamedResponse
    {
        $from = $request->get('from');
        $to = $request->get('to');

        $query = Sale::with(['customer', 'branch'])
            ->orderByDesc('created_at');

        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="classicpos-sales-' . date('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');
            
            fputcsv($handle, [
                'Invoice #', 'Date', 'Customer', 'Branch',
                'Subtotal', 'Tax', 'Total', 'Payment Method',
                'Status', 'Notes',
            ]);

            $query->chunk(500, function ($sales) use ($handle) {
                foreach ($sales as $sale) {
                    fputcsv($handle, [
                        $sale->invoice_number,
                        $sale->created_at?->format('Y-m-d H:i'),
                        $sale->customer?->name ?? 'Walk-in',
                        $sale->branch?->name ?? '',
                        number_format($sale->subtotal ?? 0, 2),
                        number_format($sale->tax_amount ?? 0, 2),
                        number_format($sale->total_amount ?? 0, 2),
                        $sale->payment_method ?? '',
                        $sale->status,
                        $sale->notes ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Export products to CSV
     */
    public function productsCsv(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="classicpos-products-' . date('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            
            fputcsv($handle, [
                'SKU', 'Name', 'Category', 'Price', 'Cost',
                'Stock', 'Min Stock', 'Status',
            ]);

            DB::table('products')
                ->join('categories', 'products.category_id', '=', 'categories.id')
                ->leftJoin('inventory', 'products.id', '=', 'inventory.product_id')
                ->select('products.sku', 'products.name', 'categories.name as category',
                         'products.price', 'products.cost_price',
                         DB::raw('COALESCE(inventory.quantity, 0) as stock'),
                         'products.min_stock', 'products.is_active')
                ->orderBy('products.name')
                ->chunk(500, function ($products) use ($handle) {
                    foreach ($products as $p) {
                        fputcsv($handle, [
                            $p->sku, $p->name, $p->category,
                            number_format($p->price, 2), number_format($p->cost_price, 2),
                            $p->stock, $p->min_stock,
                            $p->is_active ? 'Active' : 'Inactive',
                        ]);
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Backup SQLite database (copy to user-selected location)
     */
    public function backupDatabase(Request $request): \Illuminate\Http\JsonResponse
    {
        $dbPath = config('database.connections.sqlite.database');
        
        if (!file_exists($dbPath)) {
            return response()->json(['error' => 'Database file not found'], 404);
        }

        $backupDir = Storage::disk('local')->path('backups');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $backupName = 'classicpos-backup-' . date('Y-m-d-His') . '.sqlite';
        $backupPath = $backupDir . '/' . $backupName;

        // Use SQLite backup API for safe copy
        $source = new \SQLite3($dbPath);
        $dest = new \SQLite3($backupPath);
        $source->backup($dest);
        $dest->close();
        $source->close();

        $size = filesize($backupPath);

        return response()->json([
            'path' => $backupPath,
            'name' => $backupName,
            'size' => $size,
            'size_formatted' => $this->formatBytes($size),
            'created_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * List available backups
     */
    public function listBackups(): \Illuminate\Http\JsonResponse
    {
        $backupDir = Storage::disk('local')->path('backups');
        
        if (!is_dir($backupDir)) {
            return response()->json(['backups' => []]);
        }

        $files = glob($backupDir . '/*.sqlite');
        $backups = [];

        foreach ($files as $file) {
            $backups[] = [
                'name' => basename($file),
                'size' => filesize($file),
                'size_formatted' => $this->formatBytes(filesize($file)),
                'created_at' => date('Y-m-d H:i:s', filemtime($file)),
            ];
        }

        usort($backups, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return response()->json(['backups' => $backups]);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }
}
