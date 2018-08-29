<?php 
// TODO: check if URL is valid to prevent DDOS on other server using us
$ctx = stream_context_create(array(
    'http' => array(
        'timeout' => 2
        )
    )
);
//echo urldecode($_GET['url']);
   // echo "Test";
echo file_get_contents($_GET["url"], 0, $ctx);