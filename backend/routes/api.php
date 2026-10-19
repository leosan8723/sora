<?php

use App\Http\Controllers\Api\V1\Admin\AdminAppointmentController;
use App\Http\Controllers\Api\V1\Admin\AdminConsultantController;
use App\Http\Controllers\Api\V1\Admin\AdminConsultationTypeController;
use App\Http\Controllers\Api\V1\Admin\AdminContactMessageController;
use App\Http\Controllers\Api\V1\Admin\AdminReviewController;
use App\Http\Controllers\Api\V1\Admin\AdminReviewReportController;
use App\Http\Controllers\Api\V1\Admin\AdminSettingController;
use App\Http\Controllers\Api\V1\Admin\AdminSummaryController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ConsultantAvailabilityController;
use App\Http\Controllers\Api\V1\ConsultantConsultationTypeController;
use App\Http\Controllers\Api\V1\ConsultantController;
use App\Http\Controllers\Api\V1\ConsultantProfileController;
use App\Http\Controllers\Api\V1\ConsultantReviewController;
use App\Http\Controllers\Api\V1\ConsultantSpecialtyController;
use App\Http\Controllers\Api\V1\ContactMessageController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\PasswordSetupController;
use App\Http\Controllers\Api\V1\ReviewReportController;
use App\Http\Controllers\Api\V1\SpecialtyController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('api.v1.health');

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:auth-register')
        ->name('api.v1.auth.register');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:auth-login')
        ->name('api.v1.auth.login');

    Route::post('/password-setup', [PasswordSetupController::class, 'store'])
        ->middleware('throttle:auth-password')
        ->name('api.v1.auth.password-setup');

    Route::get('/email/verify', [EmailVerificationController::class, 'verify'])
        ->middleware('throttle:auth-password')
        ->name('api.v1.auth.email.verify');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        Route::put('/profile', [AuthController::class, 'updateProfile'])->name('api.v1.auth.profile');
        Route::put('/password', [AuthController::class, 'changePassword'])
            ->middleware('throttle:auth-password')
            ->name('api.v1.auth.password');

        Route::post('/email/resend', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:auth-email')
            ->name('api.v1.auth.email.resend');
    });
});

Route::get('/specialties', [SpecialtyController::class, 'index'])->name('api.v1.specialties.index');

Route::post('/contact-messages', [ContactMessageController::class, 'store'])
    ->middleware('throttle:contact')
    ->name('api.v1.contact.store');

Route::get('/consultants', [ConsultantController::class, 'index'])->name('api.v1.consultants.index');
Route::get('/consultants/{consultant}', [ConsultantController::class, 'show'])->name('api.v1.consultants.show');
Route::get('/consultants/{consultant}/slots', [ConsultantController::class, 'slots'])->name('api.v1.consultants.slots');

Route::get('/consultants/{consultant}/reviews', [ConsultantReviewController::class, 'index'])
    ->name('api.v1.consultants.reviews.index');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/consultants/{consultant}/reviews', [ConsultantReviewController::class, 'store'])
        ->middleware('throttle:reviews')
        ->name('api.v1.consultants.reviews.store');

    Route::get('/consultants/{consultant}/reviewable-appointments', [ConsultantReviewController::class, 'reviewableAppointments'])
        ->name('api.v1.consultants.reviews.reviewable');

    Route::put('/reviews/{review}', [ConsultantReviewController::class, 'update'])
        ->name('api.v1.reviews.update');

    Route::delete('/reviews/{review}', [ConsultantReviewController::class, 'destroy'])
        ->name('api.v1.reviews.destroy');

    Route::post('/reviews/{review}/report', [ReviewReportController::class, 'store'])
        ->middleware('throttle:review-reports')
        ->name('api.v1.reviews.report');
});

