<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\HierarchyController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicTicketController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/quick-switch/{user}', [AuthController::class, 'quickSwitch'])->name('auth.quick-switch');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/lacak', [PublicTicketController::class, 'form'])->name('tickets.track');
Route::post('/lacak', [PublicTicketController::class, 'search'])->name('tickets.track.search');
Route::get('/lacak/{ticketNumber}', [PublicTicketController::class, 'show'])->name('tickets.track.show');

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Profil Pengguna & Unggah Foto
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/users/{user}/avatar', [ProfileController::class, 'avatar'])->name('users.avatar');

    // Modul Tiketing
    Route::get('/tickets/my', [TicketController::class, 'myTickets'])->name('tickets.my');
    Route::get('/tickets/history', [TicketController::class, 'historyTickets'])->name('tickets.history');
    Route::get('/tickets/inbox', [TicketController::class, 'inbox'])->name('tickets.inbox');
    Route::get('/tickets/staff-progress', [TicketController::class, 'staffProgressPage'])->name('tickets.staff-progress');
    Route::get('/tickets/report', [TicketController::class, 'monthlyReport'])->name('tickets.report');
    Route::get('/tickets/report/export', [TicketController::class, 'exportMonthlyReport'])->name('tickets.report.export');
    Route::get('/tickets/workload', [TicketController::class, 'divisionWorkload'])->name('tickets.workload');
    Route::get('/tickets/calendar', [TicketController::class, 'calendar'])->name('tickets.calendar');
    Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy'])->name('tickets.destroy');
    Route::get('/tickets/{ticket}/preview', [TicketController::class, 'previewAttachment'])->name('tickets.preview');
    Route::get('/tickets/{ticket}/attachment', [TicketController::class, 'downloadAttachment'])->name('tickets.attachment');
    Route::get('/tickets/{ticket}/proof', [TicketController::class, 'previewProof'])->name('tickets.proof');
    Route::get('/tickets/{ticket}/proof/download', [TicketController::class, 'downloadProof'])->name('tickets.proof.download');
    Route::get('/categories/{category}/template/preview', [TicketController::class, 'previewCategoryTemplate'])->name('categories.template.preview');
    Route::get('/categories/{category}/template/download', [TicketController::class, 'downloadCategoryTemplate'])->name('categories.template.download');
    Route::post('/tickets/{ticket}/problems', [TicketController::class, 'storeProblems'])->name('tickets.problems.store');
    Route::delete('/tickets/{ticket}/problems/{problem}', [TicketController::class, 'destroyProblem'])->name('tickets.problems.destroy');
    Route::post('/tickets/{ticket}/problems/{problem}/toggle', [TicketController::class, 'toggleProblem'])->name('tickets.problems.toggle');
    Route::post('/tickets/{ticket}/progress', [TicketController::class, 'updateProgress'])->name('tickets.progress');
    Route::post('/tickets/{ticket}/achievement', [TicketController::class, 'updateAchievement'])->name('tickets.achievement');
    Route::post('/tickets/{ticket}/due-date', [TicketController::class, 'updateDueDate'])->name('tickets.due-date');
    Route::post('/tickets/{ticket}/priority', [TicketController::class, 'updatePriority'])->name('tickets.priority');
    Route::post('/tickets/{ticket}/action', [TicketController::class, 'handleAction'])->name('tickets.action');
    Route::post('/tickets/{ticket}/reopen', [TicketController::class, 'reopen'])->name('tickets.reopen');
    Route::post('/tickets/{ticket}/comment', [TicketController::class, 'addComment'])->name('tickets.comment');

    // Modul Manajemen Superuser
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', fn () => redirect()->route('admin.users'))->name('index');

        Route::get('/hierarchy', [HierarchyController::class, 'index'])->name('hierarchy');
        Route::post('/hierarchy/roles', [HierarchyController::class, 'storeRole'])->name('hierarchy.store');
        Route::post('/hierarchy/divisions', [HierarchyController::class, 'storeDivision'])->name('hierarchy.divisions.store');
        Route::put('/hierarchy/divisions/{division}', [HierarchyController::class, 'updateDivision'])->name('hierarchy.divisions.update');
        Route::delete('/hierarchy/divisions/{division}', [HierarchyController::class, 'destroyDivision'])->name('hierarchy.divisions.destroy');
        Route::get('/hierarchy/roles/{role}/staff', [HierarchyController::class, 'roleStaff'])->name('hierarchy.staff');
        Route::put('/hierarchy/roles/{role}/staff', [HierarchyController::class, 'syncStaff'])->name('hierarchy.staff.sync');
        Route::delete('/hierarchy/roles/{role}/staff/{user}', [HierarchyController::class, 'removeStaff'])->name('hierarchy.staff.destroy');
        Route::put('/hierarchy/roles/{role}', [HierarchyController::class, 'updateHierarchy'])->name('hierarchy.update');
        Route::delete('/hierarchy/roles/{role}', [HierarchyController::class, 'destroyRole'])->name('hierarchy.destroy');

        Route::get('/menus', [MenuController::class, 'index'])->name('menus');
        Route::post('/menus', [MenuController::class, 'store'])->name('menus.store');
        Route::put('/menus/{menu}', [MenuController::class, 'update'])->name('menus.update');
        Route::post('/menus/matrix', [MenuController::class, 'syncMatrix'])->name('menus.matrix');
        Route::delete('/menus/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy');

        Route::get('/services-roles', fn () => redirect()->route('admin.categories'))->name('services-roles');
        Route::get('/categories', [CategoryController::class, 'index'])->name('categories');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        Route::get('/roles', [RoleController::class, 'index'])->name('roles');
        Route::get('/divisions', [RoleController::class, 'index'])->name('divisions');

        // Manajemen Pengguna per Role / Unit Divisi
        Route::get('/users', [UserController::class, 'index'])->name('users');
        Route::get('/users/division/{division:slug}', [UserController::class, 'divisionUsers'])->name('users.division');
        Route::get('/users/role/{role:slug}', [UserController::class, 'roleUsers'])->name('users.role');
        Route::get('/users/employees', [UserController::class, 'employees'])->name('users.employees');
        Route::get('/users/students', fn () => redirect()->route('admin.users'))->name('users.students');
        Route::get('/users/lecturers', fn () => redirect()->route('admin.users'))->name('users.lecturers');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});
