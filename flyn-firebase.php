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
    global $post;
    if ( ! $post_id && $post ) $post_id = $post->ID;
    elseif ( $post_id ) $post = get_post( $post_id );

    if ( $post && $post->post_status != 'publish' )
        return "";

    if ( ( is_singular() || $post ) && ! is_front_page() ) {
        $shortlink = get_post_meta( $post_id, '_ffirebase_shortlink', true );
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
        $shorturl = wp_get_shortlink();
        $shorturl_caption = preg_replace( '/https?\:\/\//', '', $shorturl );
        printf( '<a href="%s">%s</a>', esc_url( $shorturl ), esc_html( $shorturl_caption ) );
    }
});

add_action( 'save_post', function( $post_ID, $post ) {
    // Don't act on auto drafts and revisions.
    if ( 'auto-draft' == $post->post_status || 'revision' == $post->post_type )
        return;

    delete_post_meta( $post_ID, '_ffirebase_shortlink' );
}, 10, 2 );