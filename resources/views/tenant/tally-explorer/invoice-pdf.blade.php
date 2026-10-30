<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice {{ $invoice->invoice_no }}</title>
<style>
  @page { margin: 24px 28px; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1f2937; margin: 0; }
  table { width: 100%; border-collapse: collapse; }
  .muted { color: #64748b; }
  .right { text-align: right; }
  .center { text-align: center; }

  .notice { margin-top: 2px; font-size: 7.2pt; color: #94a3b8; }

  .band { border-bottom: 3px solid #007a7a; padding-bottom: 10px; margin-bottom: 14px; }
  .band h1 { font-size: 16pt; color: #005a5a; margin: 0 0 2px; letter-spacing: .3px; }
  .band .company { font-size: 8.5pt; color: #475569; }

  .meta td { vertical-align: top; padding: 0; }
  .meta .box { border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px 10px; }
  .meta .lbl { font-size: 6.6pt; text-transform: uppercase; letter-spacing: .5px; color: #94a3b8; font-weight: bold; }
  .meta .val { font-size: 8.8pt; color: #1f2937; margin-top: 1px; }

  .items { margin-top: 16px; }
  .items th { background: #f1f5f9; color: #475569; font-size: 7pt; text-transform: uppercase; letter-spacing: .4px;
              border: 1px solid #e2e8f0; padding: 5px 7px; text-align: left; }
  .items td { border: 1px solid #e2e8f0; padding: 5px 7px; font-size: 8.4pt; }
  .items tfoot td { font-weight: bold; background: #f8fafc; }

  .totals { width: 260px; margin-left: auto; margin-top: 10px; }
  .totals td { padding: 3px 7px; font-size: 8.6pt; }
  .totals .grand td { border-top: 2px solid #005a5a; font-weight: bold; font-size: 10pt; color: #005a5a; padding-top: 6px; }

  .foot { margin-top: 24px; padding-top: 8px; border-top: 1px solid #e2e8f0; font-size: 7.2pt; color: #94a3b8; }
</style>
</head>
<body>

  <table class="band"><tr>
    <td>
      <h1>Tax Invoice</h1>
      <div class="company">{{ $settings->company_name ?? 'HI-TECH GROUP' }}</div>
      @if($settings->company_address ?? null)<div class="company muted">{{ $settings->company_address }}</div>@endif
    </td>
    <td class="right">
      <div class="meta"><div class="box" style="display:inline-block; min-width:220px;">
        <div class="lbl">Invoice No.</div><div class="val">{{ $invoice->invoice_no }}</div>
        <div class="lbl" style="margin-top:6px;">Invoice Date</div>
        <div class="val">{{ $invoice->invoice_date ? \Illuminate\Support\Carbon::parse($invoice->invoice_date)->format('d M Y') : '—' }}</div>
      </div></div>
    </td>
  </tr></table>

  <table class="meta"><tr>
    <td style="width:60%; padding-right:10px;">
      <div class="box">
        <div class="lbl">Billed To</div>
        <div class="val" style="font-weight:bold;">{{ $ledger->mailing_name ?: $ledger->name }}</div>
        @if($ledger->address)<div class="val muted">{{ $ledger->address }}</div>@endif
        @if($partyGstin)<div class="val">GSTIN: {{ $partyGstin }}</div>@endif
        @if($placeOfSupply)<div class="val muted">Place of Supply: {{ $placeOfSupply }}</div>@endif
      </div>
    </td>
    <td style="width:40%;">
      <div class="box">
        <div class="lbl">Reference</div>
        @if($invoice->po_number)<div class="val">PO No: {{ $invoice->po_number }}</div>@endif
        @if($invoice->lr_no)<div class="val">LR No: {{ $invoice->lr_no }}</div>@endif
        @if($invoice->transporter)<div class="val">Transporter: {{ $invoice->transporter }}</div>@endif
        @if($invoice->destination)<div class="val">Destination: {{ $invoice->destination }}</div>@endif
        @if(!$invoice->po_number && !$invoice->lr_no && !$invoice->transporter && !$invoice->destination)<div class="val muted">—</div>@endif
      </div>
    </td>
  </tr></table>

  <table class="items">
    <thead><tr>
      <th style="width:28px;">#</th>
      <th>Item</th>
      <th>HSN</th>
      <th class="right">Qty</th>
      <th class="right">Rate</th>
      <th class="right">GST %</th>
      <th class="right">Taxable (₹)</th>
      <th class="right">GST (₹)</th>
    </tr></thead>
    <tbody>
    @forelse($lines as $i => $l)
      <tr>
        <td class="center">{{ $i + 1 }}</td>
        <td>{{ $l->item_name }}</td>
        <td>{{ $l->hsn ?: '—' }}</td>
        <td class="right">{{ $l->quantity !== null ? number_format((float)$l->quantity, 2) : '—' }} {{ $l->unit }}</td>
        <td class="right">{{ $l->rate !== null ? number_format((float)$l->rate, 2) : '—' }}</td>
        <td class="right">{{ $l->gst_rate !== null ? number_format((float)$l->gst_rate, 1) . '%' : '—' }}</td>
        <td class="right">{{ number_format((float)$l->taxable_amount, 2) }}</td>
        <td class="right">{{ $l->tax_amount !== null ? number_format((float)$l->tax_amount, 2) : '—' }}</td>
      </tr>
    @empty
      <tr><td colspan="8" class="center muted" style="padding:14px;">No item-line detail synced for this invoice — showing invoice total only.</td></tr>
    @endforelse
    </tbody>
  </table>

  <table class="totals">
    @if($invoice->taxable_total !== null)<tr><td class="muted">Taxable Value</td><td class="right">₹{{ number_format((float)$invoice->taxable_total, 2) }}</td></tr>@endif
    @if($invoice->tax_total !== null)<tr><td class="muted">Total GST</td><td class="right">₹{{ number_format((float)$invoice->tax_total, 2) }}</td></tr>@endif
    <tr class="grand"><td>Invoice Total</td><td class="right">₹{{ number_format((float)$invoice->amount, 2) }}</td></tr>
  </table>

  <div class="foot">
    Generated from HRX's synced Tally data, {{ now()->format('d M Y, h:i A') }} — a quick-reference copy, not a
    replacement for the statutory invoice printed from Tally itself (which alone carries bank, signatory and
    legal terms).
  </div>

</body>
</html>
