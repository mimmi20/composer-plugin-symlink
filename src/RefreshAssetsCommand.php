<?php
/**
 * This file is part of the mimmi20/composer-plugin-symlink package.
 *
 * Copyright (c) 2023-2024, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Mimmi20\CopyPlugin;

use Composer\Command\BaseCommand;
use Composer\Util\Filesystem;
use RuntimeException;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * neuer Composer-Befehl zum neuen Kopieren der Assets
 */
final class RefreshAssetsCommand extends BaseCommand
{
    /** @throws InvalidArgumentException */
    protected function configure(): void
    {
        $this->setName('refresh-assets');
    }

    /**
     * @throws RuntimeException
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $composer = $this->requireComposer();

        $installer = new AssetsInstaller($composer, $this->getIO(), new Filesystem());
        $installer->process();

        return self::SUCCESS;
    }
}
