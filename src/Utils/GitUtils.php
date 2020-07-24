<?php

declare(strict_types=1);

namespace Symplify\MonorepoBuilder\Utils;

use Symplify\MonorepoBuilder\Release\Process\ProcessRunner;

final class GitUtils
{
    /**
     * @var ProcessRunner
     */
    private $processRunner;

    public function __construct(ProcessRunner $processRunner)
    {
        $this->processRunner = $processRunner;
    }

    public function clone(string $repoUrl, ?string $branch, string $path, int $limitDepth = null)
    {
        $command = ['git', 'clone', '--single-branch'];

        if ($limitDepth) {
            $command[] = '--depth';
            $command[] = $limitDepth;
        }

        if (! empty($branch)) {
            $command[] = '--branch';
            $command[] = $branch;
        }
        $command[] = $repoUrl;
        $command[] = '.';

        return $this->processRunner->run($command, false, $path);
    }

    public function pull(string $path)
    {
        $command = ['git', 'pull'];
        return $this->processRunner->run($command, false, $path);
    }

    public function push(string $path)
    {
        $command = ['git', 'push'];
        return $this->processRunner->run($command, false, $path);
    }

    public function reset(string $path)
    {
        $command = ['git', 'reset', '--hard'];
        return $this->processRunner->run($command, false, $path);
    }

    public function checkout(string $branch, string $path)
    {
        $command = ['git', 'checkout', '-B', $branch];
        return $this->processRunner->run($command, false, $path);
    }

    public function commit(string $message, string $path)
    {
        $command = ['git', 'add', '--all'];
        $this->processRunner->run($command, false, $path);

        $command = ['git', 'commit', '--all', '-m', $message];
        return $this->processRunner->run($command, false, $path);
    }

    public function status(string $path)
    {
        $command = ['git', 'status', '--porcelain'];
        return $this->processRunner->run($command, false, $path);
    }
}
