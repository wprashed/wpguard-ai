# WPGuard AI

**AI-powered spam registration protection plugin for WordPress.**

Detect and block spam user registrations using heuristic rules combined with optional OpenAI AI analysis. Quarantine suspicious users by assigning a custom role instead of deleting them.

---

## Features

- Automatically block or quarantine spam registrations.
- Configurable spam detection threshold (0.0 to 1.0).
- Quarantine mode assigns suspicious users a role with restricted capabilities.
- OpenAI GPT API integration for advanced spam detection.
- Logs all blocked attempts for auditing.
- Email alerts on spam blocks and high OpenAI token usage.
- Dashboard widget and admin page to view spam logs.
- Easy settings page for configuration of API key, thresholds, and quarantine capabilities.

---

## Installation

1. Upload the `wpguard-ai` folder to `/wp-content/plugins/`.
2. Activate the plugin via the WordPress **Plugins** menu.
3. Go to **WPGuard AI** settings page in the admin panel.
4. Configure the spam threshold, quarantine mode, OpenAI API key (optional), token usage alert threshold, and quarantine role capabilities.
5. Save changes.

---

## Frequently Asked Questions

### Does this plugin delete spam users?

By default, spam users are deleted. If quarantine mode is enabled, suspicious users are assigned a quarantine role instead.

### Do I need an OpenAI API key?

No, the plugin works with built-in heuristics alone. Adding an OpenAI API key enables AI-powered username analysis for improved accuracy.

### How can I view blocked spam attempts?

Blocked registrations are logged and can be viewed under the **Spam Logs** submenu or in the dashboard widget.

### Can I customize the quarantine role capabilities?

Yes, you can configure the quarantine role's capabilities in the plugin settings page.

---

## Changelog

### 1.0
- Initial release.

---

## License

This plugin is licensed under the [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html).