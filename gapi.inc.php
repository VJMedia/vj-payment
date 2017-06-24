<?php
class VJPayment_GAPI_Controller{
	
	public $client;	public $service; public $timeshift; public $error_timeout; private $managequota; private $gadwp;
	private $access = array( '65556128781.apps.googleusercontent.com', 'Kc7888wgbc_JbeCmApbFjnYpwE' );

	public function get_timeouts( $daily ) {
		$local_time = time() + $this->timeshift;
		if ( $daily ) {
			$nextday = explode( '-', date( 'n-j-Y', strtotime( ' +1 day', $local_time ) ) );
			$midnight = mktime( 0, 0, 0, $nextday[0], $nextday[1], $nextday[2] );
			return $midnight - $local_time;
		} else {
			$nexthour = explode( '-', date( 'H-n-j-Y', strtotime( ' +1 hour', $local_time ) ) );
			$newhour = mktime( $nexthour[0], 0, 0, $nexthour[1], $nexthour[2], $nexthour[3] );
			return $newhour - $local_time;
		}
	}
	
	public function gapi_errors_handler() {
		$errors = GADWP_Tools::get_cache( 'gapi_errors' );
		var_dump($errors);
		if ( $errors === false || ! isset( $errors[0] ) ) { // invalid error
			return false;
		}
		if ( isset( $errors[1][0]['reason'] ) && ( $errors[1][0]['reason'] == 'invalidCredentials' || $errors[1][0]['reason'] == 'authError' || $errors[1][0]['reason'] == 'insufficientPermissions' || $errors[1][0]['reason'] == 'required' || $errors[1][0]['reason'] == 'keyExpired' ) ) {
			$this->reset_token( false );
			return true;
		}
		if ( isset( $errors[1][0]['reason'] ) && ( $errors[1][0]['reason'] == 'userRateLimitExceeded' || $errors[1][0]['reason'] == 'quotaExceeded' ) ) {
			if ( $this->gadwp->config->options['api_backoff'] <= 5 ) {
				usleep( rand( 100000, 1500000 ) );
				$this->gadwp->config->options['api_backoff'] = $this->gadwp->config->options['api_backoff'] + 1;
				$this->gadwp->config->set_plugin_options();
				return false;
			} else {
				return true;
			}
		}
		
		if ( $errors[0] == 400 || $errors[0] == 401 || $errors[0] == 403 ) {
			return true;
		}
		return false;
	}
	
	private function handle_corereports( $projectId, $from, $to, $metrics, $options ) {
		
		try {
			if ( $from == "today" ) {
				$timeouts = 0;
			} else {
				$timeouts = 1;
			}
			
			
			/*if ( $this->gapi_errors_handler() ) {
				return - 23;
			}*/
			
			
			$data = $this->service->data_ga->get( 'ga:' . $projectId, $from, $to, $metrics, $options );
			$this->gadwp->config->options['api_backoff'] = 0;
			$this->gadwp->config->set_plugin_options();
			
		} catch ( Google_Service_Exception $e ) {
			GADWP_Tools::set_cache( 'last_error', date( 'Y-m-d H:i:s' ) . ': ' . esc_html( "(" . $e->getCode() . ") " . $e->getMessage() ), $this->error_timeout );
			GADWP_Tools::set_cache( 'gapi_errors', array( $e->getCode(), (array) $e->getErrors() ), $this->error_timeout );
			return $e->getCode();
		} catch ( Exception $e ) {
			GADWP_Tools::set_cache( 'last_error', date( 'Y-m-d H:i:s' ) . ': ' . esc_html( $e ), $this->error_timeout );
			return $e->getCode();
		}
		
		if ( $data->getRows() > 0 ) {
			return $data;
		} else {
			return - 21;
		}
	}
	
	private function map( $map ) {
		return str_ireplace( 'map', chr( 112 ), $map );
	}
		
	private function set_error_timeout() {
		$midnight = strtotime( "tomorrow 00:00:00" ); // UTC midnight
		$midnight = $midnight + 8 * 3600; // UTC 8 AM
		$this->error_timeout = $midnight - time();
		return;
	}
	
