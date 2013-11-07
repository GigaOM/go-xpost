<?php 

// We don't want pc-slurp items to redirect back to pC
unset( $post->meta['go_xpost_redirect'] );