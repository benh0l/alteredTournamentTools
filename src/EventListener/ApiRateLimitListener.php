<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\ApiCredential;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[AsEventListener(event: KernelEvents::REQUEST, method: 'onKernelRequest', priority: 8)]
#[AsEventListener(event: KernelEvents::RESPONSE, method: 'onKernelResponse', priority: 0)]
class ApiRateLimitListener
{
    public function __construct(
        private readonly RateLimiterFactory $apiMinuteLimiter,
        private readonly RateLimiterFactory $apiHourLimiter,
        private readonly RateLimiterFactory $apiDayLimiter,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Only apply to API routes (excluding documentation)
        if (!str_starts_with($path, '/api/v1') || str_starts_with($path, '/api/v1/doc')) {
            return;
        }

        // Get API credential from request (set by authenticator)
        /** @var ApiCredential|null $credential */
        $credential = $request->attributes->get('api_credential');

        if ($credential === null) {
            // No credential yet (authentication hasn't run), skip rate limiting
            // The authenticator will handle authentication errors
            return;
        }

        $key = 'api_' . $credential->getId();

        // Check all rate limiters
        $limiters = [
            'minute' => $this->apiMinuteLimiter->create($key),
            'hour' => $this->apiHourLimiter->create($key),
            'day' => $this->apiDayLimiter->create($key),
        ];

        foreach ($limiters as $name => $limiter) {
            $limit = $limiter->consume(1, false);

            if (!$limit->isAccepted()) {
                $retryAfter = $limit->getRetryAfter()->getTimestamp() - time();

                $event->setResponse(new JsonResponse(
                    [
                        'error' => 'rate_limit_exceeded',
                        'message' => sprintf('Rate limit exceeded (%s). Please retry after %d seconds.', $name, $retryAfter),
                        'retry_after' => $retryAfter,
                    ],
                    Response::HTTP_TOO_MANY_REQUESTS,
                    [
                        'Retry-After' => (string) $retryAfter,
                        'X-RateLimit-Limit' => (string) $limit->getLimit(),
                        'X-RateLimit-Remaining' => '0',
                        'X-RateLimit-Reset' => (string) $limit->getRetryAfter()->getTimestamp(),
                    ]
                ));

                return;
            }
        }

        // Store rate limit info for response headers
        $minuteLimit = $limiters['minute']->consume(0);
        $request->attributes->set('rate_limit_info', [
            'limit' => $minuteLimit->getLimit(),
            'remaining' => $minuteLimit->getRemainingTokens(),
            'reset' => $minuteLimit->getRetryAfter()->getTimestamp(),
        ]);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Only apply to API routes
        if (!str_starts_with($path, '/api/v1') || str_starts_with($path, '/api/v1/doc')) {
            return;
        }

        $rateLimitInfo = $request->attributes->get('rate_limit_info');

        if ($rateLimitInfo === null) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->set('X-RateLimit-Limit', (string) $rateLimitInfo['limit']);
        $response->headers->set('X-RateLimit-Remaining', (string) $rateLimitInfo['remaining']);
        $response->headers->set('X-RateLimit-Reset', (string) $rateLimitInfo['reset']);
    }
}
