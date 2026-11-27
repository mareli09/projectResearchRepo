<?php

use App\Http\Controllers\AccessRequestController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\EvaluatorController;
use App\Http\Controllers\Admin\HealthController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AiPerformanceController;
use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\ClusteringController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Design\AdviserController;
use App\Http\Controllers\Design\CoordinatorController;
use App\Http\Controllers\Design\SiteContentController;
use App\Http\Controllers\Design\StudentController;
use App\Http\Controllers\Design\UrdcController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\RecordFileController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\Urdc\ImportController;
use App\Http\Controllers\Urdc\RecordController;
use App\Models\AcademicUnit;
use App\Models\Program;
use App\Models\Sdg;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

/*
| Public: landing page (§8), public catalog of approved titles and abstracts (§7 M4).
*/
Route::get('/', [SiteController::class, 'home'])->name('home');
Route::get('/catalog', [SiteController::class, 'catalog'])->name('catalog');
Route::get('/catalog/{publicId}', [SiteController::class, 'catalogShow'])->name('catalog.show');

// Design system reference (components and tokens). Local and testing only.
if (app()->environment(['local', 'testing'])) {
    Route::get('/styleguide', fn () => view('site.start', [
        'units' => AcademicUnit::count(), 'programs' => Program::count(), 'sdgs' => Sdg::count(), 'roles' => Role::count(),
    ]))->name('styleguide');
}

