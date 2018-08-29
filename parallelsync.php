<?php
ini_set('max_execution_time', 36000); //3600 seconds = 10 hour
ignore_user_abort(true);
set_time_limit(0);
 // optional
 ob_end_clean();
 header("Connection: close");

 ob_start();
 echo ('Text the user will see');
 $size = ob_get_length();
 header("Content-Length: $size");
 ob_end_flush(); // Strange behaviour, will not work
 flush();            // Unless both are called !
 session_write_close(); // Added a line suggested in the comment
 
@error_reporting( 0 );

    // to load wordpress core
$parse_uri = explode( 'wp-content', $_SERVER['SCRIPT_FILENAME'] );
require_once( $parse_uri[0] . 'wp-load.php' );


/**
 * Created by PhpStorm.
 * User: vivek
 * Date: 11/07/16
 * Time: 1:15 PM
 * pass site base url to identify mulisite, and concurrency integer value
 * eg. php parallelsync.php http://brandwiki.buzz/citrusadvertising 10
 *
 */

// start output buffer
//if (ob_get_level() == 0) ob_start();
// because of flushing issue 
//echo str_repeat('&nbsp;', 50) . "<br />\n";

if( !isset( $_GET['blogid'] ) && empty( $_GET['blogid'])  && !isset( $_GET['parallel'] ) && empty( $_GET['parallel'])
    && !isset( $_GET['secret'] ) && empty( $_GET['secret'] ) )
    die(" Invalid params ");

if( $_GET["secret"] != "bw78901603")
    die(" Enter correct code ");
$blogid = $_GET["blogid"]; // blogid to process
//$imgid = $_GET["imgid"];  // imgid if required 
$parallel = $_GET["parallel"]; // num of requests to run in parallel

echo $blogid."---".$parallel;

$url = "http".(!empty($_SERVER['HTTPS'])?"s":"").
"://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];

$baseurl =  substr($url,0,strrpos($url,'/')+1);

$clipath = $baseurl."lib/classes/clisync.php";


// get all image ids for this site
$handle = file_get_contents($clipath."?secret=bw78901603&action=all_image_ids&blogid=".$blogid);
// we get array of image ids
$image_ids = unserialize($handle);
echo $handle;
print_r($image_ids);
echo "total images to process ====== ".count($image_ids)."\n\r";
@wp_cache_flush();
@delete_site_option("gcs_sync_status_".$blogid);
update_site_option( "gcs_sync_status_".$blogid, array("total"=>count($image_ids), "done"=>0, "failed"=>0, "failed_path"=>array("") ) );
$urls = array();

foreach($image_ids as $img){
    // an array of URL's to fetch
    $urls[] = $clipath."?secret=bw78901603&action=process_image&blogid=".$blogid."&imgid=".$img; 

}

// print_r( $urls );

// die();

// a function that will process the returned responses
function request_callback($response, $info) {
    // parse the page title out of the returned HTML
    echo $response."<br />";
    //ob_flush();
    //flush();
}

// create a new RollingCurl object and pass it the name of your custom callback function
$rc = new RollingCurl("request_callback");
// the window size determines how many simultaneous requests to allow.  
$rc->window_size = $parallel;
$time_start = microtime(true);
foreach ($urls as $url) {
    // add each request to the RollingCurl object
    $request = new RollingCurlRequest($url);
    $rc->add($request);
}
$rc->execute();

echo "Total time for images is ".(microtime(true) - $time_start);






/*
Authored by Josh Fraser (www.joshfraser.com)
Released under Apache License 2.0

Maintained by Alexander Makarov, http://rmcreative.ru/

$Id$
*/

/**
 * Class that represent a single curl request
 */
class RollingCurlRequest {
    public $url = false;
    public $method = 'GET';
    public $post_data = null;
    public $headers = null;
    public $options = null;

    /**
     * @param string $url
     * @param string $method
     * @param  $post_data
     * @param  $headers
     * @param  $options
     * @return void
     */
    function __construct($url, $method = "GET", $post_data = null, $headers = null, $options = null) {
        $this->url = $url;
        $this->method = $method;
        $this->post_data = $post_data;
        $this->headers = $headers;
        $this->options = $options;
    }

    /**
     * @return void
     */
    public function __destruct() {
        unset($this->url, $this->method, $this->post_data, $this->headers, $this->options);
    }
}

/**
 * RollingCurl custom exception
 */
class RollingCurlException extends Exception {}

/**
 * Class that holds a rolling queue of curl requests.
 *
 * @throws RollingCurlException
 */
