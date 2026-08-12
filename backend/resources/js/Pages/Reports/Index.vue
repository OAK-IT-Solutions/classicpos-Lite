<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import 'ag-grid-community/styles/ag-grid.css';
import 'ag-grid-community/styles/ag-theme-alpine.css';
import { AgGridVue } from 'ag-grid-vue3';
import ReportChart from '@/Components/ReportChart.vue';
import DateRangePicker from '@/Components/DateRangePicker.vue';
import { useReports, type ReportDef, type DateRange } from '@/composables/useReports';
import { Printer, FileText, FileSpreadsheet, Download, Search } from 'lucide-vue-next';

const reports: ReportDef[] = [
  { key: 'summary', label: 'Sales Summary', description: 'Daily and total sales with revenue', category: 'Financial',
    columns: [{ field: 'metric', header: 'Metric' }, { field: 'value', header: 'Value' }] },
  { key: 'revenue-by-payment', label: 'Revenue by Payment Method', description: 'Revenue grouped by payment type', category: 'Financial',
    columns: [{ field: 'payment_method', header: 'Method' }, { field: 'count', header: 'Count' }, { field: 'revenue', header: 'Revenue' }],
    chartType: 'doughnut' },
  { key: 'daily-revenue', label: 'Daily Revenue', description: 'Revenue trend day by day', category: 'Financial',
    columns: [{ field: 'date', header: 'Date' }, { field: 'revenue', header: 'Revenue' }, { field: 'tax', header: 'Tax' }, { field: 'count', header: 'Orders' }],
    chartType: 'line' },
  { key: 'tax-report', label: 'Tax Report', description: 'VAT/tax collected per sale', category: 'Financial',
    columns: [{ field: 'invoice_number', header: 'Invoice' }, { field: 'created_at', header: 'Date' }, { field: 'total_amount', header: 'Total' }, { field: 'tax_amount', header: 'Tax' }, { field: 'payment_method', header: 'Payment' }],
    chartType: 'bar' },
  { key: 'profit-loss', label: 'Profit & Loss', description: 'Revenue vs cost vs tax summary', category: 'Financial',
    columns: [{ field: 'metric', header: 'Metric' }, { field: 'value', header: 'Value' }] },
  { key: 'cogs', label: 'Cost of Goods Sold', description: 'Daily COGS with margin %', category: 'Financial',
    columns: [{ field: 'date', header: 'Date' }, { field: 'cogs', header: 'COGS' }, { field: 'revenue', header: 'Revenue' }, { field: 'gross_profit', header: 'Gross Profit' }, { field: 'margin_pct', header: 'Margin %' }],
    chartType: 'bar' },
  { key: 'payments-reconciliation', label: 'Payment Reconciliation', description: 'Payment method totals and status', category: 'Financial',
    columns: [{ field: 'payment_method', header: 'Method' }, { field: 'count', header: 'Count' }, { field: 'total', header: 'Total' }, { field: 'completed', header: 'Completed' }, { field: 'voided', header: 'Voided' }],
    chartType: 'bar' },
  { key: 'sales-journal', label: 'Sales Journal', description: 'Line-item detail for bookkeeping', category: 'Financial',
    columns: [{ field: 'date', header: 'Date' }, { field: 'invoice', header: 'Invoice' }, { field: 'product', header: 'Product' }, { field: 'qty', header: 'Qty' }, { field: 'price', header: 'Price' }, { field: 'total', header: 'Total' }, { field: 'payment', header: 'Payment' }, { field: 'branch', header: 'Branch' }] },
  { key: 'trial-balance', label: 'Trial Balance', description: 'All accounts with debit/credit balances', category: 'Financial',
    columns: [{ field: 'code', header: 'Code' }, { field: 'name', header: 'Account' }, { field: 'type', header: 'Type' }, { field: 'debit', header: 'Debit' }, { field: 'credit', header: 'Credit' }] },
  { key: 'balance-sheet', label: 'Balance Sheet', description: 'Assets = Liabilities + Equity', category: 'Financial',
    columns: [{ field: 'section', header: 'Section' }, { field: 'account', header: 'Account' }, { field: 'balance', header: 'Balance' }] },
  { key: 'income-statement', label: 'Income Statement (P&L)', description: 'Revenue - Expenses = Net Income', category: 'Financial',
    columns: [{ field: 'section', header: 'Section' }, { field: 'account', header: 'Account' }, { field: 'balance', header: 'Amount' }] },
  { key: 'general-ledger', label: 'General Ledger', description: 'Detailed journal entry lines', category: 'Financial',
    columns: [{ field: 'entry_number', header: 'Entry #' }, { field: 'entry_date', header: 'Date' }, { field: 'account_code', header: 'Account' }, { field: 'account_name', header: 'Name' }, { field: 'debit_amount', header: 'Debit' }, { field: 'credit_amount', header: 'Credit' }, { field: 'description', header: 'Description' }] },
  { key: 'top-products', label: 'Top Products', description: 'Best selling products by quantity', category: 'Operations',
    columns: [{ field: 'name', header: 'Product' }, { field: 'total_quantity', header: 'Sold' }, { field: 'total_revenue', header: 'Revenue' }],
    chartType: 'bar' },
  { key: 'low-stock', label: 'Low Stock Report', description: 'Products below minimum stock', category: 'Operations',
    columns: [{ field: 'product_name', header: 'Product' }, { field: 'warehouse', header: 'Warehouse' }, { field: 'quantity', header: 'Qty' }, { field: 'min_stock', header: 'Min' }],
    chartType: 'horizontalBar' },
  { key: 'inventory-valuation', label: 'Inventory Valuation', description: 'Stock value by product', category: 'Operations',
    columns: [{ field: 'product_name', header: 'Product' }, { field: 'quantity', header: 'Qty' }, { field: 'price', header: 'Price' }, { field: 'value', header: 'Value' }],
    chartType: 'doughnut' },
  { key: 'inventory-movement', label: 'Inventory Movement Summary', description: 'Stock in/out by product', category: 'Operations',
    columns: [{ field: 'product_name', header: 'Product' }, { field: 'in_qty', header: 'In' }, { field: 'out_qty', header: 'Out' }, { field: 'net', header: 'Net Change' }] },
  { key: 'purchase-orders', label: 'Purchase Orders', description: 'PO status and values', category: 'Operations',
    columns: [{ field: 'po_number', header: 'PO #' }, { field: 'supplier', header: 'Supplier' }, { field: 'status', header: 'Status' }, { field: 'total_amount', header: 'Total' }, { field: 'created_at', header: 'Date' }] },
  { key: 'customer-statement', label: 'Customer Statement', description: 'Customer spend and visit summary', category: 'Customers',
    columns: [{ field: 'name', header: 'Name' }, { field: 'phone', header: 'Phone' }, { field: 'total_spend', header: 'Total Spend' }, { field: 'total_visits', header: 'Visits' }, { field: 'loyalty_points', header: 'Points' }, { field: 'last_purchase', header: 'Last Purchase' }],
    chartType: 'bar' },
  { key: 'loyalty-points', label: 'Loyalty Points Report', description: 'Customer loyalty points', category: 'Customers',
    columns: [{ field: 'name', header: 'Name' }, { field: 'phone', header: 'Phone' }, { field: 'loyalty_points', header: 'Points' }, { field: 'member_level', header: 'Level' }, { field: 'total_spend', header: 'Total Spend' }] },
  { key: 'sales-by-user', label: 'Sales by User', description: 'Sales per cashier', category: 'Employees',
    columns: [{ field: 'user_name', header: 'User' }, { field: 'total_sales', header: 'Sales' }, { field: 'total_revenue', header: 'Revenue' }],
    chartType: 'bar' },
];

