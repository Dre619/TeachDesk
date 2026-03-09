<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\InviteController;

Route::view('/', 'welcome', [
    'plans' => \App\Models\SubscriptionPlan::orderBy('sort_order')->get()
])->name('home');

Route::middleware(['auth', 'verified','subscribed'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('user/classrooms','pages::user.classes.class_room')->name('user.class.rooms');
    Route::livewire('user/students/{classId}','pages::user.classes.students')->name('user.students');
    Route::livewire('user/lesson-plans/{classId}','pages::user.classes.lesson-plans')->name('user.lesson.plans');
    Route::livewire('user/assesments/{classId}','pages::user.classes.assesments')->name('user.assesments');
    Route::livewire('user/attendance/{classId}','pages::user.classes.attendance')->name('user.attendance');
    Route::livewire('user/reports/{classId}','pages::user.classes.reports')->name('user.reports');
    Route::livewire('user/behaviour-logs/{classId}','pages::user.classes.behaviour-logs')->name('user.behaviour-logs');
    Route::livewire('user/members/{classId}','pages::user.classes.members')->name('user.members');
});

Route::livewire('admin/plans','pages::admin.plans')->name('admin.plans');

Route::livewire('user/plans','pages::user.subscriptions.plans')->name('subscription.plans');

Route::get('/invites/{token}/accept',  [InviteController::class, 'accept'])->name('invites.accept');
Route::get('/invites/{token}/decline', [InviteController::class, 'decline'])->name('invites.decline');

require __DIR__.'/settings.php';
