<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\WizardSessionService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * @covers \App\Service\WizardSessionService
 */
final class WizardSessionServiceTest extends TestCase
{
    private WizardSessionService $service;
    private Session $session;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($this->session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $this->service = new WizardSessionService($requestStack);
    }

    public function testSetAndGetStepData(): void
    {
        $wizardName = 'test_wizard';
        $stepData = ['name' => 'Test Tournament'];

        $this->service->setStepData($wizardName, 1, $stepData);
        $result = $this->service->getStepData($wizardName, 1);

        $this->assertSame($stepData, $result);
    }

    public function testGetStepDataReturnsNullForUnsetStep(): void
    {
        $result = $this->service->getStepData('test_wizard', 5);

        $this->assertNull($result);
    }

    public function testGetCurrentStepReturnsOneByDefault(): void
    {
        $result = $this->service->getCurrentStep('test_wizard');

        $this->assertSame(1, $result);
    }

    public function testSetStepDataUpdatesCurrentStep(): void
    {
        $wizardName = 'test_wizard';

        $this->service->setStepData($wizardName, 1, ['name' => 'Test']);
        $this->assertSame(1, $this->service->getCurrentStep($wizardName));

        $this->service->setStepData($wizardName, 3, ['format' => 'constructed']);
        $this->assertSame(3, $this->service->getCurrentStep($wizardName));

        // Setting a lower step should not decrease current step
        $this->service->setStepData($wizardName, 2, ['date' => '2026-01-15']);
        $this->assertSame(3, $this->service->getCurrentStep($wizardName));
    }

    public function testGetAllDataReturnsAllSteps(): void
    {
        $wizardName = 'test_wizard';

        $this->service->setStepData($wizardName, 1, ['name' => 'Test']);
        $this->service->setStepData($wizardName, 2, ['date' => '2026-01-15']);
        $this->service->setStepData($wizardName, 3, ['format' => 'constructed']);

        $result = $this->service->getAllData($wizardName);

        $this->assertCount(3, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);
        $this->assertArrayHasKey(3, $result);
        $this->assertSame('Test', $result[1]['name']);
        $this->assertSame('2026-01-15', $result[2]['date']);
    }

    public function testClearWizardRemovesAllData(): void
    {
        $wizardName = 'test_wizard';

        $this->service->setStepData($wizardName, 1, ['name' => 'Test']);
        $this->service->setStepData($wizardName, 2, ['date' => '2026-01-15']);

        $this->service->clearWizard($wizardName);

        $this->assertNull($this->service->getStepData($wizardName, 1));
        $this->assertNull($this->service->getStepData($wizardName, 2));
        $this->assertSame(1, $this->service->getCurrentStep($wizardName));
        $this->assertEmpty($this->service->getAllData($wizardName));
    }

    public function testMultipleWizardsAreIsolated(): void
    {
        $this->service->setStepData('wizard_a', 1, ['name' => 'Wizard A']);
        $this->service->setStepData('wizard_b', 1, ['name' => 'Wizard B']);

        $this->assertSame('Wizard A', $this->service->getStepData('wizard_a', 1)['name']);
        $this->assertSame('Wizard B', $this->service->getStepData('wizard_b', 1)['name']);

        $this->service->clearWizard('wizard_a');

        $this->assertNull($this->service->getStepData('wizard_a', 1));
        $this->assertSame('Wizard B', $this->service->getStepData('wizard_b', 1)['name']);
    }

    public function testOverwriteStepData(): void
    {
        $wizardName = 'test_wizard';

        $this->service->setStepData($wizardName, 1, ['name' => 'Original']);
        $this->service->setStepData($wizardName, 1, ['name' => 'Updated']);

        $result = $this->service->getStepData($wizardName, 1);

        $this->assertSame('Updated', $result['name']);
    }
}
