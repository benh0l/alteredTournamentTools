<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscriber to set the locale from the session on each request.
 */
final class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly string $defaultLocale = 'fr',
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Must be registered before the default Locale listener
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->hasPreviousSession()) {
            return;
        }

        // Try to get locale from session
        $locale = $request->getSession()->get('_locale');

        if ($locale) {
            $request->setLocale($locale);
        } else {
            // Try to detect from browser Accept-Language header
            $preferredLanguage = $request->getPreferredLanguage(['fr', 'en']);
            if ($preferredLanguage) {
                $request->setLocale($preferredLanguage);
            }
        }
    }
}
