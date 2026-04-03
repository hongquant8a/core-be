<?php

namespace App\Modules\Core\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaService
{
    /**
     * Upload một file vào collection của model.
     */
    public function uploadOne(HasMedia $model, UploadedFile $file, string $collection, array $options = []): Media
    {
        $this->assertFileValid($file, $options);

        $disk = $options['disk'] ?? config('media-library.disk_name', 'public');
        $customProperties = array_merge(
            ['original_name' => $file->getClientOriginalName()],
            $options['custom_properties'] ?? []
        );

        return $model->addMedia($file)
            ->usingName(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
            ->usingFileName($file->hashName())
            ->withCustomProperties($customProperties)
            ->toMediaCollection($collection, $disk);
    }

    /**
     * Upload nhiều file vào collection.
     *
     * @return array<int, array{disk: string, path: string}>
     */
    public function uploadMany(HasMedia $model, array $files, string $collection, array $options = []): array
    {
        $storedFiles = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $media = $this->uploadOne($model, $file, $collection, $options);

            $storedFiles[] = [
                'disk' => $media->disk,
                'path' => $media->getPathRelativeToRoot(),
            ];
        }

        return $storedFiles;
    }

    public function removeByIds(HasMedia $model, array $mediaIds, ?string $collection = null): void
    {
        if (empty($mediaIds)) {
            return;
        }

        $query = $model->media()->whereIn('id', $mediaIds);
        if ($collection) {
            $query->where('collection_name', $collection);
        }

        $query->get()->each->delete();
    }

    /**
     * Cleanup file vật lý khi transaction rollback.
     *
     * @param  array<int, array{disk: string, path: string}>  $storedFiles
     */
    public function cleanupStoredFiles(array $storedFiles): void
    {
        foreach ($storedFiles as $storedFile) {
            if (! empty($storedFile['disk']) && ! empty($storedFile['path'])) {
                Storage::disk($storedFile['disk'])->delete($storedFile['path']);
            }
        }
    }

    /**
     * Decode base64 data URI thành UploadedFile.
     * Input: "data:image/png;base64,iVBOR..." → UploadedFile
     * Trả null nếu không phải base64 data URI.
     */
    public function decodeBase64ToFile(string $value): ?UploadedFile
    {
        if (! preg_match('/^data:([\w\/\+\-\.]+);base64,(.+)$/s', $value, $matches)) {
            return null;
        }

        $mimeType = $matches[1];
        $data = base64_decode($matches[2], true);

        if ($data === false) {
            return null;
        }

        $extensions = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            'image/webp' => 'webp',
            'image/x-icon' => 'ico',
            'image/vnd.microsoft.icon' => 'ico',
        ];

        $ext = $extensions[$mimeType] ?? 'bin';
        $tmpPath = tempnam(sys_get_temp_dir(), 'media_').'.'.$ext;
        file_put_contents($tmpPath, $data);

        return new UploadedFile($tmpPath, 'upload.'.$ext, $mimeType, null, true);
    }

    private function assertFileValid(UploadedFile $file, array $options): void
    {
        $allowedMimes = $options['allowed_mimes'] ?? [];
        $maxSizeKb = $options['max_size_kb'] ?? null;

        if (! empty($allowedMimes) && ! in_array($file->getMimeType(), $allowedMimes, true)) {
            throw new InvalidArgumentException('Định dạng file không được hỗ trợ.');
        }

        if ($maxSizeKb !== null && $file->getSize() > ($maxSizeKb * 1024)) {
            throw new InvalidArgumentException('Kích thước file vượt quá giới hạn cho phép.');
        }
    }
}
