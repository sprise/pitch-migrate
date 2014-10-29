<!DOCTYPE html>
<html>
<head>

<title>HMA Public Relations : PitchEngine Newsroom</title>
<meta charset="utf-8">
</head>

<body>
	
<?php

require 'vendor/autoload.php';

$mig = new SPR_Pitch_Migrate();

/* Optional settings */
$mig->set_debug('y'); // default: n
//$mig->set_posttype('news'); // default: post

/* Create import xml */
$posts = $mig->do_wp_import(1);

echo '<pre>'; print_r($posts); echo '</pre>';

echo count($mig->links).' posts found.'; 

?>


</body>
</html>
