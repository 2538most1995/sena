<?php
// Local PHP preview: Apache deployments use the directory .htaccess rules.
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH) ?: '/');
$resolved=realpath(__DIR__.'/'.$path);
$private=[__DIR__.'/config.local.php',__DIR__.'/uploads/photos',__DIR__.'/uploads/id_cards',__DIR__.'/uploads/house_registrations',__DIR__.'/uploads/certificates',__DIR__.'/tests'];
foreach($private as $directory) if($resolved && ($resolved===$directory || str_starts_with($resolved,$directory.'/'))) {http_response_code(403);exit('Forbidden');}
return false;
