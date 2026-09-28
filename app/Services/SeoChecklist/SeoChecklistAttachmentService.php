<?php

namespace App\Services\SeoChecklist;

use App\SeoChecklist\SeoChecklistItem;
use App\SeoChecklist\SeoChecklistItemAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SeoChecklistAttachmentService
{
    /**
     * @return list<string>
     */
    public function allowedExtensions(): array
    {
        return array_values(array_map('strtolower', (array) config('cabinet-seo-checklist.attachments.extensions', [])));
    }

    public function maxKb(): int
    {
        return max(1, (int) config('cabinet-seo-checklist.attachments.max_kb', 10240));
    }

    public function maxFiles(): int
    {
        return max(1, (int) config('cabinet-seo-checklist.attachments.max_files', 5));
    }

    public function acceptAttribute(): string
    {
        return implode(',', array_map(function ($ext) {
            return '.' . $ext;
        }, $this->allowedExtensions()));
    }

    /**
     * @return list<UploadedFile>
     */
    public function filesFromRequest(Request $request, string $key = 'files'): array
    {
        $files = $request->file($key);
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }
        if (!is_array($files)) {
            return [];
        }

        return array_values(array_filter($files, function ($file) {
            return $file instanceof UploadedFile;
        }));
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    public function validate(array $files): ?string
    {
        if ($files === []) {
            return null;
        }
        if (!SeoChecklistItemAttachment::tableReady()) {
            return __('Attachments are not available yet');
        }
        if (count($files) > $this->maxFiles()) {
            return __('Too many files, max :count', ['count' => $this->maxFiles()]);
        }

        $allowed = $this->allowedExtensions();
        foreach ($files as $file) {
            $name = (string) $file->getClientOriginalName();
            if (!$file->isValid()) {
                return __('File :name was not uploaded', ['name' => $name]);
            }
            $ext = strtolower((string) $file->getClientOriginalExtension());
            if ($ext === '' || !in_array($ext, $allowed, true)) {
                return __('File type not allowed: :name', ['name' => $name]);
            }
            if ((int) $file->getSize() > $this->maxKb() * 1024) {
                return __('File :name is larger than :size MB', [
                    'name' => $name,
                    'size' => number_format($this->maxKb() / 1024, 0, '', ' '),
                ]);
            }
        }

        return null;
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return list<SeoChecklistItemAttachment>
     */
    public function store(SeoChecklistItem $item, ?int $noteId, int $userId, array $files): array
    {
        $saved = [];
        $dir = 'seo-checklist/' . (int) $item->project_id . '/' . (int) $item->id;
        foreach ($files as $file) {
            $ext = strtolower((string) $file->getClientOriginalExtension());
            $stored = Storage::disk(SeoChecklistItemAttachment::DISK)
                ->putFileAs($dir, $file, Str::random(32) . '.' . $ext);
            if (!$stored) {
                continue;
            }
            $saved[] = SeoChecklistItemAttachment::query()->create([
                'item_id' => (int) $item->id,
                'note_id' => $noteId,
                'user_id' => $userId > 0 ? $userId : null,
                'original_name' => Str::limit((string) $file->getClientOriginalName(), 250, ''),
                'path' => $stored,
                'mime' => Str::limit((string) $file->getClientMimeType(), 145, ''),
                'size' => (int) $file->getSize(),
            ]);
        }

        return $saved;
    }

    /**
     * @param  list<int>  $itemIds
     */
    public function deleteForItems(array $itemIds): void
    {
        if ($itemIds === [] || !SeoChecklistItemAttachment::tableReady()) {
            return;
        }

        $rows = SeoChecklistItemAttachment::query()->whereIn('item_id', $itemIds)->get(['id', 'path']);
        $disk = Storage::disk(SeoChecklistItemAttachment::DISK);
        foreach ($rows as $row) {
            try {
                $disk->delete((string) $row->path);
            } catch (\Throwable $e) {
                // файл уже мог быть удалён вручную
            }
        }
        SeoChecklistItemAttachment::query()->whereIn('item_id', $itemIds)->delete();
    }
}
