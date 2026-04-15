<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\InviteController;
use App\Http\Controllers\User\ClassTransferController;
use App\Http\Controllers\SchoolController;

Route::view('/', 'welcome', [
    'plans' => \App\Models\SubscriptionPlan::orderBy('sort_order')->get()
])->name('home');

// Public school lookup — used during registration (no auth required)
Route::get('/schools/search',        [SchoolController::class, 'search'])->name('schools.search');
Route::get('/schools/emis/{emis}',   [SchoolController::class, 'findByEmis'])->name('schools.find-by-emis');

// Authenticated school actions (no subscription check — needed before subscription is set up)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::patch('/dashboard/school/{school}', [SchoolController::class, 'assignToTeacher'])->name('dashboard.school.assign');
    Route::post('/dashboard/school',           [SchoolController::class, 'createAndAssign'])->name('dashboard.school.create');
});

Route::middleware(['auth', 'verified','subscribed'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('user/classrooms','pages::user.classes.class_room')->name('user.class.rooms');
    Route::livewire('user/students/{classId}','pages::user.classes.students')->name('user.students');
    Route::livewire('user/lesson-plans/{classId}','pages::user.classes.lesson-plans')->name('user.lesson.plans');
    Route::livewire('user/assesments/{classId}','pages::user.classes.assesments')->name('user.assesments');
    Route::livewire('user/attendance/{classId}','pages::user.classes.attendance')->name('user.attendance');
    Route::livewire('user/reports/{classId}', 'pages::user.classes.reports')->name('user.reports');
    Route::livewire('user/analytics/{classId}', 'pages::user.classes.analytics')->name('user.analytics');
    Route::livewire('user/reports/{classId}/customise', 'pages::user.classes.report-settings')->name('user.report-settings');

    // Renders the report card as plain HTML for iframe preview — no PDF
    Route::get('user/reports/{classId}/preview/{studentId?}', function (int $classId, ?int $studentId = null) {
        $classroom = \App\Models\ClassRoom::forTeacher(auth()->id())->findOrFail($classId);

        if (! $studentId) {
            $first = \App\Models\Student::forTeacher(auth()->id())
                ->inClass($classId)
                ->whereNull('deleted_at')
                ->alphabetical()
                ->first();

            abort_unless($first, 404, 'No students in this class.');
            $studentId = $first->id;
        }

        $term = (int) request('term', 1);
        $year = (int) request('year', date('Y'));

        $data = (new \App\Actions\BuildReportData)->execute($classId, $studentId, $term, $year, auth()->id());

        return view('reports.report-card', $data);
    })->name('user.report-preview');
    Route::livewire('user/behaviour-logs/{classId}','pages::user.classes.behaviour-logs')->name('user.behaviour-logs');

    // Printable attendance register — plain HTML, no Livewire
    Route::get('user/attendance/{classId}/register', function (int $classId) {
        $classroom = \App\Models\ClassRoom::forTeacher(auth()->id())->findOrFail($classId);

        $month = (int) request('month', date('n'));
        $year  = (int) request('year',  date('Y'));

        $students = \App\Models\Student::forTeacher(auth()->id())
            ->inClass($classId)
            ->whereNull('deleted_at')
            ->alphabetical()
            ->get();

        $attendance = \App\Models\Attendance::forClass($classId)
            ->inMonth($year, $month)
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->groupBy(fn ($a) => $a->student_id . '-' . $a->date->format('Y-m-d'));

        // Collect all dates that have at least one record in this month
        $dates = \App\Models\Attendance::forClass($classId)
            ->inMonth($year, $month)
            ->select('date')
            ->distinct()
            ->orderBy('date')
            ->pluck('date')
            ->map(fn ($d) => \Carbon\Carbon::parse($d));

        return view('reports.attendance-register', compact('classroom', 'students', 'attendance', 'dates', 'month', 'year'));
    })->name('user.attendance.register');
    Route::livewire('user/members/{classId}','pages::user.classes.members')->name('user.members');
    Route::livewire('user/gradebook/{classId}', 'pages::user.classes.gradebook')->name('user.gradebook');

    // Printable behaviour log — plain HTML, no Livewire
    Route::get('user/behaviour-logs/{classId}/print', function (int $classId) {
        $classroom = \App\Models\ClassRoom::forTeacher(auth()->id())->findOrFail($classId);
        $studentId = request()->integer('student_id') ?: null;

        $query = \App\Models\BehaviourLog::with('student')
            ->forTeacher(auth()->id())
            ->whereHas('student', fn ($q) => $q->where('class_id', $classId))
            ->recent();

        if ($studentId) {
            $query->forStudent($studentId);
        }

        $logs     = $query->get()->groupBy('student_id');
        $students = \App\Models\Student::forTeacher(auth()->id())
            ->inClass($classId)->whereNull('deleted_at')->alphabetical()->get()->keyBy('id');

        return view('reports.behaviour-log-print', compact('classroom', 'logs', 'students', 'studentId'));
    })->name('user.behaviour-logs.print');

    // Bulk ZIP download of all generated reports for a class/term/year
    Route::get('user/reports/{classId}/download-zip', function (int $classId) {
        $classroom  = \App\Models\ClassRoom::forTeacher(auth()->id())->findOrFail($classId);
        $term       = request()->integer('term', 1);
        $year       = request()->integer('year', (int) date('Y'));

        $reports = \App\Models\Report::forTeacher(auth()->id())
            ->whereIn('student_id', \App\Models\Student::forTeacher(auth()->id())
                ->inClass($classId)->whereNull('deleted_at')->pluck('id'))
            ->forTerm($term, $year)
            ->generated()
            ->whereNotNull('pdf_path')
            ->get();

        abort_if($reports->isEmpty(), 404, 'No generated reports found.');

        $zipPath = tempnam(sys_get_temp_dir(), 'reports_') . '.zip';
        $zip     = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);

        foreach ($reports as $report) {
            $full = \Illuminate\Support\Facades\Storage::disk('public')->path($report->pdf_path);
            if (file_exists($full)) {
                $zip->addFile($full, basename($full));
            }
        }

        $zip->close();

        $filename = "reports-{$classroom->name}-term{$term}-{$year}.zip";
        return response()->download($zipPath, $filename)->deleteFileAfterSend(true);
    })->name('user.reports.download-zip');
});

