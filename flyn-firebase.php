<?php

/**
 * @package Firebase Short Links
 * @version 2.1.1
 *
 * Plugin Name: Firebase Short Links
 * Description: A simple Firebase URL shortener for WordPress.
 * Version: 2.1.1
 * Author: Flynsarmy
 * Author URI: https://www.flynsarmy.com/
 */

 /**
  * Get Shortlink.
  *
  * @param string|WP_Error $shortlink
  * @param int $id
  * @param string $context
  * @param bool $allow_slugs
  * @return string
  */
add_filter('get_shortlink', function (string $shortlink, int $id, string $context, bool $allow_slugs) {
    global $post;

    if (!defined('FLYN_FIREBASE_URL') || !defined('FLYN_FIREBASE_API_KEY')) {
        return $shortlink;
    }

    if (! $id && $post) {
        $id = $post->ID;
    } elseif ($id) {
        $post = get_post($id);
    }

    if ($post && !in_array($post->post_status, ['publish', 'private'])) {
        return "";
    }

    if ((is_singular() || $post) && ! is_front_page()) {
        $shortlink = ffirebase_cached_shortlink($id);
        if ($shortlink) {
            return $shortlink;
        }
        // Goo.gl plugin support
        $shortlink = get_post_meta($id, '_googl_shortlink', true);
        if ($shortlink) {
            update_post_meta($id, '_ffirebase_shortlink', $shortlink);
            return $shortlink;
        }

        $permalink = get_permalink($id);
        $shortlink = ffirebase_shorten($permalink);

        if (!is_wp_error($shortlink)) {
            update_post_meta($id, '_ffirebase_shortlink', $shortlink);
            return $shortlink;
        } else {
            return $shortlink;
        }
    } elseif (is_front_page()) {
        $shortlink = (string) get_option('_ffirebase_shortlink_home');
        if ($shortlink) {
            return $shortlink;
        }
        // Goo.gl plugin support
        $shortlink = (string) get_option('_googl_shortlink_home');
        if ($shortlink) {
            update_option('_ffirebase_shortlink_home', $shortlink);
            return $shortlink;
        }

        $ffirebase_shortlink = ffirebase_shorten(home_url('/'));
        if (!is_wp_error($ffirebase_shortlink)) {
            update_option('_ffirebase_shortlink_home', $ffirebase_shortlink);
            return $ffirebase_shortlink;
        } else {
            return home_url('/');
        }
    }
}, 9, 4);

/**
 * Get Cached Shortlink.
 *
 * @param int $post_id
 * @return string
 */
function ffirebase_cached_shortlink(int $post_id)
{
    return (string)get_post_meta($post_id, '_ffirebase_shortlink', true);
}

/**
 * Generates a short URL for the given URL.
 *
 * @param string $url
 * @return string|WP_Error
 */
function ffirebase_shorten(string $url)
{
    $result = wp_remote_post(
        add_query_arg(
            'key',
            apply_filters(
                'ffirebase_api_key',
                FLYN_FIREBASE_API_KEY
            ),
            'https://firebasedynamiclinks.googleapis.com/v1/shortLinks'
        ),
        [
            'body' => json_encode([
                //'dynamicLinkInfo' => [
                //    'link' => $url,
                //],
                'longDynamicLink' => FLYN_FIREBASE_URL . "/?link=" . esc_url($url),
                'suffix' => [
                    'option' => 'SHORT',
                ]
            ]),
            'headers' => [ 'Content-Type' => 'application/json' ],
        ]
    );

    // Return an error if we got one
    if (is_wp_error($result)) {
        return $result;
        // return $url;
    }

    $result = json_decode($result['body']);
    $shortlink = $result->shortLink;
    if (!$shortlink) {
        return new WP_Error('invalid_response', "Invalid response from Firebase");
    }

    return $shortlink;
}

