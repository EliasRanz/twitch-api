<?php

namespace Twitch;

use Twitch\TwitchTV_Curl_Cache\TwitchTV_Curl_Cache;

/**
 * Class Twitch
 * @package TwitchTV
 */
class Twitch {
    /**
     * The base url for the api.
     * @var string
     */
    private $_kraken_url = "https://api.twitch.tv/kraken/";
    /**
     * The url for the pubsub websocket.
     * @var string
     */
    private $_pubsub_url = "wss://pubsub-edge.twitch.tv";
    /**
     * The version of the API that we should be using.
     * @var int
     */
    private $_api_version = 5;
    /**
     * The client Id for the given application
     * @var string
     */
    private $_client_id;
    /**
     * The client secret for the given application
     * @var string
     */
    private $_client_secret;
    /**
     * The redirect URI, This is required for oauth workflow.
     * @var string
     */
    private $_redirect_url;
    /**
     * The users access token for authenticated requests
     * @var string
     */
    private $_access_token;
    /**
     * Gets the user id of the stream.
     * @var
     */
    private $_user_id;
    /**
     * These are the scopes to be requested by the application
     * @var array
     */
    private $_scope_array = array('user_read','channel_read','chat_login','user_follows_edit','channel_editor','channel_commercial','channel_check_subscription', 'channel_subscriptions');
    /**
     * The username for the given channel.
     * @var
     */
    private $_username;
    /**
     * The channel data for the given account.
     * @var null
     */
    private $_channel_data = null;
    /**
     * A curl cache class instance to limit api requests.
     * @var TwitchTV_Curl_Cache
     */
    private $_curl_cache;

    /**
     * Twitch constructor.
     * @param $client_id
     * @param $client_secret
     * @param $redirect_url
     */
    public function __construct($client_id, $client_secret, $redirect_url) {
        $this->_curl_cache = new TwitchTV_Curl_Cache();
        $this->_client_id = $client_id;
        $this->_client_secret = $client_secret;
        $this->_redirect_url = $redirect_url;
    }

    /**
     * Gets the authentication url.
     * @return string
     */
    public function authenticate() {
        $return = "";
        $len = count($this->_scope_array);
		for($i = 0; $i < $len; $i++) {
			if($len - ($i + 1) === 0) {
				$return .= $this->_scope_array[$i];
			} else {
                $return .= $this->_scope_array[$i] . "+";
            }
        }
        return $this->_kraken_url . 'oauth2/authorize?response_type=code&client_id=' . $this->_client_id.'&redirect_uri=' . $this->_redirect_url . '&scope=' . $return;
    }

    /**
     * Gets the access token used for all API requests
     * @return string
     */
    private function get_access_token() {
        return $this->_access_token;
    }

    /**
     * Gets the access token and sets the $_access_token property
     * This is REQUIRED for many of the functions.
     * @param $code string      :This is the code that Twitch passes onto the $_redirect_url. This would often be pulled via a $_GET['code'] request.
     */
    public function set_access_token($code) {
        $response = $this->run_curl($this->_kraken_url."oauth2/token?client_id=" . $this->_client_id."&client_secret=".$this->_client_secret."&code=".$code."&grant_type=authorization_code&redirect_uri=".$this->_redirect_url,
			array(CURLOPT_FOLLOWLOCATION => FALSE,CURLOPT_RETURNTRANSFER => TRUE,CURLOPT_POST => 1));
        $this->_access_token = $response->access_token;
    }

    /**
     * Gets the authenticated users' username.
     */
    public function get_authenticated_user() {
        $response = $this->run_curl($this->_kraken_url . "user",
			array(CURLOPT_RETURNTRANSFER => 1));

        if(isset($response->error)) {
            die($response->error);
        } else {
            $this->_username = $response->name;
            $this->_user_id = $response->_id;
        }

        return $this->_username;
    }

    /**
     * Makes sure that we have a valid stream
     * @return bool:        true => valid Twitch channel, false => invalid Twitch channel
     */
    public function validate_stream() {
            $response = $this->run_curl($this->_kraken_url . 'users/' . $this->get_userid() . '?client_id' . $this->_client_id . '&api_version=' . $this->_api_version,
				array(CURLOPT_RETURNTRANSFER => 1));
            if (isset($response->error)) {
                return false;
            } else {
                return true;
            }
    }

	/**
	 * Sets a $_user_id for a given channel
	 * @param mixed $channel
	 * @return mixed
	 */
    public function set_userid($channel = null) {
    	if(!isset($this->_user_id)) {
			$response = $this->run_curl($this->_kraken_url . 'users?login=' . $channel, array(CURLOPT_RETURNTRANSFER => 1));
			if (!empty($this->_username)) {
				$return = json_decode($response, true);
				$this->_user_id = $return['users'][0]->_id;
			} else {
				die('Failed to get user_id');
			}
		}
    }

    /**
     * Gets the users' Twitch Id via the $_user_id property
     * @return mixed    :Users Twitch Id
     */
    private function get_userid() {
        if(isset($this->_user_id)) {
            return $this->_user_id;
        } else {
            $this->set_userid();
        }
    }

	/**
	 * Gets the channel data for a given stream, assumes that you have a twitch user id
	 * @return array
	 */
	public function get_channel() {
		$result = $this->run_curl($this->_kraken_url . 'channels/' . $this->_user_id, array(CURLOPT_RETURNTRANSFER => 1));

		return array(
			'id' => $result->_id,
			'display_name' => $result->display_name,
			'status' => $result->status,
			'game' => $result->game,
			'banner' => $result->video_banner,
			'logo' => $result->logo,
			'followers' => $result->followers,
			'views' => $result->views
		);
	}

	/**
	 * Gets the subscribers for the authenticated stream, assumes that you have a twitch user id
	 * @return array
	 */
	public function get_channel_subscribers() {
		return $this->run_curl($this->_kraken_url . 'channels/' . $this->_user_id . '/subscriptions', array(CURLOPT_RETURNTRANSFER => 1));
	}

	public function get_stream_stats($id) {
		$result = $this->run_curl($this->_kraken_url. 'streams/' . $id, array(CURLOPT_RETURNTRANSFER => 1));

		if(isset($result->error) || $result->stream === null) {
			return array('is_live' => false);
		} else {
			return array(
				'viewers' => $result->stream->viewers,
				'created_at' => $result->stream->created_at,
				'followers' => $result->channel->followers,
				'is_live' => true
			);
		}
	}

    /**
     * Runs a curl command and returns a json response.
     * @param $url      :The url to make the curl request on
     * @param $options  :The curl options to use for the request
     * @return mixed    :The json response
     */
    private function run_curl($url, $options) {
    	$required_params = array();
		array_push($required_params, 'Client-Id: '. $this->_client_id);
		array_push($required_params,'Accept: application/vnd.twitchtv.v'. $this->_api_version .'+json');
		if(isset($this->_access_token)) {
			array_push($required_params, 'Authorization: OAuth ' . $this->get_access_token());
		}
    	if(isset($options[CURLOPT_HEADER])) {
			$options[CURLOPT_HEADER] = array_push($required_params, $options[CURLOPT_HEADER]);
		} else {
			$options[CURLOPT_HTTPHEADER] = $required_params;
		}

		$ch = curl_init($url);
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        curl_close($ch);
        if(!$response) {
            die('Error: "' . curl_error($ch) . '" - Code: "' . curl_errno($ch));
        } else {
            return json_decode($response);
        }
    }

    protected function debug($object) {
    	echo "<pre>" .print_r($object,true) . "</pre>";
	}



}
