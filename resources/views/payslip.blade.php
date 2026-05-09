<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Courier New', monospace; background: #fff; color: #111; padding: 40px; max-width: 600px; margin: 0 auto; }
  .header { text-align: center; border-bottom: 2px solid #111; padding-bottom: 16px; margin-bottom: 24px; }
  .header h1 { font-size: 22px; letter-spacing: 4px; }
  .header p  { font-size: 12px; color: #555; margin-top: 4px; }
  .section   { margin-bottom: 20px; }
  .section h2 { font-size: 11px; text-transform: uppercase; letter-spacing: 2px; color: #888; border-bottom: 1px solid #eee; padding-bottom: 6px; margin-bottom: 12px; }
  .row       { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
  .row:last-child { border-bottom: none; }
  .row .label { color: #555; }
  .row .val   { font-weight: 600; }
  .gross      { display: flex; justify-content: space-between; padding: 14px 0; font-size: 16px; font-weight: 700; border-top: 2px solid #111; margin-top: 8px; }
  .footer     { text-align: center; font-size: 10px; color: #aaa; margin-top: 40px; border-top: 1px solid #eee; padding-top: 16px; }
</style>
</head>
<body>

<div class="header">
  <h1>☕ RUSTYCREW</h1>
  <p>Employee Payslip</p>
  <p style="margin-top:8px; font-size:13px; font-weight:600;">
    {{ $employee->first_name }} {{ $employee->last_name }}
  </p>
  <p style="font-size:11px; color:#888;">{{ $employee->role }} · {{ $employee->email }}</p>
</div>

<div class="section">
  <h2>Pay Period</h2>
  <div class="row">
    <span class="label">From</span>
    <span class="val">{{ $from }}</span>
  </div>
  <div class="row">
    <span class="label">To</span>
    <span class="val">{{ $to }}</span>
  </div>
</div>

<div class="section">
  <h2>Hours Summary</h2>
  <div class="row">
    <span class="label">Regular hours</span>
    <span class="val">{{ $totalHours }}h</span>
  </div>
  <div class="row">
    <span class="label">Overtime hours</span>
    <span class="val">{{ $otHours }}h</span>
  </div>
  <div class="row">
    <span class="label">Pay type</span>
    <span class="val">{{ $employee->is_salaried ? 'Salaried' : 'Hourly (₱' . $employee->hourly_rate . '/hr)' }}</span>
  </div>
</div>

<div class="section">
  <h2>Earnings</h2>
  <div class="row">
    <span class="label">Base pay</span>
    <span class="val">₱{{ number_format($base, 2) }}</span>
  </div>
  <div class="row">
    <span class="label">Overtime pay (1.25×)</span>
    <span class="val">₱{{ number_format($otPay, 2) }}</span>
  </div>
  <div class="gross">
    <span>Gross Pay</span>
    <span>₱{{ number_format($gross, 2) }}</span>
  </div>
</div>

<div class="footer">
  This payslip is computer-generated and does not require a signature.<br>
  Generated on {{ now()->format('F d, Y \a\t h:i A') }} · BrewCrew EMS
</div>

</body>
</html>