<?php

declare(strict_types=1);

namespace Plugin\SynaScriber\Service;

use App\Service\PluginDataService;

/**
 * Sessions, room pointers and segments in plugin_data, all under the plugin
 * owner (the account whose API key the transcriber uses). The person who
 * started a session is a field of the session, not the row owner.
 *
 * plugin_data stores type and key reduced to [a-z0-9_] but looks them up as
 * given, so every type and key built here is already in that alphabet.
 */
final readonly class SessionStore
{
    private const PLUGIN = 'synascriber';
    private const TYPE_SESSION = 'session';
    private const TYPE_ROOM = 'room';
    private const SEGMENT_PREFIX = 'seg_';

    public function __construct(
        private PluginDataService $data,
        private Settings $settings,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $ref): ?array
    {
        return $this->data->get($this->owner(), self::PLUGIN, self::TYPE_SESSION, $ref);
    }

    /**
     * @param array<string, mixed> $session
     */
    public function save(array $session): void
    {
        $this->data->set($this->owner(), self::PLUGIN, self::TYPE_SESSION, (string) $session['ref'], $session);
    }

    public function activeRefForRoom(string $room): ?string
    {
        $pointer = $this->data->get($this->owner(), self::PLUGIN, self::TYPE_ROOM, self::roomKey($room));

        return is_array($pointer) && isset($pointer['ref']) ? (string) $pointer['ref'] : null;
    }

    public function markRoom(string $room, string $ref): void
    {
        $this->data->set($this->owner(), self::PLUGIN, self::TYPE_ROOM, self::roomKey($room), ['ref' => $ref, 'room' => $room]);
    }

    public function clearRoom(string $room, string $ref): void
    {
        if ($this->activeRefForRoom($room) === $ref) {
            $this->data->delete($this->owner(), self::PLUGIN, self::TYPE_ROOM, self::roomKey($room));
        }
    }

    /**
     * @param array{at: int, speaker: string, text: string} $segment
     */
    public function addSegment(string $ref, array $segment): void
    {
        $key = sprintf('%013d_%s', $segment['at'], preg_replace('/[^a-z0-9]/', '_', strtolower($segment['speaker'])));
        $this->data->set($this->owner(), self::PLUGIN, self::SEGMENT_PREFIX.$ref, $key, $segment);
    }

    /**
     * @return list<array{at: int, speaker: string, text: string}>
     */
    public function segments(string $ref): array
    {
        $rows = $this->data->listWithKeys($this->owner(), self::PLUGIN, self::SEGMENT_PREFIX.$ref);
        usort($rows, static fn (array $a, array $b): int => strcmp($a['key'], $b['key']));

        $segments = [];
        foreach ($rows as $row) {
            $data = $row['data'];
            if (isset($data['at'], $data['speaker'], $data['text'])) {
                $segments[] = ['at' => (int) $data['at'], 'speaker' => (string) $data['speaker'], 'text' => (string) $data['text']];
            }
        }

        return $segments;
    }

    public function deleteSegments(string $ref): void
    {
        $this->data->deleteAllByType($this->owner(), self::PLUGIN, self::SEGMENT_PREFIX.$ref);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function startedBy(int $userId, int $limit): array
    {
        $sessions = array_values(array_filter(
            $this->data->list($this->owner(), self::PLUGIN, self::TYPE_SESSION),
            static fn (array $s): bool => (int) ($s['starterId'] ?? 0) === $userId,
        ));
        usort($sessions, static fn (array $a, array $b): int => ($b['startedAt'] ?? 0) <=> ($a['startedAt'] ?? 0));

        return array_slice($sessions, 0, $limit);
    }

    private static function roomKey(string $room): string
    {
        return 'r_'.substr(hash('sha256', $room), 0, 40);
    }

    private function owner(): int
    {
        $owner = $this->settings->ownerUserId();
        if ($owner <= 0) {
            throw new SessionException('not_configured', 'The plugin has no owner account yet. An administrator installs it first.', 503);
        }

        return $owner;
    }
}