if (defined('WP_ADMIN') && WP_ADMIN) {
    /**
     * Add Dashboard Widget.
     *
     * @return void
     */
    add_action('wp_dashboard_setup', function (): void {
        wp_add_dashboard_widget('ffirebase_shortener', 'Shorten Link', function () {
            require __DIR__ . '/views/dashboard_shortener.php';
        });
    });

    /**
     * Add Shortlink Column.
     *
     * @return array $columns
     * @return array
     */
    add_filter('manage_edit-post_columns', function (array $columns): array {
        $columns['shortlink'] = 'Shortlink';

        return $columns;
    });

    /**
     * Add Shortlink Column Content.
     *
     * @param string $column
     * @param int $post_id
     * @return void
     */
    add_action('manage_posts_custom_column', function (string $column, int $post_id): void {
        if ('shortlink' === $column) {
            // Don't try to load shortlinks for non-public posts
            $post_status = get_post_status(get_the_ID());
            if (!in_array($post_status, ['publish', 'private'])) {
                return;
            }

            $shorturl = ffirebase_cached_shortlink(get_the_ID());
            if ($shorturl) {
                $shorturl_caption = preg_replace('/https?\:\/\//', '', $shorturl);
                printf('<a href="%s">%s</a>', esc_url($shorturl), esc_html($shorturl_caption));
            } else {
                echo "<span class='ffirebase_load_shortlink' data-post_id='" . get_the_ID() . "'>Generating...</span>";
            }
        }
    }, 10, 2);

    /**
     * Enqueue Admin Scripts
     *
     * @return void
     */
    add_action('admin_print_footer_scripts-edit.php', function (): void {
        require __DIR__ . '/views/post_list_footer.php';
    });

    if (!defined('FLYN_FIREBASE_URL') || !defined('FLYN_FIREBASE_API_KEY')) {
        /**
         * Admin Notice
         *
         * @return void
         */
        add_action('admin_notices', function (): void {
            require __DIR__ . '/views/missing_constant_nag.php';
        });
    }
}

if (defined('DOING_AJAX') && DOING_AJAX) {
    /**
     * AJAX Generate Shortlink
     *
     * @return void
     */
    add_action('wp_ajax_ffirebase_generate_shortlink', function (): void {
        header('Content-Type: application/json');

        $url = @$_POST['url'];

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            exit(json_encode([
                'success' => 0,
                'message' => "Please enter a valid URL"
            ]));
        }

        $shortlink = ffirebase_shorten($url);

        if (is_wp_error($shortlink)) {
            exit(json_encode([
                'success' => 0,
                'message' => $shortlink->get_error_message()
            ]));
        }

        exit(json_encode([
            'success' => 1,
            'message' => $shortlink
        ]));
    });

    /**
     * AJAX Generate Shortlinks
     *
     * @return void
     */
    add_action('wp_ajax_ffirebase_generate_shortlinks', function (): void {
        header('Content-Type: application/json');

        $ids = @$_GET['ids'];
        if (!is_array($ids)) {
            exit(json_encode([
                'success' => 0,
                'message' => "No post IDs specified.",
            ]));
        }

        $ids = array_unique(array_map('intval', $ids));
        $results = [];

        foreach ($ids as $id) {
            $results[] = [
                'id' => $id,
                'url' => wp_get_shortlink($id)
            ];
        }

        exit(json_encode([
            'success' => 1,
            'message' => $results,
        ]));
    });
}

/**
 * Delete post meta on save.
 *
 * @param int $post_ID
 * @param WP_Post $post
 * @param bool $update
 * @return mixed
 */
// add_action('save_post', function (int $post_ID, WP_Post $post, bool $update) {
//     // Don't act on auto drafts and revisions.
//     if ('auto-draft' === $post->post_status || 'revision' === $post->post_type) {
//         return;
//     }

//     delete_post_meta($post_ID, '_ffirebase_shortlink');
// }, 10, 3);

/**
 * Delete post meta on save if slug has changed.
 *
 * @param int $post_ID
 * @param WP_Post $post_after
 * @param WP_Post $post_before
 * @return void
 */
add_action('post_updated', function (int $post_ID, WP_Post $post_after, WP_Post $post_before): void {
    // Don't act on auto drafts and revisions.
    if ('auto-draft' === $post_after->post_status || 'revision' === $post_after->post_type) {
        return;
    }

    // Don't delete the shortlink if the slug was empty prior, or the slug has not changed.
    if (!$post_before->post_name || $post_after->post_name === $post_before->post_name) {
        return;
    }

    delete_post_meta($post_ID, '_ffirebase_shortlink');
}, 10, 3);
