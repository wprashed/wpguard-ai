<?php
/*
Plugin Name: WPGuard AI
Description: AI-based spam registration protection for WordPress.
Version: 1.0.0
Author: Rashed Hossain
*/

if (!defined('ABSPATH')) {
    exit;
}

class WPGuardAI {

    private $log_file;
    private $token_usage_file;
    private $openai_api_key;
    private $last_request_time;
    private $token_usage_alert_threshold;

    public function __construct() {
        $this->log_file = plugin_dir_path(__FILE__) . 'wpguardai_spam_log.txt';
        $this->token_usage_file = plugin_dir_path(__FILE__) . 'wpguardai_token_usage.log';
        $this->openai_api_key = get_option('wpguardai_openai_api_key', '');
        $this->last_request_time = get_transient('wpguardai_last_openai_request');
        $this->token_usage_alert_threshold = (int) get_option('wpguardai_token_alert_threshold', 1000);

        add_action('user_register', array($this, 'check_user_registration'), 10, 1);
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        add_action('init', array($this, 'plugin_activate'));
    }

    public function plugin_activate() {
        // Create or update quarantine role and assign caps from settings
        $caps = get_option('wpguardai_quarantine_caps', []);
        $role = get_role('wpguardai_quarantined');
        if (!$role) {
            $role = add_role('wpguardai_quarantined', 'WPGuard AI Quarantined');
        }
        if ($role) {
            // Remove all known caps to reset
            $known_caps = ['read', 'edit_posts', 'publish_posts'];
            foreach ($known_caps as $cap) {
                $role->remove_cap($cap);
            }
            foreach ($caps as $cap) {
                if (in_array($cap, $known_caps, true)) {
                    $role->add_cap($cap);
                }
            }
        }
    }

    public function check_user_registration($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $username = $user->user_login;
        $email = $user->user_email;

        $score = $this->get_spam_score($username, $email);
        $threshold = (float) get_option('wpguardai_spam_threshold', 0.9);
        $quarantine = (bool) get_option('wpguardai_quarantine_mode', false);

        if ($score >= $threshold) {
            $this->log_spam_attempt($username, $email, $score);
            $this->notify_admin($username, $email, $score);

            if ($quarantine) {
                // Assign quarantine role instead of deleting
                $this->quarantine_user($user_id);
            } else {
                wp_delete_user($user_id);
            }
        }
    }

    private function quarantine_user($user_id) {
        $user = new WP_User($user_id);
        $user->set_role('wpguardai_quarantined');
        update_user_meta($user_id, '_wpguardai_quarantined', 1);
    }

    private function log_spam_attempt($username, $email, $score) {
        $entry = sprintf(
            "%s - Blocked user: %s | %s | Score: %.2f\n",
            date('Y-m-d H:i:s'),
            $username,
            $email,
            $score
        );
        file_put_contents($this->log_file, $entry, FILE_APPEND);
    }

    private function notify_admin($username, $email, $score) {
        $admin_email = get_option('admin_email');
        $subject = '[WPGuard AI] Spam Registration Blocked';
        $message = sprintf(
            "A spam registration attempt was blocked.\n\nUsername: %s\nEmail: %s\nSpam Score: %.2f",
            $username,
            $email,
            $score
        );
        wp_mail($admin_email, $subject, $message);
    }

    private function get_spam_score($username, $email) {
        $score = 0.0;
        $username = strtolower($username);
        $email = strtolower($email);

        $disposable_domains = ['tempmail.com', '10minutemail.com', 'mailinator.com'];
        $domain = substr(strrchr($email, "@"), 1);
        if (in_array($domain, $disposable_domains, true)) {
            $score += 0.5;
        }

        if (preg_match('/https?:\/\/|www\.|\.com|\.net|\.org|\.cl|\.xyz/', $username)) {
            $score += 0.5;
        }

        $bad_keywords = ['btc', 'binance', 'crypto', 'forex', 'nft', 'paypal', 'carding'];
        foreach ($bad_keywords as $keyword) {
            if (strpos($username, $keyword) !== false) {
                $score += 0.4;
                break;
            }
        }

        if (preg_match('/[^a-z0-9]/i', $username)) {
            $score += 0.2;
        }
        if (strlen($username) > 30) {
            $score += 0.2;
        }

        if (!empty($this->openai_api_key)) {
            $openai_score = $this->analyze_with_openai($username);
            if ($openai_score >= 0.6) {
                $score += 0.3;
            }
        }

        return min($score, 1.0);
    }

