<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\NeoController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\WorkQueueController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Middleware\WorkspaceAccess;
use Illuminate\Support\Facades\Route;

Route::post('/testar', [AuthController::class, 'trial'])->middleware('throttle:5,1')->name('trial');
Route::get('/entrar', [AuthController::class, 'login'])->name('login');
Route::post('/auth/neon/session', [AuthController::class, 'exchange'])->middleware('throttle:20,1')->name('auth.exchange');
Route::post('/auth/neon/{action}', [AuthController::class, 'action'])->whereIn('action', ['login', 'register', 'recover', 'reset', 'refresh'])->middleware('throttle:20,1');
Route::post('/sair', [AuthController::class, 'logout'])->name('logout');

Route::middleware(WorkspaceAccess::class)->group(function (): void {
    Route::post('/api/v1/neo/chat', [NeoController::class, 'chat'])->middleware('throttle:20,1');
    Route::get('/reunioes/gravar/{id}', [MeetingController::class, 'window'])->name('meetings.record');
    Route::post('/api/v1/meetings/consent', [MeetingController::class, 'consent']);
    Route::post('/api/v1/meetings/{id}/recordings', [MeetingController::class, 'start']);
    Route::post('/api/v1/meeting-recordings/{id}/chunks', [MeetingController::class, 'chunk']);
    Route::post('/api/v1/meeting-recordings/{id}/finish', [MeetingController::class, 'finish']);
    Route::patch('/api/v1/meeting-recordings/{id}/notes', [MeetingController::class, 'notes']);
    Route::get('/reunioes/audio/{id}', [MeetingController::class, 'audio'])->name('meetings.audio');
    Route::post('/api/v1/messages', [WorkspaceController::class, 'message']);
    Route::get('/api/v1/activities', [WorkspaceController::class, 'activities']);
    Route::patch('/api/v1/records/{kind}/{id}', [WorkspaceController::class, 'update']);
    Route::post('/api/v1/work/preview', [WorkQueueController::class, 'preview']);
    Route::post('/api/v1/work/import/{id}', [WorkQueueController::class, 'import']);
    Route::post('/api/v1/work/assign', [WorkQueueController::class, 'assign']);
    Route::get('/api/v1/work/{id}', [WorkQueueController::class, 'detail']);
    Route::patch('/api/v1/work/{id}', [WorkQueueController::class, 'update']);
    Route::get('/prazos/planilha/{id}', [WorkQueueController::class, 'export'])->name('work.export');
    Route::post('/api/v1/team/invite', [TeamController::class, 'invite']);
    Route::post('/api/v1/team/categories', [TeamController::class, 'category']);
    Route::patch('/api/v1/team/categories/{id}', [TeamController::class, 'category']);
    Route::patch('/api/v1/team/members/{id}', [TeamController::class, 'member']);
    Route::patch('/api/v1/team/assign/{kind}/{id}', [TeamController::class, 'assign']);
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
