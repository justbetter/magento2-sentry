<?php
declare(strict_types=1);

namespace JustBetter\Sentry\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;

class ReleaseIdentifier
{
    const RELEASE_FILE = 'sentry-releaseid.txt';

    /** @var Filesystem */
    private $filesystem;

    /** @var DirectoryList */
    private $directoryList;

    /** @var string */
    private $releaseId;

    public function __construct(Filesystem $filesystem, DirectoryList $directoryList)
    {
        $this->filesystem = $filesystem;
        $this->directoryList = $directoryList;
    }

    public function getReleaseId()
    {
        if (empty($this->releaseId) && $this->releaseId !== false) {
            $reader = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR);

            if (!$reader->isFile(self::RELEASE_FILE)) {
                $this->releaseId = false;
                return false;
            }

            $this->releaseId = trim($reader->readFile(self::RELEASE_FILE));
        }

        return $this->releaseId;
    }
}