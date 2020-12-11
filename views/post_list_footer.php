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