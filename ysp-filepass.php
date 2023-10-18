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

$test = '';
$other = "Here is the $test";

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('filepass-styles', plugins_url('/assets/css/style.css', __FILE__));
});

add_action('admin_notices', 'ysp_enterMakeURL');
function ysp_enterMakeURL()
{
    if (get_option('make_webhook_url') === false) {
        add_settings_error('ysp_filepass', 'missing_url', 'Please add your webhook URL in the Tools » Filepass page.', 'error');
        settings_errors('ysp_filepass');
    }
}

add_action('init', __NAMESPACE__ . '\ysp_processFilepassRequest');
function ysp_processFilepassRequest()
{
    if (get_option('make_webhook_url') === false)
        return;

    $url = get_option('make_webhook_url');

    if (function_exists('str_contains')) {
        if (!str_contains($_SERVER['HTTP_REFERER'], 'filepass') || !isset($_SERVER['HTTP_REFERER']))
            return;
    } else {
        if (strpos($_SERVER['HTTP_REFERER'], 'filepass') === false)
            return;
    }

    if (!isset($_GET['email']) || !isset($_GET['song']) || !isset($_GET['project']) || !isset($_GET['filepass']))
        return;

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

    // ysp_sendFilepassRequest($url, $data);
}

function ysp_sendFilepassRequest($url, $data)
{
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

// Add a submenu page under Tools
add_action('admin_menu', 'ysp_addFileOptionsPage');
function ysp_addFileOptionsPage()
{
    add_submenu_page(
        'tools.php',
        // Parent menu slug (Tools)
        'Filepass Revisions Options',
        // Page title
        'Filepass Tool',
        // Menu title
        'manage_options',
        // Capability
        'filepass-revisions-options',
        // Menu slug
        'ysp_renderFilepassOptionsPage' // Callback function to render the page
    );
}

// Render the options page content
function ysp_renderFilepassOptionsPage()
{
    $filepass_url = get_option('make_webhook_url', ''); // Retrieve the current value
    $filepass_page = get_option('filepass_page_id', ''); // Retrieve the current value
    $pageArgs = [
        'name' => 'filepass_page_id',
        'id' => 'filepass_page_id',
        'echo' => 0,
        'selected' => $filepass_page
    ];
    $pages = wp_dropdown_pages($pageArgs);

    echo '<div class="wrap">';
    echo '<h1>Filepass Tool Options</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields('make_webhook_url_options_group');
    echo '<label for="make_webhook_url">Filepass URL:</label>';
    echo '<input type="text" id="make_webhook_url" name="make_webhook_url" value="' . esc_attr($filepass_url) . '" />';
    echo '<p><small>Enter the Webhook URL.</small></p>';
    echo '<label for="filepass_page_id">Select the page for Filepass</label>: ';
    echo $pages;
    submit_button();
    echo '</form>';
    echo '</div>';
}

// Register the settings and sanitize the input
add_action('admin_init', 'ysp_registerFilepassSettings');
function ysp_registerFilepassSettings()
{
    register_setting('make_webhook_url_options_group', 'make_webhook_url', 'esc_url_raw');
    register_setting('make_webhook_url_options_group', 'filepass_page_id', 'integer');
}


add_shortcode('FILEPASS_REVISIONS', 'ysp_filepassRevisions');
function ysp_filepassRevisions($atts, $content = null)
{

    // Disable during development
    // This double checks that the traffic is coming from Filepass to prevent false triggers
    // if (strpos($_SERVER['HTTP_REFERER'], 'filepass') === false) return 'Yikes! You\'re not from Filepass!';

    $data = shortcode_atts([
        'make_url' => null
    ], $atts);

    if ($data['make_url'] === null)
        return 'Missing value for "make_url"';
    if (!isset($_GET['email']) || !isset($_GET['song']) || !isset($_GET['project']))
        return 'Missing email, song, or project data.';
    if (!is_email($_GET['email']))
        return 'Whoops! It looks like the email provided is not a valid email address.';

    if (isset($_GET['filepass'])) {
        $filepassArray = explode('/', $_GET['filepass']);
        $filepassURL = str_replace(end($filepassArray), urlencode(end($filepassArray)), $_GET['filepass']);
    } else {
        $filepassURL = '';
    }
    $fileExtension = str_contains($_GET['song'], '.wav') ? '.wav' : '.mp3';
    $song = explode($fileExtension, $_GET['song'])[0];
    echo '<p class="ysp_filepass">' . $content . '</p>';

    $url = $data['make_url'];
    $body = [
        'email' => $_GET['email'],
        'song' => $song,
        'project' => $_GET['project'],
        'filepass_url' => $filepassURL,
        'date' => date("m/d/Y H:i:s")
    ];

    $response = wp_remote_post($url, [
        'headers' => [
            'Content-Type' => 'application/json'
        ],
        'body' => wp_json_encode($body)
    ]);

    if (is_wp_error($response)) {
        return '<h2>Error!</h2><pre>' . print_r($response, 1) . '</pre>';
    }
    return;
}