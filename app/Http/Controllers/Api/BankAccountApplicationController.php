<?php

namespace App\Http\Controllers\Api;

use App\Enums\BankAccountDocumentType;
use App\Enums\ClientType;
use App\Http\Controllers\Controller;
use App\Http\Requests\BankAccount\StoreBankAccountApplicationDocumentRequest;
use App\Http\Resources\BankAccountApplicationDocumentResource;
use App\Http\Resources\BankAccountApplicationResource;
use App\Models\BankAccountApplication;
use App\Models\Client;
use App\Services\BankAccountApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

abstract class BankAccountApplicationController extends Controller
{
    public function __construct(protected BankAccountApplicationService $applications) {}

    abstract protected function clientType(): ClientType;

    abstract protected function openApiTag(): string;

    protected function resolveClient(Request $request): Client
    {
        $client = $request->user()?->client;
        abort_unless($client, 403);
        abort_unless(
            $client->client_type === $this->clientType(),
            403,
            'Ce compte client ne correspond pas à ce type d’adhésion.'
        );

        return $client;
    }

    protected function assertApplicationType(BankAccountApplication $application): void
    {
        $application->loadMissing('client');
        abort_unless(
            $application->client?->client_type === $this->clientType(),
            404
        );
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BankAccountApplication::class);
        $client = $this->resolveClient($request);

        $items = BankAccountApplication::query()
            ->where('client_id', $client->id)
            ->with(['caisse', 'guichet', 'cashDesk', 'financialAccount', 'parties', 'documents'])
            ->latest('id')
            ->get();

        return response()->json([
            'client_type' => $this->clientType()->value,
            'data' => BankAccountApplicationResource::collection($items),
        ]);
    }

    public function show(BankAccountApplication $bankAccountApplication): JsonResponse
    {
        $this->assertApplicationType($bankAccountApplication);
        $this->authorize('view', $bankAccountApplication);

        $bankAccountApplication->load([
            'caisse',
            'guichet.cashDesks',
            'cashDesk',
            'financialAccount',
            'parties',
            'documents',
            'reviewer',
        ]);

        return response()->json([
            'application' => new BankAccountApplicationResource($bankAccountApplication),
        ]);
    }

    public function submit(Request $request, BankAccountApplication $bankAccountApplication): JsonResponse
    {
        $this->assertApplicationType($bankAccountApplication);
        $this->authorize('submit', $bankAccountApplication);

        try {
            $application = $this->applications->submit($bankAccountApplication, $request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'application' => new BankAccountApplicationResource($application),
        ]);
    }

    public function storeDocument(
        StoreBankAccountApplicationDocumentRequest $request,
        BankAccountApplication $bankAccountApplication
    ): JsonResponse {
        $this->assertApplicationType($bankAccountApplication);
        $type = BankAccountDocumentType::from($request->validated('document_type'));

        try {
            $document = $this->applications->storeDocument(
                $bankAccountApplication,
                $request->user(),
                $type,
                $request->file('file')
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'document' => new BankAccountApplicationDocumentResource($document),
        ], 201);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function storeDraft(Client $client, array $validated): JsonResponse
    {
        $parties = $validated['parties'] ?? [];
        unset($validated['parties']);

        $application = $this->applications->createDraft($client, $validated, $parties);

        return response()->json([
            'application' => new BankAccountApplicationResource($application),
        ], 201);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function updateDraft(BankAccountApplication $bankAccountApplication, array $validated): JsonResponse
    {
        $this->assertApplicationType($bankAccountApplication);

        $parties = array_key_exists('parties', $validated) ? $validated['parties'] : null;
        unset($validated['parties']);

        try {
            $application = $this->applications->updateDraft($bankAccountApplication, $validated, $parties);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'application' => new BankAccountApplicationResource($application),
        ]);
    }
}
