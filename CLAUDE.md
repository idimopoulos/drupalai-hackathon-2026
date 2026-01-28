# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **Drupal CMS 2.0** project configured for the DrupalAI Hackathon 2026. It serves as the starting point for hackathon participants building AI-powered Drupal solutions.

The project uses the **amazee.io AI provider** (mandatory for hackathon submissions) with Mistral models for AI features.

## Development Commands

### Local Development with DDEV

```bash
# Start the environment
ddev start

# Install Drupal from existing config
ddev install

# Launch the site in browser
ddev launch

# Run Drush commands
ddev drush <command>

# Export configuration changes
ddev drush cex

# Import configuration
ddev drush cim

# Clear cache
ddev drush cr

# Get a one-time login link
ddev drush uli
```

### DevPanel Deployment (Manual Installation)

```bash
composer install
drush -y si --existing-config --db-url="${DB_DRIVER}://${DB_USER}:${DB_PASSWORD}@${DB_HOST}:${DB_PORT}/${DB_NAME}"
```

## Architecture

### Directory Structure

- `web/` - Drupal docroot
- `web/modules/contrib/` - Contributed modules (installed via Composer)
- `web/modules/custom/` - Custom modules (create here for hackathon work)
- `web/themes/custom/` - Custom themes
- `config/sync/` - Drupal configuration (YAML files)
- `recipes/` - Drupal recipes for site configuration
- `.ddev/` - DDEV local development configuration
- `.devpanel/` - DevPanel deployment configuration

### Key AI Modules

- **ai** (AI Core) - Abstraction layer for AI services
- **ai_agents** - Makes Drupal taskable by AI agents
- **ai_provider_amazeeio** - amazee.io AI provider integration (required for hackathon)
- **canvas** - Component-based page building
- **canvas_ai** - AI features for Canvas page builder

### Configuration Management

Configuration is exported to `config/sync/`. Sensitive AI provider keys are excluded via `config_ignore`:
- `ai_provider_amazeeio.settings`
- `key.key.amazeeio_ai`
- `key.key.amazeeio_ai_database`

After deploying to a new environment (including DevPanel), you must manually configure the amazee.io AI provider at `/admin/config/ai/providers/amazeeio`.

## Hackathon-Specific Notes

- All AI features must use the **amazee.io provider** with Mistral models
- Fork this repository and submit work via pull request back to the reference repository
- Custom modules should be placed in `web/modules/custom/`
- Always export configuration with `drush cex` before committing
- The amazee.io provider configuration is intentionally ignored from config export for security
