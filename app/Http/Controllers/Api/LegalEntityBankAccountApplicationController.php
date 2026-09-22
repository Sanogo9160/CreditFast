<?php

namespace App\Http\Controllers\Api;

use App\Enums\ClientType;
use App\Http\Requests\BankAccount\StoreBankAccountApplicationDocumentRequest;
use App\Http\Requests\BankAccount\StoreLegalEntityBankAccountApplicationRequest;
use App\Http\Requests\BankAccount\UpdateLegalEntityBankAccountApplicationRequest;
use App\Models\BankAccountApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class LegalEntityBankAccountApplicationController extends BankAccountApplicationController
{
    protected function clientType(): ClientType
    {
        return ClientType::LegalEntity;
    }

    protected function openApiTag(): string
    {
        return 'Adhésion compte PM';
    }

    #[OA\Get(
        path: '/api/bank-account-applications/legal-entity',
        operationId: 'pmBankAccountApplicationsIndex',
        tags: ['Adhésion compte PM'],
        summary: '[Lister] Mes demandes d’adhésion (personne morale)',
        description: '**Rôle :** Client `LEGAL_ENTITY` uniquement.',
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Liste')]
    )]
    public function index(Request $request): JsonResponse
    {
        return parent::index($request);
    }

    #[OA\Post(
        path: '/api/bank-account-applications/legal-entity',
        operationId: 'pmBankAccountApplicationsStore',
        tags: ['Adhésion compte PM'],
        summary: '[Créer] Fiche d’adhésion personne morale',
        description: 'Remplir le **body JSON** : société, `parties` (dirigeants SIGNATORY ≤3 + UBO), origine des fonds, checklist. **Pas de classification risque.**',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreLegalEntityBankAccountApplicationRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Brouillon créé'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function store(StoreLegalEntityBankAccountApplicationRequest $request): JsonResponse
    {
        return $this->storeDraft($this->resolveClient($request), $request->validated());
    }

    #[OA\Get(
        path: '/api/bank-account-applications/legal-entity/{bankAccountApplication}',
        operationId: 'pmBankAccountApplicationsShow',
        tags: ['Adhésion compte PM'],
        summary: '[Lire] Une demande PM',
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Détail')]
    )]
    public function show(BankAccountApplication $bankAccountApplication): JsonResponse
    {
        return parent::show($bankAccountApplication);
    }

    #[OA\Put(
        path: '/api/bank-account-applications/legal-entity/{bankAccountApplication}',
        operationId: 'pmBankAccountApplicationsUpdate',
        tags: ['Adhésion compte PM'],
        summary: '[Modifier] Brouillon ou demande renvoyée (PM)',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreLegalEntityBankAccountApplicationRequest')
        ),
        responses: [new OA\Response(response: 200, description: 'Mis à jour')]
    )]
    public function update(
        UpdateLegalEntityBankAccountApplicationRequest $request,
        BankAccountApplication $bankAccountApplication
    ): JsonResponse {
        return $this->updateDraft($bankAccountApplication, $request->validated());
    }

    #[OA\Post(
        path: '/api/bank-account-applications/legal-entity/{bankAccountApplication}/submit',
        operationId: 'pmBankAccountApplicationsSubmit',
        tags: ['Adhésion compte PM'],
        summary: '[Soumettre] La fiche PM',
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Soumise')]
    )]
    public function submit(Request $request, BankAccountApplication $bankAccountApplication): JsonResponse
    {
        return parent::submit($request, $bankAccountApplication);
    }

    #[OA\Post(
        path: '/api/bank-account-applications/legal-entity/{bankAccountApplication}/documents',
        operationId: 'pmBankAccountApplicationsStoreDocument',
        tags: ['Adhésion compte PM'],
        summary: '[Ajouter] Une pièce PM (NIF, RCCM, statuts…)',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(ref: '#/components/schemas/StoreBankAccountApplicationDocumentRequest')
            )
        ),
        responses: [new OA\Response(response: 201, description: 'Pièce ajoutée')]
    )]
    public function storeDocument(
        StoreBankAccountApplicationDocumentRequest $request,
        BankAccountApplication $bankAccountApplication
    ): JsonResponse {
        return parent::storeDocument($request, $bankAccountApplication);
    }
}
