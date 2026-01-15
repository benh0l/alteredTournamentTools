<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service for managing wizard progress in session.
 */
final class WizardSessionService
{
    private const SESSION_PREFIX = 'wizard_';

    public function __construct(
        private readonly RequestStack $requestStack
    ) {
    }

    public function setStepData(string $wizardName, int $step, array $data): void
    {
        $session = $this->requestStack->getSession();
        $key = $this->getKey($wizardName, (string) $step);
        $session->set($key, $data);

        // Update current step tracker
        $currentStep = $this->getCurrentStep($wizardName);
        if ($step > $currentStep) {
            $session->set($this->getKey($wizardName, 'current'), $step);
        }
    }

    public function getStepData(string $wizardName, int $step): ?array
    {
        $session = $this->requestStack->getSession();

        return $session->get($this->getKey($wizardName, (string) $step));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllData(string $wizardName): array
    {
        $data = [];

        for ($i = 1; $i <= 10; ++$i) {
            $stepData = $this->getStepData($wizardName, $i);
            if ($stepData !== null) {
                $data[$i] = $stepData;
            }
        }

        return $data;
    }

    public function getCurrentStep(string $wizardName): int
    {
        $session = $this->requestStack->getSession();

        return (int) $session->get($this->getKey($wizardName, 'current'), 1);
    }

    public function clearWizard(string $wizardName): void
    {
        $session = $this->requestStack->getSession();

        for ($i = 1; $i <= 10; ++$i) {
            $session->remove($this->getKey($wizardName, (string) $i));
        }
        $session->remove($this->getKey($wizardName, 'current'));
    }

    private function getKey(string $wizardName, string $suffix): string
    {
        return self::SESSION_PREFIX . $wizardName . '_' . $suffix;
    }
}
