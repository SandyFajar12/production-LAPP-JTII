<?php

use App\Services\AppNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('app-notifications')->group(function () {

    // KJPP Appraisal
    Route::post('/kjpp-appraisal/count', function () {
        return app(AppNotificationService::class)->kjppAppraisalCount();
    });

    Route::post('/kjpp-appraisal/items', function () {
        return app(AppNotificationService::class)->kjppAppraisalItems();
    });

    Route::post('/kjpp-appraisal/mark-read-one', function (Request $req) {
        return app(AppNotificationService::class)->kjppAppraisalMarkReadOne($req->AppraisalId);
    });

    Route::post('/kjpp-appraisal/mark-read-many', function () {
        return app(AppNotificationService::class)->kjppAppraisalMarkReadMany();
    });

    // Internal Audit - Progress Temuan (Auditor: audit-list)
    Route::post('/internal-audit-list/count', function () {
        return app(AppNotificationService::class)->internalAuditListCount();
    });

    Route::post('/internal-audit-list/items', function () {
        return app(AppNotificationService::class)->internalAuditListItems();
    });

    Route::post('/internal-audit-list/mark-read-one', function (Request $req) {
        return app(AppNotificationService::class)->internalAuditListMarkReadOne($req->AuditId);
    });

    Route::post('/internal-audit-list/mark-read-many', function () {
        return app(AppNotificationService::class)->internalAuditListMarkReadMany();
    });

    // Internal Audit - Progress Temuan (Auditee: status-penyelesaian)
    Route::post('/internal-audit-sp/count', function () {
        return app(AppNotificationService::class)->internalAuditSPCount();
    });

    Route::post('/internal-audit-sp/items', function () {
        return app(AppNotificationService::class)->internalAuditSPItems();
    });

    Route::post('/internal-audit-sp/mark-read-one', function (Request $req) {
        return app(AppNotificationService::class)->internalAuditSPMarkReadOne($req->AuditId);
    });

    Route::post('/internal-audit-sp/mark-read-many', function () {
        return app(AppNotificationService::class)->internalAuditSPMarkReadMany();
    });

    // Modul lain nanti tinggal tambah blok Route serupa di sini,
    // manggil function baru yang ditambahkan di AppNotificationService.

});