	public function __construct() {
		$this->gadwp = GADWP();

		include_once ( GADWP_DIR . 'tools/autoload.php' );
		$config = new Google_Config();
		$config->setCacheClass( 'Google_Cache_Null' );
		if ( function_exists( 'curl_version' ) ) {
			$curlversion = curl_version();
			if ( isset( $curlversion['version'] ) && ( version_compare( PHP_VERSION, '5.3.0' ) >= 0 ) && version_compare( $curlversion['version'], '7.10.8' ) >= 0 && defined( 'GADWP_IP_VERSION' ) && GADWP_IP_VERSION ) {
				$config->setClassConfig( 'Google_IO_Curl', array( 'options' => array( CURLOPT_IPRESOLVE => GADWP_IP_VERSION ) ) );
				// Force CURL_IPRESOLVE_V4 or CURL_IPRESOLVE_V6
			}
		}
		$this->client = new Google_Client( $config );
		$this->client->setScopes( 'https://www.googleapis.com/auth/analytics.readonly' );
		$this->client->setAccessType( 'offline' );
		$this->client->setApplicationName( 'Google Analytics Dashboard' );
		$this->client->setRedirectUri( 'urn:ietf:wg:oauth:2.0:oob' );
		$this->set_error_timeout();
		$this->managequota = 'u' . get_current_user_id() . 's' . get_current_blog_id();
		$this->access = array_map( array( $this, 'map' ), $this->access );
		if ( $this->gadwp->config->options['ga_dash_userapi'] ) {
			$this->client->setClientId( $this->gadwp->config->options['ga_dash_clientid'] );
			$this->client->setClientSecret( $this->gadwp->config->options['ga_dash_clientsecret'] );
			$this->client->setDeveloperKey( $this->gadwp->config->options['ga_dash_apikey'] );
		} else {
			$this->client->setClientId( $this->access[0] );
			$this->client->setClientSecret( $this->access[1] );
		}
		$this->service = new Google_Service_Analytics( $this->client );
		if ( $this->gadwp->config->options['ga_dash_token'] ) {
			$token = $this->gadwp->config->options['ga_dash_token'];
			if ( $token ) {
				try {
					$this->client->setAccessToken( $token );
					$gadwp->config->options['ga_dash_token'] = $this->client->getAccessToken();
				} catch ( Google_IO_Exception $e ) {
					GADWP_Tools::set_cache( 'ga_dash_lasterror', date( 'Y-m-d H:i:s' ) . ': ' . esc_html( $e ), $this->error_timeout );
				} catch ( Google_Service_Exception $e ) {
					GADWP_Tools::set_cache( 'ga_dash_lasterror', date( 'Y-m-d H:i:s' ) . ': ' . esc_html( "(" . $e->getCode() . ") " . $e->getMessage() ), $this->error_timeout );
					GADWP_Tools::set_cache( 'ga_dash_gapi_errors', array( $e->getCode(), (array) $e->getErrors() ), $this->error_timeout );
					$this->reset_token();
				} catch ( Exception $e ) {
					GADWP_Tools::set_cache( 'ga_dash_lasterror', date( 'Y-m-d H:i:s' ) . ': ' . esc_html( $e ), $this->error_timeout );
					$this->reset_token();
				}
				$this->gadwp->config->set_plugin_options();
			}
		}
	}
		
	public function scan( $projectId, $from=1,$max_result=100,$page=1) {
		if(is_numeric($from)){ $from = $from."daysAgo"; }
		$from="2012-01-01";
		$start_index=(($page-1)*$max_result)+1;
		//$ga->requestReportData(ga_profile_id,array('pageviews','visits','entrances','uniquePageviews'),'-visits',$filter,'2012-01-01',date('Y-m-d'),$start,result_per_page);
		
		$options = array( 'dimensions' => 'ga:pageTitle,ga:pagePath', /*'quotaUser' => $this->managequota . 'p' . $projectId,*/ 'sort' => '-ga:pageviews', 'max-results' => $max_result, 'filters' => 'ga:pagePath=~/articles/*/*/*/*', 'start-index'=>$start_index);
		//var_dump($this->gadwp->config->options);	
		$data = $this->handle_corereports( $projectId, $from, 'yesterday', 'ga:pageViews', $options );
		
		if ( is_numeric( $data ) ) { return $data; }
		$gadwp_data=[]; foreach ( $data->getRows() as $row ){
				$gadwp_data[] = $row;
		}

		return $gadwp_data;
	}
}
?>