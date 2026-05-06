<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\DashboardController;
// Admin sub-controllers referenced via Admin\ControllerName syntax
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Subcontractor;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

Route::middleware(['auth'])->group(function () {
    Route::patch('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::patch('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
});

Route::middleware(['auth', 'verified', 'role:super_admin,admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('main-contractors', [Admin\MainContractorController::class, 'index'])->name('main-contractors.index');
    Route::post('main-contractors', [Admin\MainContractorController::class, 'store'])->name('main-contractors.store');

    Route::get('clients', [Admin\ClientController::class, 'index'])->name('clients.index');
    Route::post('clients', [Admin\ClientController::class, 'store'])->name('clients.store');

    Route::get('projects', [Admin\ProjectController::class, 'index'])->name('projects.index');
    Route::post('projects', [Admin\ProjectController::class, 'store'])->name('projects.store');
    Route::get('projects/{project}', [Admin\ProjectController::class, 'show'])->name('projects.show');

    Route::get('subcontractors', [Admin\SubcontractorController::class, 'index'])->name('subcontractors.index');
    Route::post('subcontractors', [Admin\SubcontractorController::class, 'store'])->name('subcontractors.store');

    Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::post('users', [Admin\UserController::class, 'store'])->name('users.store');

    Route::get('assignments', [Admin\AssignmentController::class, 'index'])->name('assignments.index');
    Route::get('assignments/sites/{site}', [Admin\AssignmentController::class, 'siteAssignments'])->name('assignments.site-assignments');
    Route::post('assignments/sites/{site}/assign', [Admin\AssignmentController::class, 'storeForSite'])->name('assignments.site-assign');
    Route::delete('assignments/{assignment}', [Admin\AssignmentController::class, 'destroy'])->name('assignments.destroy');
    Route::get('assignments/{assignment}', [Admin\AssignmentController::class, 'show'])->name('assignments.show');
    Route::post('assignments/{assignment}/verify', [Admin\AssignmentController::class, 'verify'])->name('assignments.verify');
    Route::post('assignments/{assignment}/revise', [Admin\AssignmentController::class, 'revise'])->name('assignments.revise');
    Route::patch('assignments/{assignment}/drop', [Admin\AssignmentController::class, 'drop'])->name('assignments.drop');
    Route::patch('assignments/{assignment}/restore', [Admin\AssignmentController::class, 'restore'])->name('assignments.restore');
    Route::patch('assignments/{assignment}/survey-parking-slot', [Admin\AssignmentController::class, 'updateSurveyParkingSlot'])->name('assignments.survey-parking-slot');
    Route::patch('assignments/{assignment}/construction-prerequisite', [Admin\AssignmentController::class, 'updateConstructionPrerequisite'])->name('assignments.construction-prerequisite');
    Route::get('assignments/{assignment}/bast-report', [Admin\AssignmentController::class, 'downloadBastReport'])->name('assignments.bast-report');
    Route::post('projects/{project}/import-sites', [Admin\SiteImportController::class, 'store'])->name('projects.import-sites');
    Route::get('projects/{project}/import-sites/template', [Admin\SiteImportController::class, 'template'])->name('projects.import-sites.template');
    Route::post('projects/{project}/import-assignments', [Admin\AssignmentImportController::class, 'store'])->name('projects.import-assignments');
    Route::get('projects/{project}/import-assignments/template', [Admin\AssignmentImportController::class, 'template'])->name('projects.import-assignments.template');

    Route::get('sites/{site}/edit', [Admin\SiteController::class, 'edit'])->name('sites.edit');
    Route::patch('sites/{site}', [Admin\SiteController::class, 'update'])->name('sites.update');

    Route::patch('assignments/{assignment}/admin-survey', [Admin\AssignmentController::class, 'updateSurveyData'])->name('assignments.admin-survey');
    Route::patch('assignments/{assignment}/admin-pln', [Admin\AssignmentController::class, 'updatePlnData'])->name('assignments.admin-pln');
    Route::patch('assignments/{assignment}/admin-construction', [Admin\AssignmentController::class, 'updateConstructionSubconData'])->name('assignments.admin-construction');
    Route::patch('assignments/{assignment}/admin-bast', [Admin\AssignmentController::class, 'updateBastData'])->name('assignments.admin-bast');
    Route::patch('assignments/{assignment}/reassign', [Admin\AssignmentController::class, 'reassign'])->name('assignments.reassign');

    Route::get('reports', [Admin\ReportController::class, 'index'])->name('reports.index');
    Route::post('reports', [Admin\ReportController::class, 'store'])->name('reports.store');
    Route::get('reports/{report}/download', [Admin\ReportController::class, 'download'])->name('reports.download');
});

Route::middleware(['auth', 'verified', 'role:subcontractor'])->prefix('subcontractor')->name('subcontractor.')->group(function () {
    Route::get('assignments', [Subcontractor\AssignmentController::class, 'index'])->name('assignments.index');
    Route::get('assignments/{assignment}', [Subcontractor\AssignmentController::class, 'show'])->name('assignments.show');
    Route::patch('assignments/{assignment}/survey', [Subcontractor\AssignmentController::class, 'updateSurveyData'])->name('assignments.survey');
    Route::patch('assignments/{assignment}/pln', [Subcontractor\AssignmentController::class, 'updatePlnData'])->name('assignments.pln');
    Route::patch('assignments/{assignment}/construction', [Subcontractor\AssignmentController::class, 'updateConstructionData'])->name('assignments.construction');
    Route::post('assignments/{assignment}/construction/photos', [Subcontractor\AssignmentController::class, 'storeConstructionPhoto'])->name('assignments.construction.photos');
    Route::delete('assignments/{assignment}/construction/photos/{photo}', [Subcontractor\AssignmentController::class, 'destroyConstructionPhoto'])->name('assignments.construction.photos.destroy');
    Route::patch('assignments/{assignment}/bast', [Subcontractor\AssignmentController::class, 'updateBastData'])->name('assignments.bast');
    Route::post('assignments/{assignment}/bast/photos', [Subcontractor\AssignmentController::class, 'storeBastPhoto'])->name('assignments.bast.photos');
    Route::delete('assignments/{assignment}/bast/photos/{photo}', [Subcontractor\AssignmentController::class, 'destroyBastPhoto'])->name('assignments.bast.photos.destroy');
});

require __DIR__.'/settings.php';
