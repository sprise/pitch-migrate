<?php

require 'vendor/autoload.php';

$mig = new Pitch_Migrate();

/* Optional settings */
//$mig->set_debug('y'); // default: n
//$mig->set_posttype('news'); // default: post

/* Create import xml */
$xml = $mig->create_wp_import(4);

var_dump($xml);

echo count($mig->links).' posts found.';
echo '<br><br>XML<pre>'.$xml.'</pre>';
