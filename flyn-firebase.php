<?php
/*
Plugin Name: Firebase Short Links
Plugin URI: https://www.flynsarmy.com/
Description: A simple Firebase URL shortener for WordPress.
Author: Flyn San
Version: 1.0.0
Author URI: https://www.flynsarmy.com/
*/

add_filter( 'get_shortlink', function( $url, $post_id = false ) {
    if ( !defined('FLYN_FIREBASE_URL') || !defined('FLYN_FIREBASE_API_KEY') )
        return $url;

    global $post;
    if ( ! $post_id && $post ) $post_id = $post->ID;
    elseif ( $post_id ) $post = get_post( $post_id );

    if ( $post && !in_array($post->post_status, ['publish', 'private']) )
        return "";

    if ( ( is_singular() || $post ) && ! is_front_page() ) {
        $shortlink = ffirebase_cached_shortlink($post_id);
        if ( $shortlink )
            return $shortlink;
        // Goo.gl plugin support
        $shortlink = get_post_meta( $post_id, '_googl_shortlink', true );
        if ( $shortlink )
        {
            add_post_meta( $post_id, '_ffirebase_shortlink', $shortlink, true );
            return $shortlink;
        }

        $permalink = get_permalink( $post_id );
        $shortlink = ffirebase_shorten( $permalink );

        if ( $shortlink !== $url ) {
            add_post_meta( $post_id, '_ffirebase_shortlink', $shortlink, true );
            return $shortlink;
        }
        else {
            return $url;
        }
    } elseif ( is_front_page() ) {
        $shortlink = (string) get_option( '_ffirebase_shortlink_home' );
        if ( $shortlink )
            return $shortlink;
        // Goo.gl plugin support
        $shortlink = (string) get_option( '_googl_shortlink_home' );
        if ( $shortlink )
        {
            update_option( '_ffirebase_shortlink_home', $shortlink );
            return $shortlink;
        }

        $ffirebase_shortlink = ffirebase_shorten( home_url( '/' ) );
        if ( $ffirebase_shortlink !== $shortlink ) {
            update_option( '_ffirebase_shortlink_home', $ffirebase_shortlink );
            return $ffirebase_shortlink;
        } else {
            return home_url( '/' );
        }
    }
}, 9, 2 );

function ffirebase_cached_shortlink( $post_id )
{
    return (string)get_post_meta( $post_id, '_ffirebase_shortlink', true );
}

function ffirebase_shorten( $url ) {
	$result = wp_remote_post( add_query_arg( 'key', apply_filters( 'ffirebase_api_key', FLYN_FIREBASE_API_KEY), 'https://firebasedynamiclinks.googleapis.com/v1/shortLinks' ), [
		'body' => json_encode([
            //'dynamicLinkInfo' => [
            //    'link' => $url,
            //],
            'longDynamicLink' => FLYN_FIREBASE_URL."/?link=".esc_url($url),
            'suffix' => [
                'option' => 'SHORT',
            ]
        ]),
		'headers' => array( 'Content-Type' => 'application/json' ),
	]);

	// Return the URL if the request got an error.
	if ( is_wp_error( $result ) )
		return $url;

	$result = json_decode( $result['body'] );
	$shortlink = $result->shortLink;
	if ( $shortlink )
		return $shortlink;

	return $url;
}

add_filter( 'manage_edit-post_columns', function( $columns ) {
    $columns['shortlink'] = 'Shortlink';
	return $columns;
});

add_action( 'manage_posts_custom_column', function( $column ) {
    if ( 'shortlink' == $column ) {
        // Don't try to load shortlinks for non-public posts
        $post_status = get_post_status(get_the_ID());
        if ( !in_array($post_status, ['publish', 'private']) )
            return;

        $shorturl = ffirebase_cached_shortlink( get_the_ID() );
        if ( $shorturl )
        {
            $shorturl_caption = preg_replace( '/https?\:\/\//', '', $shorturl );
            printf( '<a href="%s">%s</a>', esc_url( $shorturl ), esc_html( $shorturl_caption ) );
        }
        else
        {
            echo "<span class='ffirebase_load_shortlink' data-post_id='".get_the_ID()."'>Generating...</span>";
        }
    }
});

add_action('admin_print_footer_scripts-edit.php', function() {
    ?>
    <script type="text/javascript">
        // Grab all IDs to generate shortlink sfor
        var ffb_shortlink_ids = jQuery('#the-list .column-shortlink:not(.hidden) .ffirebase_load_shortlink').map(function() {
            return jQuery(this).data('post_id');
        }).get();
        // Split into chunks of 5 for AJAX requests
        var ffb_shortlink_chunks = [];
        for ( var i = 0; i < ffb_shortlink_ids.length; i+= 5 )
            ffb_shortlink_chunks.push(ffb_shortlink_ids.slice(i, i+5));

        ffb_get_shortlinks( ffb_shortlink_chunks );

        function ffb_get_shortlinks( chunks )
        {
            if ( !chunks.length )
                return;

            jQuery.ajax({
                dataType: "json",
                url: "<?= admin_url("admin-ajax.php?action=ffirebase_generate_shortlinks") ?>",
                data: jQuery.param({ "ids[]": chunks.shift() }, true),
                success: function(data, textStatus, jqXHR) {
                    if ( data.success )
                    {
                        for ( var i = 0; i < data.message.length; i++ )
                            jQuery("#the-list .column-shortlink .ffirebase_load_shortlink[data-post_id="+data.message[i].id+"]").html(
                                "<a href=\""+data.message[i].url+"\">"+data.message[i].url.replace(/^https?:\/\//, '')+"</a>"
                            );
                    }

                    ffb_get_shortlinks(chunks);
                }
            });
        }
    </script>
    <?php
});

add_action('wp_ajax_ffirebase_generate_shortlinks', function() {
    header('Content-Type: application/json');

    $ids = @$_GET['ids'];
    if ( !is_array($ids) )
        exit(json_encode([
            'success' => 0,
            'message' => "No post IDs specified.",
        ]));

    $ids = array_unique(array_map('intval', $ids));
    $results = [];

    foreach ( $ids as $id )
        $results[] = [
            'id' => $id,
            'url' => wp_get_shortlink($id)
        ];

    exit(json_encode([
        'success' => 1,
        'message' => $results,
    ]));
});

add_action( 'save_post', function( $post_ID, $post ) {
    // Don't act on auto drafts and revisions.
    if ( 'auto-draft' == $post->post_status || 'revision' == $post->post_type )
        return;

    delete_post_meta( $post_ID, '_ffirebase_shortlink' );
}, 10, 2 );

if ( !defined('FLYN_FIREBASE_URL') || !defined('FLYN_FIREBASE_API_KEY') )
{
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-error is-dismissable">
            <p>Firebase Short Links plugin is enabled but <em>FLYN_FIREBASE_URL</em> or <em>FLYN_FIREBASE_API_KEY </em> constants aren't defined in <em>wp-config.php</em>. Please see the installation instructions section for this plugin.</p>
        </div>
        <?php

    });
}