<?php

//namespace DrewM\MailChimp;

/**
 * Super-simple, minimum abstraction MailChimp API v3 wrapper
 * This wrapper: https://github.com/drewm/mailchimp-api
 * MailChimp API v3: http://developer.mailchimp.com
 *
 * @author Drew McLellan <drew.mclellan@gmail.com>
 * @version 2.2
 */
class DCMM_MailChimp {
    private $api_key;
    private $api_endpoint = 'https://<dc>.api.mailchimp.com/3.0';

    /*  SSL Verification
        Read before disabling:
        http://snippets.webaware.com.au/howto/stop-turning-off-curlopt_ssl_verifypeer-and-fix-your-php-config/
    */
    public $verify_ssl = false;
    // public $verify_ssl = true;

    private $request_successful = false;
    private $last_error         = '';
    private $last_response      = array();
    private $last_request       = array();

    /**
     * Create a new instance
     * @param string $api_key Your MailChimp API key
     * @throws \Exception
     */
    public function __construct($api_key) {
        $this->api_key = $api_key;

        if (strpos($this->api_key, '-') === false) {
            throw new \Exception("Invalid MailChimp API key `{$api_key}` supplied.");
        }

        list(, $data_center) = explode('-', $this->api_key);
        $this->api_endpoint  = str_replace('<dc>', $data_center, $this->api_endpoint);

        $this->last_response = array('headers' => null, 'body' => null);
    }

    /**
     * Create a new instance of a Batch request. Optionally with the ID of an existing batch.
     * @param string $batch_id Optional ID of an existing batch, if you need to check its status for example.
     * @return Batch New Batch object.
     */
    public function new_batch($batch_id = null) {
        return new Batch($this, $batch_id);
    }

    /**
     * @return string The url to the API endpoint
     */
    public function getApiEndpoint() {
        return $this->api_endpoint;
    }

    /**
     * Convert an email address into a 'subscriber hash' for identifying the subscriber in a method URL
     * @param   string $email The subscriber's email address
     * @return  string          Hashed version of the input
     */
    public function subscriberHash($email) {
        return md5(strtolower($email));
    }

    /**
     * Was the last request successful?
     * @return bool  True for success, false for failure
     */
    public function success() {
        return $this->request_successful;
    }

    /**
     * Get the last error returned by either the network transport, or by the API.
     * If something didn't work, this should contain the string describing the problem.
     * @return  array|false  describing the error
     */
    public function getLastError() {
        return $this->last_error ?: false;
    }

    /**
     * Get an array containing the HTTP headers and the body of the API response.
     * @return array  Assoc array with keys 'headers' and 'body'
     */
    public function getLastResponse() {
        return $this->last_response;
    }

    /**
     * Get an array containing the HTTP headers and the body of the API request.
     * @return array  Assoc array
     */
    public function getLastRequest() {
        return $this->last_request;
    }

    /**
     * Make an HTTP DELETE request - for deleting data
     * @param   string $method URL of the API request method
     * @return  array|false   Assoc array of API response, decoded from JSON
     */
    public function delete($method, $args = array(), $timeout = 10) {
        return $this->makeRequest('delete', $method, $args, $timeout);
    }

    /**
     * Make an HTTP GET request - for retrieving data
     * @param   string $method URL of the API request method
     * @param   array  $args   Assoc array of arguments (usually your data)
     * @param   int    $timeout
     * @return  array|false   Assoc array of API response, decoded from JSON
     */
    public function get($method, $args = array(), $timeout = 10) {
        return $this->makeRequest('get', $method, $args, $timeout);
    }

    /**
     * Make an HTTP PATCH request - for performing partial updates
     * @param   string $method URL of the API request method
     * @param   array  $args   Assoc array of arguments (usually your data)
     * @param   int    $timeout
     * @return  array|false   Assoc array of API response, decoded from JSON
     */
    public function patch($method, $args = array(), $timeout = 10)
    {
        return $this->makeRequest('patch', $method, $args, $timeout);
    }

    /**
     * Make an HTTP POST request - for creating and updating items
     * @param   string $method URL of the API request method
     * @param   array  $args   Assoc array of arguments (usually your data)
     * @param   int    $timeout
     * @return  array|false   Assoc array of API response, decoded from JSON
     */
    public function post($method, $args = array(), $timeout = 10) {
        return $this->makeRequest('post', $method, $args, $timeout);
    }

    /**
     * Make an HTTP PUT request - for creating new items
     * @param   string $method URL of the API request method
     * @param   array  $args   Assoc array of arguments (usually your data)
     * @param   int    $timeout
     * @return  array|false   Assoc array of API response, decoded from JSON
     */
    public function put($method, $args = array(), $timeout = 10) {
        return $this->makeRequest('put', $method, $args, $timeout);
    }

