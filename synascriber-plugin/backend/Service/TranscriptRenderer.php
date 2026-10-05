<?php

declare(strict_types=1);

namespace Plugin\SynaScriber\Service;

/**
 * One Markdown transcript per session, in the meeting language: header with
 * who started it, when, who spoke; then one paragraph per speaker turn.
 */
final class TranscriptRenderer
{
    /** Consecutive windows of the same speaker closer than this form one turn. */
    private const MERGE_GAP_MS = 30_000;

    private const COPY = [
        'en' => ['title' => 'Meeting notes', 'started_by' => 'Started by', 'time' => 'Time', 'language' => 'Language', 'speakers' => 'Speakers', 'participant' => 'Participant', 'audio' => 'Audio was not kept. This text was produced by speech recognition and can contain mistakes.', 'name' => 'English'],
        'de' => ['title' => 'Mitschrift', 'started_by' => 'Gestartet von', 'time' => 'Zeit', 'language' => 'Sprache', 'speakers' => 'Sprechende', 'participant' => 'Teilnehmende Person', 'audio' => 'Audio wurde nicht gespeichert. Dieser Text stammt aus der Spracherkennung und kann Fehler enthalten.', 'name' => 'Deutsch'],
        'es' => ['title' => 'Notas de la reunión', 'started_by' => 'Iniciado por', 'time' => 'Hora', 'language' => 'Idioma', 'speakers' => 'Participantes que hablaron', 'participant' => 'Participante', 'audio' => 'El audio no se guardó. Este texto procede del reconocimiento de voz y puede contener errores.', 'name' => 'Español'],
        'fr' => ['title' => 'Notes de réunion', 'started_by' => 'Lancé par', 'time' => 'Heure', 'language' => 'Langue', 'speakers' => 'Intervenants', 'participant' => 'Participant', 'audio' => "L'audio n'a pas été conservé. Ce texte provient de la reconnaissance vocale et peut contenir des erreurs.", 'name' => 'Français'],
        'tr' => ['title' => 'Toplantı notları', 'started_by' => 'Başlatan', 'time' => 'Saat', 'language' => 'Dil', 'speakers' => 'Konuşanlar', 'participant' => 'Katılımcı', 'audio' => 'Ses kaydedilmedi. Bu metin konuşma tanımadan gelir ve hata içerebilir.', 'name' => 'Türkçe'],
    ];

    /**
     * @param array<string, mixed>                                  $session
     * @param list<array{at: int, speaker: string, text: string}> $segments
     */
    public function render(array $session, array $segments, \DateTimeZone $zone): string
    {
        $copy = self::COPY[$session['language'] ?? 'en'] ?? self::COPY['en'];
        $roster = is_array($session['roster'] ?? null) ? $session['roster'] : [];
        $labels = $this->labels($segments, $roster, $copy['participant']);

        $start = $this->clock((int) $session['startedAt'], $zone, 'Y-m-d H:i');
        $end = $this->clock((int) ($session['stoppedAt'] ?? $session['finishedAt'] ?? time()), $zone, 'H:i');

        $lines = [
            sprintf('# %s — %s', $copy['title'], (string) $session['room']),
            '',
            sprintf('- %s: %s', $copy['started_by'], (string) ($session['starterName'] ?? '')),
            sprintf('- %s: %s – %s (%s)', $copy['time'], $start, $end, $zone->getName()),
            sprintf('- %s: %s', $copy['language'], self::COPY[$session['language'] ?? '']['name'] ?? (string) ($session['language'] ?? '')),
            sprintf('- %s: %s', $copy['speakers'], implode(', ', array_unique(array_values($labels)))),
            '',
            sprintf('_%s_', $copy['audio']),
            '',
        ];

        foreach ($this->turns($segments) as $turn) {
            $lines[] = sprintf('**%s · %s:** %s', $this->clock(intdiv($turn['at'], 1000), $zone, 'H:i'), $labels[$turn['speaker']] ?? $copy['participant'], $turn['text']);
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $session
     */
    public function filename(array $session, \DateTimeZone $zone): string
    {
        $copy = self::COPY[$session['language'] ?? 'en'] ?? self::COPY['en'];
        $room = (string) preg_replace('/[^\p{L}\p{N}_-]+/u', '-', (string) $session['room']);

        return sprintf('%s %s %s.md', $copy['title'], $this->clock((int) $session['startedAt'], $zone, 'Y-m-d H-i'), trim($room, '-'));
    }

    /**
     * Endpoint id = the part of the bridge tag before the first "-"
     * (tags are "<endpointId>-<ssrc>").
     */
    public static function endpointOf(string $speakerTag): string
    {
        return explode('-', $speakerTag, 2)[0];
    }

    /**
     * @param list<array{at: int, speaker: string, text: string}> $segments
     * @param array<string, string>                                 $roster
     *
     * @return array<string, string> speaker tag => label
     */
    private function labels(array $segments, array $roster, string $fallback): array
    {
        $labels = [];
        $unknown = 0;
        foreach ($segments as $segment) {
            $tag = $segment['speaker'];
            if (isset($labels[$tag])) {
                continue;
            }
            $name = trim((string) ($roster[self::endpointOf($tag)] ?? ''));
            $labels[$tag] = '' !== $name ? $name : sprintf('%s %d', $fallback, ++$unknown);
        }

        return $labels;
    }

    /**
     * @param list<array{at: int, speaker: string, text: string}> $segments
     *
     * @return list<array{at: int, speaker: string, text: string}>
     */
    private function turns(array $segments): array
    {
        usort($segments, static fn (array $a, array $b): int => $a['at'] <=> $b['at']);
        $turns = [];
        $current = -1;
        $lastAt = 0;
        foreach ($segments as $segment) {
            if ($current >= 0 && $turns[$current]['speaker'] === $segment['speaker'] && $segment['at'] - $lastAt < self::MERGE_GAP_MS) {
                $turns[$current]['text'] .= ' '.$segment['text'];
            } else {
                $turns[] = $segment;
                ++$current;
            }
            $lastAt = $segment['at'];
        }

        return $turns;
    }

    private function clock(int $timestamp, \DateTimeZone $zone, string $format): string
    {
        return (new \DateTimeImmutable('@'.$timestamp))->setTimezone($zone)->format($format);
    }
}
