<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Behaviour Log — {{ $classroom->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 13px;
            color: #1e293b;
            background: #f8fafc;
        }

        /* ── Screen controls ── */
        .screen-only {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .screen-only .btn-print {
            background: #4f46e5;
            color: #fff;
            border: none;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        .screen-only .btn-print:hover { background: #4338ca; }

        /* ── Page ── */
        .page {
            max-width: 900px;
            margin: 24px auto;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }

        /* ── Header ── */
        .page-header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: #fff;
            padding: 24px 28px 20px;
        }
        .page-header h1 { font-size: 20px; font-weight: 700; }
        .page-header .sub {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 4px;
        }
        .page-header .meta {
            display: flex;
            gap: 16px;
            margin-top: 14px;
            flex-wrap: wrap;
        }
        .page-header .meta-item {
            background: rgba(255,255,255,0.08);
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 12px;
        }
        .page-header .meta-item span { color: #94a3b8; }

        /* ── Student section ── */
        .student-section { border-top: 1px solid #e2e8f0; }
        .student-section:first-child { border-top: none; }

        .student-heading {
            background: #f1f5f9;
            padding: 10px 20px;
            font-weight: 700;
            font-size: 13px;
            color: #334155;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .student-heading .counts {
            display: flex;
            gap: 8px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-pos { background: #dcfce7; color: #16a34a; padding: 2px 8px; border-radius: 20px; }
        .badge-neg { background: #fee2e2; color: #dc2626; padding: 2px 8px; border-radius: 20px; }

        /* ── Log table ── */
        table { width: 100%; border-collapse: collapse; }
        th {
            background: #f8fafc;
            padding: 8px 16px;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
        }
        td {
            padding: 10px 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
            font-size: 12.5px;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #fafafa; }

        .type-pos {
            display: inline-block;
            background: #dcfce7;
            color: #15803d;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 20px;
        }
        .type-neg {
            display: inline-block;
            background: #fee2e2;
            color: #b91c1c;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 20px;
        }

        .date-cell { white-space: nowrap; color: #64748b; }
        .cat-cell { white-space: nowrap; }
        .desc-cell { max-width: 280px; }
        .action-cell { max-width: 200px; color: #475569; font-style: italic; }

        .empty { padding: 20px; text-align: center; color: #94a3b8; font-style: italic; }

        /* ── Footer ── */
        .page-footer {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 12px 24px;
            font-size: 11px;
            color: #94a3b8;
            display: flex;
            justify-content: space-between;
        }

        /* ── Print ── */
        @media print {
            body { background: #fff; }
            .screen-only { display: none !important; }
            .page {
                max-width: 100%;
                margin: 0;
                border: none;
                border-radius: 0;
            }
            .page-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .student-heading { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .badge-pos, .badge-neg, .type-pos, .type-neg {
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
            }
            tr { page-break-inside: avoid; }
            .student-section { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

{{-- Screen controls --}}
<div class="screen-only">
    <div>
        <strong>{{ $classroom->name }}</strong> — Behaviour Log
        @if($studentId && $logs->first()?->first()?->student)
            &nbsp;·&nbsp; {{ $logs->first()->first()->student->full_name }}
        @endif
    </div>
    <button class="btn-print" onclick="window.print()">🖨️ Print</button>
</div>

<div class="page">

    {{-- Header --}}
    <div class="page-header">
        <h1>Behaviour Log</h1>
        <div class="sub">{{ $classroom->name }} &middot; {{ $classroom->subject }} &middot; {{ $classroom->academic_year }}</div>
        <div class="meta">
            @php
                $totalEntries = $logs->flatten()->count();
                $totalPos     = $logs->flatten()->where('type', 'positive')->count();
                $totalNeg     = $logs->flatten()->where('type', 'negative')->count();
            @endphp
            <div class="meta-item"><span>Total entries:</span> {{ $totalEntries }}</div>
            <div class="meta-item"><span>Positive:</span> {{ $totalPos }}</div>
            <div class="meta-item"><span>Negative:</span> {{ $totalNeg }}</div>
            <div class="meta-item"><span>Students:</span> {{ $logs->count() }}</div>
            <div class="meta-item"><span>Printed:</span> {{ now()->format('d M Y, H:i') }}</div>
        </div>
    </div>

    {{-- Student sections --}}
    @if ($logs->isEmpty())
        <div class="empty">No behaviour logs found.</div>
    @else
        @foreach ($logs as $sid => $studentLogs)
            @php $student = $students[$sid] ?? null; @endphp
            <div class="student-section">
                <div class="student-heading">
                    <span>{{ $student?->full_name ?? 'Unknown Student' }}</span>
                    <div class="counts">
                        <span class="badge-pos">✅ {{ $studentLogs->where('type', 'positive')->count() }} positive</span>
                        <span class="badge-neg">⚠️ {{ $studentLogs->where('type', 'negative')->count() }} negative</span>
                    </div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Action Taken</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($studentLogs as $log)
                        <tr>
                            <td class="date-cell">{{ \Carbon\Carbon::parse($log->date)->format('d M Y') }}</td>
                            <td>
                                @if ($log->type === 'positive')
                                    <span class="type-pos">Positive</span>
                                @else
                                    <span class="type-neg">Negative</span>
                                @endif
                            </td>
                            <td class="cat-cell">{{ $log->category }}</td>
                            <td class="desc-cell">{{ $log->description }}</td>
                            <td class="action-cell">{{ $log->action_taken ?: '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif

    <div class="page-footer">
        <span>{{ $classroom->name }} &middot; Behaviour Log</span>
        <span>Generated {{ now()->format('d M Y') }}</span>
    </div>
</div>

</body>
</html>
