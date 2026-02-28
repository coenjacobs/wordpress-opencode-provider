<?php

declare(strict_types=1);

namespace CoenJacobs\OpenCodeProvider\Providers\Zen;

use CoenJacobs\OpenCodeProvider\Admin\SettingsPage;

class ZenSettings
{
    public const PROVIDER_ID = 'opencode-zen';
    public const CREDENTIALS_OPTION = 'wp_ai_client_provider_credentials';

    /**
     * Check if the API key is configured via environment variable or PHP constant.
     */
    public static function hasEnvApiKey(): bool
    {
        $env = getenv('OPENCODE_ZEN_API_KEY');
        if (is_string($env) && $env !== '') {
            return true;
        }

        if (defined('OPENCODE_ZEN_API_KEY')) {
            $constant = constant('OPENCODE_ZEN_API_KEY');
            return is_string($constant) && $constant !== '';
        }

        return false;
    }

    /**
     * Get the active API key (ENV takes precedence over constant, constant over wp_options).
     */
    public static function getActiveApiKey(): string
    {
        $env = getenv('OPENCODE_ZEN_API_KEY');
        if (is_string($env) && $env !== '') {
            return $env;
        }

        if (defined('OPENCODE_ZEN_API_KEY')) {
            $constant = constant('OPENCODE_ZEN_API_KEY');
            if (is_string($constant) && $constant !== '') {
                return $constant;
            }
        }

        $credentials = get_option(self::CREDENTIALS_OPTION, []);
        if (is_array($credentials) && isset($credentials[self::PROVIDER_ID])) {
            $key = $credentials[self::PROVIDER_ID];
            if (is_string($key)) {
                return $key;
            }
        }

        return '';
    }

    public function registerSettings(): void
    {
        $this->handleRefreshModels();

        register_setting(SettingsPage::OPTION_GROUP, self::CREDENTIALS_OPTION, [
            'type' => 'object',
            'default' => [],
            'sanitize_callback' => [$this, 'sanitizeCredentials'],
        ]);

        register_setting(SettingsPage::OPTION_GROUP, 'opencode_zen_enabled_models', [
            'type' => 'array',
            'default' => [],
            'sanitize_callback' => [$this, 'sanitizeEnabledModels'],
        ]);

        add_settings_section(
            'opencode_zen',
            'OpenCode Zen',
            [$this, 'renderSectionDescription'],
            SettingsPage::PAGE_SLUG
        );

        add_settings_field(
            'opencode_zen_api_key',
            'API Key',
            [$this, 'renderApiKeyField'],
            SettingsPage::PAGE_SLUG,
            'opencode_zen'
        );

        add_settings_field(
            'opencode_zen_enabled_models',
            'Enabled Models',
            [$this, 'renderModelField'],
            SettingsPage::PAGE_SLUG,
            'opencode_zen'
        );
    }

    public function renderSectionDescription(): void
    {
        echo '<p>Get your API key from <a href="https://opencode.ai/zen" target="_blank"'
            . ' rel="noopener noreferrer">opencode.ai/zen</a>.</p>';
    }

    /**
     * Render the API key settings field, showing env-configured key or an input.
     */
    public function renderApiKeyField(): void
    {
        if (self::hasEnvApiKey()) {
            $key = self::getActiveApiKey();
            $masked = strlen($key) > 8
                ? substr($key, 0, 3) . str_repeat('*', strlen($key) - 7) . substr($key, -4)
                : str_repeat('*', strlen($key));

            $source = getenv('OPENCODE_ZEN_API_KEY') !== false && getenv('OPENCODE_ZEN_API_KEY') !== ''
                ? 'OPENCODE_ZEN_API_KEY environment variable'
                : 'OPENCODE_ZEN_API_KEY constant';

            echo '<p>';
            echo '<span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span> ';
            echo 'Configured via ' . esc_html($source);
            echo ' (<code>' . esc_html($masked) . '</code>)';
            echo '</p>';

            return;
        }

        $credentials = get_option(self::CREDENTIALS_OPTION, []);
        $value = $credentials[self::PROVIDER_ID] ?? '';
        echo '<input type="password" id="opencode_zen_api_key"'
            . ' name="' . esc_attr(self::CREDENTIALS_OPTION) . '[' . esc_attr(self::PROVIDER_ID) . ']"'
            . ' value="' . esc_attr($value) . '" class="regular-text" autocomplete="off" />';
    }

