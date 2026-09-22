<?php

namespace App\Services;

use App\Enums\BankAccountApplicationStatus;
use App\Enums\BankAccountDocumentType;
use App\Enums\BankAccountPartyRole;
use App\Enums\ClientType;
use App\Models\BankAccountApplication;
use App\Models\BankAccountApplicationDocument;
use App\Models\CashDesk;
use App\Models\Client;
use App\Models\Guichet;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class BankAccountApplicationService
{
    public function __construct(
        protected AccountNumberGenerator $accountNumbers,
        protected AuditLogger $auditLogger,
        protected InstitutionalHistoryService $institutionalHistory,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $parties
     */
    public function createDraft(Client $client, array $attributes, array $parties = []): BankAccountApplication
    {
        $this->assertNoOpenApplication($client);
        $this->assertSelectableGuichet(
            (int) $attributes['caisse_id'],
            (int) $attributes['guichet_id']
        );

        return DB::transaction(function () use ($client, $attributes, $parties) {
            $application = BankAccountApplication::create([
                ...$this->baseAttributesFromClient($client),
                ...$attributes,
                'client_id' => $client->id,
                'status' => BankAccountApplicationStatus::Draft,
            ]);

            $this->syncParties($application, $client, $parties);

            $this->auditLogger->record($client->user, 'BANK_ACCOUNT_APPLICATION_CREATED', BankAccountApplication::class, $application->id, [
                'client_id' => $client->id,
            ]);

            return $application->fresh(['parties', 'documents', 'caisse', 'guichet']);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>|null  $parties
     */
    public function updateDraft(BankAccountApplication $application, array $attributes, ?array $parties = null): BankAccountApplication
    {
        if (! $application->status->isEditableByClient()) {
            throw new InvalidArgumentException('Cette demande ne peut plus être modifiée.');
        }

        if (isset($attributes['caisse_id'], $attributes['guichet_id'])) {
            $this->assertSelectableGuichet(
                (int) $attributes['caisse_id'],
                (int) $attributes['guichet_id']
            );
        } elseif (isset($attributes['guichet_id'])) {
            $this->assertSelectableGuichet(
                (int) $application->caisse_id,
                (int) $attributes['guichet_id']
            );
        }

        return DB::transaction(function () use ($application, $attributes, $parties) {
            $application->fill($attributes);
            $application->save();

            if ($parties !== null) {
                $this->syncParties($application, $application->client, $parties);
            }

            return $application->fresh(['parties', 'documents', 'caisse', 'guichet']);
        });
    }

    public function submit(BankAccountApplication $application, User $actor): BankAccountApplication
    {
        if (! $application->status->isEditableByClient()) {
            throw new InvalidArgumentException('Seules les demandes en brouillon ou renvoyées peuvent être soumises.');
        }

        $this->assertSelectableGuichet((int) $application->caisse_id, (int) $application->guichet_id);
        $this->assertReadyForSubmit($application);

        $application->status = BankAccountApplicationStatus::Submitted;
        $application->review_comment = null;
        $application->save();

        $this->auditLogger->record($actor, 'BANK_ACCOUNT_APPLICATION_SUBMITTED', BankAccountApplication::class, $application->id);

        Notification::create([
            'user_id' => $actor->id,
            'title' => 'Demande d’adhésion soumise',
            'message' => 'Votre fiche d’adhésion a été transmise pour vérification.',
            'type' => 'BANK_ACCOUNT_APPLICATION',
        ]);

        return $application->fresh(['parties', 'documents', 'caisse', 'guichet']);
    }

    public function returnForCompletion(BankAccountApplication $application, User $actor, string $comment): BankAccountApplication
    {
        if ($application->status !== BankAccountApplicationStatus::Submitted) {
            throw new InvalidArgumentException('Seules les demandes soumises peuvent être renvoyées au client.');
        }

        $application->status = BankAccountApplicationStatus::Returned;
        $application->review_comment = $comment;
        $application->reviewed_by = $actor->id;
        $application->reviewed_at = now();
        $application->save();

        $this->auditLogger->record($actor, 'BANK_ACCOUNT_APPLICATION_RETURNED', BankAccountApplication::class, $application->id, [
            'comment' => $comment,
        ]);

        $clientUserId = $application->client?->user_id;
        if ($clientUserId) {
            Notification::create([
                'user_id' => $clientUserId,
                'title' => 'Compléments demandés — adhésion',
                'message' => $comment,
                'type' => 'BANK_ACCOUNT_APPLICATION',
            ]);
        }

        return $application->fresh(['parties', 'documents', 'caisse', 'guichet', 'reviewer']);
    }

    public function reject(BankAccountApplication $application, User $actor, string $comment): BankAccountApplication
    {
        if ($application->status !== BankAccountApplicationStatus::Submitted) {
            throw new InvalidArgumentException('Seules les demandes soumises peuvent être refusées.');
        }

        $application->status = BankAccountApplicationStatus::Rejected;
        $application->review_comment = $comment;
        $application->reviewed_by = $actor->id;
        $application->reviewed_at = now();
        $application->save();

        $this->auditLogger->record($actor, 'BANK_ACCOUNT_APPLICATION_REJECTED', BankAccountApplication::class, $application->id, [
            'comment' => $comment,
        ]);

        $clientUserId = $application->client?->user_id;
        if ($clientUserId) {
            Notification::create([
                'user_id' => $clientUserId,
                'title' => 'Adhésion refusée',
                'message' => $comment,
                'type' => 'BANK_ACCOUNT_APPLICATION',
            ]);
        }

        return $application->fresh(['parties', 'documents', 'caisse', 'guichet', 'reviewer']);
    }

    /**
     * @param  array{cash_desk_id?: int|null, account_type?: string|null}  $options
     */
    public function approve(BankAccountApplication $application, User $actor, array $options = []): BankAccountApplication
    {
        if ($application->status !== BankAccountApplicationStatus::Submitted) {
            throw new InvalidArgumentException('Seules les demandes soumises peuvent être approuvées.');
        }

        $application->loadMissing(['caisse', 'guichet.cashDesks', 'client.user']);

        $this->assertSelectableGuichet((int) $application->caisse_id, (int) $application->guichet_id);

        $cashDesk = $this->resolveCashDesk($application->guichet, $options['cash_desk_id'] ?? null);

        return DB::transaction(function () use ($application, $actor, $cashDesk, $options) {
            $accountNumber = $this->accountNumbers->generate($application->caisse, $application->guichet);

            $account = $this->institutionalHistory->createAccount($application->client, [
                'account_number' => $accountNumber,
                'account_type' => $options['account_type'] ?? 'SAVINGS',
                'balance' => 0,
                'opened_at' => now()->toDateString(),
                'status' => 'ACTIVE',
                'caisse_id' => $application->caisse_id,
                'guichet_id' => $application->guichet_id,
                'cash_desk_id' => $cashDesk->id,
                'bank_account_application_id' => $application->id,
            ], $actor);

            $application->status = BankAccountApplicationStatus::Approved;
            $application->cash_desk_id = $cashDesk->id;
            $application->financial_account_id = $account->id;
            $application->reviewed_by = $actor->id;
            $application->reviewed_at = now();
            $application->review_comment = null;
            $application->adhesion_date = $application->adhesion_date ?? now()->toDateString();
            $application->save();

            $this->syncClientProfile($application);

            $this->auditLogger->record($actor, 'BANK_ACCOUNT_APPLICATION_APPROVED', BankAccountApplication::class, $application->id, [
                'account_number' => $accountNumber,
                'financial_account_id' => $account->id,
            ]);

            $clientUserId = $application->client?->user_id;
            if ($clientUserId) {
                Notification::create([
                    'user_id' => $clientUserId,
                    'title' => 'Compte bancaire ouvert',
                    'message' => "Votre compte {$accountNumber} a été créé avec succès.",
                    'type' => 'BANK_ACCOUNT_APPLICATION',
                ]);
            }

            return $application->fresh(['parties', 'documents', 'caisse', 'guichet', 'cashDesk', 'financialAccount', 'reviewer']);
        });
    }

    public function storeDocument(
        BankAccountApplication $application,
        User $actor,
        BankAccountDocumentType $type,
        UploadedFile $file
    ): BankAccountApplicationDocument {
        if (! $application->status->isEditableByClient() && ! $actor->isStaff()) {
            throw new InvalidArgumentException('Impossible d’ajouter une pièce à cette demande.');
        }

        $path = $file->store("bank_account_applications/{$application->id}", 'local');

        $document = $application->documents()->create([
            'document_type' => $type,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'uploaded_by' => $actor->id,
        ]);

        $this->auditLogger->record($actor, 'BANK_ACCOUNT_APPLICATION_DOCUMENT_UPLOADED', BankAccountApplicationDocument::class, $document->id, [
            'application_id' => $application->id,
            'document_type' => $type->value,
        ]);

        return $document;
    }

    protected function assertNoOpenApplication(Client $client): void
    {
        $exists = BankAccountApplication::query()
            ->where('client_id', $client->id)
            ->whereIn('status', [
                BankAccountApplicationStatus::Draft,
                BankAccountApplicationStatus::Submitted,
                BankAccountApplicationStatus::Returned,
            ])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'application' => 'Vous avez déjà une demande d’adhésion en cours.',
            ]);
        }
    }

    protected function assertSelectableGuichet(int $caisseId, int $guichetId): void
    {
        $guichet = Guichet::query()
            ->with(['caisse', 'cashDesks'])
            ->whereKey($guichetId)
            ->where('caisse_id', $caisseId)
            ->first();

        if ($guichet === null || ! $guichet->isSelectable()) {
            throw ValidationException::withMessages([
                'guichet_id' => 'Le guichet choisi est invalide, inactif, ou n’a pas de case active.',
            ]);
        }
    }

    protected function resolveCashDesk(Guichet $guichet, mixed $cashDeskId): CashDesk
    {
        if ($cashDeskId !== null && $cashDeskId !== '') {
            $desk = CashDesk::query()
                ->whereKey((int) $cashDeskId)
                ->where('guichet_id', $guichet->id)
                ->where('is_active', true)
                ->first();

            if ($desk === null) {
                throw ValidationException::withMessages([
                    'cash_desk_id' => 'La case sélectionnée est invalide pour ce guichet.',
                ]);
            }

            return $desk;
        }

        $desk = $guichet->firstActiveCashDesk();

        if ($desk === null) {
            throw ValidationException::withMessages([
                'guichet_id' => 'Ce guichet n’a aucune case active.',
            ]);
        }

        return $desk;
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseAttributesFromClient(Client $client): array
    {
        $user = $client->user;

        return [
            'first_name' => $user?->first_name,
            'last_name' => $user?->last_name,
            'email' => $user?->email,
            'phone' => $user?->phone,
            'city' => $client->city,
            'residential_zone' => $client->residential_zone,
            'address' => $client->address,
            'date_of_birth' => $client->date_of_birth,
            'profession' => $client->occupation,
            'company_name' => $client->company_name,
            'legal_form' => $client->legal_form,
            'tax_id' => $client->tax_id,
            'rccm_number' => $client->rccm_number ?? $client->registration_number,
            'receipt_number' => $client->receipt_number,
            'inps_number' => $client->inps_number,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $parties
     */
    protected function syncParties(BankAccountApplication $application, Client $client, array $parties): void
    {
        if ($client->client_type !== ClientType::LegalEntity) {
            $application->parties()->delete();

            return;
        }

        $application->parties()->delete();

        foreach ($parties as $index => $party) {
            $application->parties()->create([
                'role' => $party['role'] ?? BankAccountPartyRole::Signatory->value,
                'sort_order' => $party['sort_order'] ?? ($index + 1),
                'first_name' => $party['first_name'],
                'last_name' => $party['last_name'],
                'date_of_birth' => $party['date_of_birth'] ?? null,
                'birth_place' => $party['birth_place'] ?? null,
                'nationality' => $party['nationality'] ?? null,
                'function_in_company' => $party['function_in_company'] ?? null,
                'id_document_type' => $party['id_document_type'] ?? null,
                'id_document_number' => $party['id_document_number'] ?? null,
                'address' => $party['address'] ?? null,
                'phone' => $party['phone'] ?? null,
                'link_with_company' => $party['link_with_company'] ?? null,
            ]);
        }
    }

    protected function assertReadyForSubmit(BankAccountApplication $application): void
    {
        $application->loadMissing(['client', 'parties']);

        $client = $application->client;
        $errors = [];

        if ($client->isPhysicalPerson()) {
            foreach ([
                'first_name' => 'prénoms',
                'last_name' => 'nom',
                'date_of_birth' => 'date de naissance',
                'birth_place' => 'lieu de naissance',
                'nationality' => 'nationalité',
                'gender' => 'sexe',
                'city' => 'ville',
                'address' => 'adresse',
                'phone' => 'téléphone',
                'id_document_type' => 'type de pièce',
                'id_document_number' => 'numéro de pièce',
                'funds_origin' => 'origine des fonds',
                'account_main_usage' => 'usage principal du compte',
            ] as $field => $label) {
                if (blank($application->{$field})) {
                    $errors[$field] = "Le champ {$label} est obligatoire pour soumettre.";
                }
            }
        } else {
            foreach ([
                'company_name' => 'raison sociale',
                'legal_form' => 'forme juridique',
                'tax_id' => 'NIF',
                'rccm_number' => 'RCCM / agrément',
                'head_office_address' => 'adresse du siège',
                'main_activity' => 'activité principale',
                'initial_contribution_origin' => 'origine de l’apport',
                'planned_operations_nature' => 'nature des opérations prévues',
            ] as $field => $label) {
                if (blank($application->{$field})) {
                    $errors[$field] = "Le champ {$label} est obligatoire pour soumettre.";
                }
            }

            $signatories = $application->parties
                ->where('role', BankAccountPartyRole::Signatory)
                ->count();

            if ($signatories < 1) {
                $errors['signatories'] = 'Au moins un signataire (dirigeant) est obligatoire.';
            }

            if ($signatories > 3) {
                $errors['signatories'] = 'Un maximum de 3 signataires est autorisé.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function syncClientProfile(BankAccountApplication $application): void
    {
        $client = $application->client;
        if ($client === null) {
            return;
        }

        $client->fill([
            'date_of_birth' => $application->date_of_birth ?? $client->date_of_birth,
            'address' => $application->address ?? $application->head_office_address ?? $client->address,
            'city' => $application->city ?? $client->city,
            'residential_zone' => $application->residential_zone ?? $client->residential_zone,
            'occupation' => $application->profession ?? $client->occupation,
            'company_name' => $application->company_name ?? $client->company_name,
            'legal_form' => $application->legal_form ?? $client->legal_form,
            'tax_id' => $application->tax_id ?? $client->tax_id,
            'rccm_number' => $application->rccm_number ?? $client->rccm_number,
            'receipt_number' => $application->receipt_number ?? $client->receipt_number,
            'inps_number' => $application->inps_number ?? $client->inps_number,
            'registration_number' => $application->rccm_number
                ?? $application->tax_id
                ?? $client->registration_number,
            'institution_verified_at' => now(),
        ]);
        $client->save();
    }
}
