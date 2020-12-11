<form
    method="POST"
    action="<?= admin_url('admin-ajax.php?action=ffirebase_generate_shortlink') ?>"
    onsubmit="return ffbShorten(this)">
    <table width="100%" border="0"><tr>
        <td><input type="text" name="url" placeholder="https://yoursite.com" style="width:100%" /></td>
        <td width="10"><input type="submit" value="Shorten" class="button" /></td>
    </tr></table>

    <div class="notice notice-success inline" style="display:none">
        <p>Shortlink: https://yoursite.com</p>
    </div>
</form>
<script>

function ffbShorten( form ) {
    var $form = jQuery( form );

    jQuery.ajax( {
        url: $form.attr( 'action' ),
        method: $form.attr( 'method' ),
        data: $form.serialize(),
        dataType: 'json',
        beforeSend: function () {
            $form
                .find( '.notice' )
                .removeClass( 'notice-success notice-error' )
                .hide();

            $form.find( ':submit' ).attr( 'disabled', 'disabled' );
        },
        error: function ( jqXHR, textStatus, errorThrown ) {
            $form
                .find( '.notice' )
                .html( '<p>Error: ' + errorThrown + '</p>' )
                .addClass( 'notice-error' )
                .fadeIn();
        },
        success: function ( data ) {
            if ( data.success ) {
                $form
                    .find( '.notice' )
                    .html( '<p>Shortlink: ' + data.message + '</p>' )
                    .addClass( 'notice-success' )
                    .fadeIn();

                form.reset();
            } else {
                $form
                    .find( '.notice' )
                    .html( '<p>Error: ' + data.message + '</p>' )
                    .addClass( 'notice-error' )
                    .fadeIn();
            }
        },
        complete: function () {
            $form.find( ':submit' ).removeAttr( 'disabled' );
        },
    } );

	return false;
}

</script>