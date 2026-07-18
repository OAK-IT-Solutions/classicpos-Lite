<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\DocumentPayment;
use App\Models\OperatingAccount;
use App\Services\AccountingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class DocumentController extends Controller
{
    public function __construct(
        protected AccountingService $accountingService,
    ) {}
    #[OA\Get(path: "/documents", tags: ["Documents"], summary: "List documents", responses: [new OA\Response(response: 200, description: "Paginated documents")])]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchIds = $user->branches()->pluck('branches.id')->toArray();
        if (empty($branchIds)) $branchIds = [$user->branch_id];

        $query = Document::with(['customer:id,name', 'branch:id,name'])
            ->whereIn('branch_id', $branchIds)
            ->orderByDesc('created_at');

        if ($request->filled('type')) $query->where('document_type', $request->type);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('document_number', 'like', "%{$s}%")
                  ->orWhereHas('customer', fn($cq) => $cq->where('name', 'like', "%{$s}%"));
            });
        }

        return response()->json($query->paginate($request->per_page ?? 20));
    }

    #[OA\Post(path: "/documents", tags: ["Documents"], summary: "Create document", responses: [new OA\Response(response: 201, description: "Document created")])]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_type' => 'required|in:quote,invoice',
            'customer_id' => 'nullable|uuid|exists:customers,id',
            'issue_date' => 'required|date',
            'expiry_date' => 'nullable|date|after_or_equal:issue_date',
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'notes' => 'nullable|string|max:2000',
            'terms_conditions' => 'nullable|string|max:5000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|uuid|exists:products,id',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $prefix = $validated['document_type'] === 'quote' ? 'QTE' : 'INV';
            $number = $prefix . '-' . strtoupper(substr((string) Str::uuid(), 0, 8));

            $subtotal = 0;
            $totalTax = 0;
            $items = [];

            foreach ($validated['items'] as $item) {
                $lineTotal = $item['quantity'] * $item['unit_price'];
                $lineDiscount = ($item['discount'] ?? 0);
                $lineAfterDiscount = $lineTotal - $lineDiscount;
                $taxRate = $item['tax_rate'] ?? 0;
                $lineTax = $lineAfterDiscount * ($taxRate / 100);
                $lineFinalTotal = $lineAfterDiscount + $lineTax;

                $subtotal += $lineTotal;
                $totalTax += $lineTax;
                $items[] = [
                    'id' => (string) Str::uuid(),
                    'product_id' => $item['product_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $lineDiscount,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $lineTax,
                    'total' => $lineFinalTotal,
                ];
            }

            $totalAmount = $subtotal + $totalTax;
            $status = $validated['document_type'] === 'quote' ? 'draft' : 'unpaid';

            $document = Document::create([
                'id' => (string) Str::uuid(),
                'document_number' => $number,
                'document_type' => $validated['document_type'],
                'status' => $status,
                'customer_id' => $validated['customer_id'] ?? null,
                'branch_id' => $request->user()->branch_id,
                'issue_date' => $validated['issue_date'],
                'expiry_date' => $validated['expiry_date'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'subtotal' => $subtotal,
                'discount' => 0,
                'tax_amount' => $totalTax,
                'total_amount' => $totalAmount,
                'notes' => $validated['notes'] ?? null,
                'terms_conditions' => $validated['terms_conditions'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($items as $item) {
                $item['document_id'] = $document->id;
                DocumentItem::create($item);
            }

            $document->load(['customer:id,name', 'items']);

            try {
                $this->createInvoiceJournalEntry($document);
            } catch (\Exception $e) {
                Log::error('Failed to create invoice journal entry: ' . $e->getMessage());
            }

            return response()->json(['data' => $document], 201);
        });
    }

    private function createPaymentJournalEntry(DocumentPayment $payment, Document $document): void
    {
        $defaultOp = OperatingAccount::where('branch_id', $document->branch_id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
        $arAccount = ChartOfAccount::where('branch_id', $document->branch_id)
            ->where('code', '1200')->where('is_active', true)->first();

        if (!$defaultOp || !$arAccount) return;

        $entry = $this->accountingService->createJournalEntry(
            branchId: $document->branch_id,
            entryDate: $payment->payment_date,
            description: "Payment for {$document->document_number}",
            lines: [
                ['account_id' => $defaultOp->account_id, 'debit' => $payment->amount, 'credit' => 0],
                ['account_id' => $arAccount->id, 'debit' => 0, 'credit' => $payment->amount],
            ],
            referenceType: 'invoice_payment',
            referenceId: $payment->id,
            createdBy: $document->created_by,
        );

        $this->accountingService->updateOperatingAccountBalance($defaultOp->id);
    }

    private function createInvoiceJournalEntry(Document $document): void
    {
        if ($document->document_type !== 'invoice') return;
        if ($document->total_amount <= 0) return;

        $revenueAccount = ChartOfAccount::where('branch_id', $document->branch_id)
            ->where('code', '4100')->where('is_active', true)->first();
        $arAccount = ChartOfAccount::where('branch_id', $document->branch_id)
            ->where('code', '1200')->where('is_active', true)->first();
        $taxAccount = ChartOfAccount::where('branch_id', $document->branch_id)
            ->where('code', '2140')->where('is_active', true)->first();

        if (!$arAccount || !$revenueAccount) return;

        $lines = [
            ['account_id' => $arAccount->id, 'debit' => $document->total_amount, 'credit' => 0],
            ['account_id' => $revenueAccount->id, 'debit' => 0, 'credit' => $document->subtotal],
        ];

        if ($document->tax_amount > 0 && $taxAccount) {
            $lines[] = ['account_id' => $taxAccount->id, 'debit' => 0, 'credit' => $document->tax_amount];
        }

        $this->accountingService->createJournalEntry(
            branchId: $document->branch_id,
            entryDate: $document->issue_date,
            description: "Invoice {$document->document_number}",
            lines: $lines,
            referenceType: 'invoice',
            referenceId: $document->id,
            createdBy: $document->created_by,
        );
    }

    public function show(string $id): JsonResponse
    {
        $document = Document::with(['customer', 'branch', 'items.product', 'payments', 'convertedFrom'])->findOrFail($id);
        return response()->json(['data' => $document]);
    }

    public function convertToInvoice(string $id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            $quote = Document::with('items')->findOrFail($id);

            if ($quote->document_type !== 'quote') {
                return response()->json(['error' => ['code' => 'ERR_NOT_A_QUOTE', 'message' => 'Only quotes can be converted to invoices.']], 400);
            }
            if ($quote->status !== 'accepted') {
                return response()->json(['error' => ['code' => 'ERR_QUOTE_NOT_ACCEPTED', 'message' => 'Quote must be accepted before converting to invoice.']], 400);
            }

            $invoice = Document::create([
                'id' => (string) Str::uuid(),
                'document_number' => 'INV-' . strtoupper(substr((string) Str::uuid(), 0, 8)),
                'document_type' => 'invoice',
                'status' => 'unpaid',
                'customer_id' => $quote->customer_id,
                'branch_id' => $quote->branch_id,
                'issue_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(30)->format('Y-m-d'),
                'subtotal' => $quote->subtotal,
                'discount' => $quote->discount,
                'tax_amount' => $quote->tax_amount,
                'total_amount' => $quote->total_amount,
                'notes' => $quote->notes,
                'terms_conditions' => $quote->terms_conditions,
                'converted_from_id' => $quote->id,
                'created_by' => $quote->created_by,
            ]);

            foreach ($quote->items as $item) {
                DocumentItem::create([
                    'document_id' => $invoice->id,
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount' => $item->discount,
                    'tax_rate' => $item->tax_rate,
                    'tax_amount' => $item->tax_amount,
                    'total' => $item->total,
                ]);
            }

            $quote->update(['status' => 'converted']);
            $invoice->load(['customer:id,name', 'items']);

            return response()->json(['data' => $invoice], 201);
        });
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $document = Document::findOrFail($id);

        $status = $request->query('status');

        if (!$status) {
            return response()->json(['error' => ['code' => 'ERR_VALIDATION', 'message' => 'Status is required.']], 422);
        }

        $allowed = $document->document_type === 'quote'
            ? ['draft', 'sent', 'accepted', 'expired']
            : ['unpaid', 'paid', 'cancelled'];

        if (!in_array($status, $allowed)) {
            return response()->json(['error' => ['code' => 'ERR_VALIDATION', 'message' => "Invalid status. Allowed: " . implode(', ', $allowed)]], 422);
        }

        $document->update(['status' => $status]);
        $document->load(['customer:id,name', 'items']);

        return response()->json(['data' => $document]);
    }

    public function recordPayment(Request $request, string $id): JsonResponse
    {
        $document = Document::findOrFail($id);

        if ($document->document_type !== 'invoice') {
            return response()->json(['error' => ['code' => 'ERR_NOT_INVOICE', 'message' => 'Payments can only be recorded on invoices.']], 400);
        }

        $amount = $request->query('amount');
        $method = $request->query('method');
        $reference = $request->query('reference');
        $paymentDate = $request->query('payment_date');
        $notes = $request->query('notes');

        if (!$amount || !$method || !$paymentDate) {
            return response()->json(['error' => ['code' => 'ERR_VALIDATION', 'message' => 'amount, method, and payment_date are required.']], 422);
        }

        return DB::transaction(function () use ($document, $amount, $method, $reference, $paymentDate, $notes) {
            $newPaid = $document->paid_amount + (float) $amount;

            if ($newPaid > $document->total_amount) {
                return response()->json(['error' => ['code' => 'ERR_OVERPAYMENT', 'message' => 'Payment exceeds invoice balance.']], 400);
            }

            $payment = DocumentPayment::create([
                'id' => (string) Str::uuid(),
                'document_id' => $document->id,
                'amount' => (float) $amount,
                'method' => $method,
                'reference' => $reference ?: null,
                'payment_date' => $paymentDate,
                'notes' => $notes ?: null,
            ]);

            $status = $newPaid >= $document->total_amount ? 'paid' : 'partial';
            $document->update(['paid_amount' => $newPaid, 'status' => $status]);
            $document->load(['payments', 'customer:id,name']);

            try {
                $this->createPaymentJournalEntry($payment, $document);
            } catch (\Exception $e) {
                Log::error('Failed to create payment journal entry: ' . $e->getMessage());
            }

            return response()->json(['data' => $document]);
        });
    }

    public function destroy(string $id): JsonResponse
    {
        $document = Document::findOrFail($id);
        $document->delete();
        return response()->json(['message' => 'Document deleted.']);
    }
}
