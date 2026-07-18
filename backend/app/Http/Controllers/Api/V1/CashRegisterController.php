<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CashRegisterShift;
use App\Models\ChartOfAccount;
use App\Models\OperatingAccount;
use App\Models\Sale;
use App\Services\AccountingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class CashRegisterController extends Controller
{
    public function __construct(
        protected AccountingService $accountingService,
    ) {}
    #[OA\Get(path: "/cash-register/status", tags: ["Cash Register"], summary: "Get status", responses: [new OA\Response(response: 200, description: "Current shift")])]
    public function status(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;

        $openShift = CashRegisterShift::where('branch_id', $branchId)
            ->where('status', 'open')
            ->with('user:id,name')
            ->first();

        return response()->json(['data' => $openShift]);
    }

    #[OA\Post(path: "/cash-register/open", tags: ["Cash Register"], summary: "Open shift", responses: [new OA\Response(response: 201, description: "Shift opened")])]
    public function open(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $user->branch_id;

        $existing = CashRegisterShift::where('branch_id', $branchId)
            ->where('status', 'open')
            ->first();

        if ($existing) {
            return response()->json([
                'error' => ['code' => 'ERR_SHIFT_OPEN', 'message' => 'An open shift already exists for this branch.'],
            ], 400);
        }

        $password = $request->input('password');
        if (!$password || !Hash::check($password, $user->password)) {
            return response()->json([
                'error' => ['code' => 'ERR_INVALID_PASSWORD', 'message' => 'Password is required to open the register.'],
            ], 403);
        }

        $openingBalance = $request->input('opening_balance');

        if (!$openingBalance || $openingBalance < 0) {
            return response()->json([
                'error' => ['code' => 'ERR_VALIDATION', 'message' => 'Opening balance is required and must be >= 0.'],
            ], 422);
        }

        $shift = CashRegisterShift::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'branch_id' => $branchId,
            'opened_at' => now(),
            'opening_balance' => (float) $openingBalance,
            'cash_sales' => 0,
            'expected_balance' => (float) $openingBalance,
            'status' => 'open',
        ]);

        $shift->load('user:id,name');

        return response()->json(['data' => $shift], 201);
    }

    #[OA\Post(path: "/cash-register/close", tags: ["Cash Register"], summary: "Close shift", responses: [new OA\Response(response: 200, description: "Shift closed")])]
    public function close(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $password = $request->input('password');
        if (!$password || !Hash::check($password, $user->password)) {
            return response()->json([
                'error' => ['code' => 'ERR_INVALID_PASSWORD', 'message' => 'Password is required to close the register.'],
            ], 403);
        }

        $shift = CashRegisterShift::findOrFail($id);

        if ($shift->status !== 'open') {
            return response()->json([
                'error' => ['code' => 'ERR_SHIFT_CLOSED', 'message' => 'Shift is already closed.'],
            ], 400);
        }

        $actualBalance = $request->input('actual_balance');
        $notes = $request->input('notes');

        if ($actualBalance === null || $actualBalance < 0) {
            return response()->json([
                'error' => ['code' => 'ERR_VALIDATION', 'message' => 'Actual balance is required and must be >= 0.'],
            ], 422);
        }

        $actualBalance = (float) $actualBalance;

        $cashSales = Sale::where('branch_id', $shift->branch_id)
            ->where('created_at', '>=', $shift->opened_at)
            ->where('payment_method', 'cash')
            ->whereIn('status', ['completed', 'pending_sync'])
            ->sum('total_amount');

        $expectedBalance = $shift->opening_balance + $cashSales;
        $variance = $actualBalance - $expectedBalance;
        $revenueToBank = $actualBalance - $shift->opening_balance;

        $shift->update([
            'cash_sales' => $cashSales,
            'expected_balance' => $expectedBalance,
            'actual_balance' => $actualBalance,
            'variance' => $variance,
            'revenue_to_bank' => $revenueToBank,
            'closed_at' => now(),
            'status' => 'closed',
            'notes' => $notes,
        ]);

        $shift->load('user:id,name');

        try {
            $this->createCloseShiftJournalEntry($shift);
        } catch (\Exception $e) {
            Log::error('Failed to create shift close journal entry: ' . $e->getMessage());
        }

        return response()->json(['data' => $shift]);
    }

    private function createCloseShiftJournalEntry(CashRegisterShift $shift): void
    {
        if ($shift->revenue_to_bank <= 0 && ($shift->variance ?? 0) == 0) {
            return;
        }

        $lines = [];
        $cashDrawer = OperatingAccount::where('branch_id', $shift->branch_id)
            ->where('type', 'cash')
            ->where('is_active', true)
            ->first();

        if (!$cashDrawer) return;

        $bankAccount = OperatingAccount::where('branch_id', $shift->branch_id)
            ->where('type', 'bank')
            ->where('is_active', true)
            ->first();

        if ($shift->revenue_to_bank > 0 && $bankAccount) {
            $lines[] = ['account_id' => $bankAccount->account_id, 'debit' => $shift->revenue_to_bank, 'credit' => 0];
            $lines[] = ['account_id' => $cashDrawer->account_id, 'debit' => 0, 'credit' => $shift->revenue_to_bank];
        }

        $variance = (float) ($shift->variance ?? 0);
        if ($variance != 0) {
            $miscExpense = ChartOfAccount::where('branch_id', $shift->branch_id)
                ->where('code', '7220')
                ->first();

            if ($miscExpense) {
                if ($variance > 0) {
                    $lines[] = ['account_id' => $cashDrawer->account_id, 'debit' => $variance, 'credit' => 0];
                    $lines[] = ['account_id' => $miscExpense->id, 'debit' => 0, 'credit' => $variance];
                } else {
                    $lines[] = ['account_id' => $miscExpense->id, 'debit' => abs($variance), 'credit' => 0];
                    $lines[] = ['account_id' => $cashDrawer->account_id, 'debit' => 0, 'credit' => abs($variance)];
                }
            }
        }

        if (empty($lines)) return;

        $this->accountingService->createJournalEntry(
            branchId: $shift->branch_id,
            entryDate: now()->format('Y-m-d'),
            description: "Cash register shift close (opened: {$shift->opened_at->format('Y-m-d H:i')})",
            lines: $lines,
            referenceType: 'shift_close',
            referenceId: $shift->id,
            createdBy: $shift->user_id,
        );

        if ($bankAccount) {
            $this->accountingService->updateOperatingAccountBalance($bankAccount->id);
        }
        $this->accountingService->updateOperatingAccountBalance($cashDrawer->id);
    }

    public function shifts(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchIds = $user->branches()->pluck('branches.id')->toArray();
        if (empty($branchIds)) {
            $branchIds = [$user->branch_id];
        }

        $shifts = CashRegisterShift::whereIn('branch_id', $branchIds)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 50);

        return response()->json($shifts);
    }

    public function showShift(string $id): JsonResponse
    {
        $shift = CashRegisterShift::with('user:id,name', 'branch:id,name')
            ->findOrFail($id);

        return response()->json(['data' => $shift]);
    }
}
