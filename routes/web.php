<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Middleware\WorkspaceAccess;
use Illuminate\Support\Facades\Route;

Route::get('/entrar', [AuthController::class, 'login'])->name('login');
Route::post('/auth/neon/session', [AuthController::class, 'exchange'])->middleware('throttle:20,1')->name('auth.exchange');
Route::post('/sair', [AuthController::class, 'logout'])->name('logout');

Route::middleware(WorkspaceAccess::class)->group(function (): void {
    Route::get('/', [WorkspaceController::class, 'index'])->name('home');
    Route::post('/api/v1/records/{kind}', [WorkspaceController::class, 'store'])->name('records.store');
    Route::get('/api/v1/records/{kind}/{id}', [WorkspaceController::class, 'detail'])->name('records.show');
    Route::patch('/api/v1/tasks/{id}/completion', [WorkspaceController::class, 'completeTask'])->name('tasks.complete');
    Route::patch('/api/v1/tasks/{id}', [WorkspaceController::class, 'updateTask'])->name('tasks.update');
    Route::patch('/api/v1/deadlines/{id}/completion', [WorkspaceController::class, 'completeDeadline'])->name('deadlines.complete');
    Route::post('/api/v1/documents', [WorkspaceController::class, 'upload'])->name('documents.upload');
    Route::get('/documentos/{id}/baixar', [WorkspaceController::class, 'download'])->name('documents.download');
    Route::patch('/api/v1/settings', [WorkspaceController::class, 'settings'])->name('settings.update');
    Route::get('/{module}', [WorkspaceController::class, 'index'])->whereIn('module', array_keys(WorkspaceController::MODULES))->name('workspace');
});
