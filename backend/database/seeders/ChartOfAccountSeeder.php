<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\OperatingAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChartOfAccountSeeder extends Seeder
{
    public function run(): void
    {
        $branchIds = DB::table('branches')->pluck('id');

        foreach ($branchIds as $branchId) {
            $this->seedAccountsForBranch($branchId);
            $this->seedOperatingAccountsForBranch($branchId);
        }
    }

    private function seedAccountsForBranch(string $branchId): void
    {
        $accounts = [
            // ===== ASSETS (1xxx) =====
            // Current Assets (11xx)
            ['code' => '1100', 'name' => 'Cash on Hand',                 'type' => 'asset',     'group' => 'current_asset',        'normal_balance' => 'debit',  'description' => 'Physical cash at the business premises'],
            ['code' => '1110', 'name' => 'Petty Cash',                   'type' => 'asset',     'group' => 'current_asset',        'normal_balance' => 'debit',  'description' => 'Small cash fund for minor expenses'],
            ['code' => '1120', 'name' => 'Checking Account',             'type' => 'asset',     'group' => 'current_asset',        'normal_balance' => 'debit',  'description' => 'Business checking/current account'],
            ['code' => '1130', 'name' => 'Savings Account',              'type' => 'asset',     'group' => 'current_asset',        'normal_balance' => 'debit',  'description' => 'Business savings account'],
            ['code' => '1140', 'name' => 'Mobile Money Account',         'type' => 'asset',     'group' => 'current_asset',        'normal_balance' => 'debit',  'description' => 'Mobile money wallet (M-Pesa, Airtel Money, etc.)'],
            ['code' => '1200', 'name' => 'Accounts Receivable',          'type' => 'asset',     'group' => 'current_asset',        'normal_balance' => 'debit',  'description' => 'Amounts owed by customers for credit sales'],
            ['code' => '1210', 'name' => 'Employee Advances',            'type' => 'asset',     'group' => 'current_asset',        'normal_balance' => 'debit',  'description' => 'Advances paid to employees'],
            ['code' => '1300', 'name' => 'Inventory - Raw Materials',    'type' => 'asset',     'group' => 'current_asset',        'normal_balance' => 'debit',  'description' => 'Raw materials used in production'],
            ['code' => '1310', 'name' => 'Inventory - Work in Progress', 'type' => 'asset',     'group' => 'current_asset',        'normal_balance' => 'debit',  'description' => 'Partially completed goods'],
            ['code' => '1320', 'name' => 'Inventory - Finished Goods',   'type' => 'asset',     'group' => 'current_asset',        'normal_balance' => 'debit',  'description' => 'Completed goods ready for sale'],
            ['code' => '1330', 'name' => 'Inventory - Trading Goods',    'type' => 'asset',     'group' => 'current_asset',        'normal_balance' => 'debit',  'description' => 'Goods purchased for resale'],
            ['code' => '1340', 'name' => 'Inventory - Supplies',         'type' => 'asset',     'group' => 'current_asset',        'normal_balance' => 'debit',  'description' => 'Office and operational supplies'],
            ['code' => '1400', 'name' => 'Prepaid Expenses',             'type' => 'asset',     'group' => 'current_asset',        'normal_balance' => 'debit',  'description' => 'Expenses paid in advance'],
            ['code' => '1410', 'name' => 'Prepaid Insurance',            'type' => 'asset',     'group' => 'current_asset',        'normal_balance' => 'debit',  'description' => 'Insurance premiums paid in advance'],
            ['code' => '1500', 'name' => 'Tax Receivable',               'type' => 'asset',     'group' => 'current_asset',        'normal_balance' => 'debit',  'description' => 'Input VAT and other recoverable taxes'],

            // Fixed Assets (16xx)
            ['code' => '1600', 'name' => 'Land',                         'type' => 'asset',     'group' => 'fixed_asset',          'normal_balance' => 'debit',  'description' => 'Land owned by the business'],
            ['code' => '1610', 'name' => 'Buildings',                    'type' => 'asset',     'group' => 'fixed_asset',          'normal_balance' => 'debit',  'description' => 'Buildings and structures owned by the business'],
            ['code' => '1620', 'name' => 'Furniture & Fixtures',         'type' => 'asset',     'group' => 'fixed_asset',          'normal_balance' => 'debit',  'description' => 'Office furniture, shelving, and fixtures'],
            ['code' => '1630', 'name' => 'Office Equipment',             'type' => 'asset',     'group' => 'fixed_asset',          'normal_balance' => 'debit',  'description' => 'Office machinery and equipment'],
            ['code' => '1640', 'name' => 'Computer Equipment',           'type' => 'asset',     'group' => 'fixed_asset',          'normal_balance' => 'debit',  'description' => 'Computers, servers, and IT hardware'],
            ['code' => '1650', 'name' => 'Motor Vehicles',               'type' => 'asset',     'group' => 'fixed_asset',          'normal_balance' => 'debit',  'description' => 'Company vehicles'],
            ['code' => '1660', 'name' => 'Leasehold Improvements',       'type' => 'asset',     'group' => 'fixed_asset',          'normal_balance' => 'debit',  'description' => 'Improvements to leased premises'],
            ['code' => '1670', 'name' => 'Machinery & Equipment',        'type' => 'asset',     'group' => 'fixed_asset',          'normal_balance' => 'debit',  'description' => 'Production machinery and heavy equipment'],

            // Accumulated Depreciation (17xx)
            ['code' => '1700', 'name' => 'Accum. Depreciation - Buildings',   'type' => 'asset', 'group' => 'accum_depreciation', 'normal_balance' => 'credit', 'description' => 'Accumulated depreciation on buildings'],
            ['code' => '1710', 'name' => 'Accum. Depreciation - Furniture',   'type' => 'asset', 'group' => 'accum_depreciation', 'normal_balance' => 'credit', 'description' => 'Accumulated depreciation on furniture'],
            ['code' => '1720', 'name' => 'Accum. Depreciation - Equipment',   'type' => 'asset', 'group' => 'accum_depreciation', 'normal_balance' => 'credit', 'description' => 'Accumulated depreciation on equipment'],
            ['code' => '1730', 'name' => 'Accum. Depreciation - Vehicles',    'type' => 'asset', 'group' => 'accum_depreciation', 'normal_balance' => 'credit', 'description' => 'Accumulated depreciation on vehicles'],

            // Other Assets (18xx)
            ['code' => '1800', 'name' => 'Security Deposits',            'type' => 'asset',     'group' => 'other_asset',          'normal_balance' => 'debit',  'description' => 'Refundable deposits (rent, utilities, etc.)'],
            ['code' => '1810', 'name' => 'Goodwill',                     'type' => 'asset',     'group' => 'other_asset',          'normal_balance' => 'debit',  'description' => 'Goodwill acquired in business acquisitions'],
            ['code' => '1820', 'name' => 'Intangible Assets',            'type' => 'asset',     'group' => 'other_asset',          'normal_balance' => 'debit',  'description' => 'Patents, trademarks, copyrights, licenses'],

            // ===== LIABILITIES (2xxx) =====
            // Current Liabilities (21xx)
            ['code' => '2100', 'name' => 'Accounts Payable',             'type' => 'liability', 'group' => 'current_liability',    'normal_balance' => 'credit', 'description' => 'Amounts owed to suppliers'],
            ['code' => '2110', 'name' => 'Accrued Expenses',             'type' => 'liability', 'group' => 'current_liability',    'normal_balance' => 'credit', 'description' => 'Expenses incurred but not yet paid'],
            ['code' => '2120', 'name' => 'Accrued Salaries & Wages',     'type' => 'liability', 'group' => 'current_liability',    'normal_balance' => 'credit', 'description' => 'Salaries earned but not yet paid'],
            ['code' => '2130', 'name' => 'Accrued Taxes Payable',        'type' => 'liability', 'group' => 'current_liability',    'normal_balance' => 'credit', 'description' => 'Taxes incurred but not yet remitted'],
            ['code' => '2140', 'name' => 'Sales Tax Payable',            'type' => 'liability', 'group' => 'current_liability',    'normal_balance' => 'credit', 'description' => 'Output VAT/sales tax collected from customers'],
            ['code' => '2150', 'name' => 'Withholding Tax Payable',      'type' => 'liability', 'group' => 'current_liability',    'normal_balance' => 'credit', 'description' => 'Withholding tax to be remitted to tax authority'],
            ['code' => '2160', 'name' => 'Customer Deposits',            'type' => 'liability', 'group' => 'current_liability',    'normal_balance' => 'credit', 'description' => 'Advance payments from customers'],
            ['code' => '2170', 'name' => 'Short-term Loans',             'type' => 'liability', 'group' => 'current_liability',    'normal_balance' => 'credit', 'description' => 'Short-term borrowings due within 12 months'],
            ['code' => '2200', 'name' => 'Credit Cards Payable',         'type' => 'liability', 'group' => 'current_liability',    'normal_balance' => 'credit', 'description' => 'Outstanding credit card balances'],
            ['code' => '2210', 'name' => 'Gift Cards / Vouchers Payable','type' => 'liability', 'group' => 'current_liability',    'normal_balance' => 'credit', 'description' => 'Outstanding gift card and voucher liabilities'],

            // Long-term Liabilities (23xx)
            ['code' => '2300', 'name' => 'Long-term Loans',              'type' => 'liability', 'group' => 'long_term_liability',  'normal_balance' => 'credit', 'description' => 'Long-term borrowings due after 12 months'],
            ['code' => '2310' , 'name' => 'Bank Loans',                  'type' => 'liability', 'group' => 'long_term_liability',  'normal_balance' => 'credit', 'description' => 'Bank loans and overdrafts'],
            ['code' => '2320', 'name' => 'Mortgage Payable',             'type' => 'liability', 'group' => 'long_term_liability',  'normal_balance' => 'credit', 'description' => 'Mortgage loans secured by property'],
            ['code' => '2330', 'name' => 'Shareholder Loans',            'type' => 'liability', 'group' => 'long_term_liability',  'normal_balance' => 'credit', 'description' => 'Loans from shareholders'],

            // ===== EQUITY (3xxx) =====
            ['code' => '3100', 'name' => 'Owner\'s Capital',             'type' => 'equity',    'group' => 'owner_equity',        'normal_balance' => 'credit', 'description' => 'Owner\'s initial and additional capital contributions'],
            ['code' => '3110', 'name' => 'Owner\'s Drawings',            'type' => 'equity',    'group' => 'owner_equity',        'normal_balance' => 'debit',  'description' => 'Owner\'s personal withdrawals from the business'],
            ['code' => '3200', 'name' => 'Retained Earnings',            'type' => 'equity',    'group' => 'retained_earnings',   'normal_balance' => 'credit', 'description' => 'Accumulated earnings retained in the business'],
            ['code' => '3300', 'name' => 'Current Year Earnings',        'type' => 'equity',    'group' => 'retained_earnings',   'normal_balance' => 'credit', 'description' => 'Net profit/loss for the current fiscal year'],
            ['code' => '3400', 'name' => 'Share Capital',                'type' => 'equity',    'group' => 'owner_equity',        'normal_balance' => 'credit', 'description' => 'Capital received from share issuance'],
            ['code' => '3410', 'name' => 'Share Premium',                'type' => 'equity',    'group' => 'owner_equity',        'normal_balance' => 'credit', 'description' => 'Amount received above par value of shares'],

            // ===== REVENUE (4xxx) =====
            // Operating Revenue (41xx)
            ['code' => '4100', 'name' => 'Sales Revenue - Products',     'type' => 'revenue',   'group' => 'operating_revenue',   'normal_balance' => 'credit', 'description' => 'Revenue from product sales'],
            ['code' => '4110', 'name' => 'Sales Revenue - Services',     'type' => 'revenue',   'group' => 'operating_revenue',   'normal_balance' => 'credit', 'description' => 'Revenue from services rendered'],
            ['code' => '4120', 'name' => 'Sales Revenue - Digital Goods','type' => 'revenue',   'group' => 'operating_revenue',   'normal_balance' => 'credit', 'description' => 'Revenue from digital product sales'],
            ['code' => '4200', 'name' => 'Service Charges',              'type' => 'revenue',   'group' => 'operating_revenue',   'normal_balance' => 'credit', 'description' => 'Service fees and charges'],
            ['code' => '4210', 'name' => 'Delivery & Shipping Income',   'type' => 'revenue',   'group' => 'operating_revenue',   'normal_balance' => 'credit', 'description' => 'Revenue from delivery and shipping services'],

            // Contra-Revenue (43xx)
            ['code' => '4300', 'name' => 'Sales Discounts',              'type' => 'revenue',   'group' => 'contra_revenue',      'normal_balance' => 'debit',  'description' => 'Discounts allowed to customers'],
            ['code' => '4310', 'name' => 'Sales Returns & Allowances',   'type' => 'revenue',   'group' => 'contra_revenue',      'normal_balance' => 'debit',  'description' => 'Returns and allowances granted to customers'],

            // Other Revenue (44xx)
            ['code' => '4400', 'name' => 'Interest Income',              'type' => 'revenue',   'group' => 'other_revenue',       'normal_balance' => 'credit', 'description' => 'Interest earned on bank deposits and investments'],
            ['code' => '4410', 'name' => 'Rental Income',                'type' => 'revenue',   'group' => 'other_revenue',       'normal_balance' => 'credit', 'description' => 'Income from property rental'],
            ['code' => '4420', 'name' => 'Commission Income',            'type' => 'revenue',   'group' => 'other_revenue',       'normal_balance' => 'credit', 'description' => 'Commission earned from third-party sales'],
            ['code' => '4430', 'name' => 'Other Income',                 'type' => 'revenue',   'group' => 'other_revenue',       'normal_balance' => 'credit', 'description' => 'Miscellaneous non-operating income'],

            // ===== EXPENSES (5xxx-7xxx) =====
            // Cost of Goods Sold (51xx)
            ['code' => '5100', 'name' => 'COGS - Products',              'type' => 'expense',   'group' => 'cogs',                'normal_balance' => 'debit',  'description' => 'Cost of products sold'],
            ['code' => '5110', 'name' => 'COGS - Services',              'type' => 'expense',   'group' => 'cogs',                'normal_balance' => 'debit',  'description' => 'Direct cost of services delivered'],
            ['code' => '5120' , 'name' => 'COGS - Digital Goods',        'type' => 'expense',   'group' => 'cogs',                'normal_balance' => 'debit',  'description' => 'Cost of digital goods sold'],
            ['code' => '5130', 'name' => 'Freight & Shipping Costs',     'type' => 'expense',   'group' => 'cogs',                'normal_balance' => 'debit',  'description' => 'Freight and shipping costs on purchases'],
            ['code' => '5140', 'name' => 'Inventory Adjustments',        'type' => 'expense',   'group' => 'cogs',                'normal_balance' => 'debit',  'description' => 'Write-offs, shrinkage, and inventory adjustments'],

            // Operating Expenses - Occupancy (61xx)
            ['code' => '6100', 'name' => 'Rent Expense',                 'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Rent for business premises'],
            ['code' => '6110', 'name' => 'Lease Expense',                'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Equipment and vehicle lease payments'],

            // Utilities (62xx)
            ['code' => '6200', 'name' => 'Utilities Expense',            'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'General utilities'],
            ['code' => '6210', 'name' => 'Electricity',                  'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Electricity bills'],
            ['code' => '6220', 'name' => 'Water & Sewer',                'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Water and sewer bills'],
            ['code' => '6230', 'name' => 'Internet & Telephone',         'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Internet, phone, and communication costs'],

            // Personnel (63xx)
            ['code' => '6300', 'name' => 'Salaries & Wages',             'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Employee salaries and wages'],
            ['code' => '6310', 'name' => 'Employee Benefits',            'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Health insurance, pension, and other benefits'],
            ['code' => '6320', 'name' => 'Payroll Taxes',                'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Employer payroll taxes and contributions'],
            ['code' => '6330', 'name' => 'Commission Expense',           'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Sales commissions paid to staff'],

            // Office & Administrative (64xx)
            ['code' => '6400', 'name' => 'Office Supplies Expense',      'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Office supplies and stationery'],
            ['code' => '6410', 'name' => 'Printing & Stationery',        'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Printing, copying, and stationery costs'],

            // Maintenance (65xx)
            ['code' => '6500', 'name' => 'Maintenance & Repairs',        'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'General maintenance and repair costs'],
            ['code' => '6510', 'name' => 'Building Maintenance',         'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Building and premises maintenance'],
            ['code' => '6520', 'name' => 'Equipment Maintenance',        'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Equipment servicing and repair'],

            // Marketing (66xx)
            ['code' => '6600', 'name' => 'Marketing & Advertising',      'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Advertising and marketing costs'],
            ['code' => '6610', 'name' => 'Promotional Expenses',         'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Promotional events and materials'],
            ['code' => '6620', 'name' => 'Online Advertising',           'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Digital and social media advertising'],

            // Transportation (67xx)
            ['code' => '6700', 'name' => 'Transportation & Travel',       'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Business travel and transportation costs'],
            ['code' => '6710', 'name' => 'Fuel Expense',                  'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Vehicle fuel costs'],
            ['code' => '6720', 'name' => 'Travel & Accommodation',      'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Travel and hotel accommodation'],

            // Insurance (68xx)
            ['code' => '6800', 'name' => 'Insurance Expense',             'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'General insurance costs'],
            ['code' => '6810', 'name' => 'Health Insurance',              'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Employee health insurance premiums'],

            // Professional Services (69xx)
            ['code' => '6900', 'name' => 'Professional Fees',             'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Professional service fees'],
            ['code' => '6910', 'name' => 'Legal Fees',                    'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Legal and attorney fees'],
            ['code' => '6920', 'name' => 'Accounting & Audit Fees',       'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Accounting, bookkeeping, and audit fees'],
            ['code' => '6930', 'name' => 'Consulting Fees',               'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Management and IT consulting fees'],

            // Administrative (70xx)
            ['code' => '7000', 'name' => 'Bank Charges',                  'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Bank service charges and fees'],
            ['code' => '7010', 'name' => 'License & Permit Fees',         'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Business licenses and permits'],
            ['code' => '7020', 'name' => 'Taxes & Licenses',              'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Business taxes and regulatory fees'],
            ['code' => '7030', 'name' => 'Subscriptions & Memberships',   'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Software subscriptions and professional memberships'],
            ['code' => '7040', 'name' => 'Training & Development',        'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Employee training and professional development'],

            // Depreciation (71xx)
            ['code' => '7100', 'name' => 'Depreciation Expense',          'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Depreciation of fixed assets'],
            ['code' => '7110', 'name' => 'Amortization Expense',          'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Amortization of intangible assets'],

            // Other Expenses (72xx)
            ['code' => '7200', 'name' => 'Loss on Disposal of Assets',    'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Loss on sale or disposal of fixed assets'],
            ['code' => '7210', 'name' => 'Foreign Exchange Loss',         'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Losses from foreign currency fluctuations'],
            ['code' => '7220', 'name' => 'Miscellaneous Expenses',        'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Small miscellaneous expenses'],
            ['code' => '7230', 'name' => 'Penalties & Fines',             'type' => 'expense',   'group' => 'operating_expense',   'normal_balance' => 'debit',  'description' => 'Regulatory penalties and late fees'],
        ];

        $now = now();

        foreach ($accounts as $account) {
            ChartOfAccount::firstOrCreate(
                [
                    'branch_id' => $branchId,
                    'code' => $account['code'],
                ],
                [
                    'id' => (string) Str::uuid(),
                    'branch_id' => $branchId,
                    'code' => $account['code'],
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'group' => $account['group'],
                    'normal_balance' => $account['normal_balance'],
                    'description' => $account['description'],
                    'is_system' => true,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function seedOperatingAccountsForBranch(string $branchId): void
    {
        $cashAccount = ChartOfAccount::where('branch_id', $branchId)->where('code', '1100')->first();
        $pettyCashAccount = ChartOfAccount::where('branch_id', $branchId)->where('code', '1110')->first();
        $bankAccount = ChartOfAccount::where('branch_id', $branchId)->where('code', '1120')->first();

        $operatingAccounts = [
            [
                'id' => (string) Str::uuid(),
                'branch_id' => $branchId,
                'account_id' => $cashAccount?->id ?? '',
                'name' => 'Main Cash Drawer',
                'type' => 'cash',
                'account_number' => null,
                'bank_name' => null,
                'currency' => 'KES',
                'is_default' => true,
                'opening_balance' => 0,
                'current_balance' => 0,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'id' => (string) Str::uuid(),
                'branch_id' => $branchId,
                'account_id' => $pettyCashAccount?->id ?? '',
                'name' => 'Petty Cash Fund',
                'type' => 'petty_cash',
                'account_number' => null,
                'bank_name' => null,
                'currency' => 'KES',
                'is_default' => false,
                'opening_balance' => 0,
                'current_balance' => 0,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'id' => (string) Str::uuid(),
                'branch_id' => $branchId,
                'account_id' => $bankAccount?->id ?? '',
                'name' => 'Business Bank Account',
                'type' => 'bank',
                'account_number' => null,
                'bank_name' => null,
                'currency' => 'KES',
                'is_default' => false,
                'opening_balance' => 0,
                'current_balance' => 0,
                'is_system' => true,
                'is_active' => true,
            ],
        ];

        foreach ($operatingAccounts as $oa) {
            if (empty($oa['account_id'])) continue;

            OperatingAccount::firstOrCreate(
                [
                    'branch_id' => $branchId,
                    'account_id' => $oa['account_id'],
                ],
                $oa
            );
        }
    }
}
