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

use Composer\Command\BaseCommand;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\LogicException;

final class CommandProviderTest extends TestCase
{
    private CommandProvider $object;

    /** @throws void */
    protected function setUp(): void
    {
        $this->object = new CommandProvider();
    }

    /**
     * @throws Exception
     * @throws LogicException
     */
    public function testGetCommands(): void
    {
        $commands = $this->object->getCommands();

        self::assertCount(1, $commands);

        foreach ($commands as $command) {
            self::assertInstanceOf(BaseCommand::class, $command);
        }
    }
}
