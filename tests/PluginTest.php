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

use Composer\Plugin\Capability\CommandProvider as CommandProviderInterface;
use Composer\Script\ScriptEvents;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\TestCase;

final class PluginTest extends TestCase
{
    /** @throws Exception */
    public function testGetSubscribedEvents(): void
    {
        $events = (new Plugin())->getSubscribedEvents();

        self::assertCount(2, $events);
        self::assertArrayHasKey(ScriptEvents::POST_UPDATE_CMD, $events);
        self::assertArrayHasKey(ScriptEvents::POST_INSTALL_CMD, $events);
    }

    /** @throws Exception */
    public function testGetCapabilities(): void
    {
        $capabilities = (new Plugin())->getCapabilities();

        self::assertCount(1, $capabilities);
        self::assertArrayHasKey(CommandProviderInterface::class, $capabilities);
    }
}
