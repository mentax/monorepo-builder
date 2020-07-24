<?php

declare(strict_types=1);

namespace Symplify\MonorepoBuilder\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symplify\MonorepoBuilder\Split\Configuration\RepositoryGuard;
use Symplify\MonorepoBuilder\Split\Exception\InvalidGitRepositoryException;
use Symplify\MonorepoBuilder\Utils\GitUtils;
use Symplify\PackageBuilder\Console\Command\CommandNaming;
use Symplify\PackageBuilder\Console\ShellCode;
use Symplify\SmartFileSystem\Exception\DirectoryNotFoundException;
use Throwable;

final class GitCommitCommand extends Command
{
    /**
     * @var string
     */
    private const MESSAGE_ARGUMENT = 'message';

    /**
     * @var string
     */
    private const ALL_OPTION = 'all';

    private SymfonyStyle $symfonyStyle;

    private string $rootDirectory;

    private RepositoryGuard $repositoryGuard;

    private GitUtils $gitUtils;

    private array $repositoriesBranches = [];

    private array $directoriesToRepositories = [];

    public function __construct(
        SymfonyStyle $symfonyStyle,
        GitUtils $gitUtils,
        RepositoryGuard $repositoryGuard,
        string $rootDirectory,
        array $directoriesToRepositories,
        array $repositoriesBranches
    ) {
        parent::__construct();

        $this->symfonyStyle = $symfonyStyle;
        $this->gitUtils = $gitUtils;
        $this->repositoryGuard = $repositoryGuard;

        $this->rootDirectory = $rootDirectory;
        $this->directoriesToRepositories = $directoriesToRepositories;
        $this->repositoriesBranches = $repositoriesBranches;
    }

    protected function configure(): void
    {
        $this->setName(CommandNaming::classToName(self::class));
        $this->setDescription(
            'Commituje zmiany w paczkach, do aktualnego brancha lub drugiego brancha z repositories_branches'
        );
        $this->addArgument(
            self::MESSAGE_ARGUMENT,
            InputArgument::REQUIRED,
            'Commit message'
        );
        $this->addOption(
            self::ALL_OPTION,
            null,
            InputOption::VALUE_NONE,
            'Commit all repos, default = only repos without composer.lock'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $message */
        $message = $input->getArgument(self::MESSAGE_ARGUMENT);
        $all = $input->getOption(self::ALL_OPTION);

        foreach ($this->directoriesToRepositories as $dir => $repo) {
            $this->processRepoDir($dir, $repo, $message, $all);
        }

        $this->symfonyStyle->success(sprintf('All changes commited'));

        return ShellCode::SUCCESS;
    }

    private function processRepoDir(string $dir, string $repoName, string $message, bool $all): void
    {
        $path = $this->rootDirectory . DIRECTORY_SEPARATOR . $dir;
        $this->repositoryGuard->ensureIsRepositoryDirectory($path);

        if (! $all and file_exists($dir . DIRECTORY_SEPARATOR . 'composer.lock')) {
            return;
        }

        try {
            if (isset($this->repositoriesBranches[$dir][0])) {
                $branch = $this->repositoriesBranches[$dir][0];
                $this->gitUtils->checkout($branch, $path);

                $this->symfonyStyle->success(sprintf('git checkout in repo %s [%s]', $repoName, $branch));
                if (! empty($res)) {
                    $this->symfonyStyle->note(sprintf(trim($res)));
                }
            }
        } catch (DirectoryNotFoundException | InvalidGitRepositoryException $e) {
            $this->symfonyStyle->error(get_class($e) . ' ' . $e->getMessage());
        } catch (Throwable $throwable) {
            $this->symfonyStyle->error(get_class($throwable) . ' ' . $throwable->getMessage());
        }

        try {
            $res = $this->gitUtils->status($path);

            if (empty($res)) {
                $this->symfonyStyle->comment(sprintf('nothing to commit on %s', $repoName));
                return;
            }
        } catch (Throwable $throwable) {
            $this->symfonyStyle->error(get_class($throwable) . ' ' . $throwable->getMessage());
        }

        try {
            $res = $this->gitUtils->commit($message, $path);

            if ($res) {
                $this->symfonyStyle->success(sprintf('commit in repo %s', $repoName));
            } else {
                $this->symfonyStyle->error(sprintf('commit problem in repo %s', $repoName));
            }
            if (is_string($res)) {
                $this->symfonyStyle->comment(trim($res));
            }
        } catch (Throwable $throwable) {
            $this->symfonyStyle->error(get_class($throwable) . ' ' . $throwable->getMessage());
        }
        /**
         *        $res = $this->gitTools->push($path);
         *
         *        $this->symfonyStyle->success(sprintf('push in repo %s', $repoName));
         *        $this->symfonyStyle->comment(trim($res));
         */
    }
}