const sidebarOpen = ref(true);
const sidebarSearch = ref('');
const activeReport = ref<ReportDef>(reports[0]);
const dateRange = ref<DateRange>({ from: '', to: '' });

const {
  loading, error, rowData, chartLabels, chartData, chartDatasets,
  loadReport, exportCSV, exportPDF, exportExcel,
} = useReports();

const filteredReports = computed(() => {
  if (!sidebarSearch.value) return reports;
  const q = sidebarSearch.value.toLowerCase();
  return reports.filter(r => r.label.toLowerCase().includes(q) || r.category.toLowerCase().includes(q));
});

const categories = computed(() => {
  const cats = new Map<string, ReportDef[]>();
  for (const r of filteredReports.value) {
    if (!cats.has(r.category)) cats.set(r.category, []);
    cats.get(r.category)!.push(r);
  }
  return Array.from(cats.entries());
});

const gridOptions = {
  defaultColDef: { sortable: true, filter: true, resizable: true, flex: 1, minWidth: 100 },
  enableCellTextSelection: true,
  ensureDomOrder: true,
};

async function handleLoadReport(report: ReportDef) {
  activeReport.value = report;
  await loadReport(report, dateRange.value.from && dateRange.value.to ? dateRange.value : undefined);
}

function handleExportCSV() { exportCSV(activeReport.value, rowData.value) }
async function handleExportPDF() { await exportPDF(activeReport.value, rowData.value, activeReport.value.label) }
async function handleExportExcel() { await exportExcel(activeReport.value, rowData.value) }
function handlePrint() { window.print() }

