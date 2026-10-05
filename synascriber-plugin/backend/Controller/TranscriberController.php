<?php

declare(strict_types=1);

namespace Plugin\SynaScriber\Controller;

use App\Entity\User;
use OpenApi\Attributes as OA;
use Plugin\SynaScriber\Service\SessionException;
use Plugin\SynaScriber\Service\SessionService;
use Plugin\SynaScriber\Service\Settings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Called by the transcriber sidecar with the plugin owner's API key. Every
 * call is checked against a live session: no session, no transcription.
 */
#[Route('/api/v1/plugins/synascriber/transcriber/sessions/{ref}', name: 'api_plugin_synascriber_transcriber_', requirements: ['ref' => '[a-f0-9]{16}'])]
#[OA\Tag(name: 'synaScriber Plugin')]
final class TranscriberController extends AbstractController
{
    public function __construct(
        private readonly SessionService $sessions,
        private readonly Settings $settings,
    ) {
    }

    #[Route('', name: 'bind', methods: ['GET'])]
    #[OA\Get(summary: 'Bind the bridge connection to an active session')]
    #[OA\Response(response: 200, description: 'Session is active: language to use')]
    #[OA\Response(response: 410, description: 'No active session: close the connection')]
    public function bind(string $ref, #[CurrentUser] ?User $user): JsonResponse
    {
        if (null !== $denied = $this->denied($user)) {
            return $denied;
        }
        $session = $this->sessions->bind($ref);
        if (null === $session) {
            return $this->json(['error' => 'not_active', 'message' => 'These meeting notes are not active.'], 410);
        }

        return $this->json(['id' => $session['ref'], 'state' => $session['state'], 'language' => $session['language']]);
    }

    #[Route('/audio', name: 'audio', methods: ['POST'])]
    #[OA\Post(summary: 'Transcribe one audio window (Ogg Opus) of one speaker')]
    #[OA\Parameter(name: 'speaker', in: 'query', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'at', in: 'query', required: false, schema: new OA\Schema(type: 'integer', description: 'Window start, ms since epoch'))]
    #[OA\Response(response: 200, description: 'Recognised text (may be empty)')]
    #[OA\Response(response: 410, description: 'Session ended: stop sending')]
    public function audio(string $ref, Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (null !== $denied = $this->denied($user)) {
            return $denied;
        }

        try {
            $text = $this->sessions->transcribeWindow(
                $ref,
                (string) $request->query->get('speaker', ''),
                (int) $request->query->get('at', '0'),
                $request->getContent(),
            );
        } catch (SessionException $e) {
            return $this->json(['error' => $e->errorCode, 'message' => $e->getMessage()], $e->httpStatus);
        }

        return $this->json(['text' => $text]);
    }

    #[Route('/finish', name: 'finish', methods: ['POST'])]
    #[OA\Post(summary: 'The bridge closed: write the transcript')]
    #[OA\Response(response: 200, description: 'Final state of the session')]
    public function finish(string $ref, #[CurrentUser] ?User $user): JsonResponse
    {
        if (null !== $denied = $this->denied($user)) {
            return $denied;
        }

        try {
            $session = $this->sessions->finish($ref, 'bridge_closed');
        } catch (SessionException $e) {
            return $this->json(['error' => $e->errorCode, 'message' => $e->getMessage()], $e->httpStatus);
        }

        return $this->json(['id' => $session['ref'], 'state' => $session['state'], 'fileId' => $session['fileId'] ?? null]);
    }

    private function denied(?User $user): ?JsonResponse
    {
        $owner = $this->settings->ownerUserId();
        if (!$user instanceof User || $owner <= 0 || (int) $user->getId() !== $owner) {
            return $this->json(['error' => 'not_allowed', 'message' => 'Only the plugin owner\'s API key may call the transcriber routes.'], 403);
        }

        return null;
    }
}