/*
| M1 Authentication (CLAUDE.md §7). Sign in and sign out come from Fortify (/login, /logout).
*/
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/register/done', [RegisterController::class, 'done'])->name('register.done');

    Route::get('/activate/{token}', [ActivationController::class, 'show'])->name('activation.show');
    Route::post('/activate/{token}', [ActivationController::class, 'store'])->name('activation.store');
    Route::post('/activation/resend', [ActivationController::class, 'resend'])->middleware('throttle:recovery')->name('activation.resend');

    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'storeEmail'])->name('password.email');
    Route::get('/forgot-password/channel', [ForgotPasswordController::class, 'channel'])->name('password.channel');
    Route::post('/forgot-password/channel', [ForgotPasswordController::class, 'sendCode'])->middleware('throttle:recovery')->name('password.send-code');
    Route::get('/forgot-password/code', [ForgotPasswordController::class, 'code'])->name('password.code');
    Route::post('/forgot-password/code', [ForgotPasswordController::class, 'reset'])->middleware('throttle:recovery')->name('password.code.reset');

    // One-time link from assisted recovery
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->middleware('throttle:recovery')->name('password.update.link');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Account and recovery (every role)
    Route::get('/account', [AccountController::class, 'show'])->name('account');
    Route::post('/account/theme', [ThemeController::class, 'update'])->middleware('throttle:30,1')->name('account.theme');
    Route::prefix('/account/recovery/{kind}')->where(['kind' => AccountController::kindPattern()])->group(function () {
        Route::post('/', [AccountController::class, 'storeContact'])->middleware('throttle:recovery')->name('account.contact.store');
        Route::post('/resend', [AccountController::class, 'resendCode'])->middleware('throttle:recovery')->name('account.contact.resend');
        Route::post('/verify', [AccountController::class, 'verifyContact'])->middleware('throttle:recovery')->name('account.contact.verify');
        Route::delete('/', [AccountController::class, 'destroyContact'])->name('account.contact.destroy');
    });

    // Users and roles (M1, M9)
    Route::middleware('permission:users.assist')->prefix('/admin/users')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('admin.users');
        Route::post('/{user}/resend-activation', [UserController::class, 'resendActivation'])->name('admin.users.resend');
        Route::post('/{user}/assisted-recovery', [UserController::class, 'assistedRecovery'])->name('admin.users.assist');

        Route::middleware('permission:users.manage')->group(function () {
            Route::put('/{user}/role', [UserController::class, 'updateRole'])->name('admin.users.role');
            Route::post('/{user}/deactivate', [UserController::class, 'deactivate'])->name('admin.users.deactivate');
            Route::post('/{user}/reactivate', [UserController::class, 'reactivate'])->name('admin.users.reactivate');
        });
    });

    // M2 Records and metadata, bulk import (URDC)
    Route::middleware('permission:records.manage')->prefix('/urdc')->group(function () {
        Route::get('/records', [RecordController::class, 'index'])->name('urdc.records');
        Route::get('/records/{record}', [RecordController::class, 'edit'])->withTrashed()->name('urdc.records.edit');
        Route::put('/records/{record}', [RecordController::class, 'update'])->name('urdc.records.update');
        Route::post('/records/{record}/files/{slot}', [RecordController::class, 'upload'])->name('urdc.records.upload');
        Route::post('/records/{record}/imrad', [RecordController::class, 'imrad'])->name('urdc.records.imrad');
        Route::post('/records/{record}/publish', [RecordController::class, 'publish'])->name('urdc.records.publish');
        Route::post('/records/{record}/withdraw', [RecordController::class, 'withdraw'])->name('urdc.records.withdraw');

        Route::get('/import', [ImportController::class, 'create'])->name('urdc.import');
        Route::get('/import/template.{format}', [ImportController::class, 'template'])->whereIn('format', ['csv', 'xlsx'])->name('urdc.import.template');
        Route::post('/import/preview', [ImportController::class, 'preview'])->name('urdc.import.preview');
        Route::get('/import/{token}', [ImportController::class, 'show'])->whereUuid('token')->name('urdc.import.show');
        Route::post('/import/{token}', [ImportController::class, 'store'])->whereUuid('token')->name('urdc.import.store');
    });

    // Downloads: signed, expiring link + permission check + log (§1 rule 9)
    Route::get('/files/{file}', [RecordFileController::class, 'download'])->middleware('signed')->name('files.download');

    /*
    | Design screens (all roles). Pages read real data where it exists; actions that are not
    | built yet are marked "Design preview" and name their sprint (CLAUDE.md §18).
    */
    Route::get('/search', [LibraryController::class, 'search'])->name('search');
    Route::get('/studies/{publicId}', [LibraryController::class, 'study'])->name('studies.show');
    Route::post('/studies/{publicId}/request', [AccessRequestController::class, 'store'])
        ->middleware(['role:'.User::ROLE_STUDENT, 'throttle:10,1'])->name('studies.request');
    Route::get('/notifications', [LibraryController::class, 'notifications'])->name('notifications');
    Route::post('/notifications/read', [LibraryController::class, 'markAllRead'])->name('notifications.read');

    // M3 Submission and approval workflow (live from Sprint 3). Each action is also checked by SubmissionPolicy.
    Route::middleware('role:'.User::ROLE_STUDENT)->prefix('/student')->name('student.')->group(function () {
        Route::get('/', [StudentController::class, 'home'])->name('home');
        Route::get('/submit', [StudentController::class, 'submit'])->name('submit');
        Route::post('/submit', [StudentController::class, 'store'])->middleware('throttle:10,1')->name('store');
        Route::post('/suggest', [StudentController::class, 'suggest'])->middleware('throttle:llm')->name('suggest');
        Route::get('/status', [StudentController::class, 'status'])->name('status');
        Route::post('/resubmit', [StudentController::class, 'resubmit'])->middleware('throttle:10,1')->name('resubmit');
        Route::get('/summary', [StudentController::class, 'summary'])->name('summary');
    });

    Route::middleware('role:'.User::ROLE_ADVISER)->prefix('/adviser')->name('adviser.')->group(function () {
        Route::get('/', [AdviserController::class, 'queue'])->name('queue');
        Route::get('/review', [AdviserController::class, 'review'])->name('review');
        Route::post('/review', [AdviserController::class, 'decide'])->name('decide');
        Route::get('/supervised', [AdviserController::class, 'supervised'])->name('supervised');
    });

    Route::middleware('role:'.User::ROLE_COORDINATOR)->prefix('/coordinator')->name('coordinator.')->group(function () {
        Route::get('/', [CoordinatorController::class, 'queue'])->name('queue');
        Route::get('/screening', [CoordinatorController::class, 'screening'])->name('screening');
        Route::post('/screening', [CoordinatorController::class, 'decide'])->name('decide');
        Route::get('/themes', [ClusteringController::class, 'themes'])->name('themes');
        Route::get('/analytics', [CoordinatorController::class, 'analytics'])->name('analytics');
        Route::get('/analytics/export/{format}', [CoordinatorController::class, 'export'])->where('format', 'xlsx|csv|pdf')->middleware('throttle:20,1')->name('analytics.export');
    });

    Route::middleware('role:'.User::ROLE_URDC)->prefix('/urdc')->name('urdc.')->group(function () {
        Route::get('/analytics', [UrdcController::class, 'analytics'])->name('analytics');
        Route::get('/analytics/export/{format}', [UrdcController::class, 'export'])->where('format', 'xlsx|csv|pdf')->middleware('throttle:20,1')->name('analytics.export');
        Route::get('/approvals', [UrdcController::class, 'approvals'])->name('approvals');
        Route::post('/approvals', [UrdcController::class, 'decide'])->name('decide');
        Route::get('/requests', [AccessRequestController::class, 'index'])->name('requests');
        Route::post('/requests/{accessRequest}', [AccessRequestController::class, 'decide'])->name('requests.decide');
        // M6 Thematic clustering (live from Sprint 6): the run is a queue job, never part of the request.
        Route::get('/clustering', [ClusteringController::class, 'index'])->name('clustering');
        Route::post('/clustering', [ClusteringController::class, 'run'])->middleware('throttle:6,1')->name('clustering.run');
        Route::post('/clustering/{run}/confirm', [ClusteringController::class, 'confirm'])->name('clustering.confirm');
        Route::get('/ai-performance', [AiPerformanceController::class, 'index'])->name('ai');
        Route::get('/ai-performance/export', [AiPerformanceController::class, 'export'])->name('ai.export');
    });

    Route::middleware('role:'.User::ROLE_SYSADMIN)->prefix('/admin')->name('admin.')->group(function () {
        Route::get('/health', [HealthController::class, 'health'])->name('health');
        Route::post('/jobs/retry', [HealthController::class, 'retryFailed'])->name('jobs.retry');
        Route::get('/services', [HealthController::class, 'services'])->name('services');
        Route::post('/services/{service}/test', [HealthController::class, 'test'])->where('service', 'analytics|llm|mail')
            ->middleware('throttle:10,1')->name('services.test');
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings');
        Route::post('/settings/{group}', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('/backups', [BackupController::class, 'index'])->name('backups');
        Route::post('/backups', [BackupController::class, 'store'])->middleware('throttle:6,1')->name('backups.run');
        Route::post('/backups/{file}/restore', [BackupController::class, 'restore'])->where('file', '[\w.-]+\.zip')
            ->middleware('throttle:6,1')->name('backups.restore');
        Route::get('/evaluators', [EvaluatorController::class, 'index'])->name('evaluators');
        Route::post('/evaluators', [EvaluatorController::class, 'store'])->middleware('throttle:20,1')->name('evaluators.store');
        Route::post('/evaluators/{user}/end', [EvaluatorController::class, 'end'])->name('evaluators.end');
    });

    // Theme labels: coordinators and the URDC accept, edit or reject LLM label suggestions (§4, §7 M6).
    Route::middleware('role:'.User::ROLE_COORDINATOR.'|'.User::ROLE_URDC)->group(function () {
        Route::get('/themes', [ClusteringController::class, 'themes'])->name('themes.review');
        Route::post('/themes/{cluster}', [ClusteringController::class, 'label'])->name('themes.label');
    });

    // [Not in manuscript] Landing page CMS: URDC and System administrator (§8)
    Route::middleware('role:'.User::ROLE_URDC.'|'.User::ROLE_SYSADMIN)->prefix('/site-content')->group(function () {
        Route::get('/', [SiteContentController::class, 'edit'])->name('site.content');
        Route::post('/layout', [SiteContentController::class, 'layout'])->name('site.content.layout');
        Route::post('/publish', [SiteContentController::class, 'publish'])->name('site.content.publish');
        Route::post('/discard', [SiteContentController::class, 'discard'])->name('site.content.discard');
        Route::post('/defaults', [SiteContentController::class, 'defaults'])->name('site.content.defaults');
        Route::post('/{tab}', [SiteContentController::class, 'save'])->where('tab', '[a-z]+')->name('site.content.save');
    });

    // Activity log (M8)
    Route::middleware('permission:activity.view')->group(function () {
        Route::get('/activity', [ActivityLogController::class, 'index'])->name('activity');
        Route::get('/activity/export', [ActivityLogController::class, 'export'])->name('activity.export');
    });
});
