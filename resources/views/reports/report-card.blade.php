<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Report Card – {{ $student->full_name }}</title>
    @php
        $accent = $settings->accent_color ?? '#4f46e5';
        // Derive a darker shade for header backgrounds (simple: use the accent at 90% opacity over dark)
        $accentDark = $accent; // used in inline styles
    @endphp
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
            padding: 12mm 14mm;
            background: #fff;
        }

        /* ── Header ── */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 3px solid {{ $accent }};
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .header-left h1 {
            font-size: 20px;
            font-weight: 800;
            color: {{ $accent }};
            letter-spacing: -0.5px;
        }
        .header-left .motto {
            font-size: 10px;
            color: #64748b;
            margin-top: 1px;
            font-style: italic;
        }
        .header-left p {
            font-size: 10px;
            color: #64748b;
            margin-top: 2px;
        }
        .header-right { text-align: right; }
        .term-badge {
            display: inline-block;
            background: {{ $accent }};
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            letter-spacing: 0.5px;
        }
        .year-label {
            font-size: 10px;
            color: #94a3b8;
            margin-top: 4px;
        }

        /* ── Student info card ── */
        .student-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }
        .student-info h2 {
            font-size: 15px;
            font-weight: 800;
            color: #1e293b;
        }
        .student-info p {
            font-size: 10px;
            color: #64748b;
            margin-top: 2px;
        }
        .student-meta {
            display: flex;
            gap: 24px;
            text-align: right;
        }
        .student-meta .label {
            font-size: 9px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .student-meta .value {
            font-size: 11px;
            font-weight: 700;
            color: #1e293b;
            margin-top: 1px;
        }

        /* ── Section headings ── */
        .section-title {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #64748b;
            margin-bottom: 6px;
            padding-bottom: 4px;
            border-bottom: 1px solid #e2e8f0;
        }

        /* ── Assessment table ── */
        .assessment-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .assessment-table th {
            background: #1e293b;
            color: #fff;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 6px 8px;
            text-align: left;
        }
        .assessment-table th.center { text-align: center; }
        .assessment-table td {
            padding: 6px 8px;
            font-size: 10px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .assessment-table tr:nth-child(even) td { background: #f8fafc; }
        .assessment-table td.center { text-align: center; }

        /* ── Subject header rows ── */
        .subject-header td {
            background: #1e1b4b !important;
            color: #fff;
            font-weight: 800;
            font-size: 10.5px;
            padding: 7px 8px;
            letter-spacing: 0.3px;
        }
        .subject-header td span {
            font-weight: 400;
            font-size: 9px;
            color: #a5b4fc;
            margin-left: 6px;
        }

        /* ── Subject average rows ── */
        .subject-summary td {
            background: #eef2ff !important;
            font-weight: 800;
            font-size: 10px;
            color: #3730a3;
            border-top: 1px solid #c7d2fe;
            border-bottom: 2px solid #c7d2fe;
        }

        /* ── Type header rows ── */
        .type-header td {
            background: #ede9fe !important;
            color: {{ $accent }};
            font-weight: 700;
            font-size: 10px;
            padding: 5px 8px;
        }
        .type-summary td {
            background: #f0fdf4 !important;
            font-weight: 700;
            font-size: 10px;
            color: #166534;
            border-top: 1px solid #bbf7d0;
        }

        /* ── Grade badge ── */
        .grade-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            font-size: 10px;
            font-weight: 800;
        }
        .grade-A { background: #dcfce7; color: #166534; }
        .grade-B { background: #ccfbf1; color: #115e59; }
        .grade-C { background: #dbeafe; color: #1e40af; }
        .grade-D { background: #fef9c3; color: #854d0e; }
        .grade-E { background: #ffedd5; color: #9a3412; }
        .grade-F { background: #fee2e2; color: #991b1b; }

        /* ── Progress bar ── */
        .bar-wrap {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .bar-bg {
            flex: 1;
            background: #e2e8f0;
            border-radius: 4px;
            height: 6px;
            overflow: hidden;
        }
        .bar-fill { height: 6px; border-radius: 4px; }
        .bar-pct { font-size: 9px; color: #64748b; white-space: nowrap; }

        /* ── Overall result box ── */
        .overall-box {
            background: #1e293b;
            color: #fff;
            border-radius: 8px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }
        .overall-box .label { font-size: 10px; color: #94a3b8; }
        .overall-box .big   { font-size: 22px; font-weight: 900; }
        .overall-box .grade-circle {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 900;
            border: 3px solid #fff;
        }

        /* ── Attendance + Conduct side by side ── */
        .att-conduct-grid {
            display: flex;
            gap: 12px;
            margin-bottom: 14px;
        }
        .att-section  { flex: 2; }
        .conduct-section { flex: 1; }

        /* ── Attendance cards ── */
        .attendance-grid {
            display: flex;
            gap: 8px;
            height: 100%;
        }
        .att-card {
            flex: 1;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px 6px;
            text-align: center;
        }
        .att-card .att-value {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 2px;
        }
        .att-card .att-label {
            font-size: 9px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .att-present .att-value { color: #16a34a; }
        .att-absent  .att-value { color: #dc2626; }
        .att-late    .att-value { color: #d97706; }
        .att-rate    .att-value { color: {{ $accent }}; }

        /* ── Conduct box ── */
        .conduct-box {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }
        .conduct-box .conduct-header {
            background: #1e293b;
            color: #fff;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 5px 10px;
        }
        .conduct-box .conduct-body {
            padding: 10px 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-align: center;
        }
        .conduct-grade-circle {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 900;
        }
        .conduct-desc {
            font-size: 9px;
            color: #64748b;
            line-height: 1.4;
        }

        /* ── Grading scale ── */
        .scale-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 9px;
        }
        .scale-table th {
            background: #f1f5f9;
            color: #64748b;
            padding: 4px 8px;
            text-align: center;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .scale-table td {
            padding: 4px 8px;
            text-align: center;
            border-bottom: 1px solid #f1f5f9;
        }

        /* ── Comments ── */
        .comment-box {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 8px;
            min-height: 44px;
        }
        .comment-box.form-teacher { background: #fefce8; border-color: #fde68a; }
        .comment-box.head-teacher { background: #f8fafc; border-color: #e2e8f0; }
        .comment-label {
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #94a3b8;
            margin-bottom: 4px;
        }
        .comment-box.form-teacher .comment-label { color: #92400e; }
        .comment-box p { font-size: 11px; color: #1e293b; line-height: 1.6; }
        .comment-box .no-comment { color: #94a3b8; font-style: italic; }
        .comment-sig {
            font-size: 8.5px;
            color: #94a3b8;
            font-style: italic;
            margin-top: 5px;
        }

        /* ── Footer ── */
        .footer {
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
            margin-top: 14px;
        }
        .footer-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .footer .generated { font-size: 9px; color: #94a3b8; }
        .footer .footer-note { font-size: 9px; color: #94a3b8; font-style: italic; margin-top: 4px; }
        .signature-line { text-align: center; }
        .signature-line .line {
            width: 100px;
            border-bottom: 1px solid #94a3b8;
            margin: 0 auto 4px;
        }
        .signature-line p { font-size: 9px; color: #64748b; }

        .signatures-row {
            display: flex;
            justify-content: space-around;
            margin-top: 18px;
        }

        /* ── No data ── */
        .no-data {
            text-align: center;
            padding: 20px;
            color: #94a3b8;
            font-size: 11px;
            font-style: italic;
        }
    </style>
</head>
<body>
<div class="page">

    {{-- ── HEADER ── --}}
    <div class="header">
        <div style="display:flex;align-items:center;gap:12px;">
            @if ($settings->school_logo && \Illuminate\Support\Facades\Storage::disk('public')->exists($settings->school_logo))
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($settings->school_logo) }}"
                     alt="School logo"
                     style="height:52px;width:auto;object-fit:contain;display:block;" />
            @endif
            <div class="header-left">
                <h1>{{ $settings->school_name ?? 'Student Report Card' }}</h1>
                @if ($settings->school_motto)
                    <div class="motto">{{ $settings->school_motto }}</div>
                @endif
                <p>{{ $classroom->name }} &bull; {{ $classroom->academic_year }}</p>
            </div>
        </div>
        <div class="header-right">
            <div class="term-badge">Term {{ $term }}</div>
            <div class="year-label">Academic Year {{ $academic_year }}</div>
        </div>
    </div>

    {{-- ── STUDENT INFO ── --}}
    <div class="student-card">
        <div class="student-info">
            <h2>{{ $student->full_name }}</h2>
            <p>{{ $student->register_name }}</p>
        </div>
        <div class="student-meta">
            <div>
                <div class="label">Class</div>
                <div class="value">{{ $classroom->name }}</div>
            </div>
            @if ($student->date_of_birth)
            <div>
                <div class="label">Date of Birth</div>
                <div class="value">{{ $student->date_of_birth->format('d M Y') }}</div>
            </div>
            @endif
            <div>
                <div class="label">Gender</div>
                <div class="value">{{ ucfirst($student->gender) }}</div>
            </div>
            <div>
                <div class="label">Form Teacher</div>
                <div class="value">{{ $classroom->teacher->name ?? '—' }}</div>
            </div>
        </div>
    </div>

    {{-- ── ASSESSMENT RESULTS ── --}}
    <div class="section-title">Assessment Results</div>

    @if (empty($bySubject))
        <div class="no-data">No assessments recorded for this term.</div>
    @else
        @php
            $typeLabels = [
                'test'       => 'Class Test',
                'exam'       => 'Examination',
                'assignment' => 'Assignment',
                'ca'         => 'Continuous Assessment',
                'other'      => 'Other',
            ];
            $barColors = [
                'A' => '#16a34a', 'B' => '#0d9488',
                'C' => '#2563eb', 'D' => '#ca8a04',
                'E' => '#ea580c', 'F' => '#dc2626',
            ];
        @endphp

        <table class="assessment-table">
            <thead>
                <tr>
                    <th>Subject / Assessment</th>
                    <th class="center">Score</th>
                    <th class="center">Max</th>
                    <th style="width:80px;">Progress</th>
                    <th class="center">%</th>
                    <th class="center">Grade</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($bySubject as $subject => $subjectData)

                    {{-- Subject heading --}}
                    <tr class="subject-header">
                        <td colspan="7">
                            {{ $subject }}
                            <span>{{ $subjectData['count'] }} {{ Str::plural('record', $subjectData['count']) }}</span>
                        </td>
                    </tr>

                    {{-- Types within this subject --}}
                    @foreach ($subjectData['byType'] as $type => $data)

                        <tr class="type-header">
                            <td colspan="7" style="padding-left:14px;">
                                {{ $typeLabels[$type] ?? ucfirst($type) }}
                                &nbsp;<span style="font-weight:400;font-size:9px;">({{ $data['count'] }} {{ Str::plural('record', $data['count']) }})</span>
                            </td>
                        </tr>

                        @foreach ($data['scores'] as $i => $score)
                        <tr>
                            <td style="padding-left:26px;color:#64748b;">Entry {{ $i + 1 }}</td>
                            <td class="center">{{ $score['score'] }}</td>
                            <td class="center">{{ $score['max_score'] }}</td>
                            <td>
                                <div class="bar-wrap">
                                    <div class="bar-bg">
                                        <div class="bar-fill" style="width:{{ min($score['percentage'], 100) }}%;background:{{ $barColors[$score['grade']] ?? '#94a3b8' }};"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="center">{{ $score['percentage'] }}%</td>
                            <td class="center">
                                <span class="grade-badge grade-{{ $score['grade'] }}">{{ $score['grade'] }}</span>
                            </td>
                            <td style="color:#64748b;font-size:9px;">{{ $score['remarks'] ?? '—' }}</td>
                        </tr>
                        @endforeach

                        <tr class="type-summary">
                            <td style="padding-left:26px;">{{ $typeLabels[$type] ?? ucfirst($type) }} Average</td>
                            <td class="center">—</td>
                            <td class="center">—</td>
                            <td>
                                <div class="bar-wrap">
                                    <div class="bar-bg">
                                        <div class="bar-fill" style="width:{{ min($data['average'], 100) }}%;background:{{ $barColors[$data['grade']] ?? '#94a3b8' }};"></div>
                                    </div>
                                    <span class="bar-pct">{{ $data['average'] }}%</span>
                                </div>
                            </td>
                            <td class="center">{{ $data['average'] }}%</td>
                            <td class="center">
                                <span class="grade-badge grade-{{ $data['grade'] }}">{{ $data['grade'] }}</span>
                            </td>
                            <td></td>
                        </tr>

                    @endforeach

                    {{-- Subject overall average --}}
                    <tr class="subject-summary">
                        <td style="padding-left:14px;">{{ $subject }} — Overall Average</td>
                        <td class="center">—</td>
                        <td class="center">—</td>
                        <td>
                            <div class="bar-wrap">
                                <div class="bar-bg">
                                    <div class="bar-fill" style="width:{{ min($subjectData['average'], 100) }}%;background:{{ $barColors[$subjectData['overallGrade']] ?? '#94a3b8' }};"></div>
                                </div>
                                <span class="bar-pct">{{ $subjectData['average'] }}%</span>
                            </div>
                        </td>
                        <td class="center">{{ $subjectData['average'] }}%</td>
                        <td class="center">
                            <span class="grade-badge grade-{{ $subjectData['overallGrade'] }}">{{ $subjectData['overallGrade'] }}</span>
                        </td>
                        <td></td>
                    </tr>

                @endforeach
            </tbody>
        </table>
    @endif

    {{-- ── OVERALL RESULT ── --}}
    @if ($overallAvg !== null)
    <div class="overall-box">
        <div>
            <div class="label">Overall Average (All Subjects)</div>
            <div class="big">{{ $overallAvg }}%</div>
        </div>
        <div style="text-align:center;">
            <div class="label" style="margin-bottom:6px;">Final Grade</div>
            <div class="grade-circle grade-{{ $overallGrade }}" style="margin:0 auto;">{{ $overallGrade }}</div>
        </div>
        <div style="text-align:right;">
            @php
                $overallRemark = match($overallGrade) {
                    'A' => 'Excellent',
                    'B' => 'Very Good',
                    'C' => 'Good',
                    'D' => 'Satisfactory',
                    'E' => 'Below Average',
                    default => 'Needs Improvement',
                };
            @endphp
            <div class="label">Performance</div>
            <div style="font-size:14px;font-weight:800;margin-top:4px;">{{ $overallRemark }}</div>
        </div>
    </div>
    @endif

    {{-- ── ATTENDANCE + CONDUCT ── --}}
    @if ($settings->show_attendance || $settings->show_conduct)
    <div class="section-title">
        @if ($settings->show_attendance && $settings->show_conduct)
            Attendance &amp; Conduct — {{ $academic_year }}
        @elseif ($settings->show_attendance)
            Attendance — {{ $academic_year }}
        @else
            Conduct
        @endif
    </div>
    <div class="att-conduct-grid">

        @if ($settings->show_attendance)
        <div class="att-section">
            <div class="attendance-grid">
                <div class="att-card att-present">
                    <div class="att-value">{{ $presentDays }}</div>
                    <div class="att-label">Present</div>
                </div>
                <div class="att-card att-absent">
                    <div class="att-value">{{ $absentDays }}</div>
                    <div class="att-label">Absent</div>
                </div>
                <div class="att-card att-late">
                    <div class="att-value">{{ $lateDays }}</div>
                    <div class="att-label">Late</div>
                </div>
                <div class="att-card att-rate">
                    <div class="att-value">{{ $attendanceRate !== null ? $attendanceRate . '%' : '—' }}</div>
                    <div class="att-label">Att. Rate</div>
                </div>
                <div class="att-card">
                    <div class="att-value" style="color:#475569;">{{ $totalDays }}</div>
                    <div class="att-label">Total Days</div>
                </div>
            </div>
        </div>
        @endif

        @if ($settings->show_conduct)
        <div class="conduct-section">
            <div class="conduct-box">
                <div class="conduct-header">Conduct</div>
                <div class="conduct-body">
                    @if ($conduct_grade)
                        @php
                            $conductDesc = match($conduct_grade) {
                                'A' => 'Excellent — Exemplary behaviour',
                                'B' => 'Very Good — Consistently well-behaved',
                                'C' => 'Good — Generally well-behaved',
                                'D' => 'Satisfactory — Some areas need improvement',
                                'E' => 'Needs Improvement — Behaviour is a concern',
                                'F' => 'Unsatisfactory — Urgent improvement required',
                                default => '',
                            };
                        @endphp
                        <div class="conduct-grade-circle grade-{{ $conduct_grade }}">{{ $conduct_grade }}</div>
                        <div class="conduct-desc">{{ $conductDesc }}</div>
                    @else
                        <div class="conduct-desc" style="font-style:italic;">Not recorded</div>
                    @endif
                </div>
            </div>
        </div>
        @endif

    </div>
    @endif

    {{-- ── GRADING SCALE ── --}}
    @if ($settings->show_grading_scale)
    <div class="section-title">ECZ Grading Scale</div>
    <table class="scale-table">
        <thead>
            <tr>
                @foreach (array_reverse(array_keys($gradingScale)) as $i => $threshold)
                    <th>Grade {{ array_values(array_reverse($gradingScale))[$i] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                @php
                    $thresholds = array_keys($gradingScale);
                    $ranges     = [];
                    foreach ($thresholds as $i => $t) {
                        $next = $thresholds[$i - 1] ?? 100;
                        $ranges[] = ($i === 0) ? "90–100%" : ($t === 0 ? "0–" . ($next - 1) . "%" : "{$t}–" . ($next - 1) . "%");
                    }
                    $ranges = array_reverse($ranges);
                @endphp
                @foreach ($ranges as $range)
                    <td>{{ $range }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>
    @endif

    {{-- ── COMMENTS ── --}}
    <div class="section-title">Comments</div>

    <div class="comment-box form-teacher">
        <div class="comment-label">Form Teacher's Comment</div>
        @if ($form_teacher_comment)
            <p>{{ $form_teacher_comment }}</p>
            <div class="comment-sig">— {{ $classroom->teacher->name ?? 'Form Teacher' }}</div>
        @else
            <p class="no-comment">No comment added.</p>
        @endif
    </div>

    <div class="comment-box head-teacher">
        <div class="comment-label">Head Teacher's Comment</div>
        @if ($head_teacher_comment)
            <p>{{ $head_teacher_comment }}</p>
            <div class="comment-sig">— Head Teacher</div>
        @else
            <p class="no-comment">No comment added.</p>
        @endif
    </div>

    {{-- ── FOOTER ── --}}
    <div class="footer">
        <div class="footer-top">
            <div class="generated">
                Generated: {{ $generated_at->format('d M Y, H:i') }}<br>
                {{ $classroom->name }} &bull; Term {{ $term }} &bull; {{ $academic_year }}
            </div>
            @if ($settings->show_signatures)
            <div style="display:flex;gap:32px;">
                <div class="signature-line">
                    <div class="line"></div>
                    <p>Form Teacher's Signature</p>
                </div>
                <div class="signature-line">
                    <div class="line"></div>
                    <p>Head Teacher's Signature</p>
                </div>
                <div class="signature-line">
                    <div class="line"></div>
                    <p>Parent / Guardian's Signature</p>
                </div>
            </div>
            @endif
        </div>
        @if ($settings->footer_note)
            <div class="footer-note">{{ $settings->footer_note }}</div>
        @endif
    </div>

</div>
</body>
</html>
