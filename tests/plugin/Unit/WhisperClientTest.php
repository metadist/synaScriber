<?php

declare(strict_types=1);

namespace Plugin\SynaScriber\Tests;

use Plugin\SynaScriber\Service\SessionException;
use Plugin\SynaScriber\Service\WhisperClient;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class WhisperClientTest extends PluginTestCase
{
    /**
     * @param array<string, mixed> $body
     */
    private function client(array $body, int $status = 200): WhisperClient
    {
        $http = new MockHttpClient(new MockResponse((string) json_encode($body), ['http_code' => $status]));

        return new WhisperClient($http, $this->settings(), new NullLogger());
    }

    /**
     * @param list<array{0: float, 1: float, 2: string}> $segments start, end (s), text
     *
     * @return array<string, mixed>
     */
    private static function body(array $segments): array
    {
        return [
            'text' => implode('', array_column($segments, 2)),
            'segments' => array_map(static fn (array $s): array => ['start' => $s[0], 'end' => $s[1], 'text' => $s[2], 'no_speech_prob' => 0.01, 'avg_logprob' => -0.2], $segments),
        ];
    }

    public function testSpeechIsJoinedWithPhraseEnds(): void
    {
        $result = $this->client(self::body([[0.3, 1.7, ' Guten Morgen'], [1.9, 3.25, ' zusammen ,'], [3.4, 4.1, ' wir fangen an.']]))->recognize('OggS', 'de');

        self::assertSame('Guten Morgen zusammen, wir fangen an.', $result['text']);
        self::assertNull($result['dropped']);
        self::assertSame([1700, 3250, 4100], $result['segmentEndsMs']);
        self::assertSame(4100, $result['speechEndMs']);
    }

    public function testNoSegmentsIsNoSpeech(): void
    {
        $result = $this->client(['text' => '', 'segments' => []])->recognize('OggS', 'de');

        self::assertSame(['text' => '', 'dropped' => 'no_speech', 'speechEndMs' => null, 'segmentEndsMs' => []], $result);
    }

    public function testSilencePhrasesAreHallucinations(): void
    {
        foreach ([' Vielen Dank fürs Zuschauen.', ' Untertitel im Auftrag des ZDF, 2021', ' Amen.'] as $phrase) {
            $result = $this->client(self::body([[0.0, 2.0, $phrase]]))->recognize('OggS', 'de');
            self::assertSame('', $result['text'], $phrase);
            self::assertSame('hallucination', $result['dropped'], $phrase);
            self::assertSame([], $result['segmentEndsMs'], $phrase);
        }
    }

    public function testRepetitionLoopIsDropped(): void
    {
        $result = $this->client(self::body([[0.0, 6.0, str_repeat(' ja ja', 6)]]))->recognize('OggS', 'de');

        self::assertSame('hallucination', $result['dropped']);
    }

    public function testUnlikelySpeechIsSkipped(): void
    {
        $body = ['segments' => [['start' => 0.0, 'end' => 1.0, 'text' => ' hm', 'no_speech_prob' => 0.9, 'avg_logprob' => -1.4]]];

        self::assertSame('no_speech', $this->client($body)->recognize('OggS', 'de')['dropped']);
    }

    public function testRequestNamesTheModel(): void
    {
        $sent = $this->captureRequest([]);

        self::assertStringContainsString('name="model"'."\r\n\r\n".'whisper'."\r\n", $sent['body']);
        self::assertNotContains('authorization', array_map(static fn (string $h): string => strtolower(strstr($h, ':', true) ?: $h), $sent['headers']));
    }

    public function testApiKeyIsSentAsBearer(): void
    {
        $sent = $this->captureRequest(['stt_model' => 'openai/whisper-large-v3-turbo', 'stt_api_key' => 'k-123']);

        self::assertStringContainsString('name="model"'."\r\n\r\n".'openai/whisper-large-v3-turbo'."\r\n", $sent['body']);
        self::assertContains('Authorization: Bearer k-123', $sent['headers']);
    }

    /**
     * Sends one window and returns the request as the server saw it.
     *
     * @param array<string, string> $settings
     *
     * @return array{headers: list<string>, body: string}
     */
    private function captureRequest(array $settings): array
    {
        $sent = ['headers' => [], 'body' => ''];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$sent): MockResponse {
            $body = $options['body'] ?? '';
            if ($body instanceof \Closure) {
                $chunks = '';
                while ('' !== ($chunk = (string) $body(16384))) {
                    $chunks .= $chunk;
                }
                $body = $chunks;
            } elseif (is_iterable($body)) {
                $body = implode('', [...$body]);
            }
            $sent = ['headers' => array_values($options['headers'] ?? []), 'body' => (string) $body];

            return new MockResponse((string) json_encode(self::body([[0.0, 1.0, ' Hallo']])));
        });
        (new WhisperClient($http, $this->settings($settings), new NullLogger()))->recognize('OggS', 'de');

        return $sent;
    }

    public function testUnreachableServerIsReported(): void
    {
        $http = new MockHttpClient(static fn (): MockResponse => throw new TransportException('connection refused'));
        $client = new WhisperClient($http, $this->settings(), new NullLogger());

        $this->expectException(SessionException::class);
        $this->expectExceptionMessage('did not answer');
        $client->recognize('OggS', 'de');
    }

    public function testServerErrorIsReported(): void
    {
        try {
            $this->client(['error' => 'boom'], 500)->recognize('OggS', 'de');
            self::fail('A 500 must not count as silence.');
        } catch (SessionException $e) {
            self::assertSame('stt_error', $e->errorCode);
        }
    }
}