    /**
     * Render the model selection checkboxes as a flat alphabetical list.
     */
    public function renderModelField(): void
    {
        $models = $this->fetchModels();
        $enabled = get_option('opencode_zen_enabled_models', []);
        if (!is_array($enabled)) {
            $enabled = [];
        }

        $fetchError = get_transient('opencode_zen_models_fetch_error');
        if (is_string($fetchError) && $fetchError !== '') {
            echo '<div class="notice notice-error inline"><p>'
                . 'Failed to fetch models: ' . esc_html($fetchError)
                . '</p></div>';
        }

        if (empty($models)) {
            echo '<p class="description">No models found. Try <strong>Refresh Model List</strong> below.</p>';
            return;
        }

        $modelIds = array_column($models, 'id');
        $staleModels = array_values(array_diff($enabled, $modelIds));

        $pluginFile = dirname(__DIR__, 3) . '/opencode-provider.php';

        wp_enqueue_script(
            'opencode-model-selector',
            plugins_url('assets/model-selector.js', $pluginFile),
            [],
            '0.1.0',
            true
        );

        wp_enqueue_style(
            'opencode-model-selector',
            plugins_url('assets/model-selector.css', $pluginFile),
            [],
            '0.1.0'
        );

        echo '<div class="model-selector" data-default-collapsed="false" data-grouped="false"'
            . ' data-stale-models="' . esc_attr((string) wp_json_encode($staleModels)) . '">';
        echo '<input type="text" class="model-selector__search" placeholder="Search models..." />';
        echo '<div class="model-selector__chips"></div>';

        echo '<div class="model-selector__panel">';
        foreach ($models as $model) {
            $checked = in_array($model['id'], $enabled, true) ? ' checked' : '';
            echo '<label class="model-selector__item"'
                . ' data-model-id="' . esc_attr($model['id']) . '"'
                . ' data-model-name="' . esc_attr($model['id']) . '">';
            echo '<input type="checkbox" name="opencode_zen_enabled_models[]"'
                . ' value="' . esc_attr($model['id']) . '"' . $checked . '>';
            echo '<span class="model-selector__item-label">' . esc_html($model['id']) . '</span>';
            echo '</label>';
        }
        echo '<p class="model-selector__no-results">No models match your search.</p>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Fetch available models from the API via the model metadata directory.
     *
     * @return list<array{id: string, name?: string}>
     */
    private function fetchModels(): array
    {
        $directory = new ZenModelMetadataDirectory();
        return $directory->fetchAllModels();
    }

    /**
     * @param mixed $input
     * @return list<string>
     */
    public function sanitizeEnabledModels($input): array
    {
        if (!is_array($input)) {
            return [];
        }

        return array_values(array_map('sanitize_text_field', $input));
    }

    /**
     * Sanitize the credentials option, merging our key into the shared array.
     *
     * @param array|mixed $input
     * @return array
     */
    public function sanitizeCredentials($input): array
    {
        $existing = get_option(self::CREDENTIALS_OPTION, []);
        if (!is_array($existing)) {
            $existing = [];
        }

        if (!is_array($input)) {
            return $existing;
        }

        $new_key = isset($input[self::PROVIDER_ID])
            ? trim($input[self::PROVIDER_ID])
            : ($existing[self::PROVIDER_ID] ?? '');

        $old_key = $existing[self::PROVIDER_ID] ?? '';
        if ($new_key !== $old_key) {
            delete_transient('opencode_zen_models_raw');
        }

        $existing[self::PROVIDER_ID] = $new_key;

        return $existing;
    }

    private function handleRefreshModels(): void
    {
        if (!isset($_GET['opencode_refresh_models'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        if (!check_admin_referer('opencode_refresh_models')) {
            return;
        }

        delete_transient('opencode_zen_models_raw');

        wp_safe_redirect(admin_url('options-general.php?page=' . SettingsPage::PAGE_SLUG));
        exit;
    }
}
