<?php

declare(strict_types=1);

namespace Plugin\SynaScriber\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\File\FileUploadService;
use App\Service\File\UploadOptions;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Saves the transcript as a Markdown file in the starter's Synaplan Files
 * folder, extracted and vectorized like an upload so chat can use it.
 */
final readonly class TranscriptWriter
{
    private const SOURCE = 'api';

    public function __construct(
        private FileUploadService $uploads,
        private UserRepository $users,
    ) {
    }

    /**
     * @return array{id: int, filename: string}
     */
    public function write(int $userId, string $folder, string $filename, string $markdown): array
    {
        $user = $this->users->find($userId);
        if (!$user instanceof User) {
            throw new SessionException('starter_missing', sprintf('The account %d that started the notes no longer exists.', $userId), 409);
        }

        $path = tempnam(sys_get_temp_dir(), 'synascriber-');
        if (false === $path || false === file_put_contents($path, $markdown)) {
            throw new SessionException('file_not_saved', 'The transcript could not be prepared for saving.', 500);
        }

        try {
            $file = new UploadedFile($path, $filename, 'text/markdown', null, true);
            $result = $this->uploads->uploadBatch([$file], $user, $folder, 'vectorize', new UploadOptions(source: self::SOURCE, originalName: $filename));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $saved = $result['files'][0] ?? null;
        if (!is_array($saved) || !isset($saved['id'])) {
            $reason = is_array($result['errors'][0] ?? null) ? (string) ($result['errors'][0]['error'] ?? '') : '';
            throw new SessionException('file_not_saved', 'Synaplan did not accept the transcript file: '.$reason, 500);
        }

        return ['id' => (int) $saved['id'], 'filename' => (string) ($saved['filename'] ?? $filename)];
    }
}