Route::middleware(['auth', 'verified', 'super_admin'])->group(function () {
    Route::livewire('admin/plans', 'pages::admin.plans')->name('admin.plans');
    Route::livewire('admin/users', 'pages::admin.users')->name('admin.users');
    Route::livewire('admin/subscriptions', 'pages::admin.subscriptions')->name('admin.subscriptions');
    Route::livewire('admin/payments', 'pages::admin.payments')->name('admin.payments');

    // User impersonation — constrained to numeric IDs so /stop never matches
    Route::post('/admin/impersonate/{user}', function (\App\Models\User $user) {
        abort_if($user->role === 'super_admin', 403, 'Cannot impersonate another admin.');

        session([
            'impersonating_id'          => $user->id,
            'impersonating_original_id' => auth()->id(),
            'impersonating_name'        => $user->name,
        ]);

        return redirect()->route('dashboard');
    })->whereNumber('user')->name('admin.impersonate.start');
});

// Stop impersonation — just remove the overlay; the admin's original session is untouched
Route::post('/admin/impersonate/stop', function () {
    session()->forget(['impersonating_id', 'impersonating_original_id', 'impersonating_name']);

    return redirect()->route('admin.users');
})->middleware(['auth', 'verified'])->name('admin.impersonate.stop');

Route::livewire('user/plans','pages::user.subscriptions.plans')->name('subscription.plans');

// Public shared report card — no auth required
Route::get('/share/report/{token}', function (string $token) {
    $report = \App\Models\Report::where('share_token', $token)
        ->whereNotNull('share_token')
        ->with('student')
        ->firstOrFail();

    $data = (new \App\Actions\BuildReportData)->execute(
        $report->student->class_id,
        $report->student_id,
        $report->term,
        $report->academic_year,
        $report->user_id,
    );

    return view('reports.report-card', $data);
})->name('report.shared');

Route::get('/invites/{token}/accept',  [InviteController::class, 'accept'])->name('invites.accept');
Route::get('/invites/{token}/decline', [InviteController::class, 'decline'])->name('invites.decline');

Route::get('/class-transfers/{token}/accept',  [ClassTransferController::class, 'accept'])->name('class-transfer.accept');
Route::get('/class-transfers/{token}/decline', [ClassTransferController::class, 'decline'])->name('class-transfer.decline');

require __DIR__.'/settings.php';