    /**
     * Performs the underlying HTTP request. Not very exciting.
     * @param  string $http_verb The HTTP verb to use: get, post, put, patch, delete
     * @param  string $method    The API method to be called
     * @param  array  $args      Assoc array of parameters to be passed
     * @param int     $timeout
     * @return array|false       Assoc array of decoded result
     * @throws \Exception
     */
    private function makeRequest($http_verb, $method, $args = array(), $timeout = 10) {
        if (!function_exists('curl_init') || !function_exists('curl_setopt')) {
            throw new \Exception("cURL support is required, but can't be found.");
        }

        $url = $this->api_endpoint . '/' . $method;

        $response = $this->prepareStateForRequest($http_verb, $method, $url, $timeout);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/vnd.api+json',
            'Content-Type: application/vnd.api+json',
            'Authorization: apikey ' . $this->api_key
        ));
        curl_setopt($ch, CURLOPT_USERAGENT, 'DrewM/MailChimp-API/3.0 (github.com/drewm/mailchimp-api)');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verify_ssl);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        switch ($http_verb) {
            case 'post':
                curl_setopt($ch, CURLOPT_POST, true);
                $this->attachRequestPayload($ch, $args);
                break;

            case 'get':
                $query = http_build_query($args, '', '&');
                curl_setopt($ch, CURLOPT_URL, $url . '?' . $query);
                break;

            case 'delete':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;

            case 'patch':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                $this->attachRequestPayload($ch, $args);
                break;

            case 'put':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                $this->attachRequestPayload($ch, $args);
                break;
        }

        $response['body']    = curl_exec($ch);
        $response['headers'] = curl_getinfo($ch);

        if ($response['body'] === false) {
            $this->last_error = curl_error($ch);
        }

        curl_close($ch);

        return $this->interpretResponse($response);
    }

    /**
     * @param string  $http_verb
     * @param string  $method
     * @param string  $url
     * @param integer $timeout
     * @return array
     */
    private function prepareStateForRequest($http_verb, $method, $url, $timeout) {
        $this->last_error = '';

        $this->request_successful = false;

        $this->last_response = array(
            'headers'     => null, // array of details from curl_getinfo()
            'httpHeaders' => null, // array of HTTP headers
            'body'        => null // content of the response
        );

        $this->last_request = array(
            'method'  => $http_verb,
            'path'    => $method,
            'url'     => $url,
            'body'    => '',
            'timeout' => $timeout,
        );

        return $this->last_response;
    }

    /**
     * Get the HTTP headers as an array of header-name => header-value pairs.
     *
     * The "Link" header is parsed into an associative array based on the
     * rel names present. The URL is available under the 'url' key.
     *
     * @param array $headersAsString
     * @return array
     */
    private function getHeadersAsArray($headersAsString)
    {
        $headers = array();

        foreach (explode("\r\n", $headersAsString) as $i => $line) {
            if ($i === 0) { // HTTP response line
                continue;
            }

            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            list($key, $value) = explode(': ', $line);

            if ($key == 'Link') {
                $value = array_merge(
                    array('url' => null),
                    $this->getLinkHeaderAsArray($value)
                );
            }

            $headers[$key] = $value;
        }

        return $headers;
    }

    /**
     * Extract all rel => URL pairs from the provided Link header value
     *
     * Mailchimp only implements the URI reference and relation type from
     * RFC 5988, so the value is something like:
     *
     * 'https://us13.api.mailchimp.com/3.0/lists?offset=100&count=10>; rel="next"'
     *
     * Though some providers may return URLs with commas, we don't need to
     * worry about those.
     *
     * @param string $linkHeaderAsString
     * @return array
     */
    private function getLinkHeaderAsArray($linkHeaderAsString)
    {
        $urls = array();

        if (trim($linkHeaderAsString) == '') {
            return $urls;
        }

        $linkHeader = explode(',', $linkHeaderAsString);
        foreach ($linkHeader as $linkValue) {
            $linkValue = trim($linkValue);
            $linkParts = preg_split('/>\s*;\s*/', $linkValue);
            $linkURL   = trim($linkParts[0], '<');
            $linkRel   = trim($linkParts[1]);

            if (preg_match('/\brel\s*=\s*"(.+)"/', $linkRel, $relMatches)) {
                $urls[$relMatches[1]] = $linkURL;
            }
        }

        return $urls;
    }

    /**
     * Encode the data and attach it to the request
     * @param   resource $ch   cURL session handle, used by reference
     * @param   array    $data Assoc array of data to attach
     */
    private function attachRequestPayload(&$ch, $data)
    {
        $encoded = json_encode($data);
        $this->last_request['body'] = $encoded;
        curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
    }

    /**
     * Decode the response and format any error messages for debugging
     * @param array $response The response from the curl request
     * @return array|false    The JSON decoded into an array
     */
    private function interpretResponse($response)
    {
        if (isset($response['headers']['request_header'])) {
            $this->last_request['headers'] = $response['headers']['request_header'];
        }

        if (!empty($response['body'])) {
            $d = json_decode($response['body'], true);

            if (isset($d['error'])) {
                $this->last_error = sprintf('%d: %s', $d['error']['code'], $d['error']['message']);
                return false;
            }

            if (floor($response['headers']['http_code'] / 100) >= 4) {
                $this->setErrorState($response);
                return false;
            }

            $this->request_successful = true;
            return $d;
        }

        if (isset($response['headers'])) {
            $this->last_response['headers'] = $response['headers'];
        }

        $this->setErrorState($response);
        return false;
    }

    /**
     * Set all the errors in the response.
     * @param array $response The response from the curl request
     */
    private function setErrorState($response)
    {
        $this->last_response['headers'] = $response['headers'];
        $this->last_response['body'] = $response['body'];

        $body = json_decode($response['body'], true);

        if (!$body) {
            $this->last_error = $response['headers']['http_code'] . ': ' . $response['body'];
        } else {

            $this->last_error = $response['headers']['http_code'] . ': ' . $body['detail'];

            if (isset($body['errors'])) {
                $this->last_error .= '. ' . $body['title'] . ': ';

                if (is_array($body['errors'])) {
                    foreach ($body['errors'] as $error) {
                        $this->last_error .= ' ' . $error['field'] . ': ' . $error['message'];
                    }
                } else {
                    $this->last_error .= ' ' . $body['errors'];
                }

            }
        }

        $this->request_successful = false;
    }

    /**
     * Check if the email address passed is in a valid format.
     * @param string $email
     * @return bool
     */
    public function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}