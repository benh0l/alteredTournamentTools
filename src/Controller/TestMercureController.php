<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Test controller for Mercure SSE functionality.
 *
 * This controller provides endpoints to test Mercure publish/subscribe functionality.
 * It should be removed or disabled in production.
 */
#[Route('/test/mercure')]
final class TestMercureController extends AbstractController
{
    public function __construct(
        private readonly HubInterface $hub
    ) {
    }

    /**
     * Test page that subscribes to Mercure updates.
     */
    #[Route('', name: 'test_mercure_subscribe', methods: ['GET'])]
    public function subscribe(): Response
    {
        return $this->render('test/mercure.html.twig', [
            'mercure_public_url' => $this->getParameter('mercure.default_hub'),
        ]);
    }

    /**
     * Publish a test message to Mercure.
     */
    #[Route('/publish', name: 'test_mercure_publish', methods: ['POST'])]
    public function publish(): Response
    {
        $update = new Update(
            // Topic URI (subscribers filter on this)
            topics: 'http://localhost/test/messages',
            // JSON data payload
            data: json_encode([
                'type' => 'test.message',
                'message' => 'Hello from Mercure!',
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ], JSON_THROW_ON_ERROR),
            // Private: false = anonymous subscribers can receive
            private: false,
            // ID for deduplication (optional)
            id: uniqid('test_', true),
            // Event type for EventSource (optional)
            type: 'message'
        );

        $this->hub->publish($update);

        return new Response('Message published!', Response::HTTP_OK);
    }
}
