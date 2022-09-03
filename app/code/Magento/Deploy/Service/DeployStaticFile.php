<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Deploy\Service;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\File\WriteInterface;
use Magento\Framework\View\Asset\Minification;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\App\View\Asset\Publisher;
use Magento\Framework\View\Asset\PreProcessor\FileNameResolver;
use Magento\Framework\Filesystem\Directory\ReadInterface;

/**
 * Deploy static file service
 */
class DeployStaticFile
{
    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var \Magento\Framework\View\Asset\Repository
     */
    private $assetRepo;

    /**
     * @var Publisher
     */
    private $assetPublisher;

    /**
     * @var FileNameResolver
     */
    private $fileNameResolver;

    /**
     * @var Minification
     */
    private $minification;

    /**
     * Public static files directory read interface
     *
     * @var ReadInterface
     */
    private $tmpDir;

    /**
     * @var Filesystem\Directory\WriteInterface
     */
    private $pubStaticDir;

    /**
     * @param Filesystem $filesystem
     * @param Repository $assetRepo
     * @param Publisher $assetPublisher
     * @param FileNameResolver $fileNameResolver
     * @param Minification $minification
     */
    public function __construct(
        Filesystem $filesystem,
        Repository $assetRepo,
        Publisher $assetPublisher,
        FileNameResolver $fileNameResolver,
        Minification $minification
    ) {
        $this->filesystem = $filesystem;
        $this->assetRepo = $assetRepo;
        $this->assetPublisher = $assetPublisher;
        $this->fileNameResolver = $fileNameResolver;
        $this->pubStaticDir = $this->filesystem->getDirectoryWrite(DirectoryList::STATIC_VIEW);
        $this->minification = $minification;
        $this->tmpDir = $filesystem->getDirectoryWrite(DirectoryList::TMP_MATERIALIZATION_DIR);
    }

    /**
     * @param string $fileName
     * @param array $params ['area' =>, 'theme' =>, 'locale' =>, 'module' =>]
     * @return string
     * @throws LocalizedException
     */
    public function deployFile(string $fileName, array $params = []): string
    {
        $params['publish'] = true;
        $asset = $this->assetRepo->createAsset($this->resolveFile($fileName), $params);

        $this->assetPublisher->publish($asset);

        return $asset->getPath();
    }

    /**
     * @param string $path
     * @return void
     * @throws FileSystemException
     */
    public function deleteFile(string $path)
    {
        if ($this->pubStaticDir->isExist($path)) {
            $absolutePath = $this->pubStaticDir->getAbsolutePath($path);
            if (is_link($absolutePath)) {
                $this->pubStaticDir->getDriver()->deleteFile($absolutePath);
            } else {
                if ($this->pubStaticDir->getDriver()->isFile($absolutePath)) {
                    $this->pubStaticDir->getDriver()->deleteFile($absolutePath);
                } else {
                    $this->pubStaticDir->getDriver()->deleteDirectory($absolutePath);
                }
            }
        }
    }

    /**
     * Read resolved file from pub static directory
     *
     * @param string $fileName
     * @param string $filePath
     * @return string|false
     * @throws FileSystemException
     */
    public function readFile(string $fileName, string $filePath): bool|string
    {
        $fileName = $this->minification->addMinifiedSign($fileName);
        $relativePath = $filePath . DIRECTORY_SEPARATOR . $this->resolveFile($fileName);
        if ($this->pubStaticDir->isFile($relativePath)) {
            return $this->pubStaticDir->readFile($relativePath);
        } else {
            return false;
        }
    }

    /**
     * @param string $fileName
     * @param string $filePath
     * @return WriteInterface
     */
    public function openFile(string $fileName, string $filePath): WriteInterface
    {
        $relativePath = $filePath . DIRECTORY_SEPARATOR . $this->resolveFile($fileName);
        return $this->pubStaticDir->openFile($relativePath, 'w+');
    }

    /**
     * Write resolved file to pub static directory
     *
     * @param string $fileName
     * @param string $filePath
     * @param string $content
     * @return int The number of bytes that were written.
     * @throws FileSystemException
     */
    public function writeFile(string $fileName, string $filePath, string $content): int
    {
        $relativePath = $filePath . DIRECTORY_SEPARATOR . $this->resolveFile($fileName);
        return $this->pubStaticDir->writeFile($relativePath, $content);
    }

    /**
     * Copy resolved $fileName from $targetPath to $destinationPath
     *
     * @param string $fileName
     * @param string $sourcePath
     * @param string $targetPath
     * @return bool
     * @throws FileSystemException
     */
    public function copyFile(string $fileName, string $sourcePath, string $targetPath): bool
    {
        $fileName = $this->minification->addMinifiedSign($fileName);
        return $this->pubStaticDir->copyFile(
            $sourcePath . DIRECTORY_SEPARATOR . $this->resolveFile($fileName),
            $targetPath . DIRECTORY_SEPARATOR . $this->resolveFile($fileName)
        );
    }

    /**
     * Read file from tmp directory
     *
     * @param string $fileName
     * @param string $filePath
     * @return bool|string
     * @throws FileSystemException
     */
    public function readTmpFile(string $fileName, string $filePath): bool|string
    {
        $relativePath = $filePath . DIRECTORY_SEPARATOR . $fileName;
        return $this->tmpDir->isFile($relativePath) ? $this->tmpDir->readFile($relativePath) : false;
    }

    /**
     * Write file to tmp directory
     *
     * @param string $fileName
     * @param string $filePath
     * @param string $content
     * @return int The number of bytes that were written.
     * @throws FileSystemException
     */
    public function writeTmpFile(string $fileName, string $filePath, string $content): int
    {
        $relativePath = $filePath . DIRECTORY_SEPARATOR . $this->resolveFile($fileName);

        return $this->tmpDir->writeFile($relativePath, $content);
    }

    /**
     * Resolve filename
     *
     * @param string $fileName
     * @return string
     */
    private function resolveFile(string $fileName): string
    {
        return str_replace(
            Repository::FILE_ID_SEPARATOR,
            '/',
            $this->fileNameResolver->resolve($fileName)
        );
    }
}
