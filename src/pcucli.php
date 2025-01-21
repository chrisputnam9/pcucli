<?php
/**
 * Pcucli Class File
 *
 * @package pcucli
 * @author  chrisputnam9
 */

/**
 * pcucli
 *
 * PHP ClickUp Command Line Interface
 */
class Pcucli extends Console_Abstract
{
    /**
     * Current tool version
     *
     * @var string
     */
    public const VERSION = "1.3.0";

    /**
     * Tool shortname - used as name of configurationd directory.
     *
     * @var string
     */
    public const SHORTNAME = 'pcucli';

    /**
     * Callable Methods
     */
    protected static $METHODS = [
		'spam_list',
        'get',
        'post',
    ];

    protected static $HIDDEN_CONFIG_OPTIONS = [
        'api_key',
        'api_cache_lifetime',
    ];

    // Constants
    public const APP_URL = "https://app.clickup.com";
    public const API_URL = "https://api.clickup.com/api/v2";

    // Config Variables
    protected $__api_key = ["ClickUp API key", "string"];
    public $api_key = "";

    protected $__api_cache = ["Whether to cache results"];
    public $api_cache = true;

    protected $__api_cache_lifetime = ["How long to cache results in seconds (if enabled)"];
    public $api_cache_lifetime = 604800; // Default: 1 week

    /**
     * The URL to check for updates
     *
     *  - PCon will check the README file - typical setup
     *
     * @var string
     * @see PCon::update_version_url
     * @api
     */
    public $update_version_url = "https://raw.githubusercontent.com/chrisputnam9/pcucli/master/README.md";

    protected $___spam_list = [
        "Test a list by adding X generic todos to it",
        ["List ID", "int"],
        ["Number of todos to add", "int"],
    ];
	public function spam_list($list_id, $number_of_todos=1)
    {
		$this->outputProgress(0, $number_of_todos, "todos");
		for ($i=1; $i<=$number_of_todos; $i++) {
			$created_at = date("Y-m-d H:i:s");
			$this->post('list/'.$list_id.'/task', [
				'name' => "Test Task $i - $created_at",
				'content' => "Test Task Content for task $i created at $created_at",
			], false);
			$this->outputProgress($i, $number_of_todos, "todos created");
			// For rate limit of 100 requests per minute
			// => 1 request every 0.6 seconds
			// => sleep 0.6 seconds = 600000 microseconds between each request
			usleep(600000);
		}
		$this->output("Done!");
	}

    protected $___get = [
        "GET data from the ClickUp API.  Refer to https://clickup.com/api/",
        ["Endpoint slug, eg. 'projects'", "string"],
        ["Fields to output in results - comma separated, false to output nothing, * to show all", "string"],
        ["Whether to return headers", "boolean"],
        ["Whether to output progress", "boolean"],
    ];
	public function get($endpoint, $output=true, $return_headers=false, $output_progress=false)
    {
        // Clean up endpoint
        $endpoint = trim($endpoint, " \t\n\r\0\x0B/");

        // Check for valid cached result if cache is enabled
        $body = "";
        if ($this->api_cache and !$return_headers)
        {
            $this->log("Cache is enabled - checking...");

            $body = $this->getAPICacheContents($endpoint);
            if (!empty($body))
            {
                $body_decoded = json_decode($body);
                if (empty($body_decoded) and !is_array($body_decoded))
                {
                    $this->warn("Invalid cached data - will try a fresh call", true);
                    $body="";
                }
                else
                {
                    $body = $body_decoded;
                }
            }
        }
        else
        {
            $this->log("Cache is disabled");
        }

        if (empty($body) and !is_array($body))
        {
            $this->log("Absent cache data, running fresh API request");

            // Get API curl object for endpoint
            $ch = $this->getAPICurl($endpoint, $output_progress);

            // Execute and check results
            list($body, $headers) = $this->runAPICurl($ch, null, [], $output_progress);

            // Cache results
            $body_json = json_encode($body, JSON_PRETTY_PRINT);
            $this->setAPICacheContents($endpoint, $body_json);
        }

        if ($output)
        {
            if (empty($body))
            {
                $this->output('No data in response.');
            }
            else
            {
                $this->output($body);
            }
        }

        if ($return_headers)
        {
            return [$body, $headers];
        }

        return $body;
    }

    protected $___post = [
        "POST data to the ClickUp API.  Refer to https://clickup.com/api/",
        ["Endpoint slug, eg. 'projects'", "string"],
        ["JSON (or HJSON) body to send", "string"],
        ["Fields to output in results - comma separated, false to output nothing, * to show all", "string"],
        ["Whether to return headers", "boolean"],
        ["Whether to output progress", "boolean"],
    ];
	public function post($endpoint, $body_json=null, $output=true, $return_headers=false, $output_progress=false)
    {
        return $this->_sendData('POST', $endpoint, $body_json, $output, $return_headers, $output_progress);
    }

