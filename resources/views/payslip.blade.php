<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payslip — {{ $entry->employee_first_name }} {{ $entry->employee_last_name }}</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Courier New', Courier, monospace;
    background: #fff;
    color: #111;
    padding: 40px;
    max-width: 620px;
    margin: 0 auto;
    font-size: 13px;
  }
  .header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 2px solid #111;
    padding-bottom: 16px;
    margin-bottom: 22px;
  }
  .company h1 {
    font-size: 20px;
    letter-spacing: 4px;
    text-transform: uppercase;
  }
  .company p { font-size: 11px; color: #777; margin-top: 3px; }
  .period { text-align: right; }
  .period .label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 2px;
    color: #888;
  }
  .period .value { font-size: 13px; font-weight: 700; margin-top: 3px; }
  .status-badge {
    display: inline-block;
    margin-top: 8px;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
  }
  .status-locked { background: #e6f9ef; color: #1a7a3a; border: 1px solid #7ad4a5; }
  .status-draft  { background: #fff8e6; color: #a06000; border: 1px solid #ffd27a; }
  .employee-block {
    background: #f8f8f8;
    border: 1px solid #e8e8e8;
    border-radius: 8px;
    padding: 14px 16px;
    margin-bottom: 22px;
  }
  .employee-name { font-size: 17px; font-weight: 700; }
  .employee-meta { font-size: 11px; color: #666; margin-top: 4px; }
  .section-title {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 2px;
    color: #888;
    border-bottom: 1px solid #eee;
    padding-bottom: 6px;
    margin-bottom: 12px;
  }
  .row {
    display: flex;
    justify-content: space-between;
    padding: 7px 0;
    border-bottom: 1px solid #f4f4f4;
    font-size: 13px;
  }
  .row:last-child { border-bottom: none; }
  .row .key   { color: #666; }
  .row .val   { font-weight: 600; }
  .gross-row {
    display: flex;
    justify-content: space-between;
    padding: 14px 0 10px;
    border-top: 2px solid #111;
    margin-top: 10px;
    font-size: 16px;
    font-weight: 700;
  }
  .section { margin-bottom: 24px; }
  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
    margin-top: 4px;
  }
  th {
    text-align: left;
    padding: 6px 8px;
    background: #f4f4f4;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #888;
    font-weight: 700;
  }
  td { padding: 6px 8px; border-bottom: 1px solid #f4f4f4; }
  .footer {
    margin-top: 32px;
    padding-top: 14px;
    border-top: 1px solid #eee;
    font-size: 10px;
    color: #aaa;
    text-align: center;
    line-height: 1.7;
  }
  .highlight { color: #1a7a3a; }
</style>
</head>
<body>

<!-- Header -->
<div class="header">
  <div class="company">
    <h1>☕ RustyCrew</h1>
    <p>Employee Payslip</p>
  </div>
  <div class="period">
    <div class="label">Pay period</div>
    <div class="value">{{ $period->start_date->format('M j') }} – {{ $period->end_date->format('M j, Y') }}</div>
    <div class="value" style="font-size:11px;font-weight:400;color:#888;margin-top:2px">
      {{ ucfirst(str_replace('_', '-', $period->frequency)) }}
    </div>
    <span class="status-badge {{ $period->status === 'locked' ? 'status-locked' : 'status-draft' }}">
      {{ $period->status === 'locked' ? '🔒 Locked' : '📝 Draft' }}
    </span>
  </div>
</div>

<!-- Employee block -->
<div class="employee-block">
  <div class="employee-name">
    {{ $entry->employee_first_name }} {{ $entry->employee_last_name }}
  </div>
  <div class="employee-meta">
    {{ $entry->employee_role }}
    @if($entry->is_salaried)
      · Salaried — ₱{{ number_format($entry->monthly_salary_snapshot, 2) }}/mo
    @else
      · Hourly — ₱{{ number_format($entry->hourly_rate_snapshot, 2) }}/hr
    @endif
  </div>
</div>

<!-- Hours summary -->
<div class="section">
  <div class="section-title">Hours Summary</div>
  <div class="row">
    <span class="key">Regular hours worked</span>
    <span class="val">{{ number_format($entry->total_hours, 2) }}h</span>
  </div>
  <div class="row">
    <span class="key">Overtime hours</span>
    <span class="val">{{ number_format($entry->ot_hours, 2) }}h</span>
  </div>
</div>

<!-- Earnings -->
<div class="section">
  <div class="section-title">Earnings</div>
  <div class="row">
    <span class="key">Base pay</span>
    <span class="val">₱{{ number_format($entry->base_pay, 2) }}</span>
  </div>
  <div class="row">
    <span class="key">Overtime pay (× 1.25)</span>
    <span class="val">₱{{ number_format($entry->ot_pay, 2) }}</span>
  </div>

  @if($entry->deductions > 0)
  <!-- Deductions -->
  <div class="row" style="margin-top:8px;border-top:1px solid #eee;padding-top:8px">
    <span class="key">Deductions</span>
    <span class="val" style="color:#c0392b">-₱{{ number_format($entry->deductions, 2) }}</span>
  </div>
  @endif

  <div class="gross-row">
    <span>{{ $entry->deductions > 0 ? 'Net Pay' : 'Gross Pay' }}</span>
    <span class="highlight">
      ₱{{ number_format($entry->deductions > 0 ? $entry->net_pay : $entry->gross_pay, 2) }}
    </span>
  </div>
</div>

<!-- Daily breakdown -->
@if(!empty($entry->daily_breakdown) && count($entry->daily_breakdown) > 0)
<div class="section">
  <div class="section-title">Daily Breakdown</div>
  <table>
    <thead>
      <tr>
        <th>Date</th>
        <th>Clock In</th>
        <th>Clock Out</th>
        <th>Hours</th>
        <th>Overtime</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      @foreach($entry->daily_breakdown as $log)
      <tr>
        <td>{{ $log['date'] }}</td>
        <td>{{ $log['clockIn'] ?? '—' }}</td>
        <td>{{ $log['clockOut'] ?? '—' }}</td>
        <td>{{ number_format($log['hoursWorked'], 1) }}h</td>
        <td>{{ $log['overtime'] > 0 ? '+'.number_format($log['overtime'], 1).'h' : '—' }}</td>
        <td>{{ $log['status'] }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif

<!-- Footer -->
<div class="footer">
  Period #{{ $period->id }} · Generated {{ $period->generated_at?->format('F d, Y \a\t h:i A') ?? now()->format('F d, Y') }}<br>
  @if($period->status === 'locked')
  🔒 This payslip is a locked, immutable snapshot. Values will not change.<br>
  @else
  ⚠ This is a draft payslip. Values may change until the period is locked.<br>
  @endif
  RustyCrew Employee Management System · Confidential
</div>

</body>
</html>