Route::middleware(['auth:sanctum', 'role:consultant'])
    ->prefix('consultant')
    ->group(function () {
        Route::get('/profile', [ConsultantProfileController::class, 'show'])
            ->name('api.v1.consultant.profile.show');
        Route::put('/profile', [ConsultantProfileController::class, 'update'])
            ->name('api.v1.consultant.profile.update');

        Route::put('/specialties', [ConsultantSpecialtyController::class, 'update'])
            ->name('api.v1.consultant.specialties.update');

        Route::get('/consultation-types', [ConsultantConsultationTypeController::class, 'index'])
            ->name('api.v1.consultant.types.index');
        Route::post('/consultation-types', [ConsultantConsultationTypeController::class, 'store'])
            ->name('api.v1.consultant.types.store');
        Route::put('/consultation-types/{consultationType}', [ConsultantConsultationTypeController::class, 'update'])
            ->name('api.v1.consultant.types.update');
        Route::delete('/consultation-types/{consultationType}', [ConsultantConsultationTypeController::class, 'destroy'])
            ->name('api.v1.consultant.types.destroy');

        Route::get('/availability', [ConsultantAvailabilityController::class, 'index'])
            ->name('api.v1.consultant.availability.index');
        Route::post('/availability', [ConsultantAvailabilityController::class, 'store'])
            ->name('api.v1.consultant.availability.store');
        Route::put('/availability/{availabilityRule}', [ConsultantAvailabilityController::class, 'update'])
            ->name('api.v1.consultant.availability.update');
        Route::delete('/availability/{availabilityRule}', [ConsultantAvailabilityController::class, 'destroy'])
            ->name('api.v1.consultant.availability.destroy');
    });

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/appointments', [AppointmentController::class, 'index'])
        ->name('api.v1.appointments.index');

    Route::post('/appointments', [AppointmentController::class, 'store'])
        ->middleware(['role:client', 'throttle:booking'])
        ->name('api.v1.appointments.store');

    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show'])
        ->name('api.v1.appointments.show');

    Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])
        ->name('api.v1.appointments.cancel');

    Route::post('/appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])
        ->middleware('role:client')
        ->name('api.v1.appointments.reschedule');

    Route::post('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])
        ->name('api.v1.appointments.status');
});

/*
|--------------------------------------------------------------------------
| Admin panel routes
|--------------------------------------------------------------------------
|
| Every route is authenticated and role-restricted. The controllers add
| their own authorize() checks so a stray middleware change cannot open
| them up.
|
*/

Route::middleware(['auth:sanctum', 'role:admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/summary', AdminSummaryController::class)
            ->name('api.v1.admin.summary');

        Route::get('/users', [AdminUserController::class, 'index'])
            ->name('api.v1.admin.users.index');
        Route::post('/users', [AdminUserController::class, 'store'])
            ->name('api.v1.admin.users.store');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])
            ->name('api.v1.admin.users.update');
        Route::post('/users/{user}/password-link', [AdminUserController::class, 'passwordLink'])
            ->middleware('throttle:admin-actions')
            ->name('api.v1.admin.users.password-link');

        Route::get('/consultants', [AdminConsultantController::class, 'index'])
            ->name('api.v1.admin.consultants.index');
        Route::put('/consultants/{consultant}', [AdminConsultantController::class, 'update'])
            ->name('api.v1.admin.consultants.update');

        Route::get('/appointments', [AdminAppointmentController::class, 'index'])
            ->name('api.v1.admin.appointments.index');

        Route::get('/consultation-types', [AdminConsultationTypeController::class, 'index'])
            ->name('api.v1.admin.consultation-types.index');
        Route::put('/consultation-types/{consultationType}', [AdminConsultationTypeController::class, 'update'])
            ->name('api.v1.admin.consultation-types.update');

        Route::get('/settings', [AdminSettingController::class, 'show'])
            ->name('api.v1.admin.settings.show');
        Route::put('/settings', [AdminSettingController::class, 'update'])
            ->name('api.v1.admin.settings.update');

        Route::get('/contact-messages', [AdminContactMessageController::class, 'index'])
            ->name('api.v1.admin.contact-messages.index');
        Route::put('/contact-messages/{contactMessage}', [AdminContactMessageController::class, 'update'])
            ->name('api.v1.admin.contact-messages.update');

        Route::get('/reviews', [AdminReviewController::class, 'index'])
            ->name('api.v1.admin.reviews.index');
        Route::put('/reviews/{review}', [AdminReviewController::class, 'update'])
            ->name('api.v1.admin.reviews.update');
        Route::delete('/reviews/{review}', [AdminReviewController::class, 'destroy'])
            ->name('api.v1.admin.reviews.destroy');

        Route::get('/review-reports', [AdminReviewReportController::class, 'index'])
            ->name('api.v1.admin.review-reports.index');
        Route::post('/review-reports/{report}/resolve', [AdminReviewReportController::class, 'resolve'])
            ->name('api.v1.admin.review-reports.resolve');
        Route::post('/review-reports/{report}/dismiss', [AdminReviewReportController::class, 'dismiss'])
            ->name('api.v1.admin.review-reports.dismiss');
    });
