<?php

use App\Modules\Beneficiary\Controllers\BeneficiaryDocumentController;
use Illuminate\Support\Facades\Route;

Route::delete('/bulk-delete', [BeneficiaryDocumentController::class, 'bulkDestroy'])
    ->middleware('permission:beneficiary-documents.bulkDestroy,web');

Route::get('/', [BeneficiaryDocumentController::class, 'index'])
    ->middleware('permission:beneficiary-documents.index,web');
Route::post('/', [BeneficiaryDocumentController::class, 'store'])
    ->middleware('permission:beneficiary-documents.store,web');
Route::get('/{document}', [BeneficiaryDocumentController::class, 'show'])
    ->middleware('permission:beneficiary-documents.show,web');
Route::put('/{document}', [BeneficiaryDocumentController::class, 'update'])
    ->middleware('permission:beneficiary-documents.update,web');
Route::delete('/{document}', [BeneficiaryDocumentController::class, 'destroy'])
    ->middleware('permission:beneficiary-documents.destroy,web');
