# WP SiteAdvisor

WordPress plugin that scans installed plugins, detects Google service integrations, and generates prioritized recommendations through OpenAI. Runs on WP-Cron for automated daily checks.

## Tech Stack

| Layer | Technologies |
|-------|-------------|
| Platform | WordPress 5.0+, PHP 7.4+ |
| AI | OpenAI API (GPT-3.5 / GPT-4) |
| Frontend | Vanilla JS, WordPress admin CSS |
| Scheduling | WP-Cron |
| Storage | WordPress Options API, Transients |

## Code Tour

| Path | What to look at |
|------|----------------|
| `includes/class-plugin-scanner.php` | Iterates active plugins, checks update status, compatibility, support quality |
| `includes/class-google-detector.php` | Detects 8 Google services (Analytics, GTM, Search Console, AdSense, Fonts, Maps, reCAPTCHA, YouTube) |
| `includes/class-openai-handler.php` | Builds analysis prompts, sends to OpenAI, parses structured recommendations |
| `includes/class-system-scanner.php` | Server environment checks (PHP version, memory, extensions) |
| `includes/class-theme-scanner.php` | Active theme analysis and compatibility assessment |
| `includes/class-admin-dashboard.php` | Main admin page -- renders scan results, recommendation cards, health score |
| `includes/class-settings.php` | Settings page with API key management and scan scheduling |
| `includes/class-weekly-report.php` | Scheduled email digest of site health changes |
| `wp-site-advisory.php` | Bootstrap file -- registers hooks, loads classes, defines constants |

## Project Structure

```
wp-site-advisory/
├── includes/          # Core PHP classes (scanners, AI handler, dashboard, settings)
├── assets/css/        # Admin stylesheet
├── assets/js/         # Admin JavaScript (AJAX scan triggers, UI updates)
├── assets/images/     # Branding assets
└── wp-site-advisory.php  # Main plugin entry point
```

## Notes

This repo is sanitized for public viewing. API keys and service credentials are loaded via `get_option()` at runtime and are not stored in source. The plugin is not intended to be run from this repo; it is here to demonstrate code quality and architecture.