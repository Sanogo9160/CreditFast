<?php

namespace App\Http\Controllers\Api;

use App\Enums\ClientType;
use App\Http\Requests\BankAccount\StoreBankAccountApplicationDocumentRequest;
use App\Http\Requests\BankAccount\StorePhysicalPersonBankAccountApplicationRequest;
use App\Http\Requests\BankAccount\UpdatePhysicalPersonBankAccountApplicationRequest;
use App\Models\BankAccountApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PhysicalPersonBankAccountApplicationController extends BankAccountApplicationController
{
    protected function clientType(): ClientType
    {
        return ClientType::PhysicalPerson;
    }

    protected function openApiTag(): string
    {
        return 'Adhésion compte PP';
    }

    #[OA\Get(
        path: '/api/bank-account-applications/physical-person',
        operationId: 'ppBankAccountApplicationsIndex',
        tags: ['Adhésion compte PP'],
        summary: '[Lister] Mes demandes d’adhésion (personne physique)',
        description: '**Rôle :** Client `PHYSICAL_PERSON` uniquement.',
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Liste')]
    )]
    public function index(Request $request): JsonResponse
    {
        return parent::index($request);
    }

    #[OA\Post(
        path: '/api/bank-account-applications/physical-person',
        operationId: 'ppBankAccountApplicationsStore',
        tags: ['Adhésion compte PP'],
        summary: '[Créer] Fiche d’adhésion personne physique',
        description: 'Remplir le **body JSON** avec les champs de la fiche papier (identité, coordonnées, pièce, activité & fonds, checklist). `caisse_id` + `guichet_id` obligatoires. **Pas de classification risque.**',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StorePhysicalPersonBankAccountApplicationRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Brouillon créé'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function store(StorePhysicalPersonBankAccountApplicationRequest $request): JsonResponse
    {
        return $this->storeDraft($this->resolveClient($request), $request->validated());
    }

    #[OA\Get(
        path: '/api/bank-account-applications/physical-person/{bankAccountApplication}',
        operationId: 'ppBankAccountApplicationsShow',
        tags: ['Adhésion compte PP'],
        summary: '[Lire] Une demande PP',
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Détail')]
    )]
    public function show(BankAccountApplication $bankAccountApplication): JsonResponse
    {
        return parent::show($bankAccountApplication);
    }

    #[OA\Put(
        path: '/api/bank-account-applications/physical-person/{bankAccountApplication}',
        operationId: 'ppBankAccountApplicationsUpdate',
        tags: ['Adhésion compte PP'],
        summary: '[Modifier] Brouillon ou demande renvoyée (PP)',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StorePhysicalPersonBankAccountApplicationRequest')
        ),
        responses: [new OA\Response(response: 200, description: 'Mis à jour')]
    )]
    public function update(
        UpdatePhysicalPersonBankAccountApplicationRequest $request,
        BankAccountApplication $bankAccountApplication
    ): JsonResponse {
        return $this->updateDraft($bankAccountApplication, $request->validated());
    }

    #[OA\Post(
        path: '/api/bank-account-applications/physical-person/{bankAccountApplication}/submit',
        operationId: 'ppBankAccountApplicationsSubmit',
        tags: ['Adhésion compte PP'],
        summary: '[Soumettre] La fiche PP',
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Soumise')]
    )]
    public function submit(Request $request, BankAccountApplication $bankAccountApplication): JsonResponse
    {
        return parent::submit($request, $bankAccountApplication);
    }

    #[OA\Post(
        path: '/api/bank-account-applications/physical-person/{bankAccountApplication}/documents',
        operationId: 'ppBankAccountApplicationsStoreDocument',
        tags: ['Adhésion compte PP'],
        summary: '[Ajouter] Une pièce PP (CNI, domicile, revenus…)',
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
