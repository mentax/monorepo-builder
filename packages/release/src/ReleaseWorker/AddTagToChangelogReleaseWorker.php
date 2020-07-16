<?php

declare(strict_types=1);

namespace Symplify\MonorepoBuilder\Release\ReleaseWorker;

use Nette\Utils\DateTime;
use Nette\Utils\FileSystem;
use Nette\Utils\Strings;
use PharIo\Version\Version;
use Symplify\MonorepoBuilder\Release\Contract\ReleaseWorker\ReleaseWorkerInterface;
use Symplify\SmartFileSystem\SmartFileSystem;

final class AddTagToChangelogReleaseWorker implements ReleaseWorkerInterface
{
    /**
     * @var SmartFileSystem
     */
    private $smartFileSystem;

    public function __construct(SmartFileSystem $smartFileSystem)
    {
        $this->smartFileSystem = $smartFileSystem;
    }

    public function work(Version $version): void
    {
        $changelogFilePath = getcwd() . '/CHANGELOG.md';
        if (! file_exists($changelogFilePath)) {
            return;
        }

        $newHeadline = $this->createNewHeadline($version);

        $changelogFileContent = $this->smartFileSystem->readFile($changelogFilePath);
        $changelogFileContent = Strings::replace($changelogFileContent, '#\#\# Unreleased#', '## ' . $newHeadline);

        FileSystem::write($changelogFilePath, $changelogFileContent);
    }

    public function getDescription(Version $version): string
    {
        $newHeadline = $this->createNewHeadline($version);

        return sprintf('Change "Unreleased" in `CHANGELOG.md` to "%s"', $newHeadline);
    }

    private function createNewHeadline(Version $version): string
    {
        return $version->getVersionString() . ' - ' . (new DateTime())->format('Y-m-d');
    }
}
