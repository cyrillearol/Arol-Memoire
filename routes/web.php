<?php

use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\ReportModerationController;
use App\Http\Controllers\Admin\TutorModerationController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CallSignalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\StudentBookingController;
use App\Http\Controllers\TutorController;
use App\Http\Controllers\TutorWorkspaceController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/tuteurs', [TutorController::class, 'index'])->name('tutors.index');
Route::get('/tuteurs/{tutor}', [TutorController::class, 'show'])->name('tutors.show');

Broadcast::routes(['middleware' => ['web', 'auth']]);
require __DIR__.'/channels.php';

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/mes-reservations', [StudentBookingController::class, 'index'])->name('bookings.index');
    Route::get('/historique', [StudentBookingController::class, 'history'])->name('bookings.history');
    Route::get('/reservations/{booking}', [StudentBookingController::class, 'show'])->name('bookings.show');

    Route::get('/tuteurs/{tutor}/reserver', [BookingController::class, 'create'])->name('bookings.create');
    Route::post('/tuteurs/{tutor}/reserver', [BookingController::class, 'store'])->name('bookings.store');
    Route::patch('/reservations/{booking}/accepter', [BookingController::class, 'accept'])->name('bookings.accept');
    Route::patch('/reservations/{booking}/refuser', [BookingController::class, 'refuse'])->name('bookings.refuse');
    Route::patch('/reservations/{booking}/annuler', [BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::patch('/reservations/{booking}/terminer', [BookingController::class, 'complete'])->name('bookings.complete');
    Route::post('/reservations/{booking}/avis', [ReviewController::class, 'store'])->name('reviews.store');

    Route::get('/tuteur/disponibilites', [TutorWorkspaceController::class, 'availabilities'])->name('tutor.availabilities');
    Route::get('/tuteur/demandes', [TutorWorkspaceController::class, 'requests'])->name('tutor.requests');
    Route::get('/tuteur/evaluations', [TutorWorkspaceController::class, 'reviews'])->name('tutor.reviews');

    Route::post('/disponibilites', [AvailabilityController::class, 'store'])->name('availabilities.store');
    Route::patch('/disponibilites/{availability}', [AvailabilityController::class, 'update'])->name('availabilities.update');
    Route::delete('/disponibilites/{availability}', [AvailabilityController::class, 'destroy'])->name('availabilities.destroy');

    Route::get('/messages/documents/{message}', [MessageController::class, 'downloadAttachment'])->name('messages.attachments.show');
    Route::get('/messages/{conversation?}', [MessageController::class, 'index'])->name('messages.index');
    Route::post('/messages/{conversation}', [MessageController::class, 'store'])->name('messages.store');
    Route::post('/messages/{conversation}/appel', [CallSignalController::class, 'store'])->name('calls.signal');

    Route::post('/signalements', [ReportController::class, 'store'])->name('reports.store');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{notification}/lu', [NotificationController::class, 'read'])->name('notifications.read');
    Route::patch('/notifications-lues', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/tuteurs', [UserManagementController::class, 'tutors'])->name('tutors.index');
        Route::get('/etudiants', [UserManagementController::class, 'students'])->name('students.index');
        Route::get('/utilisateurs/{user}', [UserManagementController::class, 'show'])->name('users.show');
        Route::patch('/utilisateurs/{user}/activer', [UserManagementController::class, 'activate'])->name('users.activate');
        Route::patch('/utilisateurs/{user}/suspendre', [UserManagementController::class, 'suspend'])->name('users.suspend');

        Route::get('/signalements', [AdminReportController::class, 'index'])->name('reports.index');
        Route::patch('/signalements/{report}', [ReportModerationController::class, 'update'])->name('reports.update');

        Route::patch('/tuteurs/{tutor}/valider', [TutorModerationController::class, 'accept'])->name('tutors.accept');
        Route::patch('/tuteurs/{tutor}/rejeter', [TutorModerationController::class, 'reject'])->name('tutors.reject');
        Route::patch('/tuteurs/{tutor}/suspendre', [TutorModerationController::class, 'suspend'])->name('tutors.suspend');
        Route::get('/documents-tuteurs/{document}', [TutorModerationController::class, 'downloadDocument'])->name('tutor-documents.download');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