    private function analyze_with_openai($text) {
        $now = time();
        if ($this->last_request_time && ($now - $this->last_request_time) < 2) {
            sleep(2);
        }
        set_transient('wpguardai_last_openai_request', $now, 10);

        $body = json_encode([
            'model' => 'gpt-4o',
            'messages' => [[
                'role' => 'user',
                'content' => "Is the following username spammy? Answer only with a number from 0 (not spam) to 1 (definitely spam):\n\nUsername: $text"
            ]],
            'temperature' => 0.2
        ]);

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->openai_api_key,
            ],
            'body' => $body,
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return 0.0;
        }

        $result = json_decode(wp_remote_retrieve_body($response), true);
        $reply = trim($result['choices'][0]['message']['content'] ?? '0');
        $usage = isset($result['usage']['total_tokens']) ? intval($result['usage']['total_tokens']) : 0;

        // Log token usage
        $log = sprintf("%s | Used %d tokens for input: %s\n", date('Y-m-d H:i:s'), $usage, $text);
        file_put_contents($this->token_usage_file, $log, FILE_APPEND);

        // Email alert if usage exceeds threshold
        if ($usage > $this->token_usage_alert_threshold) {
            $this->notify_token_alert($usage, $text);
        }

        return floatval($reply);
    }

    private function notify_token_alert($usage, $input) {
        $admin_email = get_option('admin_email');
        $subject = '[WPGuard AI] High OpenAI Token Usage Alert';
        $message = sprintf(
            "A request to OpenAI used %d tokens which exceeded the alert threshold.\n\nInput text: %s",
            $usage,
            $input
        );
        wp_mail($admin_email, $subject, $message);
    }

    public function add_admin_menu() {
        add_menu_page(
            'WPGuard AI',
            'WPGuard AI',
            'manage_options',
            'wpguard-ai',
            array($this, 'settings_page'),
            'dashicons-shield-alt'
        );
        add_submenu_page(
            'wpguard-ai',
            'Spam Logs',
            'Spam Logs',
            'manage_options',
            'wpguard-ai-logs',
            array($this, 'view_logs_page')
        );
    }

    public function register_settings() {
        register_setting('wpguardai_settings_group', 'wpguardai_spam_threshold', [
            'type' => 'number',
            'sanitize_callback' => function ($val) {
                $val = floatval($val);
                if ($val < 0) {
                    $val = 0;
                } elseif ($val > 1) {
                    $val = 1;
                }
                return $val;
            },
            'default' => 0.9,
        ]);
        register_setting('wpguardai_settings_group', 'wpguardai_quarantine_mode', [
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false,
        ]);
        register_setting('wpguardai_settings_group', 'wpguardai_openai_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting('wpguardai_settings_group', 'wpguardai_token_alert_threshold', [
            'type' => 'integer',
            'sanitize_callback' => function ($val) {
                $val = intval($val);
                return ($val > 0) ? $val : 1000;
            },
            'default' => 1000,
        ]);
        register_setting('wpguardai_settings_group', 'wpguardai_quarantine_caps', [
            'type' => 'array',
            'sanitize_callback' => function ($input) {
                $allowed = ['read', 'edit_posts', 'publish_posts'];
                if (!is_array($input)) {
                    return [];
                }
                return array_values(array_intersect($allowed, $input));
            },
            'default' => [],
        ]);
    }

    public function settings_page() {
        $threshold = get_option('wpguardai_spam_threshold', 0.9);
        $quarantine_mode = get_option('wpguardai_quarantine_mode', false);
        $openai_key = get_option('wpguardai_openai_api_key', '');
        $token_alert_threshold = get_option('wpguardai_token_alert_threshold', 1000);
        $quarantine_caps = get_option('wpguardai_quarantine_caps', []);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html('WPGuard AI Settings'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('wpguardai_settings_group'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="wpguardai_spam_threshold"><?php echo esc_html('Spam Threshold (0.0 - 1.0)'); ?></label></th>
                        <td>
                            <input 
                                id="wpguardai_spam_threshold"
                                name="wpguardai_spam_threshold"
                                type="number"
                                step="0.01"
                                min="0"
                                max="1"
                                value="<?php echo esc_attr($threshold); ?>"
                                class="small-text"
                            />
                            <p class="description"><?php echo esc_html('Higher means stricter blocking (e.g., 0.9 = 90% spam confidence)'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="wpguardai_quarantine_mode"><?php echo esc_html('Quarantine Mode'); ?></label></th>
                        <td>
                            <input 
                                id="wpguardai_quarantine_mode"
                                name="wpguardai_quarantine_mode"
                                type="checkbox"
                                value="1"
                                <?php checked(true, (bool)$quarantine_mode); ?>
                            />
                            <p class="description"><?php echo esc_html('If enabled, suspected users will be flagged and assigned quarantine role instead of being deleted.'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="wpguardai_openai_api_key"><?php echo esc_html('OpenAI API Key'); ?></label></th>
                        <td>
                            <input 
                                id="wpguardai_openai_api_key"
                                name="wpguardai_openai_api_key"
                                type="text"
                                value="<?php echo esc_attr($openai_key); ?>"
                                class="regular-text"
                            />
                            <p class="description"><?php echo esc_html('Optional: Enter your OpenAI API key to enable AI-based analysis.'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="wpguardai_token_alert_threshold"><?php echo esc_html('Token Alert Threshold'); ?></label></th>
                        <td>
                            <input 
                                id="wpguardai_token_alert_threshold"
                                name="wpguardai_token_alert_threshold"
                                type="number"
                                min="100"
                                step="10"
                                value="<?php echo esc_attr($token_alert_threshold); ?>"
                                class="small-text"
                            />
                            <p class="description"><?php echo esc_html('Send email alert if OpenAI token usage per request exceeds this number.'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php echo esc_html('Quarantine Role Capabilities'); ?></th>
                        <td>
                            <?php
                            $caps_list = [
                                'read' => 'Read Dashboard',
                                'edit_posts' => 'Edit Posts',
                                'publish_posts' => 'Publish Posts',
                            ];
                            foreach ($caps_list as $cap_key => $cap_label) {
                                $checked = in_array($cap_key, $quarantine_caps, true) ? 'checked' : '';
                                ?>
                                <label>
                                    <input 
                                        type="checkbox" 
                                        name="wpguardai_quarantine_caps[]" 
                                        value="<?php echo esc_attr($cap_key); ?>" 
                                        <?php echo $checked; ?> 
                                    /> 
                                    <?php echo esc_html($cap_label); ?>
                                </label><br />
                                <?php
                            }
                            ?>
                            <p class="description"><?php echo esc_html('Select capabilities for users assigned the quarantine role.'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
        <?php
    }

    public function view_logs_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html('WPGuard AI - Spam Logs'); ?></h1>
            <pre style="background: #fff; padding: 10px; border: 1px solid #ccc; max-height: 500px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;">
            <?php
            if (file_exists($this->log_file)) {
                echo esc_html(file_get_contents($this->log_file));
            } else {
                echo esc_html('No spam logs recorded yet.');
            }
            ?>
            </pre>
        </div>
        <?php
    }

    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'wpguardai_dashboard_widget',
            'WPGuard AI - Recent Spam Logs',
            array($this, 'render_dashboard_widget')
        );
    }

    public function render_dashboard_widget() {
        ?>
        <div style="max-height: 250px; overflow-y: auto; background: #fff; padding: 10px; border: 1px solid #ccc; white-space: pre-wrap; word-wrap: break-word;">
            <?php
            if (file_exists($this->log_file)) {
                $lines = file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $recent_logs = array_slice(array_reverse($lines), 0, 10);
                foreach ($recent_logs as $line) {
                    echo esc_html($line) . "<br />\n";
                }
            } else {
                echo esc_html('No spam logs recorded yet.');
            }
            ?>
        </div>
        <?php
    }
}

new WPGuardAI();