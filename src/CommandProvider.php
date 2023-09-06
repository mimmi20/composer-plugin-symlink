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

use Composer\Plugin\Capability\CommandProvider as CommandProviderInterface;
use Symfony\Component\Console\Exception\LogicException;

final class CommandProvider implements CommandProviderInterface
{
    /**
     * @return array<RefreshAssetsCommand>
     * @phpstan-return array<int, RefreshAssetsCommand>
     *
     * @throws LogicException
     */
    public function getCommands(): array
    {
        return [
            new RefreshAssetsCommand(),
        ];
    }
}
