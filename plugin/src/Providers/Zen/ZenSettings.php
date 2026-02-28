<?php

declare(strict_types=1);

namespace CoenJacobs\OpenCodeProvider\Providers\Zen;

use CoenJacobs\OpenCodeProvider\Dependencies\CoenJacobs\WordPressAiProvider\ModelDirectory\AbstractModelMetadataDirectory;
use CoenJacobs\OpenCodeProvider\Dependencies\CoenJacobs\WordPressAiProvider\Provider\AbstractProviderSettings;

class ZenSettings extends AbstractProviderSettings
{
    /**
     * Renders the model selection field for the settings page.
     */
    public function renderModelField(): void
    {
        $models = $this->fetchModels();
        $config = $this->getConfig();
        $enabled = get_option($config->getEnabledModelsOption(), []);
        if (!is_array($enabled)) {
            $enabled = [];
        }

        $fetchError = get_transient($config->getErrorTransientKey());
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
        $this->enqueueModelSelectorAssets($pluginFile);

        $enabledModelsOption = $config->getEnabledModelsOption();

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
            echo '<input type="checkbox" name="' . esc_attr($enabledModelsOption) . '[]"'
                . ' value="' . esc_attr($model['id']) . '"' . $checked . '>';
            echo '<span class="model-selector__item-label">' . esc_html($model['id']) . '</span>';
            echo '</label>';
        }
        echo '<p class="model-selector__no-results">No models match your search.</p>';
        echo '</div>';
        echo '</div>';
    }

    protected function createModelMetadataDirectory(): AbstractModelMetadataDirectory
    {
        return new ZenModelMetadataDirectory($this->getConfig());
    }
}
