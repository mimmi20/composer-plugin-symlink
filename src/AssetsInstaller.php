<?php
/**
 * This file is part of the mimmi20/composer-plugin-symlink package.
 *
 * Copyright (c) 2023, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Mimmi20\CopyPlugin;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use Composer\Util\Platform;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Filesystem\Exception\IOException;
use UnexpectedValueException;

use function assert;
use function dirname;
use function file_exists;
use function fopen;
use function in_array;
use function is_array;
use function is_dir;
use function is_int;
use function is_link;
use function mkdir;
use function rmdir;
use function rtrim;
use function scandir;
use function sprintf;
use function stream_copy_to_stream;
use function stream_is_local;
use function unlink;

final class AssetsInstaller
{
    public const VENDOR_DIR_KEY = 'vendor-dir';

    public const ASSETS_STRATEGY = 'assets-strategy';

    public const ASSETS_FILES = 'assets-files';

    public const STRATEGY_AUTO = 'auto';

    public const STRATEGY_COPY = 'copy';

    public const STRATEGY_SYMLINK = 'symlink';

    /** @throws void */
    public function __construct(
        private Composer $composer,
        private readonly IOInterface $io,
        private readonly Filesystem $filesystem,
    ) {
        // nothing to do
    }

    /** @throws RuntimeException */
    public function process(): void
    {
        $composer = $this->composer;
        $config   = $composer->getConfig();

        $strategy    = $this->getInstallStrategy($config);
        $assetsFiles = $this->getAssetsFiles($config);

        if (empty($assetsFiles)) {
            return;
        }

        $this->io->write('<fg=red>copy files/create symlinks</>');

        $this->processFiles($config, $assetsFiles, $strategy);
    }

    /**
     * @return array<string>
     * @phpstan-return array<array-key, string>
     *
     * @throws RuntimeException
     */
    private function getAssetsFiles(Config $config): array
    {
        $assetsFiles = $config->get(self::ASSETS_FILES);

        if (!is_array($assetsFiles) && $assetsFiles !== null) {
            $this->io->writeError(
                sprintf('<fg=red>Config option \'%s\' is invalid.</>', self::ASSETS_FILES),
            );

            return [];
        }

        if (empty($assetsFiles)) {
            return [];
        }

        $results = [];

        foreach ($assetsFiles as $vendorPath => $publicPath) {
            if (is_int($vendorPath)) {
                $this->io->writeError(
                    sprintf('<fg=red>File/Directory \'%s\' is invalid.</>', $vendorPath),
                );

                continue;
            }

            foreach ((array) $publicPath as $singlePath) {
                if (is_int($singlePath)) {
                    $this->io->writeError(
                        sprintf('<fg=red>File/Directory \'%s\' is invalid.</>', $singlePath),
                    );

                    continue;
                }

                if (isset($results[$singlePath])) {
                    $this->io->writeError(
                        sprintf(
                            '<fg=red>File/Directory \'%s\' is already used for file %s.</>',
                            $singlePath,
                            $results[$singlePath],
                        ),
                    );

                    continue;
                }

                $results[$singlePath] = $vendorPath;
            }
        }

        return $results;
    }

    /** @throws RuntimeException */
    private function getInstallStrategy(Config $config): string
    {
        $strategy = $config->get(self::ASSETS_STRATEGY);

        if ($strategy === null) {
            $strategy = self::STRATEGY_AUTO;
        }

        if ($strategy === self::STRATEGY_AUTO) {
            $strategy = Platform::isWindows() ? self::STRATEGY_COPY : self::STRATEGY_SYMLINK;
        }

        if (!in_array($strategy, [self::STRATEGY_SYMLINK, self::STRATEGY_COPY], true)) {
            throw new RuntimeException(sprintf('unknown Copy Strategy \'%s\'', $strategy));
        }

        return $strategy;
    }

    /**
     * @param array<string> $files
     *
     * @throws RuntimeException
     */
    private function processFiles(Config $config, array $files, string $strategy): void
    {
        $vendorDir = $config->get(self::VENDOR_DIR_KEY);

        foreach ($files as $target => $source) {
            if (is_int($target)) {
                $this->io->writeError(sprintf('<fg=red>invalides target \'%s\'!.</>', $target));

                continue;
            }

            if (!file_exists($source)) {
                $this->io->writeError(sprintf('<fg=red>File \'%s\' not found.</>', $source));

                continue;
            }

            $sourcePath = $source;
            $targetPath = $target;

            if (!$this->filesystem->isAbsolutePath($sourcePath)) {
                $sourcePath = $vendorDir . '/../' . $sourcePath;
            }

            if (!$this->filesystem->isAbsolutePath($targetPath)) {
                $targetPath = $vendorDir . '/../' . $targetPath;
            }

            $sourcePath = $this->filesystem->normalizePath($sourcePath);
            $targetPath = $this->filesystem->normalizePath($targetPath);

            // is_link works on broken symlinks too
            if (file_exists($targetPath) || is_link($targetPath)) {
                $message = '<fg=red>- delete ';

                if (is_dir($sourcePath)) {
                    $message .= 'Directory</>';
                } else {
                    $message .= 'File</>';
                }

                $message .= ' ' . $target;

                $this->io->write($message);

                $this->deleteDir($targetPath, 1);

                if (is_dir($targetPath) && !is_link($targetPath)) {
                    rmdir($targetPath);
                } else {
                    unlink($targetPath);
                }
            }

            $message = '<fg=yellow>- ';

            if ($strategy === self::STRATEGY_COPY) {
                $message .= 'copy';
            } else {
                $message .= 'create Symlink for';
            }

            if (is_dir($sourcePath)) {
                $message .= ' Directory</>';
            } else {
                $message .= ' File</>';
            }

            if ($strategy === self::STRATEGY_COPY) {
                $message .= ' from ';

                if (is_dir($sourcePath)) {
                    $message .= rtrim($source, '/') . '/';
                } else {
                    $message .= $source;
                }

                $message .= ' <fg=yellow>to</> ' . $target;
            } else {
                $message .= ' ' . $target;

                $message .= ' <fg=yellow>to</> ';

                if (is_dir($sourcePath)) {
                    $message .= rtrim($source, '/') . '/';
                } else {
                    $message .= $source;
                }
            }

            $this->io->write($message);

            if ($strategy === self::STRATEGY_COPY) {
                $this->copy($sourcePath, $targetPath);
            } else {
                $this->createDirectory(dirname($targetPath));
                $this->filesystem->relativeSymlink($sourcePath, $targetPath);
            }
        }
    }

    /** @throws void */
    private function createDirectory(string $directory): void
    {
        if (file_exists($directory)) {
            return;
        }

        @mkdir($directory, 0775, true);
    }

    /**
     * Copies a file or directory.
     *
     * @throws IOException
     * @throws UnexpectedValueException
     */
    private function copy(string $source, string $dest): void
    {
        if (stream_is_local($source) && !file_exists($source)) {
            throw new IOException(sprintf('File or directory \'%s\' not found.', $source));
        }

        if (is_dir($source)) {
            $this->createDirectory($dest);

            /** @var FilesystemIterator<SplFileInfo> $removeIterator */
            $removeIterator = new FilesystemIterator($dest);

            foreach ($removeIterator as $item) {
                $this->filesystem->remove($item->getPathname());
            }

            /** @var RecursiveIteratorIterator<RecursiveDirectoryIterator> $iterator */
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
            );

            foreach ($iterator as $item) {
                assert($item instanceof SplFileInfo);

                if ($item->isDir()) {
                    $this->createDirectory($dest . '/' . $iterator->getSubPathname());
                } else {
                    $this->copy($item->getPathname(), $dest . '/' . $iterator->getSubPathname());
                }
            }

            return;
        }

        $this->createDirectory(dirname($dest));

        $sourceRes = fopen($source, 'r');

        if ($sourceRes === false) {
            throw new IOException(sprintf('Unable to to read file \'%s\'.', $source));
        }

        $destRes = fopen($dest, 'w');

        if ($destRes === false) {
            throw new IOException(sprintf('Unable to to write file \'%s\'.', $dest));
        }

        if (@stream_copy_to_stream($sourceRes, $destRes) === false) {
            // @ is escalated to exception
            throw new IOException(sprintf('Unable to copy file \'%s\' to \'%s\'.', $source, $dest));
        }
    }

    /** @throws void */
    private function deleteDir(string $baseDir, int $level): void
    {
        if (!is_dir($baseDir)) {
            return;
        }

        $dirList = scandir($baseDir);

        if ($dirList === false) {
            return;
        }

        foreach ($dirList as $currentDir) {
            $ignoreList = $level === 1
                ? ['.', '..', '.gitignore']
                : ['.', '..'];

            if (in_array($currentDir, $ignoreList, true)) {
                continue;
            }

            if (is_dir($baseDir . '/' . $currentDir)) {
                $this->deleteDir($baseDir . '/' . $currentDir, $level + 1);

                rmdir($baseDir . '/' . $currentDir);
            } else {
                unlink($baseDir . '/' . $currentDir);
            }
        }
    }
}
