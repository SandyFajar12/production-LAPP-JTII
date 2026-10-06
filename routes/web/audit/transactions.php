<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Audit\Transactions\InternalAuditController;
use App\Http\Controllers\Audit\Transactions\StatusPenyelesaianController;

Route::prefix('audit/transactions')->group(function () {
    Route::get('/audit-list', [InternalAuditController::class, 'index']);
    Route::post('/audit-list/get-list', [InternalAuditController::class, 'getList']);

    Route::get('/internal-control', [InternalAuditController::class, 'index']);    

    Route::post('/audit-list/save', [InternalAuditController::class, 'save']);
    Route::post('/audit-list/update', [InternalAuditController::class, 'update']);
    Route::post('/audit-list/cancel', [InternalAuditController::class, 'cancelAudit']);
    Route::post('/audit-list/cancel-approve', [InternalAuditController::class, 'approveCancel']);
    Route::post('/audit-list/cancel-reject', [InternalAuditController::class, 'rejectCancel']);

    Route::post('/audit-list/get-temuan-list', [InternalAuditController::class, 'getTemuanList']);
    Route::post('/audit-list/save-temuan', [InternalAuditController::class, 'saveTemuan']);
    Route::post('/audit-list/delete-temuan', [InternalAuditController::class, 'deleteTemuan']);

    Route::post('/audit-list/get-lampiran-list', [InternalAuditController::class, 'getLampiranList']);
    Route::post('/audit-list/get-lampiran-list-array', [InternalAuditController::class, 'getLampiranListArray']);
    Route::post('/audit-list/save-lampiran', [InternalAuditController::class, 'saveLampiran']);
    Route::post('/audit-list/delete-lampiran', [InternalAuditController::class, 'deleteLampiran']);
    Route::post('/audit-list/update-temuan', [InternalAuditController::class, 'updateTemuan']);
    Route::post('/audit-list/get-perbaikan-list-array', [InternalAuditController::class, 'getPerbaikanListArray']);

    Route::post('/audit-list/get-progress-list', [InternalAuditController::class, 'getProgressList']);
    Route::post('/audit-list/get-progress-list-by-dtl', [InternalAuditController::class, 'getProgressListByDtl']);
    Route::post('/audit-list/get-perbaikan-dropdown', [InternalAuditController::class, 'getPerbaikanDropdown']);
    Route::post('/audit-list/save-progress', [InternalAuditController::class, 'saveProgress']);
    Route::post('/audit-list/update-status-hdr', [InternalAuditController::class, 'updateStatusHdr']);
    Route::post('/audit-list/update-workflow', [InternalAuditController::class, 'updateWorkflow']);
    Route::post('/audit-list/get-pic-list-array', [InternalAuditController::class, 'getPicListArray']);
    Route::post('/audit-list/get-sub-ordinate-all', [InternalAuditController::class, 'getSubOrdinateAll']);
    Route::post('/audit-list/get-all-approver', [InternalAuditController::class, 'getAllApprover']);

    // Status Penyelesaian
    Route::get('/status-penyelesaian', [StatusPenyelesaianController::class, 'index']);
    Route::post('/status-penyelesaian/get-list', [StatusPenyelesaianController::class, 'getList']);
    Route::post('/status-penyelesaian/get-temuan-list', [StatusPenyelesaianController::class, 'getTemuanList']);
    Route::post('/status-penyelesaian/get-lampiran-list-array', [StatusPenyelesaianController::class, 'getLampiranListArray']);
    Route::post('/status-penyelesaian/get-progress-list', [StatusPenyelesaianController::class, 'getProgressList']);
    Route::post('/status-penyelesaian/get-progress-list-by-dtl', [StatusPenyelesaianController::class, 'getProgressListByDtl']);
    Route::post('/status-penyelesaian/get-perbaikan-dropdown', [StatusPenyelesaianController::class, 'getPerbaikanDropdown']);
    Route::post('/status-penyelesaian/update-status', [StatusPenyelesaianController::class, 'updateStatus']);
    Route::post('/status-penyelesaian/approve-progress', [StatusPenyelesaianController::class, 'approveProgress']);
    Route::post('/status-penyelesaian/reject-progress', [StatusPenyelesaianController::class, 'rejectProgress']);
    Route::post('/status-penyelesaian/approve-lampiran', [StatusPenyelesaianController::class, 'approveLampiran']);
    Route::post('/status-penyelesaian/reject-lampiran', [StatusPenyelesaianController::class, 'rejectLampiran']);
    Route::post('/status-penyelesaian/approve-pending', [StatusPenyelesaianController::class, 'approvePending']);
    Route::post('/status-penyelesaian/reject-pending', [StatusPenyelesaianController::class, 'rejectPending']);
    Route::post('/status-penyelesaian/get-pic-list-array', [StatusPenyelesaianController::class, 'getPicListArray']);
    Route::post('/status-penyelesaian/get-pic-list-array-audit', [StatusPenyelesaianController::class, 'getPicListArrayAudit']);
});