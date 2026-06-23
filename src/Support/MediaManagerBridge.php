<?php

namespace EdrisaTuray\FilamentStarterMinimal\Support;

use Illuminate\Database\Eloquent\Model;
use Slimani\MediaManager\Models\File;
use Slimani\MediaManager\Models\Folder;
use Slimani\MediaManager\Models\MediaAttachment;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Mirrors a Spatie Media Library upload into the Slimani Media Manager so the
 * file appears in the Media Manager UI under a per-model folder hierarchy.
 *
 * Bridging is opt-in per model via config('filament-starter-minimal.media_manager_bridge.models').
 * The original Spatie media row on the source model is preserved (a copy is
 * placed on the new Slimani File) so Filament's SpatieMediaLibraryFileUpload
 * continues to work for that model.
 */
class MediaManagerBridge
{
    public function handleMediaCreated(Media $media): void
    {
        if (! config('filament-starter-minimal.media_manager_bridge.enabled', true)) {
            return;
        }

        // Recursion guard: skip the media row we created on the new File below.
        if ($media->model_type === File::class) {
            return;
        }

        $owner = $this->resolveOwner($media);
        if ($owner === null) {
            return;
        }

        $ownerClass = $owner::class;
        $modelConfig = config("filament-starter-minimal.media_manager_bridge.models.{$ownerClass}");
        if ($modelConfig === null) {
            return;
        }

        $parentFolderName = $this->resolveParentFolderName($owner, $modelConfig);
        $subFolderName = $media->collection_name !== '' ? $media->collection_name : 'default';

        $parentFolder = Folder::query()->firstOrCreate([
            'parent_id' => null,
            'name' => $parentFolderName,
        ]);

        $subFolder = Folder::query()->firstOrCreate([
            'parent_id' => $parentFolder->id,
            'name' => $subFolderName,
        ]);

        $file = File::query()->create([
            'folder_id' => $subFolder->id,
            'uploaded_by_user_id' => auth()->id(),
            'name' => $media->name,
            'size' => $media->size,
            'extension' => pathinfo($media->file_name, PATHINFO_EXTENSION) ?: null,
            'mime_type' => $media->mime_type,
        ]);

        try {
            $media->copy($file, 'default');
        } catch (\Throwable $exception) {
            report($exception);
            $file->delete();

            return;
        }

        MediaAttachment::query()->create([
            'media_file_id' => $file->id,
            'attachable_type' => $ownerClass,
            'attachable_id' => $owner->getKey(),
            'collection' => $media->collection_name,
        ]);
    }

    /**
     * @param  array<string, mixed>  $modelConfig
     */
    private function resolveParentFolderName(Model $owner, array $modelConfig): string
    {
        $explicit = $modelConfig['folder_property'] ?? null;
        if (is_string($explicit) && filled($value = $owner->getAttribute($explicit))) {
            return (string) $value;
        }

        $defaults = config('filament-starter-minimal.media_manager_bridge.default_folder_properties', []);
        if (is_array($defaults)) {
            foreach ($defaults as $candidate) {
                if (! is_string($candidate)) {
                    continue;
                }
                $value = $owner->getAttribute($candidate);
                if (filled($value)) {
                    return (string) $value;
                }
            }
        }

        return (string) config('filament-starter-minimal.media_manager_bridge.fallback_folder', 'Uploads');
    }

    private function resolveOwner(Media $media): ?Model
    {
        // model_type may not be set (orphan media) or may point to a class the
        // app no longer has. getAttribute('model') resolves the polymorphic
        // relation and returns null in either case.
        $owner = $media->model;

        return $owner instanceof Model ? $owner : null;
    }
}
