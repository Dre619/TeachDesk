<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Lesson Plan – {{ $plan->title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            background: #fff;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 14mm 16mm;
        }

        /* ── Header ── */
        .header {
            border-bottom: 3px solid #4f46e5;
            padding-bottom: 10px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .header-title {
            font-size: 22px;
            font-weight: 900;
            color: #1e293b;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }
        .header-sub {
            font-size: 11px;
            color: #64748b;
            margin-top: 3px;
        }
        .header-meta {
            text-align: right;
        }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.4px;
        }
        .badge-term-1 { background: #dbeafe; color: #1e40af; }
        .badge-term-2 { background: #fef9c3; color: #854d0e; }
        .badge-term-3 { background: #dcfce7; color: #166534; }
        .badge-subject { background: #ede9fe; color: #4f46e5; margin-top: 4px; }

        /* ── Meta strip ── */
        .meta-strip {
            display: flex;
            gap: 0;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 16px;
        }
        .meta-cell {
            flex: 1;
            padding: 8px 12px;
            border-right: 1px solid #e2e8f0;
        }
        .meta-cell:last-child { border-right: none; }
        .meta-label {
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: 700;
            color: #94a3b8;
            margin-bottom: 2px;
        }
        .meta-value {
            font-size: 11px;
            font-weight: 700;
            color: #1e293b;
        }

        /* ── Sections ── */
        .section {
            margin-bottom: 13px;
            page-break-inside: avoid;
        }
        .section-header {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 5px;
        }
        .section-icon {
            width: 18px;
            height: 18px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 900;
            flex-shrink: 0;
        }
        .section-title {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #1e293b;
        }
        .section-body {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 9px 12px;
            font-size: 11px;
            color: #334155;
            line-height: 1.65;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .section-body.empty {
            color: #94a3b8;
            font-style: italic;
        }

        /* Section accent colours */
        .icon-objectives  { background: #dbeafe; color: #1e40af; }
        .icon-resources   { background: #fef9c3; color: #854d0e; }
        .icon-content     { background: #ede9fe; color: #4f46e5; }
        .icon-assessment  { background: #dcfce7; color: #166534; }
        .icon-homework    { background: #ffedd5; color: #9a3412; }

        .border-objectives { border-left: 3px solid #3b82f6; }
        .border-resources  { border-left: 3px solid #eab308; }
        .border-content    { border-left: 3px solid #8b5cf6; }
        .border-assessment { border-left: 3px solid #22c55e; }
        .border-homework   { border-left: 3px solid #f97316; }

        /* ── Two-column layout for smaller sections ── */
        .two-col {
            display: flex;
            gap: 12px;
        }
        .two-col .section { flex: 1; }

        /* ── Footer ── */
        .footer {
            margin-top: 20px;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .footer-left {
            font-size: 9px;
            color: #94a3b8;
            line-height: 1.6;
        }
        .signature-block {
            text-align: center;
        }
        .sig-line {
            width: 110px;
            border-bottom: 1px solid #94a3b8;
            margin: 0 auto 3px;
        }
        .sig-label {
            font-size: 8.5px;
            color: #64748b;
        }
    </style>
</head>
<body>
<div class="page">

    {{-- ── HEADER ── --}}
    <div class="header">
        <div>
            <div class="header-title">{{ $plan->title }}</div>
            <div class="header-sub">{{ $classroom->name }} &bull; {{ $classroom->academic_year }}</div>
        </div>
        <div class="header-meta">
            @php
                $termBadge = match($plan->term) {
                    1 => 'badge-term-1',
                    2 => 'badge-term-2',
                    3 => 'badge-term-3',
                    default => '',
                };
            @endphp
            <div><span class="badge {{ $termBadge }}">{{ $plan->term_label }}</span></div>
            <div style="margin-top:4px;"><span class="badge badge-subject">{{ $plan->subject }}</span></div>
        </div>
    </div>

    {{-- ── META STRIP ── --}}
    <div class="meta-strip">
        <div class="meta-cell">
            <div class="meta-label">Topic</div>
            <div class="meta-value">{{ $plan->topic }}</div>
        </div>
        <div class="meta-cell">
            <div class="meta-label">Week</div>
            <div class="meta-value">Week {{ $plan->week_number }}</div>
        </div>
        <div class="meta-cell">
            <div class="meta-label">Term</div>
            <div class="meta-value">Term {{ $plan->term }}</div>
        </div>
        <div class="meta-cell">
            <div class="meta-label">Academic Year</div>
            <div class="meta-value">{{ $plan->academic_year }}</div>
        </div>
        <div class="meta-cell">
            <div class="meta-label">Duration</div>
            <div class="meta-value">{{ $plan->duration_label ?? '—' }}</div>
        </div>
        <div class="meta-cell">
            <div class="meta-label">Teacher</div>
            <div class="meta-value">{{ $teacher->name }}</div>
        </div>
    </div>

    {{-- ── LEARNING OBJECTIVES ── --}}
    <div class="section">
        <div class="section-header">
            <div class="section-icon icon-objectives">O</div>
            <div class="section-title">Learning Objectives</div>
        </div>
        @if ($plan->objectives)
            <div class="section-body border-objectives">{{ $plan->objectives }}</div>
        @else
            <div class="section-body empty border-objectives">No objectives recorded.</div>
        @endif
    </div>

    {{-- ── RESOURCES ── --}}
    <div class="section">
        <div class="section-header">
            <div class="section-icon icon-resources">R</div>
            <div class="section-title">Materials &amp; Resources</div>
        </div>
        @if ($plan->resources)
            <div class="section-body border-resources">{{ $plan->resources }}</div>
        @else
            <div class="section-body empty border-resources">No resources listed.</div>
        @endif
    </div>

    {{-- ── LESSON CONTENT / ACTIVITIES ── --}}
    <div class="section">
        <div class="section-header">
            <div class="section-icon icon-content">L</div>
            <div class="section-title">Lesson Content &amp; Activities</div>
        </div>
        @if ($plan->content)
            <div class="section-body border-content">{{ $plan->content }}</div>
        @else
            <div class="section-body empty border-content">No lesson content recorded.</div>
        @endif
    </div>

    {{-- ── ASSESSMENT + HOMEWORK (side by side) ── --}}
    <div class="two-col">
        <div class="section">
            <div class="section-header">
                <div class="section-icon icon-assessment">A</div>
                <div class="section-title">Assessment &amp; Closure</div>
            </div>
            @if ($plan->assessment)
                <div class="section-body border-assessment">{{ $plan->assessment }}</div>
            @else
                <div class="section-body empty border-assessment">No assessment recorded.</div>
            @endif
        </div>
        <div class="section">
            <div class="section-header">
                <div class="section-icon icon-homework">H</div>
                <div class="section-title">Homework &amp; Extension</div>
            </div>
            @if ($plan->homework)
                <div class="section-body border-homework">{{ $plan->homework }}</div>
            @else
                <div class="section-body empty border-homework">No homework assigned.</div>
            @endif
        </div>
    </div>

    {{-- ── FOOTER ── --}}
    <div class="footer">
        <div class="footer-left">
            Prepared by: <strong>{{ $teacher->name }}</strong><br>
            Class: {{ $classroom->name }} &bull; {{ $plan->subject }} &bull; {{ $plan->term_label }}, Week {{ $plan->week_number }}<br>
            Generated: {{ now()->format('d M Y') }}
        </div>
        <div class="signature-block">
            <div class="sig-line"></div>
            <div class="sig-label">Teacher's Signature</div>
        </div>
    </div>

</div>
</body>
</html>
