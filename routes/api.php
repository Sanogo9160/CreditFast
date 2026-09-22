<?php

use App\Http\Controllers\Api\Admin\CaisseAdminController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AgentBankAccountApplicationController;
use App\Http\Controllers\Api\AnalystController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CaisseController;
use App\Http\Controllers\Api\ClientProfileController;
use App\Http\Controllers\Api\CommitteeController;
use App\Http\Controllers\Api\CreditAgentController;
use App\Http\Controllers\Api\CreditProductController;
use App\Http\Controllers\Api\CreditRequestController;
use App\Http\Controllers\Api\DocumentDownloadController;
use App\Http\Controllers\Api\FieldVisitController;
use App\Http\Controllers\Api\LegalEntityBankAccountApplicationController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PhysicalPersonBankAccountApplicationController;
use App\Http\Controllers\Api\ProfilePhotoController;
use App\Http\Controllers\Api\ScoringController;
use App\Http\Controllers\Api\SimulationController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('/client/login', [AuthController::class, 'clientLogin'])->middleware('throttle:auth');
    Route::post('/staff/login', [AuthController::class, 'staffLogin'])->middleware('throttle:auth');
});

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/password', [AuthController::class, 'updatePassword'])->middleware('throttle:passwords');
    });

    Route::get('/profile-photo', [ProfilePhotoController::class, 'show']);
    Route::post('/profile-photo', [ProfilePhotoController::class, 'store'])->middleware('throttle:profile-photos');
    Route::put('/profile-photo', [ProfilePhotoController::class, 'update'])->middleware('throttle:profile-photos');
    Route::delete('/profile-photo', [ProfilePhotoController::class, 'destroy']);
    Route::get('/users/{user}/photo/file', [ProfilePhotoController::class, 'file'])->name('users.photo.file');

    Route::get('/documents/{document}/file', [DocumentDownloadController::class, 'creditDocument']);
    Route::get('/kyc-documents/{kycDocument}/file', [DocumentDownloadController::class, 'kycDocument']);
    Route::get('/guarantees/{guarantee}/file', [DocumentDownloadController::class, 'guaranteeFile'])
        ->name('guarantees.file');
    Route::get('/bank-account-applications/documents/{bankAccountApplicationDocument}/file', [DocumentDownloadController::class, 'bankAccountApplicationDocument']);

    Route::get('/caisses', [CaisseController::class, 'index']);
    Route::get('/caisses/{caisse}/guichets', [CaisseController::class, 'guichets']);

    Route::middleware('role:client')->prefix('bank-account-applications')->group(function () {
        Route::prefix('physical-person')->group(function () {
            Route::get('/', [PhysicalPersonBankAccountApplicationController::class, 'index']);
            Route::post('/', [PhysicalPersonBankAccountApplicationController::class, 'store']);
            Route::get('/{bankAccountApplication}', [PhysicalPersonBankAccountApplicationController::class, 'show']);
            Route::put('/{bankAccountApplication}', [PhysicalPersonBankAccountApplicationController::class, 'update']);
            Route::post('/{bankAccountApplication}/submit', [PhysicalPersonBankAccountApplicationController::class, 'submit']);
            Route::post('/{bankAccountApplication}/documents', [PhysicalPersonBankAccountApplicationController::class, 'storeDocument']);
        });

        Route::prefix('legal-entity')->group(function () {
            Route::get('/', [LegalEntityBankAccountApplicationController::class, 'index']);
            Route::post('/', [LegalEntityBankAccountApplicationController::class, 'store']);
            Route::get('/{bankAccountApplication}', [LegalEntityBankAccountApplicationController::class, 'show']);
            Route::put('/{bankAccountApplication}', [LegalEntityBankAccountApplicationController::class, 'update']);
            Route::post('/{bankAccountApplication}/submit', [LegalEntityBankAccountApplicationController::class, 'submit']);
            Route::post('/{bankAccountApplication}/documents', [LegalEntityBankAccountApplicationController::class, 'storeDocument']);
        });
    });

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/{notification}', [NotificationController::class, 'show']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

    Route::middleware('role:client')->prefix('profile')->group(function () {
        Route::get('/', [ClientProfileController::class, 'show']);
        Route::put('/', [ClientProfileController::class, 'updateProfile']);

        Route::get('/activities', [ClientProfileController::class, 'indexActivities']);
        Route::post('/activities', [ClientProfileController::class, 'storeActivity']);
        Route::get('/activities/{activity}', [ClientProfileController::class, 'showActivity']);
        Route::put('/activities/{activity}', [ClientProfileController::class, 'updateActivity']);
        Route::delete('/activities/{activity}', [ClientProfileController::class, 'destroyActivity']);

        Route::get('/financial-profile', [ClientProfileController::class, 'showFinancialProfile']);
        Route::post('/financial-profile', [ClientProfileController::class, 'storeFinancialProfile']);
        Route::put('/financial-profile', [ClientProfileController::class, 'updateFinancialProfile']);

        Route::get('/kyc-documents', [ClientProfileController::class, 'indexKycDocuments']);
        Route::post('/kyc-documents', [ClientProfileController::class, 'storeKycDocument']);
        Route::get('/kyc-documents/{kycDocument}', [ClientProfileController::class, 'showKycDocument']);
        Route::delete('/kyc-documents/{kycDocument}', [ClientProfileController::class, 'destroyKycDocument']);
    });

    Route::prefix('credit-requests')->group(function () {
        Route::get('/', [CreditRequestController::class, 'index']);
        Route::post('/', [CreditRequestController::class, 'store'])->middleware('role:client');
        Route::get('/{creditRequest}', [CreditRequestController::class, 'show']);
        Route::put('/{creditRequest}', [CreditRequestController::class, 'update']);
        Route::delete('/{creditRequest}', [CreditRequestController::class, 'destroy']);
        Route::post('/{creditRequest}/submit', [CreditRequestController::class, 'submit']);

        Route::get('/{creditRequest}/documents', [CreditRequestController::class, 'indexDocuments']);
        Route::post('/{creditRequest}/documents', [CreditRequestController::class, 'uploadDocument']);
        Route::get('/{creditRequest}/documents/{document}', [CreditRequestController::class, 'showDocument'])
            ->scopeBindings();
        Route::delete('/{creditRequest}/documents/{document}', [CreditRequestController::class, 'destroyDocument'])
            ->scopeBindings();

        Route::get('/{creditRequest}/guarantees', [CreditRequestController::class, 'indexGuarantees']);
        Route::post('/{creditRequest}/guarantees', [CreditRequestController::class, 'storeGuarantee']);
        Route::get('/{creditRequest}/guarantees/{guarantee}', [CreditRequestController::class, 'showGuarantee'])
            ->scopeBindings();
        Route::put('/{creditRequest}/guarantees/{guarantee}', [CreditRequestController::class, 'updateGuarantee'])
            ->scopeBindings();
        Route::delete('/{creditRequest}/guarantees/{guarantee}', [CreditRequestController::class, 'destroyGuarantee'])
            ->scopeBindings();

        Route::post('/{creditRequest}/score', [ScoringController::class, 'evaluate'])
            ->middleware('role:admin,analyst,credit_agent');
        Route::get('/{creditRequest}/analysis', [ScoringController::class, 'getAnalysis']);
    });

    Route::get('/credit-products', [CreditProductController::class, 'index']);

    Route::post('/simulations/installments', [SimulationController::class, 'installments'])
        ->middleware('throttle:simulations');

    Route::prefix('loans')->group(function () {
        Route::get('/', [LoanController::class, 'index']);
        Route::get('/{loan}', [LoanController::class, 'show']);
        Route::get('/{loan}/repayments', [LoanController::class, 'repayments']);
        Route::post('/{loan}/disburse', [LoanController::class, 'disburse'])
            ->middleware('role:admin,credit_agent');
        Route::post('/{loan}/repayments/{repayment}/record', [LoanController::class, 'recordRepayment'])
            ->middleware('role:admin,credit_agent')
            ->scopeBindings();
    });

    Route::middleware('role:credit_agent,admin')->prefix('agent')->group(function () {
        Route::get('/requests', [CreditAgentController::class, 'index']);
        Route::get('/clients', [CreditAgentController::class, 'indexClients']);
        Route::get('/clients/{client}', [CreditAgentController::class, 'showClient']);
        Route::get('/clients/{client}/kyc', [CreditAgentController::class, 'indexClientKyc']);
        Route::post('/requests/{creditRequest}/request-complements', [CreditAgentController::class, 'requestComplements']);
        Route::post('/requests/{creditRequest}/send-to-analysis', [CreditAgentController::class, 'sendToAnalysis']);
        Route::post('/clients/{client}/kyc-documents/{kycDocument}/verify', [CreditAgentController::class, 'verifyKycDocument'])
            ->scopeBindings();
        Route::post('/guarantees/{guarantee}/verify', [CreditAgentController::class, 'verifyGuarantee']);
        Route::post('/clients/{client}/financial-accounts', [CreditAgentController::class, 'storeFinancialAccount']);
        Route::post('/financial-accounts/{financialAccount}/transactions', [CreditAgentController::class, 'storeAccountTransaction']);
        Route::post('/clients/{client}/savings-history', [CreditAgentController::class, 'storeSavingsHistory']);

        Route::get('/bank-account-applications/physical-person', [AgentBankAccountApplicationController::class, 'indexPhysical']);
        Route::get('/bank-account-applications/legal-entity', [AgentBankAccountApplicationController::class, 'indexLegal']);
        Route::get('/bank-account-applications/physical-person/{bankAccountApplication}', [AgentBankAccountApplicationController::class, 'showPhysical']);
        Route::get('/bank-account-applications/legal-entity/{bankAccountApplication}', [AgentBankAccountApplicationController::class, 'showLegal']);
        Route::post('/bank-account-applications/physical-person/{bankAccountApplication}/return', [AgentBankAccountApplicationController::class, 'returnPhysical']);
        Route::post('/bank-account-applications/legal-entity/{bankAccountApplication}/return', [AgentBankAccountApplicationController::class, 'returnLegal']);
        Route::post('/bank-account-applications/physical-person/{bankAccountApplication}/reject', [AgentBankAccountApplicationController::class, 'rejectPhysical']);
        Route::post('/bank-account-applications/legal-entity/{bankAccountApplication}/reject', [AgentBankAccountApplicationController::class, 'rejectLegal']);
        Route::post('/bank-account-applications/physical-person/{bankAccountApplication}/approve', [AgentBankAccountApplicationController::class, 'approvePhysical']);
        Route::post('/bank-account-applications/legal-entity/{bankAccountApplication}/approve', [AgentBankAccountApplicationController::class, 'approveLegal']);

        Route::get('/field-visits', [FieldVisitController::class, 'index']);
        Route::get('/requests/{creditRequest}/field-visits', [FieldVisitController::class, 'indexForRequest']);
        Route::post('/requests/{creditRequest}/field-visits', [FieldVisitController::class, 'store']);
        Route::get('/field-visits/{fieldVisit}', [FieldVisitController::class, 'show']);
        Route::put('/field-visits/{fieldVisit}', [FieldVisitController::class, 'update']);
        Route::post('/field-visits/{fieldVisit}/start', [FieldVisitController::class, 'start']);
        Route::post('/field-visits/{fieldVisit}/complete', [FieldVisitController::class, 'complete']);
        Route::post('/field-visits/{fieldVisit}/cancel', [FieldVisitController::class, 'cancel']);
    });

    Route::middleware('role:analyst,admin')->prefix('analyst')->group(function () {
        Route::get('/requests', [AnalystController::class, 'index']);
        Route::get('/requests/{creditRequest}/anomalies', [AnalystController::class, 'indexAnomalies']);
        Route::post('/requests/{creditRequest}/review', [AnalystController::class, 'review']);
        Route::post('/requests/{creditRequest}/human-validation', [AnalystController::class, 'recordHumanValidation']);
        Route::post('/anomalies/{anomaly}/resolve', [AnalystController::class, 'resolveAnomaly']);
        Route::post('/clients/{client}/kyc-documents/{kycDocument}/verify', [CreditAgentController::class, 'verifyKycDocument'])
            ->scopeBindings();
        Route::post('/guarantees/{guarantee}/verify', [CreditAgentController::class, 'verifyGuarantee']);
    });

    Route::middleware('role:committee_member,admin')->prefix('committee')->group(function () {
        Route::get('/requests', [CommitteeController::class, 'index']);
        Route::post('/requests/{creditRequest}/decide', [CommitteeController::class, 'decide']);
    });

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users', [AdminController::class, 'storeUser']);
        Route::get('/users/{user}', [AdminController::class, 'showUser']);
        Route::put('/users/{user}', [AdminController::class, 'updateUser']);
        Route::put('/users/{user}/password', [AdminController::class, 'resetUserPassword'])->middleware('throttle:passwords');
        Route::delete('/users/{user}', [AdminController::class, 'destroyUser']);
        Route::get('/scoring-models', [AdminController::class, 'scoringModels']);
        Route::post('/scoring-models', [AdminController::class, 'storeScoringModel']);
        Route::post('/scoring-models/{scoringModel}/status', [AdminController::class, 'updateScoringModelStatus']);
        Route::post('/scoring-models/{scoringModel}/rules', [AdminController::class, 'storeScoringRule']);
        Route::get('/audit-logs', [AdminController::class, 'auditLogs']);

        Route::get('/caisses', [CaisseAdminController::class, 'index']);
        Route::post('/caisses', [CaisseAdminController::class, 'storeCaisse']);
        Route::get('/caisses/{caisse}', [CaisseAdminController::class, 'showCaisse']);
        Route::put('/caisses/{caisse}', [CaisseAdminController::class, 'updateCaisse']);
        Route::delete('/caisses/{caisse}', [CaisseAdminController::class, 'destroyCaisse']);

        Route::post('/guichets', [CaisseAdminController::class, 'storeGuichet']);
        Route::get('/guichets/{guichet}', [CaisseAdminController::class, 'showGuichet']);
        Route::put('/guichets/{guichet}', [CaisseAdminController::class, 'updateGuichet']);
        Route::delete('/guichets/{guichet}', [CaisseAdminController::class, 'destroyGuichet']);

        Route::post('/cash-desks', [CaisseAdminController::class, 'storeCashDesk']);
        Route::get('/cash-desks/{cashDesk}', [CaisseAdminController::class, 'showCashDesk']);
        Route::put('/cash-desks/{cashDesk}', [CaisseAdminController::class, 'updateCashDesk']);
        Route::delete('/cash-desks/{cashDesk}', [CaisseAdminController::class, 'destroyCashDesk']);
    });
});
