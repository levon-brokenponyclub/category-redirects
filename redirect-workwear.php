<?php
/*
Plugin Name: Redirect /Workwear to Homepage
Description: Redirects all URLs starting with /workwear/ to the homepage.
Version: 1.0.2
Author: Broken Pony Club
Author URI: https://brokenpony.club
*/

if (!defined('ABSPATH')) exit;

/**
 * Redirect /workwear URLs immediately
 */
add_action('template_redirect', function() {
    $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    if (stripos($path, 'workwear') === 0) {
        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $redirect_url = home_url('/');

        // Log redirect
        $redirects = get_option('sisi_workwear_redirects', []);
        $redirects[$current_url] = [
            'target' => $redirect_url,
            'status' => 307,
            'timestamp' => current_time('mysql')
        ];
        update_option('sisi_workwear_redirects', $redirects);

        wp_redirect($redirect_url, 307); // Temporary Redirect
        exit;
    }
});

/**
 * Add admin menu
 */
add_action('admin_menu', function() {
    add_menu_page(
        'Sisi Redirects',
        'Sisi Redirects',
        'manage_options',
        'sisi-redirects',
        'sisi_redirects_page',
        'dashicons-randomize',
        25
    );
});

/**
 * Fetch all historical /workwear URLs
 */
function sisi_scan_workwear_urls() {
    $urls = [];

    $args = [
        'post_type' => ['post', 'page', 'product'],
        'post_status' => 'publish',
        'posts_per_page' => -1
    ];
    $posts = get_posts($args);

    foreach ($posts as $post) {
        $permalink = get_permalink($post);
        if (stripos($permalink, '/workwear/') !== false) {
            $urls[] = $permalink;
        }
    }

    // Optional: fetch from sitemap if needed
    // $sitemap_urls = sisi_fetch_sitemap_urls(); // implement if required
    // $urls = array_merge($urls, $sitemap_urls);

    return array_unique($urls);
}

/**
 * Redirect all URLs instantly (mark as 307)
 */
function sisi_redirect_all_urls($urls) {
    $redirects = get_option('sisi_workwear_redirects', []);
    foreach ($urls as $url) {
        if (!isset($redirects[$url])) {
            $redirects[$url] = [
                'target' => home_url('/'),
                'status' => 307,
                'timestamp' => current_time('mysql')
            ];
        }
    }
    update_option('sisi_workwear_redirects', $redirects);
}

/**
 * Admin page content
 */
function sisi_redirects_page() {
    if (isset($_POST['sisi_redirect_all']) && check_admin_referer('sisi_redirect_all_action')) {
        $urls = sisi_scan_workwear_urls();
        sisi_redirect_all_urls($urls);

        echo '<div class="notice notice-success"><p>All /workwear URLs have been temporarily redirected and logged ✅</p></div>';
    }

    $urls = sisi_scan_workwear_urls();
    $redirects = get_option('sisi_workwear_redirects', []);
    ?>
    <div class="wrap">
        <h1>Sisi Redirects</h1>
        <h2>Workwear URLs</h2>

        <form method="post">
            <?php wp_nonce_field('sisi_redirect_all_action'); ?>
            <input type="submit" name="sisi_redirect_all" class="button button-primary" value="Redirect All">
        </form>

        <table class="widefat fixed" cellspacing="0">
            <thead>
                <tr>
                    <th>Original URL</th>
                    <th>Redirected To</th>
                    <th>Status</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
            <?php
            if (empty($urls)) {
                echo '<tr><td colspan="4">No /workwear URLs found.</td></tr>';
            } else {
                foreach ($urls as $url) {
                    $redirect_data = $redirects[$url] ?? null;
                    if ($redirect_data) {
                        $target = $redirect_data['target'];
                        $status = '✅ 307 Temporary Redirect';
                        $timestamp = $redirect_data['timestamp'];
                    } else {
                        $target = home_url('/');
                        $status = '❌ Not yet redirected';
                        $timestamp = '-';
                    }
                    echo '<tr>
                            <td>' . esc_html($url) . '</td>
                            <td>' . esc_html($target) . '</td>
                            <td>' . esc_html($status) . '</td>
                            <td>' . esc_html($timestamp) . '</td>
                          </tr>';
                }
            }
            ?>
            </tbody>
        </table>
    </div>
    <?php
}
