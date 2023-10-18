<?php

/*
Plugin Name: Filepass - Revisions Complete
Plugin URI: https://yoursystemspilot.com
Description: Passes a message to Make.com when revisions are complete in Filepass
Version: 1.0.1
Author: Kyle Whittaker
Author URI: https://yoursystemspilot.com/
License: GPLv2 or later
Text Domain: ysp-filepass
*/

namespace YSP\Filepass;

defined('ABSPATH') || exit;

class Revisions {

    public $webhookURL;
    public $revisionsPage;
    public $plugin;

    public function __construct() {
        $this->webhookURL = get_option('make_webhook_url');
        $this->revisionsPage = get_option('filepass_page_id');
        $this->plugin = plugin_basename(__FILE__);
    }

    public function register() {
        add_action('wp_enqueue_scripts', [$this, 'registerStyles']);

        // Make sure webhook and page values are present.
        add_action('admin_notices', [$this, 'checkRequiredInfo']);

        // Add Settings link to plugin action link.
        add_filter("plugin_action_links_{$this->plugin}", [$this, 'addSettingsLink']);

        add_action('admin_menu', [$this, 'addAdminPage']);
        add_action('admin_init', [$this, 'registerRevisionSettings']);
        add_action('wp', [$this, 'processRevision']);
    }

    public function registerStyles() {
        wp_enqueue_style('filepass-styles', plugins_url('/assets/css/style.css', __FILE__));
    }

    public function checkRequiredInfo() {
        if (!$this->webhookURL) {
            add_settings_error('ysp_filepass', 'missing_url', 'Please add your webhook URL in the <a href="tools.php?page=filepass-revisions-options">Tools » Filepass page</a>.', 'error');
        }
        if (!$this->revisionsPage) {
            add_settings_error('ysp_filepass', 'missing_page_id', 'Please select the page you\'d like to use as the Filepass page in the <a href="tools.php?page=filepass-revisions-options">Tools » Filepass page</a>.', 'error');
        }

        $currentAdmin = get_current_screen()->parent_base;
        if ($currentAdmin && $this->strContains('options', $currentAdmin)) {
            return;
        }

        settings_errors('ysp_filepass');
    }

    public function addSettingsLink($links) {
        $settings = '<a href="tools.php?page=filepass-revisions-options">Settings</a>';
        array_push($links, $settings);
        return $links;
    }

    public function addAdminPage() {
        add_submenu_page(
            // Parent menu slug (Tools)
            'tools.php',
            // Page title
            'Filepass Revisions Options',
            // Menu title
            'Filepass Tool',
            // Capability
            'manage_options',
            // Menu slug
            'filepass-revisions-options',
            [$this, 'renderOptionsPage'] // Callback function to render the page
        );
    }

    public function renderOptionsPage() {
        $filepass_url = get_option('make_webhook_url', ''); // Retrieve the current value
        $filepass_page = get_option('filepass_page_id', ''); // Retrieve the current value
        $pageArgs = [
            'name' => 'filepass_page_id',
            'id' => 'filepass_page_id',
            'class' => 'regular-text',
            'echo' => 0,
            'selected' => $filepass_page
        ];
        $pages = wp_dropdown_pages($pageArgs);

?>
        <div class="wrap">
            <h1>Filepass Tool Options</h1>
            <form method="post" action="options.php">
                <?php settings_fields('make_webhook_url_options_group'); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th>Webhook URL</th>
                            <td>
                                <input class="regular-text" type="text" id="make_webhook_url" name="make_webhook_url" value="<?php echo esc_attr($filepass_url); ?>" />
                                <p><small>Enter the Webhook URL.</small></p>
                            </td>
                        </tr>
                        <tr>
                            <th>Revisions Page</th>
                            <td>
                                <?php echo $pages; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
<?php
    }

    public function registerRevisionSettings() {
        register_setting('make_webhook_url_options_group', 'make_webhook_url', 'esc_url_raw');
        register_setting('make_webhook_url_options_group', 'filepass_page_id', 'integer');
    }

    public function processRevision() {
        if (!$this->webhookURL || !$this->revisionsPage || is_page($this->revisionsPage) === false) return;

        if (!$this->strContains('filepass', $_SERVER['HTTP_REFERER']) || !isset($_SERVER['HTTP_REFERER'])) return;

        if (!isset($_GET['email']) || !isset($_GET['song']) || !isset($_GET['project']) || !isset($_GET['filepass'])) return;

        $email = sanitize_email($_GET['email']);
        $song = sanitize_text_field($_GET['song']);
        $project = sanitize_text_field($_GET['project']);
        $filepass = sanitize_text_field($_GET['filepass']);

        $data = array(
            'email' => $email,
            'song' => $song,
            'project' => $project,
            'filepass' => $filepass,
        );

        if (!$email || !$song || !$project || !$filepass) {
            $newError = 'Filepass » Unexpected Values: ' . print_r($data, 1);
            error_log($newError);
        }

        $this->sendRevisionRequest($this->webhookURL, $data);
    }

    public function sendRevisionRequest($url, $data) {
        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($data)
        ]);

        if (is_wp_error($response)) {
            error_log('Error: ' . $response->get_error_message());
        }
    }

    private function strContains($needle, $haystack) {
        if (function_exists('str_contains')) {
            return str_contains($haystack, $needle);
        } else {
            if (stripos($haystack, $needle) === false) {
                return false;
            } else {
                return true;
            }
        }
    }
}

$revisions = new Revisions();
$revisions->register();