onMounted(() => handleLoadReport(activeReport.value));
</script>

<template>
  <AppLayout>
    <div class="flex gap-6 h-[calc(100vh-5rem)]">
      <!-- Sidebar -->
      <div :class="['flex-shrink-0 bg-surface-raised rounded-xl shadow-sm border border-border-theme overflow-y-auto transition-all flex flex-col', sidebarOpen ? 'w-56' : 'w-0 overflow-hidden']">
        <div class="p-3 border-b border-border-light">
          <div class="relative">
            <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-text-tertiary" />
            <input v-model="sidebarSearch" type="text" placeholder="Search reports..."
              class="w-full pl-8 pr-3 py-1.5 text-xs border border-border-theme rounded-lg bg-surface-alt text-text-secondary placeholder:text-text-tertiary focus:outline-none focus:ring-1 focus:ring-primary" />
          </div>
        </div>
        <div class="p-3 space-y-1 flex-1 overflow-y-auto">
          <div v-for="[cat, reps] in categories" :key="cat">
            <p class="text-xs font-semibold text-text-tertiary uppercase tracking-wide px-2 py-2">{{ cat }}</p>
            <button v-for="r in reps" :key="r.key"
              @click="handleLoadReport(r)"
              class="w-full text-left px-3 py-2 text-sm rounded-lg transition-colors"
              :class="activeReport.key === r.key ? 'bg-primary text-white' : 'text-text-secondary hover:bg-surface-alt'">
              {{ r.label }}
            </button>
          </div>
          <p v-if="!filteredReports.length" class="text-xs text-text-tertiary px-2 py-4 text-center">No reports match</p>
        </div>
      </div>

      <!-- Toggle sidebar -->
      <button @click="sidebarOpen = !sidebarOpen"
        class="flex-shrink-0 p-1.5 mt-2 text-text-tertiary hover:text-text-secondary hover:bg-surface-alt rounded-lg h-fit transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>

      <!-- Main content -->
      <div class="flex-1 min-w-0 overflow-y-auto space-y-4 print-area">
        <div class="flex items-center justify-between print:hidden">
          <div>
            <h1 class="text-xl font-bold text-text-theme">{{ activeReport.label }}</h1>
            <p class="text-sm text-text-tertiary">{{ activeReport.description }}</p>
          </div>
          <div class="flex items-center gap-2">
            <DateRangePicker v-model="dateRange" />
            <button @click="handleExportCSV" title="Export CSV"
              class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors">
              <Download class="w-4 h-4" />
            </button>
            <button @click="handleExportPDF" title="Export PDF"
              class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors">
              <FileText class="w-4 h-4" />
            </button>
            <button @click="handleExportExcel" title="Export Excel"
              class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors">
              <FileSpreadsheet class="w-4 h-4" />
            </button>
            <button @click="handlePrint" title="Print"
              class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors">
              <Printer class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- Print header (visible only when printing) -->
        <div class="hidden print:block mb-4">
          <h1 class="text-2xl font-bold">{{ activeReport.label }}</h1>
          <p class="text-sm text-gray-500">Generated: {{ new Date().toLocaleDateString() }} {{ new Date().toLocaleTimeString() }}</p>
          <hr class="my-2" />
        </div>

        <div v-if="loading" class="text-center py-12 text-text-tertiary">
          <div class="inline-block animate-spin w-6 h-6 border-2 border-primary border-t-transparent rounded-full mb-2"></div>
          <p>Loading report...</p>
        </div>

        <template v-else>
          <p v-if="error" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-4 py-3">{{ error }}</p>

          <!-- Chart -->
          <ReportChart v-if="activeReport.chartType && chartLabels.length"
            :type="activeReport.chartType"
            :labels="chartLabels"
            :datasets="chartDatasets" />

          <!-- Table -->
          <div v-if="rowData.length" class="ag-theme-alpine rounded-xl overflow-hidden border border-border-theme print:border print:shadow-none" :style="{ height: rowData.length > 20 ? '550px' : Math.max(200, rowData.length * 42 + 50) + 'px' }">
            <AgGridVue
              :rowData="rowData"
              :columnDefs="activeReport.columns.map(c => ({ field: c.field, headerName: c.header }))"
              :gridOptions="gridOptions"
              class="ag-theme-alpine w-full h-full"
              :domLayout="'autoHeight'" />
          </div>
          <div v-else-if="!error" class="text-center py-12 text-text-tertiary bg-surface-raised rounded-xl border border-border-theme">
            No data available for this report.
          </div>
        </template>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
@media print {
  .print\:hidden { display: none !important; }
  .print\:block { display: block !important; }
  .print-area { overflow: visible !important; height: auto !important; }
}
</style>
