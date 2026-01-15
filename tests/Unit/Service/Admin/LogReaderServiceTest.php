<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Admin;

use App\Service\Admin\LogReaderService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class LogReaderServiceTest extends TestCase
{
    private string $tempLogDir;
    private LogReaderService $service;

    protected function setUp(): void
    {
        // Create a temporary directory for logs
        $this->tempLogDir = sys_get_temp_dir() . '/test_logs_' . uniqid();
        mkdir($this->tempLogDir, 0755, true);

        // Create mock kernel
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getLogDir')->willReturn($this->tempLogDir);

        $this->service = new LogReaderService($kernel);
    }

    protected function tearDown(): void
    {
        // Cleanup temp directory
        $files = glob($this->tempLogDir . '/*');
        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($this->tempLogDir);
    }

    public function testReadLogsReturnsEmptyWhenNoLogFile(): void
    {
        $result = $this->service->readLogs();

        $this->assertEmpty($result['entries']);
        $this->assertSame(0, $result['total']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(100, $result['limit']);
        $this->assertSame(0, $result['totalPages']);
    }

    public function testReadLogsReturnsEntriesFromJsonLogFile(): void
    {
        // Create a log file with JSON entries
        $logEntries = [
            json_encode([
                'datetime' => '2026-01-11T10:00:00.000000+00:00',
                'level_name' => 'INFO',
                'channel' => 'app',
                'message' => 'Test message 1',
                'context' => [],
            ]),
            json_encode([
                'datetime' => '2026-01-11T10:01:00.000000+00:00',
                'level_name' => 'ERROR',
                'channel' => 'app',
                'message' => 'Test error message',
                'context' => ['error' => 'Something went wrong'],
            ]),
        ];

        file_put_contents($this->tempLogDir . '/app.log', implode("\n", $logEntries));

        $result = $this->service->readLogs();

        $this->assertCount(2, $result['entries']);
        $this->assertSame(2, $result['total']);

        // Most recent first
        $this->assertSame('ERROR', $result['entries'][0]['level_name']);
        $this->assertSame('INFO', $result['entries'][1]['level_name']);
    }

    public function testReadLogsFiltersByLevel(): void
    {
        $logEntries = [
            json_encode(['datetime' => '2026-01-11T10:00:00+00:00', 'level_name' => 'INFO', 'message' => 'Info msg', 'context' => []]),
            json_encode(['datetime' => '2026-01-11T10:01:00+00:00', 'level_name' => 'ERROR', 'message' => 'Error msg', 'context' => []]),
            json_encode(['datetime' => '2026-01-11T10:02:00+00:00', 'level_name' => 'WARNING', 'message' => 'Warning msg', 'context' => []]),
        ];

        file_put_contents($this->tempLogDir . '/app.log', implode("\n", $logEntries));

        $result = $this->service->readLogs('ERROR');

        $this->assertCount(1, $result['entries']);
        $this->assertSame('ERROR', $result['entries'][0]['level_name']);
    }

    public function testReadLogsFiltersByKeyword(): void
    {
        $logEntries = [
            json_encode(['datetime' => '2026-01-11T10:00:00+00:00', 'level_name' => 'INFO', 'message' => 'User logged in', 'context' => []]),
            json_encode(['datetime' => '2026-01-11T10:01:00+00:00', 'level_name' => 'INFO', 'message' => 'Tournament created', 'context' => []]),
            json_encode(['datetime' => '2026-01-11T10:02:00+00:00', 'level_name' => 'INFO', 'message' => 'User logged out', 'context' => []]),
        ];

        file_put_contents($this->tempLogDir . '/app.log', implode("\n", $logEntries));

        $result = $this->service->readLogs(null, null, null, 'Tournament');

        $this->assertCount(1, $result['entries']);
        $this->assertStringContainsString('Tournament', $result['entries'][0]['message']);
    }

    public function testReadLogsPaginatesResults(): void
    {
        $logEntries = [];
        for ($i = 1; $i <= 250; $i++) {
            $logEntries[] = json_encode([
                'datetime' => sprintf('2026-01-11T10:%02d:00+00:00', $i % 60),
                'level_name' => 'INFO',
                'message' => "Log entry $i",
                'context' => [],
            ]);
        }

        file_put_contents($this->tempLogDir . '/app.log', implode("\n", $logEntries));

        // Page 1
        $result = $this->service->readLogs(null, null, null, null, 1, 100);
        $this->assertCount(100, $result['entries']);
        $this->assertSame(250, $result['total']);
        $this->assertSame(3, $result['totalPages']);

        // Page 2
        $result = $this->service->readLogs(null, null, null, null, 2, 100);
        $this->assertCount(100, $result['entries']);

        // Page 3
        $result = $this->service->readLogs(null, null, null, null, 3, 100);
        $this->assertCount(50, $result['entries']);
    }

    public function testGetAvailableLevelsReturnsAllLevels(): void
    {
        $levels = $this->service->getAvailableLevels();

        $this->assertContains('DEBUG', $levels);
        $this->assertContains('INFO', $levels);
        $this->assertContains('WARNING', $levels);
        $this->assertContains('ERROR', $levels);
        $this->assertContains('CRITICAL', $levels);
    }

    public function testGetAvailableLogFilesReturnsEmptyWhenNoFiles(): void
    {
        $files = $this->service->getAvailableLogFiles();

        $this->assertEmpty($files);
    }

    public function testGetAvailableLogFilesReturnsLogFiles(): void
    {
        // Create some log files
        file_put_contents($this->tempLogDir . '/app.log', 'test');
        file_put_contents($this->tempLogDir . '/app-2026-01-10.log', 'test');
        file_put_contents($this->tempLogDir . '/app-2026-01-11.log', 'test');

        $files = $this->service->getAvailableLogFiles();

        $this->assertCount(3, $files);
        $this->assertArrayHasKey('app.log', $files);
    }

    public function testReadLogsFiltersByDateRange(): void
    {
        $logEntries = [
            json_encode(['datetime' => '2026-01-09T10:00:00+00:00', 'level_name' => 'INFO', 'message' => 'Old entry', 'context' => []]),
            json_encode(['datetime' => '2026-01-10T10:00:00+00:00', 'level_name' => 'INFO', 'message' => 'Yesterday entry', 'context' => []]),
            json_encode(['datetime' => '2026-01-11T10:00:00+00:00', 'level_name' => 'INFO', 'message' => 'Today entry', 'context' => []]),
        ];

        file_put_contents($this->tempLogDir . '/app.log', implode("\n", $logEntries));

        $startDate = new \DateTimeImmutable('2026-01-10');
        $endDate = new \DateTimeImmutable('2026-01-10 23:59:59');

        $result = $this->service->readLogs(null, $startDate, $endDate);

        $this->assertCount(1, $result['entries']);
        $this->assertStringContainsString('Yesterday', $result['entries'][0]['message']);
    }
}
