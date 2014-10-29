<?php

require 'vendor/autoload.php';

$mig = new SPR_Pitch_Migrate();

/* Optional settings */
//$mig->set_debug('y'); // default: n
//$mig->set_posttype('news'); // default: post

/* Create import xml */
$xml = $mig->do_wp_import(4);

var_dump($xml);

echo count($mig->links).' posts found.';
