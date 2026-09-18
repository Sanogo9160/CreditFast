<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        $document->loadMissing('creditRequest');

        return $user->can('view', $document->creditRequest);
    }

    public function delete(User $user, Document $document): bool
    {
        $document->loadMissing('creditRequest');

        return $user->can('delete', $document->creditRequest)
            || $user->can('uploadDocument', $document->creditRequest);
    }
}
