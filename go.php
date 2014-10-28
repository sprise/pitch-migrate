<?php

require 'vendor/autoload.php';

$mig = new Pitch_Migrate();

/* Optional settings 

// Show debug messages? Default: n
$mig->set_debug('y');

// Set source? Default: src.html
$mig->set_source('file.html'); 

*/

// Grab the posts from the source page
$mig->get_links();

echo count($mig->links).' posts found.';
