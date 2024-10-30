<?php
/**
 * Chabok remote API for tracking events
 * and attributing.
 *
 * @package ChabokIO
 * @subpackage API
 */

class Chabok_API
{
    /**
     * @var string API authorization key.
     */
    private $api_key;

    /**
     * @var string Endpoint prefix.
     */
    private $endpoint_prefix = 'sandbox';

    /**
     * Sets up the class for using.
     *
     * @return void
     */
    public function __construct()
    {
        global $chabok_options;

        if (!isset($chabok_options['api_key'])) {
            // missing api key.
            return false;
        }

        $this->api_key = $chabok_options['api_key'];

        if (isset($chabok_options['app_id'])) {
            if ($chabok_options['env'] === 'prod') {
                $this->endpoint_prefix = $chabok_options['app_id'];
            }
        }
    }

    /**
     * Sends a request to Chabok endpoint.
     *
     * @param string $endpoint REST endpoint.
     * @param mixed $data Data to send.
     * @param string $method HTTP verb to use.
     */
    private function request($endpoint, $data = array(), $method = 'POST')
    {
        $url = sprintf(URL_CHABOK_API . $this->api_key, $endpoint);
        $payload = wp_json_encode($data);
        $headers = array('Content-Type' => 'application/json',
            'Content-Length' => strlen($payload),
            'X-Access-Token' => $this->api_key,
        );
        $request_params = array('body' => $payload,
            'headers' => $headers,
            'method' => 'POST',
            'timeout' => 5,
            // 'sslverify'=>false,
            'redirection' => 5,
            'Expect' => '');
        $response = wp_remote_post($url, $request_params);
        $log = "request payload is : " . print_r($payload, true) . " - response is " . print_r($response['body'], true);
        chabok_log($log, "http");
        return $response;
    }

    /**
     * Track an event for the specified installation and user.
     *
     * @param string $event_name Event name
     * @param string $user_id User id
     * @param string $installation_id Device (installation) id
     * @param array $event_data Additional data to be sent
     * @return mixed Tracking response
     */
    public function track_event($event_name, $user_id, $installation_id, $event_data = array())
    {
        if (!$installation_id || !$user_id) {
            return false;
        }
        $log = "Sending event:$event_name for user_id:$user_id, installation_id:$installation_id, event_data:" . print_r($event_data, true);
        chabok_log($log, "event");

        return $this->request(
            '/installations/track',
            array(
                'userId' => $user_id,
                'installationId' => $installation_id,
                'eventName' => $event_name,
                'eventData' => $event_data, // array(
                'deviceType' => 'web',
                'sessionId' => session_id(),
                'createdAt' => microtime(),
            )
        );
    }
}
