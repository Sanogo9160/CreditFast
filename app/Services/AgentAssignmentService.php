<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Models\AgentAssignmentLog;
use App\Models\Caisse;
use App\Models\CreditRequest;
use App\Models\RoutingZone;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class AgentAssignmentService
{
    /**
     * @return array{agencies: list<array{code: string, name: string, city: string|null}>, zones: list<array{agency_code: string, code: string, name: string}>}
     */
    public function catalog(): array
    {
        $agencies = Caisse::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['code', 'name', 'city'])
            ->map(fn (Caisse $caisse): array => [
                'code' => $caisse->code,
                'name' => $caisse->name,
                'city' => $caisse->city,
            ])
            ->values()
            ->all();

        $zones = RoutingZone::query()
            ->where('is_active', true)
            ->orderBy('agency_code')
            ->orderBy('code')
            ->get(['agency_code', 'code', 'name'])
            ->map(fn (RoutingZone $zone): array => [
                'agency_code' => $zone->agency_code,
                'code' => $zone->code,
                'name' => $zone->name,
            ])
            ->values()
            ->all();

        return [
            'agencies' => $agencies,
            'zones' => $zones,
        ];
    }

    public function assertZoneBelongsToAgency(string $agencyCode, string $zoneCode): void
    {
        $exists = RoutingZone::query()
            ->where('agency_code', $agencyCode)
            ->where('code', $zoneCode)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'zone_code' => 'La zone choisie doit appartenir à l’agence du compte épargne. Corrigez la zone, ou faites corriger le rattachement en agence.',
            ]);
        }
    }

    /**
     * Affecte un agent disponible (tour de rôle) pour l’agence et la zone.
     *
     * @return array{agent: ?User, unassigned_reason: ?string}
     */
    public function assignOnSubmit(CreditRequest $creditRequest, string $agencyCode, string $zoneCode): array
    {
        if ($creditRequest->assigned_agent_id !== null) {
            return [
                'agent' => $creditRequest->assignedAgent,
                'unassigned_reason' => null,
            ];
        }

        $agent = $this->nextAvailableAgent($agencyCode, $zoneCode);

        $creditRequest->forceFill([
            'agency_code' => $agencyCode,
            'zone_code' => $zoneCode,
            'assigned_agent_id' => $agent?->id,
            'assignment_reason' => $agent
                ? 'Affectation automatique à la soumission'
                : 'Aucun agent disponible pour cette zone',
        ])->save();

        return [
            'agent' => $agent,
            'unassigned_reason' => $agent ? null : 'Aucun agent disponible pour cette zone',
        ];
    }

    public function assignManually(
        CreditRequest $creditRequest,
        User $newAgent,
        User $actor,
        string $reason
    ): CreditRequest {
        if ($creditRequest->agency_code && $newAgent->agency_code !== $creditRequest->agency_code) {
            throw ValidationException::withMessages([
                'agent_id' => 'Une réaffectation ne déplace pas le dossier vers une autre agence.',
            ]);
        }

        $previousAgentId = $creditRequest->assigned_agent_id;

        $creditRequest->forceFill([
            'assigned_agent_id' => $newAgent->id,
            'assignment_reason' => $reason,
        ])->save();

        AgentAssignmentLog::create([
            'credit_request_id' => $creditRequest->id,
            'assigned_by' => $actor->id,
            'previous_agent_id' => $previousAgentId,
            'new_agent_id' => $newAgent->id,
            'reason' => $reason,
        ]);

        return $creditRequest->fresh(['assignedAgent']);
    }

    public function updateAgentCoverage(User $agent, array $data): User
    {
        $agent->forceFill([
            'agency_code' => $data['agency_code'] ?? $agent->agency_code,
            'zone_codes' => $data['zone_codes'] ?? $agent->zone_codes,
            'available' => array_key_exists('available', $data) ? (bool) $data['available'] : $agent->available,
        ])->save();

        return $agent->fresh('role');
    }

    public function nextAvailableAgent(string $agencyCode, string $zoneCode): ?User
    {
        $candidates = User::query()
            ->where('status', 'active')
            ->where('available', true)
            ->where('agency_code', $agencyCode)
            ->whereHas('role', fn ($q) => $q->where('name', RoleName::CreditAgent->value))
            ->orderBy('id')
            ->get()
            ->filter(function (User $user) use ($zoneCode): bool {
                $zones = $user->zone_codes ?? [];

                return $zones === [] || in_array($zoneCode, $zones, true);
            })
            ->values();

        if ($candidates->isEmpty()) {
            return null;
        }

        $cacheKey = "agent_rr:{$agencyCode}:{$zoneCode}";
        $lastId = (int) Cache::get($cacheKey, 0);
        $next = $candidates->first(fn (User $user): bool => $user->id > $lastId) ?? $candidates->first();

        if ($next) {
            Cache::forever($cacheKey, $next->id);
        }

        return $next;
    }
}
