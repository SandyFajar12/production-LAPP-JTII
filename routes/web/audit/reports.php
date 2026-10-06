<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Audit\Reports\SummaryTlhaController;

Route::prefix('audit/reports')->group(function () {
    Route::get('/summary-tlha', [SummaryTlhaController::class, 'index']);
    Route::post('/summary-tlha/get-list', [SummaryTlhaController::class, 'getList']);
    Route::post('/summary-tlha/preview', [SummaryTlhaController::class, 'preview']);
    Route::get('/summary-tlha/exportExcel', [SummaryTlhaController::class, 'exportExcel']);
});