        /**
         * Send data to API via specified method
         */
        protected function _sendData($method, $endpoint, $body_json=null, $output=true, $return_headers=false, $output_progress=false)
        {
            // Clean up endpoint
            $endpoint = trim($endpoint, " \t\n\r\0\x0B/");

            // Check JSON
            if (is_null($body_json))
            {
                $this->error("JSON body to send is required");
            }

            if (is_string($body_json))
            {
                // Allow Human JSON to be passed in - more forgiving
                $body = $this->json_decode($body_json, ['keepWsc'=>false]);
                if (empty($body))
                {
                    $this->error("Invalid JSON body - likely syntax error. Make sure to use \"s and escape them as needed.");
                }
            }
            else
            {
                $body = $body_json;
            }

            // Wrap in data key if needed
            if (!isset($body))
            {
                $data = $body;
                $body = new StdClass();
                $body = $data;
            }
            $body_json = json_encode($body);

            // Get API curl object for endpoint
            $ch = $this->getAPICurl($endpoint, $output_progress);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
                CURLOPT_POSTFIELDS => $body_json,
            ]);

            // Execute and check results
            list($body, $headers) = $this->runAPICurl($ch, null, [], $output_progress);

            if ($output)
            {
                if (empty($body))
                {
                    $this->output('No data in response.');
                }
                else
                {
                    $this->output($body);
                }
            }

            if ($return_headers)
            {
                return [$body, $headers];
            }

            return $body;
        }

    /**
     * Prep Curl object to hit ClickUp API
     * - endpoint should be api endpoint to hit
     */
    protected function getAPICurl($endpoint, $output_progress=false)
    {
        $this->setupAPI();
        $url = self::API_URL . '/' . $endpoint;
        if ($output_progress)
        {
            $this->output("Running API request to **".$url."**");
        }
        $ch = $this->getCurl($url);

        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 1800,
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: ' . $this->api_key,
            ),
        ]);

        return $ch;
    }

    /**
     * Get link for a single API result object
     */
    public function getResultLink($item, $type='')
    {
        $app_url = self::APP_URL;

        $item_id = null;

        if (is_object($item))
        {

            if (empty($type))
            {
                $type = empty($item->resource_type) ? "" : $item->resource_type;
            }

            $item_id = $item->gid; 
        }
        else
        {
            $item_id = $item;
        }

        if ($type=='project')
        {
            return $app_url . "/0/" . $item_id;
        }

        if ($type=='task' && isset($item->permalink_url))
        {
            return $item->permalink_url;
        }

        return "NO LINK";
    }

    /**
     * Get results from pre-prepared curl object
     *  - Handle errors
     *  - Parse results
     */
    protected function runAPICurl($ch, $close=true, $recurrance=[], $output_progress=false)
    {
        if (!is_array($recurrance)) $recurrance=[];
        $recurrance = array_merge([
            'recurring' => false,
            'complete' => 0,
            'total' => 1,
        ], $recurrance);

        // Prep to receive headers
        $headers = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION,
            function($curl, $header) use (&$headers)
            {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) < 2)
                {
                    return $len;
                }

                $headers[strtolower(trim($header[0]))][] = trim($header[1]);

                return $len;
            }
        );

        if ($output_progress and !$recurrance['recurring']) $this->outputProgress($recurrance['complete'], $recurrance['total'], "initial request");

        // Execute
        $body = $this->execCurl($ch);

        // Get response code
        $response_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        // Make sure valid response
        if ( empty($body) ) {
            $this->error("Request Error: " . curl_error($ch), false);
            $this->warn("Request may have failed", true);
        }

        if (
            $response_code < 200
            or $response_code > 299
        ) {
            $this->error("Response: $response_code", false);
            $this->error($body, false);
            $this->warn("Request may have failed", true);
        }

        // Process response
        $body_decoded = json_decode($body);
        if (empty($body_decoded) and !is_array($body_decoded))
        {
            $this->error("Invalid response", false);
            $this->error($response_code, false);
            $this->error($body, false);
            $this->warn("Request may have failed", true);
        }
        $body = $body_decoded;

        if ($close)
        {
            curl_close($ch);
        }

        return [$body, $headers];
    }

    /**
     * Set up ClickUp API data
     * - prompt for any missing data and save to config
     */
    protected function setupAPI()
    {
        $api_key = $this->api_key;
        if (empty($api_key))
        {
            $api_key = $this->input("Enter ClickUp API Token (from https://app.clickup.com/settings/apps)", null, true);
            $api_key = trim($api_key);
            $this->configure('api_key', $api_key, true);
        }

        $this->saveConfig();
    }

    /**
     * Get API cache contents
     */
    protected function getAPICacheContents($endpoint)
    {
        return $this->getCacheContents(
            $this->getAPICachePath($endpoint),
            $this->api_cache_lifetime
        );
    } 

    /**
     * Set API cache contents
     */
    protected function setAPICacheContents($endpoint, $contents)
    {
        return $this->setCacheContents(
            $this->getAPICachePath($endpoint),
            $contents
        );
    } 

    /**
     * Get cache path for a given endpont
     */
    protected function getAPICachePath($endpoint)
    {
        $cache_path = ['clickup-api'];

        $url_slug = preg_replace("/[^0-9a-z_]+/", "-", self::API_URL);
        $cache_path[]= $url_slug;

        $endpoint_array = explode("/", $endpoint . ".json");
        $cache_path = array_merge($cache_path, $endpoint_array);

        return $cache_path;
    }

}//end class


if (empty($__no_direct_run__)) {
    // Kick it all off
    Pcucli::run($argv);
}

// Note: leave the end tag for packaging
?>
