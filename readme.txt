=== WPGuard AI ===
Contributors: rashedhossain
Tags: spam protection, user registration, AI, openai, quarantine, wordpress security
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered spam registration protection plugin for WordPress. Detect and block spam user registrations using heuristic rules and optional OpenAI analysis. Quarantine suspicious users by assigning a special role instead of deleting them.

== Description ==

WPGuard AI is a lightweight and powerful WordPress plugin designed to protect your site from spam user registrations. It combines heuristic checks with optional AI-powered analysis via OpenAI to accurately detect spammy usernames and emails during user registration.

Features:
* Blocks or quarantines spam registrations automatically.
* Configurable spam detection threshold.
* Optional quarantine mode that assigns suspicious users a custom role with restricted capabilities.
* Integration with OpenAI GPT API for advanced spam analysis.
* Logs all blocked attempts for audit.
* Email alerts for blocked registrations and high OpenAI token usage.
* Dashboard widget and admin page to view spam logs.
* Easy to configure OpenAI API key and settings via admin page.

== Installation ==

1. Upload the `wpguard-ai` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to WPGuard AI settings page under the admin menu to configure:
    - Spam detection threshold (0.0 to 1.0)
    - Quarantine mode toggle
    - OpenAI API key (optional)
    - Token usage alert threshold
    - Capabilities for the quarantine role
4. Save changes.

== Frequently Asked Questions ==

= Does this plugin delete spam users? =

By default, it deletes users flagged as spam during registration. You can enable quarantine mode to assign those users a quarantine role instead.

= Do I need an OpenAI API key? =

No, the plugin works with heuristic rules alone. Adding an OpenAI API key enables AI-based username analysis for improved accuracy.

= How do I view blocked spam attempts? =

Use the "Spam Logs" submenu under WPGuard AI in the admin dashboard. You can also see recent logs in the dashboard widget.

= Can I customize the quarantine role capabilities? =

Yes, you can select which capabilities the quarantine role has in the plugin settings.

== Changelog ==

= 1.0 =
* Initial release.

== Upgrade Notice ==

Version 1.4 improves security by properly sanitizing inputs and escaping outputs, and adds more control over quarantine capabilities and token alerts. It is recommended to update.

== License ==

This plugin is licensed under the GPLv2 or later license.

== Additional Notes ==

For support and feature requests, please contact Rashed Hossain at Droip.