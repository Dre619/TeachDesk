<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Attendance Register — {{ $classroom->name }} — {{ \Carbon\Carbon::create($year, $month)->format('F Y') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            background: #fff;
        }

        /* ── Screen controls ── */
        .screen-only {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .screen-only .controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .screen-only select,
        .screen-only input {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 5px 10px;
            font-size: 12px;
            background: #fff;
        }

        .btn-print {
            background: #4f46e5;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 7px 16px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-print:hover { background: #4338ca; }

        /* ── Page ── */
        .page {
            padding: 20px 24px;
            max-width: 100%;
        }

        /* ── Header ── */
        .header {
            margin-bottom: 14px;
        }

        .header h1 {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
        }

        .header p {
            font-size: 11px;
            color: #64748b;
            margin-top: 2px;
        }

        /* ── Register table ── */
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th, td {
            border: 1px solid #cbd5e1;
            text-align: center;
            padding: 4px 3px;
            vertical-align: middle;
            overflow: hidden;
            white-space: nowrap;
        }

        /* Student name column wider */
        th:first-child, td:first-child {
            text-align: left;
            padding-left: 8px;
            width: 180px;
            font-weight: 600;
        }

        thead tr:first-child th {
            background: #1e293b;
            color: #f8fafc;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 6px 3px;
        }

        thead tr:last-child th {
            background: #f1f5f9;
            font-size: 9px;
            font-weight: 700;
            color: #475569;
            padding: 3px 2px;
        }

        tbody tr:nth-child(even) td { background: #f8fafc; }
        tbody tr:hover td { background: #eff6ff; }

        td.p  { color: #16a34a; font-weight: 700; }
        td.a  { color: #dc2626; font-weight: 700; }
        td.l  { color: #d97706; font-weight: 700; }
        td.na { color: #cbd5e1; }

        /* Totals column */
        td.total, th.total {
            background: #f0fdf4 !important;
            color: #15803d;
            font-weight: 700;
        }

        td.total-a {
            background: #fff1f2 !important;
            color: #dc2626;
            font-weight: 700;
        }

        /* ── Legend ── */
        .legend {
            margin-top: 14px;
            display: flex;
            gap: 20px;
            font-size: 10px;
            color: #64748b;
        }

        .legend span { font-weight: 700; }
        .legend .lp { color: #16a34a; }
        .legend .la { color: #dc2626; }
        .legend .ll { color: #d97706; }

        /* ── Signature row ── */
        .sig {
            margin-top: 32px;
            display: flex;
            gap: 40px;
        }

        .sig-box {
            flex: 1;
            border-top: 1px solid #64748b;
            padding-top: 6px;
            font-size: 10px;
            color: #64748b;
        }

        /* ── Print overrides ── */
        @media print {
            .screen-only { display: none !important; }

            body { font-size: 10px; }

            .page { padding: 10mm 12mm; }

            @page {
                size: A4 landscape;
                margin: 10mm;
            }

            table { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

{{-- ── Screen controls ── --}}
<div class="screen-only">
    <div class="controls">
        <strong style="font-size:13px;">Attendance Register</strong>

        <form method="GET" action="{{ route('user.attendance.register', $classroom->id) }}" style="display:flex;gap:8px;align-items:center;">
            <select name="month" onchange="this.form.submit()">
                @foreach(range(1, 12) as $m)
                    <option value="{{ $m }}" @selected($m == $month)>{{ \Carbon\Carbon::create(null, $m)->format('F') }}</option>
                @endforeach
            </select>
            <input type="number" name="year" value="{{ $year }}" min="2020" max="2030" style="width:80px;" onchange="this.form.submit()" />
        </form>
    </div>

    <button class="btn-print" onclick="window.print()">
        &#128438; Print / Save PDF
    </button>
</div>

{{-- ── Printable register ── --}}
<div class="page">

    <div class="header">
        <h1>{{ $classroom->name }} — Attendance Register</h1>
        <p>
            {{ $classroom->subject ?? '' }}
            &nbsp;·&nbsp;
            {{ \Carbon\Carbon::create($year, $month)->format('F Y') }}
            &nbsp;·&nbsp;
            Printed {{ now()->format('d M Y, H:i') }}
        </p>
    </div>

    @if ($dates->isEmpty())
        <p style="color:#94a3b8;padding:20px 0;">No attendance records found for this month.</p>
    @else
        @php
            $dateList = $dates->values();
            $colWidth = max(22, min(40, (int) round((100 - 22) / ($dateList->count() + 2) * ($dateList->count()))));
        @endphp

        <table>
            <thead>
                <tr>
                    <th rowspan="2">Student</th>
                    @foreach ($dateList as $date)
                        <th>{{ $date->format('D') }}</th>
                    @endforeach
                    <th class="total" rowspan="2">P</th>
                    <th class="total" rowspan="2" style="background:#fff1f2!important;color:#dc2626;">A</th>
                    <th class="total" rowspan="2" style="background:#fffbeb!important;color:#d97706;">L</th>
                </tr>
                <tr>
                    @foreach ($dateList as $date)
                        <th>{{ $date->format('d') }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($students as $student)
                    @php
                        $present = 0;
                        $absent  = 0;
                        $late    = 0;
                    @endphp
                    <tr>
                        <td>{{ $student->full_name }}</td>
                        @foreach ($dateList as $date)
                            @php
                                $key    = $student->id . '-' . $date->format('Y-m-d');
                                $record = $attendance->get($key)?->first();
                                $code   = $record?->status_code ?? null;
                                if ($code === 'P') $present++;
                                elseif ($code === 'A') $absent++;
                                elseif ($code === 'L') $late++;
                            @endphp
                            @if ($code === 'P')
                                <td class="p">P</td>
                            @elseif ($code === 'A')
                                <td class="a">A</td>
                            @elseif ($code === 'L')
                                <td class="l">L</td>
                            @else
                                <td class="na">—</td>
                            @endif
                        @endforeach
                        <td class="total">{{ $present }}</td>
                        <td class="total-a">{{ $absent }}</td>
                        <td style="background:#fffbeb;color:#d97706;font-weight:700;">{{ $late }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="legend">
            <div><span class="lp">P</span> Present</div>
            <div><span class="la">A</span> Absent</div>
            <div><span class="ll">L</span> Late</div>
            <div>— Not recorded</div>
        </div>
    @endif

    <div class="sig">
        <div class="sig-box">Class Teacher Signature</div>
        <div class="sig-box">Head Teacher Signature</div>
        <div class="sig-box">Date</div>
    </div>

</div>
</body>
</html>
