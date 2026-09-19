<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProfilePhotoService
{
    public function store(User $user, UploadedFile $file): User
    {
        if ($user->profile_photo_path) {
            throw new ConflictHttpException('Une photo de profil est déjà enregistrée. Utilisez la mise à jour pour la remplacer.');
        }

        return $this->writePhoto($user, $file);
    }

    public function update(User $user, UploadedFile $file): User
    {
        $this->ensurePhotoExists($user);
        $this->deleteStoredFile($user);

        return $this->writePhoto($user, $file);
    }

    public function delete(User $user): User
    {
        $this->ensurePhotoExists($user);
        $this->deleteStoredFile($user);

        $user->update(['profile_photo_path' => null]);

        return $user->fresh(['role']) ?? $user;
    }

    protected function writePhoto(User $user, UploadedFile $file): User
    {
        $path = $file->store("profile_photos/{$user->id}");

        $user->update(['profile_photo_path' => $path]);

        return $user->fresh(['role']) ?? $user;
    }

    protected function ensurePhotoExists(User $user): void
    {
        if (! $user->profile_photo_path) {
            throw new NotFoundHttpException('Aucune photo de profil n’est enregistrée pour le moment.');
        }
    }

    protected function deleteStoredFile(User $user): void
    {
        if ($user->profile_photo_path && Storage::exists($user->profile_photo_path)) {
            Storage::delete($user->profile_photo_path);
        }
    }
}
