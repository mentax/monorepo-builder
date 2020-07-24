<?php declare(strict_types=1);

namespace Symplify\MonorepoBuilder\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symplify\MonorepoBuilder\Release\Process\ProcessRunner;
use Symplify\MonorepoBuilder\Split\Configuration\RepositoryGuard;
use Symplify\MonorepoBuilder\Split\Exception\InvalidGitRepositoryException;
use Symplify\MonorepoBuilder\Utils\GitUtils;
use Symplify\PackageBuilder\Console\Command\CommandNaming;
use Symplify\PackageBuilder\Console\ShellCode;
use Symplify\SmartFileSystem\Exception\DirectoryNotFoundException;

final class ComposerUpdateCommand extends Command
{
    private SymfonyStyle $symfonyStyle;

    private ProcessRunner $processRunner;

    private RepositoryGuard $repositoryGuard;

    private string $rootDirectory;

    private string $composerUpdateDirectory;

    private GitUtils $gitUtils;

    private array $repositoriesBranches = [];

    /**
     * @var string[]
     */
    private array $directoriesToRepositories = [];

    public function __construct(
        SymfonyStyle $symfonyStyle,
        ProcessRunner $processRunner,
        GitUtils $gitUtils,
        RepositoryGuard $repositoryGuard,
        string $rootDirectory,
        string $composerUpdateDirectory,
        array $directoriesToRepositories,
        array $repositoriesBranches
    ) {
        parent::__construct();

        $this->symfonyStyle = $symfonyStyle;
        $this->processRunner = $processRunner;
        $this->gitUtils = $gitUtils;
        $this->repositoryGuard = $repositoryGuard;

        $this->rootDirectory = $rootDirectory;
        $this->directoriesToRepositories = $directoriesToRepositories;
        $this->composerUpdateDirectory = $composerUpdateDirectory;
        $this->repositoriesBranches = $repositoriesBranches;
    }

    protected function configure(): void
    {
        $this->setName(CommandNaming::classToName(self::class));
        $this->setDescription('Aktualizuje composer.lock w paczkach, które mają composer.lock ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->directoriesToRepositories as $dir => $repo) {
            $updatePath = $this->composerUpdateDirectory . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], '_', $dir);

            try {
                $orgPath = $this->rootDirectory . DIRECTORY_SEPARATOR . $dir;
                if (! file_exists($orgPath . DIRECTORY_SEPARATOR . 'composer.lock')) {
                    continue;
                }

                $this->repositoryGuard->ensureIsRepositoryDirectory($updatePath);
            } catch (DirectoryNotFoundException | InvalidGitRepositoryException $e) {
                if (! is_dir($updatePath)) {
                    mkdir($updatePath, 0777, true);
                }

                $this->symfonyStyle->comment(
                    sprintf('start cloning [%s] to `%s`', $dir, str_replace(['/', '\\'], '_', $dir))
                );

                $branch = $this->repositoriesBranches[$dir][1] ?? null;
                $res = $this->gitUtils->clone($repo, $branch, $updatePath, 1);

                $this->symfonyStyle->success(sprintf('cloned repo to tmp directory %s', $repo));
                if (! empty($res)) {
                    $this->symfonyStyle->comment(trim($res));
                }
            }

            $res = $this->gitUtils->reset($updatePath);
            $res = $this->gitUtils->pull($updatePath);

            if ($res) {
                $this->symfonyStyle->success(sprintf('git pull in tmp %s', $dir));
                $this->symfonyStyle->comment(trim($res));
            }

            if (filesize($updatePath . DIRECTORY_SEPARATOR . 'composer.json') !== filesize(
                $orgPath . DIRECTORY_SEPARATOR . 'composer.json'
            )) {
                $res = copy(
                    $orgPath . DIRECTORY_SEPARATOR . 'composer.json',
                    $updatePath . DIRECTORY_SEPARATOR . 'composer.json'
                );
                $this->symfonyStyle->success(sprintf('composer.json updated in tmp %s', $dir));
            }

            $command = [
                'composer',
                'up',
                '--no-interaction',
                '--no-progress',
                '--no-ansi',
                '--no-suggest',
                '--no-autoloader',
            ];
            $res = $this->processRunner->run($command, false, $updatePath);

            if ($res) {
                $this->symfonyStyle->success(sprintf('composer up package in tmp %s', $dir));
                $this->symfonyStyle->comment(trim($res));
            }

            if (filesize($updatePath . DIRECTORY_SEPARATOR . 'composer.lock') === filesize(
                $orgPath . DIRECTORY_SEPARATOR . 'composer.lock'
            )) {
                $this->symfonyStyle->comment(sprintf('composer lock not changed in %s', $dir));
                continue;
            }

            $res = copy(
                $updatePath . DIRECTORY_SEPARATOR . 'composer.lock',
                $orgPath . DIRECTORY_SEPARATOR . 'composer.lock'
            );
            if ($res) {
                $this->symfonyStyle->success(sprintf('composer lock copied to %s', $dir));
            }
        }

        $this->symfonyStyle->success('All composer.lock updated');

        return ShellCode::SUCCESS;
    }
}
