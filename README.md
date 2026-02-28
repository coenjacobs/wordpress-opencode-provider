# OpenCode Provider for WordPress

A WordPress plugin that registers [OpenCode](https://opencode.ai) as an AI provider for the WordPress AI Client, starting with [OpenCode Zen](https://opencode.ai/zen) — a pay-as-you-go AI gateway offering 35+ models from multiple providers through a single API key.

## Requirements

- WordPress 7.0 or higher
- PHP 7.4 or higher
- [AI Experiments](https://wordpress.org/plugins/ai/) plugin (for running experiments through the WordPress admin)

## Installation

Clone this repository into your `wp-content/plugins/` directory:

```bash
git clone https://github.com/coenjacobs/wordpress-opencode-provider.git wp-content/plugins/opencode-provider
cd wp-content/plugins/opencode-provider/plugin
composer install
```

Activate the plugin through the WordPress admin panel or WP-CLI:

```bash
wp plugin activate opencode-provider
```

## Configuration

### API Key

The API key can be configured in three ways (in order of precedence):

1. **Environment variable**: Set `OPENCODE_ZEN_API_KEY` in your environment
2. **PHP constant**: Define `OPENCODE_ZEN_API_KEY` in `wp-config.php`
3. **Settings page**: Enter it at **Settings > OpenCode** in the WordPress admin

### Model Selection

Visit **Settings > OpenCode** to enable specific models. The settings page displays all available models grouped by API format (Anthropic, Google, OpenAI). Only enabled models are exposed to the WordPress AI Client and available for use in AI Experiments.

Use the **Refresh Model List** button to update the available models from the OpenCode Zen API.

## How It Works

The plugin registers a single provider (`opencode-zen`) with the WordPress AI Client registry on the `init` hook. Unlike providers that use a single API format, OpenCode Zen exposes models through their native API formats:

| Endpoint | Format | Model Families |
|----------|--------|----------------|
| `/chat/completions` | OpenAI Chat Completions | GPT, Qwen, MiniMax, GLM, and others |
| `/messages` | Anthropic Messages | Claude (Opus, Sonnet, Haiku) |
| `/models/{id}:generateContent` | Google Gemini | Gemini (Pro, Flash) |

The plugin detects the correct format for each model (via the API response or model ID prefix matching) and routes to the appropriate model class at runtime.

## Development Environment

The project includes a Docker-based development environment. No PHP, Composer, or other tools are needed on the host machine.

### Quick Start

```bash
make build    # Build the Docker image
make setup    # Full setup: download WordPress, configure, install, activate plugin
```

This gives you a working WordPress 7.0-beta2 installation at **http://localhost:8080** (admin/admin) with the plugin activated.

### Makefile Targets

| Target | Purpose |
|--------|---------|
| `make build` | Build the Docker image |
| `make setup` | Full clean setup: download WordPress, configure, install, activate plugin |
| `make up` / `make down` | Start/stop containers |
| `make clean-wp` | Stop containers and wipe the WordPress directory |
| `make composer` | Run `composer install` for the plugin |
| `make activate` | Activate the plugin via WP-CLI |

### Docker Stack

- **PHP**: 8.5 CLI Alpine with built-in web server
- **Database**: MariaDB 11
- **WordPress**: 7.0-beta2 (downloaded via `curl` + `tar`)

### Volume Mounts

- `./wordpress/` → `/var/www/html` — WordPress root (gitignored)
- `./plugin/` → `/var/www/html/wp-content/plugins/opencode-provider` — plugin source
- `./docker/mariadb/data/` → `/var/lib/mysql` — database storage (gitignored)
