<?php

declare(strict_types=1);

namespace CoenJacobs\OpenCodeProvider;

use CoenJacobs\OpenCodeProvider\Admin\SettingsPage;
use CoenJacobs\OpenCodeProvider\Providers\Zen\ZenProvider;
use CoenJacobs\OpenCodeProvider\Providers\Zen\ZenSettings;
use CoenJacobs\OpenCodeProvider\Dependencies\CoenJacobs\WordPressAiProvider\Provider\AbstractProviderPlugin;
use CoenJacobs\OpenCodeProvider\Dependencies\CoenJacobs\WordPressAiProvider\Provider\ProviderConfig;

class Plugin extends AbstractProviderPlugin
{
    /** @var static|null */
    protected static $instance = null;

    private static ProviderConfig $providerConfig;

    public static function providerConfig(): ProviderConfig
    {
        if (!isset(self::$providerConfig)) {
            self::$providerConfig = new ProviderConfig([
                'providerId' => 'opencode-zen',
                'providerName' => 'OpenCode Zen',
                'envVarName' => 'OPENCODE_ZEN_API_KEY',
                'constantName' => 'OPENCODE_ZEN_API_KEY',
                'enabledModelsOption' => 'opencode_zen_enabled_models',
                'modelsTransientKey' => 'opencode_zen_models_raw',
                'errorTransientKey' => 'opencode_zen_models_fetch_error',
                'refreshQueryParam' => 'opencode_refresh_models',
                'refreshNonceAction' => 'opencode_refresh_models',
                'pageSlug' => 'opencode-provider',
                'optionGroup' => 'opencode-provider',
                'sectionId' => 'opencode_zen',
                'sectionTitle' => 'OpenCode Zen',
                'sectionDescriptionHtml' => '<p>Get your API key from '
                    . '<a href="https://opencode.ai/zen" target="_blank" rel="noopener noreferrer">'
                    . 'opencode.ai/zen</a>.</p>',
                'pageTitle' => 'OpenCode',
                'menuTitle' => 'OpenCode',
                'infoCardTitle' => 'About OpenCode',
                'infoCardDescription' => 'OpenCode Zen: managed AI gateway with 35+ models across multiple providers.',
                'websiteUrl' => 'https://opencode.ai',
                'websiteLinkText' => 'OpenCode Website',
            ]);
        }

        return self::$providerConfig;
    }

    protected function getConfig(): ProviderConfig
    {
        return self::providerConfig();
    }

    protected function getProviderClass(): string
    {
        return ZenProvider::class;
    }

    protected function createSettingsPage()
    {
        return new SettingsPage(self::providerConfig());
    }

    protected function createSettings()
    {
        return new ZenSettings(self::providerConfig());
    }
}
