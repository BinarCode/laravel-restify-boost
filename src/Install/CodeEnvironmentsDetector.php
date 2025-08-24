<?php

declare(strict_types=1);

namespace BinarCode\LaravelRestifyMcp\Install;

use BinarCode\LaravelRestifyMcp\Install\CodeEnvironment\ClaudeDesktop;
use BinarCode\LaravelRestifyMcp\Install\CodeEnvironment\CodeEnvironment;
use BinarCode\LaravelRestifyMcp\Install\CodeEnvironment\Cursor;
use BinarCode\LaravelRestifyMcp\Install\CodeEnvironment\VSCode;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;

class CodeEnvironmentsDetector
{
    /** @var array<string, class-string<CodeEnvironment>> */
    private array $programs = [
        'claudedesktop' => ClaudeDesktop::class,
        'vscode' => VSCode::class,
        'cursor' => Cursor::class,
    ];

    public function __construct(
        private readonly Container $container
    ) {
    }

    /**
     * Detect installed applications on the current platform.
     *
     * @return array<string>
     */
    public function discoverSystemInstalledCodeEnvironments(): array
    {
        $platform = $this->getCurrentPlatform();

        return $this->getCodeEnvironments()
            ->filter(fn (CodeEnvironment $program) => $program->detectOnSystem($platform))
            ->map(fn (CodeEnvironment $program) => $program->name())
            ->values()
            ->toArray();
    }

    /**
     * Detect applications used in the current project.
     *
     * @return array<string>
     */
    public function discoverProjectInstalledCodeEnvironments(string $basePath): array
    {
        return $this->getCodeEnvironments()
            ->filter(fn ($program) => $program->detectInProject($basePath))
            ->map(fn ($program) => $program->name())
            ->values()
            ->toArray();
    }

    /**
     * Get all registered code environments.
     *
     * @return Collection<string, CodeEnvironment>
     */
    public function getCodeEnvironments(): Collection
    {
        return collect($this->programs)->map(fn (string $className) => $this->container->make($className));
    }

    private function getCurrentPlatform(): string
    {
        return match (PHP_OS_FAMILY) {
            'Darwin' => 'mac',
            'Linux' => 'linux',
            'Windows' => 'windows',
            default => 'unknown',
        };
    }
}