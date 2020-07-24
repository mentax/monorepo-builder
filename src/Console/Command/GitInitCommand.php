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

final class GitInitCommand extends Command
{
	private SymfonyStyle $symfonyStyle;

	private ProcessRunner $processRunner;

	private RepositoryGuard $repositoryGuard;

	private GitUtils $gitUtils;

	private string $rootDirectory;

	private array $directoriesToRepositories = [];

	private array $repositoriesBranches = [];

    public function __construct(
        SymfonyStyle $symfonyStyle,
        ProcessRunner $processRunner,
        GitUtils $gitUtils,
        RepositoryGuard $repositoryGuard,
        string $rootDirectory,
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
        $this->repositoriesBranches = $repositoriesBranches;
    }

    protected function configure(): void
    {
        $this->setName(CommandNaming::classToName(self::class));
        $this->setDescription('Importuje repo paczek do katalogu');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->directoriesToRepositories as $dir => $repo) {
            try {
                $path = $this->rootDirectory . DIRECTORY_SEPARATOR . $dir;
                $this->repositoryGuard->ensureIsRepositoryDirectory($path);

                $res = $this->gitUtils->pull($path);

                $this->symfonyStyle->success(sprintf('pulled repo %s', $repo));
                if (! empty($res)) {
                    $this->symfonyStyle->note(trim($res));
                }
            } catch (DirectoryNotFoundException | InvalidGitRepositoryException $e) {
                if ($e instanceof DirectoryNotFoundException) {
                    mkdir($path, 0777, true);
                }

                $this->symfonyStyle->comment(sprintf('start cloning [%s] to `%s`', $repo, $dir));

                $branch = $this->repositoriesBranches[$dir][0] ?? null;
                $res = $this->gitUtils->clone($repo, $branch, $path);

                $this->symfonyStyle->success(sprintf('cloned repo %s', $repo));
                if (! empty($res)) {
                    $this->symfonyStyle->note(trim($res));
                }
            }
        }

        $this->symfonyStyle->success('All packages imported');

        return ShellCode::SUCCESS;
    }
}