class RollingCurl {
    /**
     * @var int
     *
     * Window size is the max number of simultaneous connections allowed.
     * 
     * REMEMBER TO RESPECT THE SERVERS:
     * Sending too many requests at one time can easily be perceived
     * as a DOS attack. Increase this window_size if you are making requests
     * to multiple servers or have permission from the receving server admins.
     */
    private $window_size = 5;

    /**
     * @var float
     *
     * Timeout is the timeout used for curl_multi_select.
     */
    private $timeout = 10;

    /**
     * @var string|array
     *
     * Callback function to be applied to each result.
     */
    private $callback;

    /**
     * @var array
     *
     * Set your base options that you want to be used with EVERY request.
     */
    protected $options = array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT => 30
    );
    
    /**
     * @var array
     */
    private $headers = array();

    /**
     * @var Request[]
     *
     * The request queue
     */
    private $requests = array();

    /**
     * @var RequestMap[]
     *
     * Maps handles to request indexes
     */
    private $requestMap = array();

    /**
     * @param  $callback
     * Callback function to be applied to each result.
     *
     * Can be specified as 'my_callback_function'
     * or array($object, 'my_callback_method').
     *
     * Function should take three parameters: $response, $info, $request.
     * $response is response body, $info is additional curl info.
     * $request is the original request
     *
     * @return void
     */
    function __construct($callback = null) {
        $this->callback = $callback;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        return (isset($this->{$name})) ? $this->{$name} : null;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return bool
     */
    public function __set($name, $value){
        // append the base options & headers
        if ($name == "options" || $name == "headers") {
            $this->{$name} = $value + $this->{$name};
        } else {
            $this->{$name} = $value;
        }
        return true;
    }

    /**
     * Add a request to the request queue
     *
     * @param Request $request
     * @return bool
     */
    public function add($request) {
         $this->requests[] = $request;
         return true;
    }

    /**
     * Create new Request and add it to the request queue
     *
     * @param string $url
     * @param string $method
     * @param  $post_data
     * @param  $headers
     * @param  $options
     * @return bool
     */
    public function request($url, $method = "GET", $post_data = null, $headers = null, $options = null) {
         $this->requests[] = new RollingCurlRequest($url, $method, $post_data, $headers, $options);
         return true;
    }

    /**
     * Perform GET request
     *
     * @param string $url
     * @param  $headers
     * @param  $options
     * @return bool
     */
    public function get($url, $headers = null, $options = null) {
        return $this->request($url, "GET", null, $headers, $options);
    }

    /**
     * Perform POST request
     *
     * @param string $url
     * @param  $post_data
     * @param  $headers
     * @param  $options
     * @return bool
     */
    public function post($url, $post_data = null, $headers = null, $options = null) {
        return $this->request($url, "POST", $post_data, $headers, $options);
    }

    /**
     * Execute the curl
     *
     * @param int $window_size Max number of simultaneous connections
     * @return string|bool
     */
    public function execute($window_size = null) {
        // rolling curl window must always be greater than 1
        if (sizeof($this->requests) == 1) {
            return $this->single_curl();
        } else {
            // start the rolling curl. window_size is the max number of simultaneous connections
            return $this->rolling_curl($window_size);
        }
    }

    /**
     * Performs a single curl request
     *
     * @access private
     * @return string
     */
    private function single_curl() {
        $ch = curl_init();      
        $request = array_shift($this->requests);
        $options = $this->get_options($request);
        curl_setopt_array($ch,$options);
        $output = curl_exec($ch);
        $info = curl_getinfo($ch);

        // it's not neccesary to set a callback for one-off requests
        if ($this->callback) {
            $callback = $this->callback;
            if (is_callable($this->callback)){
                call_user_func($callback, $output, $info, $request);
            }
        }
        else
            return $output;
    return true;
    }

    /**
     * Performs multiple curl requests
     *
     * @access private
     * @throws RollingCurlException
     * @param int $window_size Max number of simultaneous connections
     * @return bool
     */
    private function rolling_curl($window_size = null) {
        if ($window_size)
            $this->window_size = $window_size;

        // make sure the rolling window isn't greater than the # of urls
        if (sizeof($this->requests) < $this->window_size)
            $this->window_size = sizeof($this->requests);
        
        if ($this->window_size < 2) {
            throw new RollingCurlException("Window size must be greater than 1");
        }

        $master = curl_multi_init();        

        // start the first batch of requests
        for ($i = 0; $i < $this->window_size; $i++) {
            $ch = curl_init();

            $options = $this->get_options($this->requests[$i]);

            curl_setopt_array($ch,$options);
            curl_multi_add_handle($master, $ch);

            // Add to our request Maps
            $key = (string) $ch;
            $this->requestMap[$key] = $i;
        }

        do {
            while(($execrun = curl_multi_exec($master, $running)) == CURLM_CALL_MULTI_PERFORM);
            if($execrun != CURLM_OK)
                break;
            // a request was just completed -- find out which one
            while($done = curl_multi_info_read($master)) {

                // get the info and content returned on the request
                $info = curl_getinfo($done['handle']);
                $output = curl_multi_getcontent($done['handle']);

                // send the return values to the callback function.
                $callback = $this->callback;
                if (is_callable($callback)){
                $key = (string)$done['handle'];
                    $request = $this->requests[$this->requestMap[$key]];
                    unset($this->requestMap[$key]);
                    call_user_func($callback, $output, $info, $request);
                }

                // start a new request (it's important to do this before removing the old one)
                if ($i < sizeof($this->requests) && isset($this->requests[$i]) && $i < count($this->requests)) {
                    $ch = curl_init();
                    $options = $this->get_options($this->requests[$i]);
                    curl_setopt_array($ch,$options);
                    curl_multi_add_handle($master, $ch);

                    // Add to our request Maps
                    $key = (string) $ch;
                    $this->requestMap[$key] = $i;
                    $i++;
                }

                // remove the curl handle that just completed
                curl_multi_remove_handle($master, $done['handle']);

            }

        // Block for data in / output; error handling is done by curl_multi_exec
        if ($running)
                curl_multi_select($master, $this->timeout);

        } while ($running);
        curl_multi_close($master);
        return true;
    }


    /**
     * Helper function to set up a new request by setting the appropriate options
     *
     * @access private
     * @param Request $request
     * @return array
     */
    private function get_options($request) {
        // options for this entire curl object
        $options = $this->__get('options');
        if (ini_get('safe_mode') == 'Off' || !ini_get('safe_mode')) {
            $options[CURLOPT_FOLLOWLOCATION] = 1;
            $options[CURLOPT_MAXREDIRS] = 5;
        }
        $headers = $this->__get('headers');

        // append custom options for this specific request
        if ($request->options) {
            $options = $request->options + $options;
        }

        // set the request URL
        $options[CURLOPT_URL] = $request->url;

        // posting data w/ this request?
        if ($request->post_data) {
            $options[CURLOPT_POST] = 1;
            $options[CURLOPT_POSTFIELDS] = $request->post_data;
        }
        if ($headers) {
            $options[CURLOPT_HEADER] = 0;
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        return $options;
    }

    /**
     * @return void
     */
    public function __destruct() {
        unset($this->window_size, $this->callback, $this->options, $this->headers, $this->requests);
    }
}


/************
error_reporting(E_ALL);

DEFINE('WP_BASE_PATH', "/home/brandwikibiz/public_html");
DEFINE('GCS_CLISYNC_PATH', "/home/brandwikibiz/public_html/wp-content/plugins/bwcdn-cs-uploader/lib/classes/clisync.php");


$flag = true;
$i = 0;


if (isset($_GET["site"])){
    $url = urldecode($_GET["site"]);
    $concurrency = urldecode($_GET["concurrency"]);
}else{

    $url = $argv[1]; // base URl of wordpress site for which code needs to be run
    $concurrency = $argv[2]; // no. of concurrent uploads

}


// get all image ids for this site
$handle = popen("php wp-cli.phar --path=".WP_BASE_PATH." --url=$url --user=1 eval-file '".GCS_CLISYNC_PATH."' all_image_ids", 'r');

// to let DB queries run echo "'$handle'; " . gettype($handle) . "\n";
sleep(5);
$read = fgets($handle);  // , 2096);
echo $read;
// we get array of image ids
$image_ids = unserialize($read);

echo "total images to process ====== ".count($image_ids)."\n\r";

//var_dump(explode($read,","));
pclose($handle);


do{
    $count_concurrency = 0;
    $handle = array();

    do{
        //echo $image_ids[$i]."\n\r";
        $handle[] = popen("php wp-cli.phar --path=".WP_BASE_PATH." --url=$url --user=1 eval-file '".GCS_CLISYNC_PATH."'  process_image ".$image_ids[$i],'r');
        $count_concurrency += 1;
        $i += 1;
        if(! array_key_exists($i, $image_ids)){
            break;
        }
    }while($count_concurrency<$concurrency);

    echo "====================== \n\r";
    sleep(5);
    // wait for process forks to complete before continuing
    $time_start = microtime(true);
    foreach($handle as $hand){
        echo fgets($hand);
        pclose($hand);
    }
    echo 'Total time for '.$concurrency." images is ".(microtime(true) - $time_start)." Rate = ".round((microtime(true) - $time_start)/$concurrency, 2)." images/sec ";

    if(! array_key_exists($i, $image_ids)){
        break;
    }

}while($flag);

********/
?>
</body>
</html>