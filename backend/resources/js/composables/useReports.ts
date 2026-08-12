import { ref } from 'vue'
import api from '@/composables/axios'

export interface ReportDef {
  key: string
  label: string
  description: string
  category: string
  columns: { field: string; header: string }[]
  chartType?: 'bar' | 'doughnut' | 'line' | 'horizontalBar'
}

export interface DateRange { from: string; to: string }

export function useReports() {
  const loading = ref(false)
  const error = ref('')
  const rowData = ref<any[]>([])
  const chartLabels = ref<string[]>([])
  const chartData = ref<number[]>([])
  const chartDatasets = ref<{ label: string; data: number[]; backgroundColor?: string | string[]; borderColor?: string }[]>([])

  async function loadReport(report: ReportDef, dateRange?: DateRange) {
    loading.value = true
    error.value = ''
    rowData.value = []
    chartLabels.value = []
    chartData.value = []
    chartDatasets.value = []

    try {
      const params: Record<string, any> = {}
      if (dateRange?.from) params.date_from = dateRange.from
      if (dateRange?.to) params.date_to = dateRange.to

      const res = await api.get(`/reports/${report.key}`, { params })
      const data = res.data?.data ?? []

      buildRows(report.key, data)
      buildChart(report, data)
    } catch (e: any) {
      error.value = e.response?.data?.error || e.message || 'Failed to load report'
      rowData.value = []
    } finally {
      loading.value = false
    }
  }

  function buildRows(key: string, data: any) {
    switch (key) {
      case 'summary':
        if (data.sales) {
          rowData.value = [
            { metric: 'Total Sales', value: data.sales.total_sales },
            { metric: 'Total Revenue', value: data.sales.total_revenue?.toFixed(2) },
            { metric: "Today's Sales", value: data.sales.today_sales },
            { metric: "Today's Revenue", value: data.sales.today_revenue?.toFixed(2) },
            { metric: 'Total Products', value: data.inventory?.total_products },
            { metric: 'Active Products', value: data.inventory?.active_products },
            { metric: 'Inventory Value', value: data.inventory?.total_value?.toFixed(2) },
            { metric: 'Low Stock Items', value: data.inventory?.low_stock_count },
            { metric: 'Total Customers', value: data.customers?.total },
            { metric: 'Branches', value: data.branches?.total },
          ]
        }
        return
      case 'profit-loss':
        if (data.total_revenue !== undefined) {
          rowData.value = [
            { metric: 'Total Revenue', value: data.total_revenue.toFixed(2) },
            { metric: 'Total Cost (COGS)', value: data.total_cost.toFixed(2) },
            { metric: 'Gross Profit', value: data.gross_profit.toFixed(2) },
            { metric: 'Tax Collected', value: data.total_tax.toFixed(2) },
            { metric: 'Net Profit', value: data.net_profit.toFixed(2) },
            { metric: 'Margin %', value: data.margin_percent + '%' },
          ]
        }
        return
      case 'trial-balance':
        rowData.value = data.accounts?.map((a: any) => ({
          code: a.code, name: a.name, type: a.type,
          debit: a.debit > 0 ? Number(a.debit).toFixed(2) : '-',
          credit: a.credit > 0 ? Number(a.credit).toFixed(2) : '-',
        })) ?? []
        if (data.totals) {
          rowData.value.push({ code: '', name: 'TOTAL', type: '', debit: Number(data.totals.debit).toFixed(2), credit: Number(data.totals.credit).toFixed(2) })
        }
        return
      case 'balance-sheet': {
        const rows: any[] = []
        data.assets?.accounts?.forEach((a: any) => rows.push({ section: 'Assets', account: `${a.code} ${a.name}`, balance: Number(a.balance).toFixed(2) }))
        rows.push({ section: 'Assets', account: 'Total Assets', balance: Number(data.assets?.total).toFixed(2) })
        data.liabilities?.accounts?.forEach((a: any) => rows.push({ section: 'Liabilities', account: `${a.code} ${a.name}`, balance: Number(a.balance).toFixed(2) }))
        rows.push({ section: 'Liabilities', account: 'Total Liabilities', balance: Number(data.liabilities?.total).toFixed(2) })
        data.equity?.accounts?.forEach((a: any) => rows.push({ section: 'Equity', account: `${a.code} ${a.name}`, balance: Number(a.balance).toFixed(2) }))
        rows.push({ section: 'Equity', account: 'Total Equity', balance: Number(data.equity?.total).toFixed(2) })
        rows.push({ section: '', account: 'Total Liabilities + Equity', balance: Number(data.total_liabilities_equity).toFixed(2) })
        rowData.value = rows
        return
      }
      case 'income-statement': {
        const rows: any[] = []
        data.revenue?.accounts?.forEach((a: any) => rows.push({ section: 'Revenue', account: `${a.code} ${a.name}`, balance: Number(a.balance).toFixed(2) }))
        rows.push({ section: 'Revenue', account: 'Total Revenue', balance: Number(data.revenue?.total).toFixed(2) })
        data.expenses?.accounts?.forEach((a: any) => rows.push({ section: 'Expenses', account: `${a.code} ${a.name}`, balance: Number(a.balance).toFixed(2) }))
        rows.push({ section: 'Expenses', account: 'Total Expenses', balance: Number(data.expenses?.total).toFixed(2) })
        rows.push({ section: '', account: 'Net Income', balance: Number(data.net_income).toFixed(2) })
        rowData.value = rows
        return
      }
      case 'general-ledger':
        rowData.value = (data.data ?? []).map((l: any) => ({
          entry_number: l.journal_entry?.entry_number ?? '',
          entry_date: l.journal_entry?.entry_date ?? '',
          account_code: l.account?.code ?? '',
          account_name: l.account?.name ?? '',
          debit_amount: Number(l.debit_amount) > 0 ? Number(l.debit_amount).toFixed(2) : '-',
          credit_amount: Number(l.credit_amount) > 0 ? Number(l.credit_amount).toFixed(2) : '-',
          description: l.description ?? '',
        }))
        return
      default:
        rowData.value = data
    }
  }

  function buildChart(report: ReportDef, data: any) {
    if (!report.chartType) return
    if (!Array.isArray(data) || !data.length) return

    let labels: string[] = []
    let datasets: { label: string; data: number[]; backgroundColor?: string | string[]; borderColor?: string }[] = []

    switch (report.key) {
      case 'revenue-by-payment':
        labels = data.map((d: any) => d.payment_method)
        datasets = [{ label: 'Revenue', data: data.map((d: any) => Number(d.revenue)), backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'] }]
        break
      case 'daily-revenue':
        labels = data.map((d: any) => d.date?.slice(5))
        datasets = [{ label: 'Revenue', data: data.map((d: any) => Number(d.revenue)), borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)' }]
        break
      case 'top-products':
        labels = data.map((d: any) => d.name?.slice(0, 15))
        datasets = [{ label: 'Qty Sold', data: data.map((d: any) => Number(d.total_quantity)), backgroundColor: '#3b82f6' }]
        break
      case 'cogs':
        labels = data.map((d: any) => d.date?.slice(5))
        datasets = [{ label: 'Gross Profit', data: data.map((d: any) => Number(d.gross_profit)), backgroundColor: '#3b82f6' }]
        break
      case 'tax-report':
        labels = data.map((d: any) => d.created_at?.slice(0, 10)?.slice(5))
        datasets = [{ label: 'Tax', data: data.map((d: any) => Number(d.tax_amount)), backgroundColor: '#f59e0b' }]
        break
      case 'payments-reconciliation':
        labels = data.map((d: any) => d.payment_method)
        datasets = [
          { label: 'Completed', data: data.map((d: any) => Number(d.completed)), backgroundColor: '#10b981' },
          { label: 'Voided', data: data.map((d: any) => Number(d.voided)), backgroundColor: '#ef4444' },
        ]
        break
      case 'customer-statement':
        labels = data.slice(0, 10).map((d: any) => d.name?.slice(0, 12))
        datasets = [{ label: 'Total Spend', data: data.slice(0, 10).map((d: any) => Number(d.total_spend)), backgroundColor: '#8b5cf6' }]
        break
      case 'sales-by-user':
        labels = data.map((d: any) => d.user_name?.slice(0, 12))
        datasets = [{ label: 'Sales', data: data.map((d: any) => d.total_sales), backgroundColor: '#3b82f6' }]
        break
      case 'low-stock':
        labels = data.slice(0, 15).map((d: any) => d.product_name?.slice(0, 15))
        datasets = [{ label: 'Qty', data: data.slice(0, 15).map((d: any) => Number(d.quantity)), backgroundColor: '#ef4444' }]
        break
      case 'inventory-valuation':
        labels = data.map((d: any) => d.product_name?.slice(0, 12))
        datasets = [{ label: 'Value', data: data.map((d: any) => Number(d.value)), backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'] }]
        break
    }

    chartLabels.value = labels
    chartData.value = datasets[0]?.data ?? []
    chartDatasets.value = datasets
  }

  function exportCSV(report: ReportDef, rows: any[]) {
    if (!rows.length) return
    const cols = report.columns.map(c => c.field)
    const csv = [cols.join(','), ...rows.map(r => cols.map(c => `"${r[c] ?? ''}"`).join(','))].join('\n')
    const blob = new Blob([csv], { type: 'text/csv' })
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = `${report.key}-${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
  }

  async function exportPDF(report: ReportDef, rows: any[], title: string) {
    if (!rows.length) return
    const jsPDF = (await import('jspdf')).default
    const autoTable = (await import('jspdf-autotable')).default

    const doc = new jsPDF()
    doc.setFontSize(16)
    doc.text(title, 14, 15)
    doc.setFontSize(9)
    doc.text(`Generated: ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}`, 14, 22)

    autoTable(doc, {
      head: [report.columns.map(c => c.header)],
      body: rows.map(r => report.columns.map(c => r[c.field] ?? '')),
      startY: 28,
      styles: { fontSize: 8 },
      headStyles: { fillColor: [59, 130, 246] },
    })

    doc.save(`${report.key}-${new Date().toISOString().slice(0, 10)}.pdf`)
  }

  async function exportExcel(report: ReportDef, rows: any[]) {
    if (!rows.length) return
    const XLSX = await import('xlsx')

    const ws = XLSX.utils.json_to_sheet(rows)
    const wb = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(wb, ws, report.label.slice(0, 31))
    XLSX.writeFile(wb, `${report.key}-${new Date().toISOString().slice(0, 10)}.xlsx`)
  }

  return {
    loading, error, rowData, chartLabels, chartData, chartDatasets,
    loadReport, exportCSV, exportPDF, exportExcel,
  }
}
