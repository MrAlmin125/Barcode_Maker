<?php
// Must be absolute first line - no BOM, no whitespace before <?php
header('Content-Type: application/manifest+json');
header('Access-Control-Allow-Origin: *');
$m = array(
'name'=>'Aldiyafa Barcode',
'short_name'=>'Aldiyafa',
'start_url'=>'./barcode.php',
'scope'=>'./',
'display'=>'standalone',
'background_color'=>'#0a0a0f',
'theme_color'=>'#f1ac14',
'orientation'=>'any',
'icons'=>array(
array('src'=>'./image/icon-192.png','sizes'=>'192x192','type'=>'image/png','purpose'=>'any'),
array('src'=>'./image/icon-512.png','sizes'=>'512x512','type'=>'image/png','purpose'=>'any'),
array('src'=>'./image/icon-512.png','sizes'=>'512x512','type'=>'image/png','purpose'=>'maskable')
)
);
echo json_encode($m);
