<?php

/*
Plugin Name: Filepass - Revisions Complete
Plugin URI: https://yoursystemspilot.com
Description: Passes a message to Make.com when revisions are complete in Filepass
Version: 1.01
Author: Kyle Whittaker
Author URI: https://yoursystemspilot.com/
License: GPLv2 or later
Text Domain: ysp
*/

defined('ABSPATH') || exit;

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('filepass-styles', plugins_url('/style.css', __FILE__));
});

add_shortcode('FILEPASS_REVISIONS', 'ysp_filepassRevisions');
function ysp_filepassRevisions($atts, $content = null) {

    // Disable during development
    // This double checks that the traffic is coming from Filepass to prevent false triggers
    // if (strpos($_SERVER['HTTP_REFERER'], 'filepass') === false) return 'Yikes! You\'re not from Filepass!';

    $data = shortcode_atts([
        'make_url' => null
    ], $atts);

    if ($data['make_url'] === null) return 'Missing value for "make_url"';
    if (!isset($_GET['email']) || !isset($_GET['song']) || !isset($_GET['project'])) return 'Missing email, song, or project data.';
    if (!is_email($_GET['email'])) return 'Whoops! It looks like the email provided is not a valid email address.';

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
