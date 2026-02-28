<?php

declare(strict_types=1);

namespace CoenJacobs\OpenCodeProvider;

use CoenJacobs\OpenCodeProvider\Admin\SettingsPage;
use CoenJacobs\OpenCodeProvider\Http\WpHttpClient;
use CoenJacobs\OpenCodeProvider\Providers\Zen\ZenProvider;
use CoenJacobs\OpenCodeProvider\Providers\Zen\ZenSettings;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\HttpTransporter;

class Plugin
{
    private static ?Plugin $instance = null;

    public static function instance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function setup(): void
    {
        add_action('init', [$this, 'registerZenProvider'], 5);

        if (is_admin()) {
            $settings_page = new SettingsPage();
            add_action('admin_menu', [$settings_page, 'registerMenu']);

            $zen_settings = new ZenSettings();
            add_action('admin_init', [$zen_settings, 'registerSettings']);
        }
    }

    /**
     * Register the Zen provider with the WordPress AI Client registry.
     */
    public function registerZenProvider(): void
    {
        if (!class_exists(AiClient::class)) {
            return;
        }

        $registry = AiClient::defaultRegistry();

        if ($registry->hasProvider(ZenProvider::class)) {
            return;
        }

        $registry->registerProvider(ZenProvider::class);

        $api_key = ZenSettings::getActiveApiKey();
        if (!empty($api_key)) {
            $auth = new ApiKeyRequestAuthentication($api_key);
            $registry->setProviderRequestAuthentication('opencode-zen', $auth);
        }

        // Set up the HTTP transporter if not already configured.
        // This is needed for actual model execution during AI Experiments.
        // Only works when AI Experiments plugin is installed (provides unscoped PSR interfaces).
        try {
            $registry->getHttpTransporter();
        } catch (\Throwable $e) {
            if (class_exists('Nyholm\\Psr7\\Factory\\Psr17Factory')) {
                $factory     = new \Nyholm\Psr7\Factory\Psr17Factory();
                $client      = new WpHttpClient();
                $transporter = new HttpTransporter($client, $factory, $factory);
                $registry->setHttpTransporter($transporter);
            }
        }
    }
}
