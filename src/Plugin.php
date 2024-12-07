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

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider as CommandProviderInterface;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;
use Override;
use RuntimeException;

final class Plugin implements Capable, EventSubscriberInterface, PluginInterface
{
    /**
     * @throws void
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    #[Override]
    public function activate(Composer $composer, IOInterface $io): void
    {
        // do nothing
    }

    /**
     * Remove any hooks from Composer
     *
     * This will be called when a plugin is deactivated before being
     * uninstalled, but also before it gets upgraded to a new version
     * so the old one can be deactivated and the new one activated.
     *
     * @throws void
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    #[Override]
    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // do nothing
    }

    /**
     * Prepare the plugin to be uninstalled
     *
     * This will be called after deactivate.
     *
     * @throws void
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    #[Override]
    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // do nothing
    }

    /**
     * @return array<string>
     * @phpstan-return array<class-string, class-string>
     *
     * @throws void
     */
    #[Override]
    public function getCapabilities(): array
    {
        return [
            CommandProviderInterface::class => CommandProvider::class,
        ];
    }

    /**
     * @return array<string>
     * @phpstan-return array<string, string>
     *
     * @throws void
     */
    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_UPDATE_CMD => 'processEvent',
            ScriptEvents::POST_INSTALL_CMD => 'processEvent',
        ];
    }

    /**
     * @throws RuntimeException
     *
     * @api
     */
    public function processEvent(Event $event): void
    {
        $installer = new AssetsInstaller($event->getComposer(), $event->getIO(), new Filesystem());
        $installer->process();
    }
}
