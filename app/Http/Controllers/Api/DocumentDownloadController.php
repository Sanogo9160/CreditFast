<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\KycDocument;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController extends Controller
{
    #[OA\Get(
        path: '/api/documents/{document}/file',
        operationId: 'documentsDownloadCredit',
        tags: ['Documents'],
        summary: '[Lire] Télécharger une pièce de dossier',
        description: '**Rôles :** Client propriétaire ou staff. Réponse binaire (fichier).',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/DocumentId')],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Fichier',
                content: new OA\MediaType(
                    mediaType: 'application/octet-stream',
                    schema: new OA\Schema(type: 'string', format: 'binary')
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function creditDocument(Document $document): StreamedResponse
    {
        $this->authorize('view', $document);

        abort_unless(Storage::exists($document->file_path), 404, 'Ce fichier n’est pas disponible pour le moment.');

        return Storage::download($document->file_path, $document->original_filename);
    }

    #[OA\Get(
        path: '/api/kyc-documents/{kycDocument}/file',
        operationId: 'documentsDownloadKyc',
        tags: ['Documents'],
        summary: '[Lire] Télécharger une pièce d’identité',
        description: '**Rôles :** Client propriétaire ou staff. Réponse binaire (fichier).',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/KycDocumentId')],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Fichier',
                content: new OA\MediaType(
                    mediaType: 'application/octet-stream',
                    schema: new OA\Schema(type: 'string', format: 'binary')
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function kycDocument(KycDocument $kycDocument): StreamedResponse
    {
        $this->authorize('view', $kycDocument);

        abort_unless(Storage::exists($kycDocument->file_path), 404, 'Ce fichier n’est pas disponible pour le moment.');

        return Storage::download($kycDocument->file_path, basename($kycDocument->file_path));
    }
}
