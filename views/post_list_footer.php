<script>
// Grab all IDs to generate shortlinks for.
var ffbShortlinIds = jQuery(
	'#the-list .column-shortlink:not(.hidden) .ffirebase_load_shortlink'
)
	.map( function () {
		return jQuery( this ).data( 'post_id' );
	} )
	.get();
// Split into chunks of 5 for AJAX requests.
var ffb_shortlink_chunks = [];
for ( var i = 0; i < ffbShortlinIds.length; i += 5 )
	ffb_shortlink_chunks.push( ffbShortlinIds.slice( i, i + 5 ) );

ffbGetShortlinks( ffb_shortlink_chunks );

function ffbGetShortlinks( chunks ) {
	if ( ! chunks.length ) return;

	jQuery.ajax( {
		dataType: 'json',
		url: '<?= admin_url('admin-ajax.php?action=ffirebase_generate_shortlinks') ?>',
		data: jQuery.param( { 'ids[]': chunks.shift() }, true ),
		success: function ( data ) {
			if ( data.success ) {
				for ( var i = 0; i < data.message.length; i++ )
					jQuery(
						'#the-list .column-shortlink .ffirebase_load_shortlink[data-post_id=' +
							data.message[ i ].id +
							']'
					).html(
						'<a href="' +
							data.message[ i ].url +
							'">' +
							data.message[ i ].url.replace(
								/^https?:\/\//,
								''
							) +
							'</a>'
					);
			}

			ffbGetShortlinks( chunks );
		},
	} );
}
</script>