<?php

/**
 * This file is part of the mimmi20/composer-plugin-symlink package.
 *
 * Copyright (c) 2023-2025, Thomas Mueller <mimmi20@live.de>
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
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function sprintf;

final class AssetsInstallerTest extends TestCase
{
    /** @throws RuntimeException */
    public function testProcessInvalidStrategy(): void
    {
        $strategy = 'abc';

        $config = $this->createMock(Config::class);
        $config->expects(self::once())
            ->method('get')
            ->with(AssetsInstaller::ASSETS_STRATEGY)
            ->willReturn($strategy);

        $composer = $this->createMock(Composer::class);
        $composer->expects(self::once())
            ->method('getConfig')
            ->willReturn($config);

        $io = $this->createMock(IOInterface::class);
        $io->expects(self::never())
            ->method('writeError');
        $io->expects(self::never())
            ->method('write');

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects(self::never())
            ->method('isAbsolutePath');

        $object = new AssetsInstaller($composer, $io, $filesystem);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('unknown Copy Strategy \'%s\'', $strategy));

        $object->process();
    }

    /** @throws RuntimeException */
    public function testProcessInvalidAssetsFiles(): void
    {
        $assetFiles = 'abc';

        $config  = $this->createMock(Config::class);
        $matcher = self::exactly(2);
        $config->expects($matcher)
            ->method('get')
            ->willReturnCallback(
                static function (string $key, int $flags = 0) use ($matcher, $assetFiles) {
                    match ($matcher->numberOfInvocations()) {
                        1 => self::assertSame(AssetsInstaller::ASSETS_STRATEGY, $key),
                        default => self::assertSame(AssetsInstaller::ASSETS_FILES, $key),
                    };

                    self::assertSame(0, $flags);

                    return match ($matcher->numberOfInvocations()) {
                        1 => AssetsInstaller::STRATEGY_COPY,
                        default => $assetFiles,
                    };
                },
            );

        $composer = $this->createMock(Composer::class);
        $composer->expects(self::once())
            ->method('getConfig')
            ->willReturn($config);

        $io = $this->createMock(IOInterface::class);
        $io->expects(self::once())
            ->method('writeError')
            ->with(
                sprintf('<fg=red>Config option \'%s\' is invalid.</>', AssetsInstaller::ASSETS_FILES),
            );
        $io->expects(self::never())
            ->method('write');

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects(self::never())
            ->method('isAbsolutePath');

        $object = new AssetsInstaller($composer, $io, $filesystem);
        $object->process();
    }

    /** @throws RuntimeException */
    public function testProcessNoAssets(): void
    {
        $config  = $this->createMock(Config::class);
        $matcher = self::exactly(2);
        $config->expects($matcher)
            ->method('get')
            ->willReturnCallback(
                static function (string $key, int $flags = 0) use ($matcher) {
                    match ($matcher->numberOfInvocations()) {
                        1 => self::assertSame(AssetsInstaller::ASSETS_STRATEGY, $key),
                        default => self::assertSame(AssetsInstaller::ASSETS_FILES, $key),
                    };

                    self::assertSame(0, $flags);

                    return match ($matcher->numberOfInvocations()) {
                        1 => AssetsInstaller::STRATEGY_COPY,
                        default => [],
                    };
                },
            );

        $composer = $this->createMock(Composer::class);
        $composer->expects(self::once())
            ->method('getConfig')
            ->willReturn($config);

        $io = $this->createMock(IOInterface::class);
        $io->expects(self::never())
            ->method('writeError');
        $io->expects(self::never())
            ->method('write');

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects(self::never())
            ->method('isAbsolutePath');

        $object = new AssetsInstaller($composer, $io, $filesystem);
        $object->process();
    }

    /** @throws RuntimeException */
    public function testProcessDuplicateAssetsAndSourceNotFound(): void
    {
        $file        = 'jquery.js';
        $sourceFile  = 'node_modules/jquery/dist/jquery.js';
        $subDir      = 'sub-dir';
        $targetDir   = 'target-dir';
        $fileContent = 'aler(\'abc\');';
        $structure   = [
            $file => $fileContent,
            $subDir => [$file => $fileContent],
        ];

        vfsStream::setup('root', null, $structure);

        $url       = vfsStream::url('root/' . $file);
        $sourceDir = vfsStream::url('root/' . $subDir . '/');
        $targetUri = vfsStream::url('root/' . $targetDir . '/');

        $assets = [
            $sourceFile => [
                $url,
                $url,
            ],
            $sourceDir => [
                $targetUri,
                $targetUri,
            ],
        ];

        $vendorDir = 'test-vendor';

        $config  = $this->createMock(Config::class);
        $matcher = self::exactly(3);
        $config->expects($matcher)
            ->method('get')
            ->willReturnCallback(
                static function (string $key, int $flags = 0) use ($matcher, $assets, $vendorDir) {
                    match ($matcher->numberOfInvocations()) {
                        1 => self::assertSame(AssetsInstaller::ASSETS_STRATEGY, $key),
                        3 => self::assertSame(AssetsInstaller::VENDOR_DIR_KEY, $key),
                        default => self::assertSame(AssetsInstaller::ASSETS_FILES, $key),
                    };

                    self::assertSame(0, $flags);

                    return match ($matcher->numberOfInvocations()) {
                        1 => AssetsInstaller::STRATEGY_COPY,
                        3 => $vendorDir,
                        default => $assets,
                    };
                },
            );

        $composer = $this->createMock(Composer::class);
        $composer->expects(self::once())
            ->method('getConfig')
            ->willReturn($config);

        $io      = $this->createMock(IOInterface::class);
        $matcher = self::exactly(3);
        $io->expects($matcher)
            ->method('writeError')
            ->willReturnCallback(
                static function (string | array $messages, bool $newline = true, int $verbosity = IOInterface::NORMAL) use ($matcher, $url, $sourceFile, $targetUri, $sourceDir): void {
                    match ($matcher->numberOfInvocations()) {
                        1 => self::assertSame(
                            sprintf(
                                '<fg=red>File/Directory \'%s\' is already used for file %s.</>',
                                $url,
                                $sourceFile,
                            ),
                            $messages,
                        ),
                        2 => self::assertSame(
                            sprintf(
                                '<fg=red>File/Directory \'%s\' is already used for file %s.</>',
                                $targetUri,
                                $sourceDir,
                            ),
                            $messages,
                        ),
                        default => self::assertSame(
                            sprintf('<fg=red>File \'%s\' not found.</>', $sourceFile),
                            $messages,
                        ),
                    };

                    self::assertTrue($newline);
                    self::assertSame(IOInterface::NORMAL, $verbosity);
                },
            );
        $matcher = self::exactly(2);
        $io->expects($matcher)
            ->method('write')
            ->willReturnCallback(
                static function (string | array $messages, bool $newline = true, int $verbosity = IOInterface::NORMAL) use ($matcher): void {
                    match ($matcher->numberOfInvocations()) {
                        1 => self::assertSame(
                            '<fg=red>copy files/create symlinks</>',
                            $messages,
                        ),
                        default => self::assertSame(
                            '<fg=yellow>- copy Directory</> from vfs://root/sub-dir/ <fg=yellow>to</> vfs://root/target-dir/',
                            $messages,
                        ),
                    };

                    self::assertTrue($newline);
                    self::assertSame(IOInterface::NORMAL, $verbosity);
                },
            );

        $filesystem = $this->createMock(Filesystem::class);
        $matcher    = self::exactly(2);
        $filesystem->expects($matcher)
            ->method('isAbsolutePath')
            ->willReturnCallback(
                static function (string $path) use ($matcher, $sourceDir, $targetUri): bool {
                    match ($matcher->numberOfInvocations()) {
                        1 => self::assertSame($sourceDir, $path),
                        default => self::assertSame($targetUri, $path),
                    };

                    return true;
                },
            );
        $matcher = self::exactly(2);
        $filesystem->expects($matcher)
            ->method('normalizePath')
            ->willReturnCallback(
                static function (string $path) use ($matcher, $targetUri, $sourceDir): string {
                    match ($matcher->numberOfInvocations()) {
                        1 => self::assertSame($sourceDir, $path),
                        default => self::assertSame($targetUri, $path),
                    };

                    return match ($matcher->numberOfInvocations()) {
                        1 => $sourceDir,
                        default => $targetUri,
                    };
                },
            );

        $object = new AssetsInstaller($composer, $io, $filesystem);
        $object->process();
    }

    /** @throws RuntimeException */
    public function testProcessCopy(): void
    {
        $file              = 'jquery.js';
        $sourceFile        = 'dist-jquery.js';
        $subDir            = 'sub-dir';
        $targetDir         = 'target-dir';
        $fileContent       = 'aler(\'abc\');';
        $sourceFileContent = 'aler(\'def\');';
        $structure         = [
            $file => $fileContent,
            $sourceFile => $sourceFileContent,
            $subDir => [
                $subDir => [$file => $fileContent],
            ],
            $targetDir => [
                $subDir => [$file => $fileContent],
            ],
        ];

        vfsStream::setup('root', null, $structure);

        $url       = vfsStream::url('root/' . $file);
        $sourceUrl = vfsStream::url('root/' . $sourceFile);
        $sourceDir = vfsStream::url('root/' . $subDir . '/');
        $targetUri = vfsStream::url('root/' . $targetDir . '/');

        $assets = [
            $sourceUrl => [$url],
            $sourceDir => $targetUri,
        ];

        $vendorDir = 'test-vendor';

        $config  = $this->createMock(Config::class);
        $matcher = self::exactly(3);
        $config->expects($matcher)
            ->method('get')
            ->willReturnCallback(
                static function (string $key, int $flags = 0) use ($matcher, $assets, $vendorDir) {
                    match ($matcher->numberOfInvocations()) {
                        1 => self::assertSame(AssetsInstaller::ASSETS_STRATEGY, $key),
                        3 => self::assertSame(AssetsInstaller::VENDOR_DIR_KEY, $key),
                        default => self::assertSame(AssetsInstaller::ASSETS_FILES, $key),
                    };

                    self::assertSame(0, $flags);

                    return match ($matcher->numberOfInvocations()) {
                        1 => AssetsInstaller::STRATEGY_COPY,
                        3 => $vendorDir,
                        default => $assets,
                    };
                },
            );

        $composer = $this->createMock(Composer::class);
        $composer->expects(self::once())
            ->method('getConfig')
            ->willReturn($config);

        $io = $this->createMock(IOInterface::class);
        $io->expects(self::never())
            ->method('writeError');
        $matcher = self::exactly(5);
        $io->expects($matcher)
            ->method('write')
            ->willReturnCallback(
                static function (string | array $messages, bool $newline = true, int $verbosity = IOInterface::NORMAL) use ($matcher): void {
                    match ($matcher->numberOfInvocations()) {
                        1 => self::assertSame(
                            '<fg=red>copy files/create symlinks</>',
                            $messages,
                        ),
                        2 => self::assertSame(
                            '<fg=red>- delete File</> vfs://root/jquery.js',
                            $messages,
                        ),
                        3 => self::assertSame(
                            '<fg=yellow>- copy File</> from vfs://root/dist-jquery.js <fg=yellow>to</> vfs://root/jquery.js',
                            $messages,
                        ),
                        4 => self::assertSame(
                            '<fg=red>- delete Directory</> vfs://root/target-dir/',
                            $messages,
                        ),
                        default => self::assertSame(
                            '<fg=yellow>- copy Directory</> from vfs://root/sub-dir/ <fg=yellow>to</> vfs://root/target-dir/',
                            $messages,
                        ),
                    };

                    self::assertTrue($newline);
                    self::assertSame(IOInterface::NORMAL, $verbosity);
                },
            );

        $filesystem = $this->createMock(Filesystem::class);
        $matcher    = self::exactly(4);
        $filesystem->expects($matcher)
            ->method('isAbsolutePath')
            ->willReturnCallback(
                static function (string $path) use ($matcher, $sourceUrl, $url, $sourceDir, $targetUri): bool {
                    match ($matcher->numberOfInvocations()) {
                        1 => self::assertSame($sourceUrl, $path),
                        2 => self::assertSame($url, $path),
                        3 => self::assertSame($sourceDir, $path),
                        default => self::assertSame($targetUri, $path),
                    };

                    return match ($matcher->numberOfInvocations()) {
                        1, 2 => false,
                        default => true,
                    };
                },
            );
        $matcher = self::exactly(4);
        $filesystem->expects($matcher)
            ->method('normalizePath')
            ->willReturnCallback(
                static function (string $path) use ($matcher, $vendorDir, $sourceUrl, $url, $sourceDir, $targetUri): string {
                    match ($matcher->numberOfInvocations()) {
                        1 => self::assertSame($vendorDir . '/../' . $sourceUrl, $path),
                        2 => self::assertSame($vendorDir . '/../' . $url, $path),
                        3 => self::assertSame($sourceDir, $path),
                        default => self::assertSame($targetUri, $path),
                    };

                    return match ($matcher->numberOfInvocations()) {
                        1 => $sourceUrl,
                        2 => $url,
                        3 => $sourceDir,
                        default => $targetUri,
                    };
                },
            );

        $object = new AssetsInstaller($composer, $io, $filesystem);
        $object->process();
    }

    /** @throws RuntimeException */
    public function testProcessSymlink(): void
    {
        $file              = 'jquery.js';
        $sourceFile        = 'dist-jquery.js';
        $subDir            = 'sub-dir';
        $targetDir         = 'target-dir';
        $fileContent       = 'aler(\'abc\');';
        $sourceFileContent = 'aler(\'def\');';
        $structure         = [
            $file => $fileContent,
            $sourceFile => $sourceFileContent,
            $subDir => [$file => $fileContent],
            $targetDir => [$file => $fileContent],
        ];

        vfsStream::setup('root', null, $structure);

        $url       = vfsStream::url('root/' . $file);
        $sourceUrl = vfsStream::url('root/' . $sourceFile);
        $sourceDir = vfsStream::url('root/' . $subDir . '/');
        $targetUri = vfsStream::url('root/' . $targetDir . '/');

        $assets = [
            $sourceUrl => [$url],
            $sourceDir => $targetUri,
        ];

        $vendorDir = 'test-vendor';

        $config  = $this->createMock(Config::class);
        $matcher = self::exactly(3);
        $config->expects($matcher)
            ->method('get')
            ->willReturnCallback(
                static function (string $key, int $flags = 0) use ($matcher, $assets, $vendorDir) {
                    match ($matcher->numberOfInvocations()) {
                        1 => self::assertSame(AssetsInstaller::ASSETS_STRATEGY, $key),
                        3 => self::assertSame(AssetsInstaller::VENDOR_DIR_KEY, $key),
                        default => self::assertSame(AssetsInstaller::ASSETS_FILES, $key),
                    };

                    self::assertSame(0, $flags);

                    return match ($matcher->numberOfInvocations()) {
                        1 => AssetsInstaller::STRATEGY_COPY,
                        3 => $vendorDir,
                        default => $assets,
                    };
                },
            );

        $composer = $this->createMock(Composer::class);
        $composer->expects(self::once())
            ->method('getConfig')
            ->willReturn($config);

        $io = $this->createMock(IOInterface::class);
        $io->expects(self::never())
            ->method('writeError');
        $matcher = self::exactly(5);
        $io->expects($matcher)
            ->method('write')
            ->willReturnCallback(
                static function (string | array $messages, bool $newline = true, int $verbosity = IOInterface::NORMAL) use ($matcher): void {
                    match ($matcher->numberOfInvocations()) {
                        1 => self::assertSame(
                            '<fg=red>copy files/create symlinks</>',
                            $messages,
                        ),
                        2 => self::assertSame(
                            '<fg=red>- delete File</> vfs://root/jquery.js',
                            $messages,
                        ),
                        3 => self::assertSame(
                            '<fg=yellow>- copy File</> from vfs://root/dist-jquery.js <fg=yellow>to</> vfs://root/jquery.js',
                            $messages,
                        ),
                        4 => self::assertSame(
                            '<fg=red>- delete Directory</> vfs://root/target-dir/',
                            $messages,
                        ),
                        default => self::assertSame(
                            '<fg=yellow>- copy Directory</> from vfs://root/sub-dir/ <fg=yellow>to</> vfs://root/target-dir/',
                            $messages,
                        ),
                    };

                    self::assertTrue($newline);
                    self::assertSame(IOInterface::NORMAL, $verbosity);
                },
            );

        $filesystem = $this->createMock(Filesystem::class);
        $matcher    = self::exactly(4);
        $filesystem->expects($matcher)
            ->method('isAbsolutePath')
            ->willReturnCallback(
                static function (string $path) use ($matcher, $sourceUrl, $url, $sourceDir, $targetUri): bool {
                    match ($matcher->numberOfInvocations()) {
                        1 => self::assertSame($sourceUrl, $path),
                        2 => self::assertSame($url, $path),
                        3 => self::assertSame($sourceDir, $path),
                        default => self::assertSame($targetUri, $path),
                    };

                    return match ($matcher->numberOfInvocations()) {
                        1, 2 => false,
                        default => true,
                    };
                },
            );
        $matcher = self::exactly(4);
        $filesystem->expects($matcher)
            ->method('normalizePath')
            ->willReturnCallback(
                static function (string $path) use ($matcher, $vendorDir, $sourceUrl, $url, $sourceDir, $targetUri): string {
                    match ($matcher->numberOfInvocations()) {
                        1 => self::assertSame($vendorDir . '/../' . $sourceUrl, $path),
                        2 => self::assertSame($vendorDir . '/../' . $url, $path),
                        3 => self::assertSame($sourceDir, $path),
                        default => self::assertSame($targetUri, $path),
                    };

                    return match ($matcher->numberOfInvocations()) {
                        1 => $sourceUrl,
                        2 => $url,
                        3 => $sourceDir,
                        default => $targetUri,
                    };
                },
            );

        $object = new AssetsInstaller($composer, $io, $filesystem);
        $object->process();
    }
}
