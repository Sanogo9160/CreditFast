<?php

namespace App\Services;

use App\Enums\ScoringMode;
use App\Enums\ScoringModelStatus;
use App\Models\ScoringModel;
use App\Models\ScoringRule;
use App\Models\User;
use InvalidArgumentException;

class ScoringModelAdminService
{
    public function __construct(protected AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createModel(array $attributes, User $actor): ScoringModel
    {
        $model = ScoringModel::create([
            ...$attributes,
            'status' => $attributes['status'] ?? ScoringModelStatus::Draft,
            'created_by' => $actor->id,
        ]);

        $this->auditLogger->record($actor, 'SCORING_MODEL_CREATED', ScoringModel::class, $model->id, [
            'version' => $model->version,
            'scoring_mode' => $model->scoring_mode?->value,
        ]);

        return $model;
    }

    public function changeStatus(ScoringModel $model, ScoringModelStatus $status, User $actor): ScoringModel
    {
        if ($status === ScoringModelStatus::Active) {
            ScoringModel::query()
                ->where('scoring_mode', $model->scoring_mode)
                ->where('id', '!=', $model->id)
                ->where('status', ScoringModelStatus::Active)
                ->update(['status' => ScoringModelStatus::Inactive, 'effective_to' => now()]);

            $model->effective_from = $model->effective_from ?? now();
            $model->effective_to = null;
        }

        $model->status = $status;
        $model->save();

        $this->auditLogger->record($actor, 'SCORING_MODEL_STATUS_CHANGED', ScoringModel::class, $model->id, [
            'status' => $status->value,
            'scoring_mode' => $model->scoring_mode instanceof ScoringMode ? $model->scoring_mode->value : $model->scoring_mode,
        ]);

        return $model->fresh('rules');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function addRule(ScoringModel $model, array $attributes, User $actor): ScoringRule
    {
        if ($model->status === ScoringModelStatus::Archived) {
            throw new InvalidArgumentException('Impossible d’ajouter une règle à un modèle archivé.');
        }

        $rule = $model->rules()->create($attributes);

        $this->auditLogger->record($actor, 'SCORING_RULE_CREATED', ScoringRule::class, $rule->id, [
            'scoring_model_id' => $model->id,
            'rule_code' => $rule->rule_code,
        ]);

        return $rule;
    }
}
