<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Message',
    required: ['message'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Opération enregistrée.'),
    ]
)]
#[OA\Schema(
    schema: 'ValidationError',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(
                type: 'array',
                items: new OA\Items(type: 'string')
            )
        ),
    ]
)]
#[OA\Schema(
    schema: 'PaginationMeta',
    properties: [
        new OA\Property(property: 'current_page', type: 'integer', example: 1),
        new OA\Property(property: 'last_page', type: 'integer', example: 1),
        new OA\Property(property: 'total', type: 'integer', example: 3),
    ]
)]
#[OA\Schema(
    schema: 'User',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'first_name', type: 'string', example: 'Mah'),
        new OA\Property(property: 'last_name', type: 'string', example: 'SANOGO'),
        new OA\Property(property: 'full_name', type: 'string', example: 'Mah SANOGO'),
        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, example: 'mahsanogo12@gmail.com'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+22377000016'),
        new OA\Property(property: 'status', ref: '#/components/schemas/UserStatus'),
        new OA\Property(property: 'role', ref: '#/components/schemas/RoleName'),
        new OA\Property(property: 'client', ref: '#/components/schemas/Client', nullable: true),
        new OA\Property(property: 'profile_photo_url', type: 'string', format: 'uri', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'AuthTokenResponse',
    required: ['message', 'token', 'user'],
    properties: [
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(property: 'token', type: 'string', description: 'Token Sanctum à coller dans Authorize (sans le mot Bearer). N’expire pas tout seul.'),
        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
    ]
)]
#[OA\Schema(
    schema: 'ClientRegisterRequest',
    required: ['client_type', 'first_name', 'last_name', 'phone', 'password'],
    properties: [
        new OA\Property(property: 'client_type', ref: '#/components/schemas/ClientType'),
        new OA\Property(property: 'first_name', type: 'string', maxLength: 100, example: 'Mah', description: 'Prénom du particulier ou du représentant légal'),
        new OA\Property(property: 'last_name', type: 'string', maxLength: 100, example: 'SANOGO', description: 'Nom du particulier ou du représentant légal'),
        new OA\Property(property: 'phone', type: 'string', maxLength: 30, example: '+22377000016'),
        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, example: 'mahsanogo12@gmail.com'),
        new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, example: 'MotDePasseFort8'),
        new OA\Property(property: 'company_name', type: 'string', maxLength: 200, nullable: true, example: 'SARL Agro Négoce Mali', description: 'Obligatoire si LEGAL_ENTITY'),
        new OA\Property(property: 'trade_name', type: 'string', maxLength: 200, nullable: true, example: 'AgroNégoce'),
        new OA\Property(property: 'registration_number', type: 'string', maxLength: 100, nullable: true, example: 'MA.BKO.2024.B.12345', description: 'RCCM / NIF — obligatoire si LEGAL_ENTITY'),
        new OA\Property(property: 'legal_form', type: 'string', maxLength: 100, nullable: true, example: 'SARL'),
    ],
    example: [
        'client_type' => 'PHYSICAL_PERSON',
        'first_name' => 'Mah',
        'last_name' => 'SANOGO',
        'phone' => '+22377000016',
        'email' => 'mahsanogo12@gmail.com',
        'password' => 'MotDePasseFort8',
    ]
)]
#[OA\Schema(
    schema: 'ClientLoginRequest',
    required: ['phone', 'password'],
    properties: [
        new OA\Property(property: 'phone', type: 'string', example: '+22377000016'),
        new OA\Property(property: 'password', type: 'string', format: 'password', example: 'MotDePasseFort8'),
    ],
    example: [
        'phone' => '+22377000016',
        'password' => 'MotDePasseFort8',
    ]
)]
#[OA\Schema(
    schema: 'StaffLoginRequest',
    required: ['email', 'password'],
    properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@creditfast.ml'),
        new OA\Property(property: 'password', type: 'string', format: 'password', example: 'MotDePasseFort8'),
    ]
)]
#[OA\Schema(
    schema: 'UpdatePasswordRequest',
    required: ['current_password', 'password', 'password_confirmation'],
    properties: [
        new OA\Property(property: 'current_password', type: 'string', format: 'password', example: 'MotDePasseFort8'),
        new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, example: 'NouveauMotDePasse9'),
        new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'NouveauMotDePasse9'),
    ]
)]
#[OA\Schema(
    schema: 'ResetUserPasswordRequest',
    required: ['password', 'password_confirmation'],
    properties: [
        new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, example: 'NouveauMotDePasse9'),
        new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'NouveauMotDePasse9'),
    ]
)]
#[OA\Schema(
    schema: 'Client',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'client_number', type: 'string', example: 'CLI-000001'),
        new OA\Property(property: 'client_type', ref: '#/components/schemas/ClientType'),
        new OA\Property(property: 'company_name', type: 'string', nullable: true),
        new OA\Property(property: 'trade_name', type: 'string', nullable: true),
        new OA\Property(property: 'registration_number', type: 'string', nullable: true),
        new OA\Property(property: 'legal_form', type: 'string', nullable: true),
        new OA\Property(property: 'kyc_status', ref: '#/components/schemas/KycStatus'),
        new OA\Property(property: 'city', type: 'string', nullable: true),
        new OA\Property(property: 'residential_zone', type: 'string', nullable: true),
        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
    ]
)]
#[OA\Schema(
    schema: 'KycDocument',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'document_type', ref: '#/components/schemas/KycDocumentType'),
        new OA\Property(property: 'document_number', type: 'string', nullable: true),
        new OA\Property(property: 'status', ref: '#/components/schemas/KycStatus'),
    ]
)]
#[OA\Schema(
    schema: 'UpdateClientProfileRequest',
    properties: [
        new OA\Property(property: 'date_of_birth', type: 'string', format: 'date', nullable: true, example: '1990-04-12'),
        new OA\Property(property: 'address', type: 'string', nullable: true, example: 'Quartier ACI 2000'),
        new OA\Property(property: 'city', type: 'string', nullable: true, example: 'Bamako'),
        new OA\Property(property: 'residential_zone', type: 'string', nullable: true, example: 'Commune V'),
        new OA\Property(property: 'occupation', type: 'string', nullable: true, example: 'Commerçante'),
        new OA\Property(property: 'company_name', type: 'string', nullable: true, description: 'Personne morale uniquement'),
        new OA\Property(property: 'trade_name', type: 'string', nullable: true, description: 'Personne morale uniquement'),
        new OA\Property(property: 'registration_number', type: 'string', nullable: true, description: 'Personne morale uniquement'),
        new OA\Property(property: 'legal_form', type: 'string', nullable: true, description: 'Personne morale uniquement'),
    ]
)]
#[OA\Schema(
    schema: 'StoreActivityRequest',
    required: ['activity_type', 'monthly_revenue'],
    properties: [
        new OA\Property(property: 'activity_type', type: 'string', example: 'Commerce'),
        new OA\Property(property: 'sector', type: 'string', nullable: true, example: 'Alimentaire'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'start_date', type: 'string', format: 'date', nullable: true, example: '2021-01-15'),
        new OA\Property(property: 'location', type: 'string', nullable: true, example: 'Marché Médina'),
        new OA\Property(property: 'monthly_revenue', type: 'number', format: 'float', example: 350000),
    ]
)]
#[OA\Schema(
    schema: 'StoreFinancialProfileRequest',
    required: ['monthly_income', 'monthly_expenses'],
    properties: [
        new OA\Property(property: 'monthly_income', type: 'number', format: 'float', example: 250000),
        new OA\Property(property: 'other_income', type: 'number', format: 'float', nullable: true, example: 20000),
        new OA\Property(property: 'monthly_expenses', type: 'number', format: 'float', example: 80000),
        new OA\Property(property: 'existing_debt_payment', type: 'number', format: 'float', nullable: true, example: 0),
        new OA\Property(property: 'dependents_count', type: 'integer', nullable: true, example: 2),
    ]
)]
#[OA\Schema(
    schema: 'GuaranteeInput',
    required: ['guarantee_type', 'declared_value'],
    properties: [
        new OA\Property(property: 'guarantee_type', ref: '#/components/schemas/GuaranteeType'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Motocyclette'),
        new OA\Property(property: 'declared_value', type: 'number', format: 'float', example: 400000),
    ]
)]
#[OA\Schema(
    schema: 'Guarantee',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'credit_request_id', type: 'integer', example: 1),
        new OA\Property(property: 'guarantee_type', ref: '#/components/schemas/GuaranteeType'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'declared_value', type: 'number', format: 'float', example: 400000),
        new OA\Property(property: 'verified_value', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'verification_status', ref: '#/components/schemas/GuaranteeVerificationStatus'),
        new OA\Property(property: 'has_file', type: 'boolean', example: true),
        new OA\Property(property: 'original_filename', type: 'string', nullable: true, example: 'titre-foncier.pdf'),
        new OA\Property(property: 'mime_type', type: 'string', nullable: true, example: 'application/pdf'),
        new OA\Property(property: 'file_url', type: 'string', format: 'uri', nullable: true),
        new OA\Property(property: 'verified_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'StoreCreditRequest',
    required: ['credit_type', 'requested_amount', 'duration_months', 'purpose', 'declared_monthly_income', 'declared_monthly_expenses'],
    properties: [
        new OA\Property(property: 'credit_type', ref: '#/components/schemas/CreditProductType'),
        new OA\Property(property: 'requested_amount', type: 'number', format: 'float', minimum: 10000, example: 500000),
        new OA\Property(property: 'duration_months', type: 'integer', minimum: 1, maximum: 60, example: 6),
        new OA\Property(property: 'purpose', type: 'string', example: 'Fonds de roulement'),
        new OA\Property(property: 'declared_monthly_income', type: 'number', format: 'float', example: 250000),
        new OA\Property(property: 'declared_monthly_expenses', type: 'number', format: 'float', example: 80000),
        new OA\Property(property: 'activity_id', type: 'integer', nullable: true),
        new OA\Property(
            property: 'guarantee',
            ref: '#/components/schemas/GuaranteeInput',
            nullable: true,
            description: 'Facultatif. Omettre, envoyer `null` ou un objet vide : aucune garantie n’est enregistrée.',
        ),
    ]
)]
#[OA\Schema(
    schema: 'UpdateCreditRequest',
    properties: [
        new OA\Property(property: 'credit_type', ref: '#/components/schemas/CreditProductType'),
        new OA\Property(property: 'requested_amount', type: 'number', format: 'float', minimum: 10000),
        new OA\Property(property: 'duration_months', type: 'integer', minimum: 1, maximum: 60),
        new OA\Property(property: 'purpose', type: 'string'),
        new OA\Property(property: 'declared_monthly_income', type: 'number', format: 'float'),
        new OA\Property(property: 'declared_monthly_expenses', type: 'number', format: 'float'),
        new OA\Property(property: 'activity_id', type: 'integer', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'CreditRequest',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'client_id', type: 'integer'),
        new OA\Property(property: 'borrower_type', ref: '#/components/schemas/ClientType', nullable: true),
        new OA\Property(property: 'credit_type', ref: '#/components/schemas/CreditProductType', nullable: true),
        new OA\Property(property: 'credit_type_label', type: 'string', nullable: true),
        new OA\Property(property: 'requested_amount', type: 'number', format: 'float'),
        new OA\Property(property: 'duration_months', type: 'integer'),
        new OA\Property(property: 'purpose', type: 'string'),
        new OA\Property(property: 'status', ref: '#/components/schemas/CreditRequestStatus'),
        new OA\Property(property: 'repayment_capacity_status', ref: '#/components/schemas/RepaymentCapacityStatus'),
        new OA\Property(property: 'estimated_monthly_payment', type: 'number', format: 'float'),
        new OA\Property(property: 'submitted_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'CreditRequestEnvelope',
    properties: [
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(property: 'credit_request', ref: '#/components/schemas/CreditRequest'),
        new OA\Property(property: 'returned_to_client', type: 'boolean', nullable: true),
        new OA\Property(property: 'next_actor', type: 'string', nullable: true, example: 'client'),
    ]
)]
#[OA\Schema(
    schema: 'Document',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'document_type', ref: '#/components/schemas/CreditDocumentType'),
        new OA\Property(property: 'original_filename', type: 'string'),
        new OA\Property(property: 'mime_type', type: 'string'),
        new OA\Property(property: 'status', type: 'string', example: 'UPLOADED'),
        new OA\Property(property: 'uploaded_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'Loan',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'client_id', type: 'integer'),
        new OA\Property(property: 'credit_request_id', type: 'integer'),
        new OA\Property(property: 'principal_amount', type: 'number', format: 'float'),
        new OA\Property(property: 'interest_amount', type: 'number', format: 'float'),
        new OA\Property(property: 'total_amount', type: 'number', format: 'float'),
        new OA\Property(property: 'duration_months', type: 'integer'),
        new OA\Property(property: 'monthly_payment', type: 'number', format: 'float'),
        new OA\Property(property: 'disbursed_at', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'funds_received', type: 'number', format: 'float', description: 'Montant mis à disposition (0 tant que non décaissé)'),
        new OA\Property(property: 'outstanding_amount', type: 'number', format: 'float'),
        new OA\Property(property: 'status', ref: '#/components/schemas/LoanStatus'),
        new OA\Property(property: 'repayments', type: 'array', items: new OA\Items(ref: '#/components/schemas/LoanRepayment')),
    ]
)]
#[OA\Schema(
    schema: 'LoanRepayment',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'loan_id', type: 'integer'),
        new OA\Property(property: 'due_date', type: 'string', format: 'date'),
        new OA\Property(property: 'payment_date', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'expected_amount', type: 'number', format: 'float'),
        new OA\Property(property: 'paid_amount', type: 'number', format: 'float'),
        new OA\Property(property: 'remaining_amount', type: 'number', format: 'float'),
        new OA\Property(property: 'days_late', type: 'integer'),
        new OA\Property(property: 'status', ref: '#/components/schemas/LoanRepaymentStatus'),
    ]
)]
#[OA\Schema(
    schema: 'DisburseLoanRequest',
    properties: [
        new OA\Property(property: 'disbursed_at', type: 'string', format: 'date', nullable: true, example: '2026-09-14'),
        new OA\Property(property: 'comment', type: 'string', nullable: true, example: 'Décaissement agence ACI 2000'),
    ]
)]
#[OA\Schema(
    schema: 'RecordRepaymentRequest',
    required: ['paid_amount'],
    properties: [
        new OA\Property(property: 'paid_amount', type: 'number', format: 'float', minimum: 0.01, example: 90000),
        new OA\Property(property: 'payment_date', type: 'string', format: 'date', nullable: true, example: '2026-09-14'),
        new OA\Property(property: 'comment', type: 'string', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'Notification',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(property: 'type', ref: '#/components/schemas/NotificationType'),
        new OA\Property(property: 'is_read', type: 'boolean'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'CreditAnalysis',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'overall_score', type: 'number', format: 'float'),
        new OA\Property(property: 'confidence_score', type: 'number', format: 'float'),
        new OA\Property(property: 'recommendation', ref: '#/components/schemas/ScoringRecommendation'),
        new OA\Property(property: 'analysis_summary', type: 'string'),
        new OA\Property(property: 'repayment_capacity_score', type: 'number', format: 'float'),
        new OA\Property(property: 'income_consistency_score', type: 'number', format: 'float'),
        new OA\Property(property: 'activity_score', type: 'number', format: 'float'),
        new OA\Property(property: 'activity_vitality_score', type: 'number', format: 'float'),
        new OA\Property(property: 'expense_score', type: 'number', format: 'float'),
        new OA\Property(property: 'document_score', type: 'number', format: 'float'),
        new OA\Property(property: 'savings_score', type: 'number', format: 'float'),
        new OA\Property(property: 'credit_history_score', type: 'number', format: 'float'),
        new OA\Property(property: 'guarantee_score', type: 'number', format: 'float'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'RequestComplementsRequest',
    required: ['comment'],
    properties: [
        new OA\Property(
            property: 'comment',
            type: 'string',
            minLength: 5,
            example: 'Merci de joindre un justificatif de domicile récent.'
        ),
    ]
)]
#[OA\Schema(
    schema: 'VerifyKycRequest',
    required: ['decision'],
    properties: [
        new OA\Property(property: 'decision', ref: '#/components/schemas/KycStatus'),
        new OA\Property(property: 'rejection_reason', type: 'string', nullable: true, description: 'Obligatoire si decision = REJECTED'),
    ]
)]
#[OA\Schema(
    schema: 'VerifyGuaranteeRequest',
    required: ['verification_status'],
    properties: [
        new OA\Property(property: 'verification_status', ref: '#/components/schemas/GuaranteeVerificationStatus'),
        new OA\Property(property: 'verified_value', type: 'number', format: 'float', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'StoreFinancialAccountRequest',
    required: ['account_number', 'account_type'],
    properties: [
        new OA\Property(property: 'account_number', type: 'string', example: 'ML-001-456789'),
        new OA\Property(property: 'account_type', type: 'string', example: 'EPARGNE'),
        new OA\Property(property: 'balance', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'opened_at', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'status', type: 'string', nullable: true, example: 'ACTIVE'),
    ]
)]
#[OA\Schema(
    schema: 'StoreAccountTransactionRequest',
    required: ['transaction_type', 'amount', 'transaction_date'],
    properties: [
        new OA\Property(property: 'transaction_type', type: 'string', example: 'DEPOSIT'),
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 25000),
        new OA\Property(property: 'transaction_date', type: 'string', format: 'date'),
        new OA\Property(property: 'reference', type: 'string', nullable: true),
        new OA\Property(property: 'description', type: 'string', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'StoreSavingsHistoryRequest',
    required: ['period_start', 'period_end', 'total_deposits', 'total_withdrawals', 'deposit_count', 'withdrawal_count', 'average_balance', 'closing_balance'],
    properties: [
        new OA\Property(property: 'account_id', type: 'integer', nullable: true),
        new OA\Property(property: 'period_start', type: 'string', format: 'date'),
        new OA\Property(property: 'period_end', type: 'string', format: 'date'),
        new OA\Property(property: 'total_deposits', type: 'number', format: 'float'),
        new OA\Property(property: 'total_withdrawals', type: 'number', format: 'float'),
        new OA\Property(property: 'deposit_count', type: 'integer'),
        new OA\Property(property: 'withdrawal_count', type: 'integer'),
        new OA\Property(property: 'average_balance', type: 'number', format: 'float', example: 150000),
        new OA\Property(property: 'closing_balance', type: 'number', format: 'float'),
    ]
)]
#[OA\Schema(
    schema: 'AnalystReviewRequest',
    required: ['recommendation', 'comment'],
    properties: [
        new OA\Property(property: 'recommendation', ref: '#/components/schemas/ScoringRecommendation'),
        new OA\Property(property: 'comment', type: 'string', minLength: 5, example: 'Dossier cohérent.'),
        new OA\Property(property: 'next_step', ref: '#/components/schemas/AnalystNextStep'),
    ]
)]
#[OA\Schema(
    schema: 'HumanValidationRequest',
    required: ['validation_type', 'decision'],
    properties: [
        new OA\Property(property: 'document_id', type: 'integer', nullable: true),
        new OA\Property(property: 'validation_type', type: 'string', example: 'DOCUMENT'),
        new OA\Property(property: 'decision', ref: '#/components/schemas/ValidationDecision'),
        new OA\Property(property: 'comment', type: 'string', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'Anomaly',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'anomaly_type', type: 'string', example: 'MISSING_MANDATORY_DOCUMENTS'),
        new OA\Property(property: 'severity', ref: '#/components/schemas/AnomalySeverity'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'status', ref: '#/components/schemas/AnomalyStatus'),
    ]
)]
#[OA\Schema(
    schema: 'ResolveAnomalyRequest',
    required: ['status', 'resolution_comment'],
    properties: [
        new OA\Property(property: 'status', ref: '#/components/schemas/AnomalyStatus'),
        new OA\Property(property: 'resolution_comment', type: 'string', minLength: 5),
    ]
)]
#[OA\Schema(
    schema: 'CommitteeDecisionRequest',
    required: ['decision', 'comment'],
    properties: [
        new OA\Property(property: 'decision', ref: '#/components/schemas/CommitteeDecision'),
        new OA\Property(property: 'approved_amount', type: 'number', format: 'float', nullable: true, description: 'Obligatoire si APPROVED ou AMENDED'),
        new OA\Property(property: 'approved_duration_months', type: 'integer', nullable: true, description: 'Obligatoire si APPROVED ou AMENDED'),
        new OA\Property(property: 'comment', type: 'string', minLength: 5, example: 'Accord du comité.'),
    ]
)]
#[OA\Schema(
    schema: 'StoreStaffUserRequest',
    required: ['first_name', 'last_name', 'email', 'password', 'role'],
    properties: [
        new OA\Property(property: 'first_name', type: 'string'),
        new OA\Property(property: 'last_name', type: 'string'),
        new OA\Property(property: 'email', type: 'string', format: 'email'),
        new OA\Property(property: 'phone', type: 'string', nullable: true),
        new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8),
        new OA\Property(property: 'role', ref: '#/components/schemas/StaffRoleName'),
    ]
)]
#[OA\Schema(
    schema: 'UpdateStaffUserRequest',
    properties: [
        new OA\Property(property: 'first_name', type: 'string'),
        new OA\Property(property: 'last_name', type: 'string'),
        new OA\Property(property: 'email', type: 'string', format: 'email'),
        new OA\Property(property: 'phone', type: 'string', nullable: true),
        new OA\Property(property: 'status', ref: '#/components/schemas/UserStatus'),
        new OA\Property(property: 'role', ref: '#/components/schemas/StaffRoleName'),
    ]
)]
#[OA\Schema(
    schema: 'StoreScoringModelRequest',
    required: ['name', 'version', 'scoring_mode'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Modèle Scoring Standard V1.1'),
        new OA\Property(property: 'version', type: 'string', example: 'V1.1'),
        new OA\Property(property: 'scoring_mode', ref: '#/components/schemas/ScoringMode'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'effective_from', type: 'string', format: 'date', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'UpdateScoringModelStatusRequest',
    required: ['status'],
    properties: [
        new OA\Property(property: 'status', ref: '#/components/schemas/ScoringModelStatus'),
    ]
)]
#[OA\Schema(
    schema: 'StoreScoringRuleRequest',
    required: ['rule_code', 'rule_name', 'factor_type', 'weight'],
    properties: [
        new OA\Property(property: 'rule_code', type: 'string', example: 'R_CAP_02'),
        new OA\Property(property: 'rule_name', type: 'string'),
        new OA\Property(property: 'factor_type', ref: '#/components/schemas/FactorType'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'weight', type: 'number', format: 'float', minimum: 0, maximum: 100),
        new OA\Property(property: 'min_score', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'max_score', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'rule_config', type: 'object', nullable: true),
        new OA\Property(property: 'priority', type: 'integer', nullable: true),
        new OA\Property(property: 'status', type: 'string', nullable: true, example: 'ACTIVE'),
    ]
)]
#[OA\Schema(
    schema: 'SimulateInstallmentsRequest',
    required: ['scenarios'],
    properties: [
        new OA\Property(property: 'monthly_income', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'other_income', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'monthly_expenses', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'existing_debt_payment', type: 'number', format: 'float', nullable: true),
        new OA\Property(
            property: 'scenarios',
            type: 'array',
            minItems: 1,
            maxItems: 8,
            items: new OA\Items(ref: '#/components/schemas/SimulationScenario')
        ),
    ]
)]
#[OA\Schema(
    schema: 'SimulationScenario',
    required: ['requested_amount', 'duration_months'],
    properties: [
        new OA\Property(property: 'requested_amount', type: 'number', format: 'float', minimum: 10000, example: 500000),
        new OA\Property(property: 'duration_months', type: 'integer', minimum: 1, maximum: 60, example: 6),
    ]
)]
#[OA\Schema(
    schema: 'ProfilePhotoEnvelope',
    properties: [
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(property: 'has_photo', type: 'boolean'),
        new OA\Property(property: 'profile_photo_url', type: 'string', format: 'uri', nullable: true),
        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
    ]
)]
#[OA\RequestBody(
    request: 'UploadProfilePhoto',
    required: true,
    description: 'multipart/form-data — JPG, PNG ou WEBP, 2 Mo max.',
    content: new OA\MediaType(
        mediaType: 'multipart/form-data',
        schema: new OA\Schema(
            required: ['photo'],
            properties: [
                new OA\Property(property: 'photo', description: 'Image JPG, PNG ou WEBP (max 2 Mo)', type: 'string', format: 'binary'),
            ]
        )
    )
)]
#[OA\RequestBody(
    request: 'UploadCreditDocument',
    required: true,
    description: 'multipart/form-data — PDF, JPG ou PNG, 10 Mo max.',
    content: new OA\MediaType(
        mediaType: 'multipart/form-data',
        schema: new OA\Schema(
            required: ['document_type', 'file'],
            properties: [
                new OA\Property(property: 'document_type', ref: '#/components/schemas/CreditDocumentType'),
                new OA\Property(property: 'file', description: 'Fichier PDF, JPG ou PNG (max 10 Mo)', type: 'string', format: 'binary'),
            ]
        )
    )
)]
#[OA\RequestBody(
    request: 'UploadKycDocument',
    required: true,
    description: 'multipart/form-data — pièce d’identité.',
    content: new OA\MediaType(
        mediaType: 'multipart/form-data',
        schema: new OA\Schema(
            required: ['document_type', 'file'],
            properties: [
                new OA\Property(property: 'document_type', ref: '#/components/schemas/KycDocumentType'),
                new OA\Property(property: 'document_number', type: 'string', nullable: true, example: 'M123456'),
                new OA\Property(property: 'file', description: 'Fichier PDF, JPG ou PNG (max 10 Mo)', type: 'string', format: 'binary'),
            ]
        )
    )
)]
#[OA\RequestBody(
    request: 'StoreGuarantee',
    required: true,
    description: 'JSON (sans fichier) ou multipart/form-data. Le champ `file` est facultatif (PDF, JPG, PNG, 10 Mo max).',
    content: [
        new OA\JsonContent(ref: '#/components/schemas/GuaranteeInput'),
        new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['guarantee_type', 'declared_value'],
                properties: [
                    new OA\Property(property: 'guarantee_type', ref: '#/components/schemas/GuaranteeType'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Motocyclette'),
                    new OA\Property(property: 'declared_value', type: 'number', format: 'float', example: 400000),
                    new OA\Property(property: 'file', description: 'Justificatif PDF, JPG ou PNG (max 10 Mo)', type: 'string', format: 'binary'),
                ]
            )
        ),
    ]
)]
#[OA\RequestBody(
    request: 'UpdateGuarantee',
    required: true,
    description: 'JSON (sans fichier) ou multipart/form-data. Un nouveau `file` remplace le justificatif existant.',
    content: [
        new OA\JsonContent(ref: '#/components/schemas/GuaranteeInput'),
        new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(property: 'guarantee_type', ref: '#/components/schemas/GuaranteeType'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'declared_value', type: 'number', format: 'float'),
                    new OA\Property(property: 'file', description: 'Justificatif PDF, JPG ou PNG (max 10 Mo)', type: 'string', format: 'binary'),
                ]
            )
        ),
    ]
)]
class Schemas {}
