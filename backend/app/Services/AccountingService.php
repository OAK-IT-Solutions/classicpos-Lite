<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\OperatingAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountingService
{
    public function createJournalEntry(
        string $branchId,
        string $entryDate,
        string $description,
        array $lines,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $createdBy = null,
    ): JournalEntry {
        $this->validateLines($lines);

        $entryNumber = $this->generateEntryNumber($branchId);

        return DB::transaction(function () use ($branchId, $entryDate, $description, $lines, $referenceType, $referenceId, $createdBy, $entryNumber) {
            $entry = JournalEntry::create([
                'branch_id' => $branchId,
                'entry_number' => $entryNumber,
                'entry_date' => $entryDate,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => $createdBy,
            ]);

            foreach ($lines as $line) {
                $account = $this->resolveAccount($branchId, $line['account_code'] ?? null, $line['account_id'] ?? null);

                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $account->id,
                    'debit_amount' => $line['debit'] ?? 0,
                    'credit_amount' => $line['credit'] ?? 0,
                    'description' => $line['description'] ?? null,
                ]);
            }

            $entry->load('lines', 'lines.account');

            return $entry;
        });
    }

    public function updateOperatingAccountBalance(string $accountId): void
    {
        $operatingAccount = OperatingAccount::findOrFail($accountId);

        $balance = JournalEntryLine::whereHas('journalEntry', function ($q) use ($operatingAccount) {
            $q->where('branch_id', $operatingAccount->branch_id);
        })
        ->where('account_id', $operatingAccount->account_id)
        ->selectRaw('COALESCE(SUM(debit_amount), 0) - COALESCE(SUM(credit_amount), 0) as net')
        ->value('net');

        $operatingAccount->update([
            'current_balance' => $operatingAccount->opening_balance + ($balance ?? 0),
        ]);
    }

    public function getAccountBalance(string $branchId, string $accountId): float
    {
        $account = ChartOfAccount::findOrFail($accountId);

        $totalDebits = JournalEntryLine::whereHas('journalEntry', function ($q) use ($branchId) {
            $q->where('branch_id', $branchId);
        })
        ->where('account_id', $accountId)
        ->sum('debit_amount');

        $totalCredits = JournalEntryLine::whereHas('journalEntry', function ($q) use ($branchId) {
            $q->where('branch_id', $branchId);
        })
        ->where('account_id', $accountId)
        ->sum('credit_amount');

        if ($account->normal_balance === 'debit') {
            return (float) ($totalDebits - $totalCredits);
        }

        return (float) ($totalCredits - $totalDebits);
    }

    private function validateLines(array $lines): void
    {
        if (empty($lines)) {
            throw new \InvalidArgumentException('Journal entry must have at least one line.');
        }

        $totalDebits = 0;
        $totalCredits = 0;

        foreach ($lines as $i => $line) {
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);

            if ($debit < 0 || $credit < 0) {
                throw new \InvalidArgumentException("Line {$i}: debit and credit amounts cannot be negative.");
            }

            if ($debit > 0 && $credit > 0) {
                throw new \InvalidArgumentException("Line {$i}: a line cannot have both debit and credit amounts.");
            }

            if ($debit === 0.0 && $credit === 0.0) {
                throw new \InvalidArgumentException("Line {$i}: a line must have either a debit or credit amount.");
            }

            $totalDebits += $debit;
            $totalCredits += $credit;
        }

        if (abs($totalDebits - $totalCredits) > 0.01) {
            throw new \InvalidArgumentException(
                "Journal entry is not balanced. Total debits: {$totalDebits}, Total credits: {$totalCredits}."
            );
        }
    }

    private function resolveAccount(string $branchId, ?string $code = null, ?string $accountId = null): ChartOfAccount
    {
        if ($accountId) {
            return ChartOfAccount::where('branch_id', $branchId)
                ->where('id', $accountId)
                ->where('is_active', true)
                ->firstOrFail();
        }

        if ($code) {
            return ChartOfAccount::where('branch_id', $branchId)
                ->where('code', $code)
                ->where('is_active', true)
                ->firstOrFail();
        }

        throw new \InvalidArgumentException('Either account_code or account_id must be provided.');
    }

    private function generateEntryNumber(string $branchId): string
    {
        $prefix = 'JE-' . now()->format('Ym') . '-';
        $lastEntry = JournalEntry::where('branch_id', $branchId)
            ->where('entry_number', 'like', $prefix . '%')
            ->orderBy('entry_number', 'desc')
            ->first();

        if ($lastEntry) {
            $lastNumber = (int) substr($lastEntry->entry_number, strlen($prefix));
            $nextNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
        } else {
            $nextNumber = '00001';
        }

        return $prefix . $nextNumber;
    }
}
