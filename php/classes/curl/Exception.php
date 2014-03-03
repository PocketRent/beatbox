<?hh

namespace beatbox\curl;
use \beatbox\errors\Exception;

class CurlException extends Exception {
	public function getEventPrefix() : \string {
		return 'curl';
	}
}

class CurlHandleException extends CurlException {
	public function __construct(\resource $ch) {

		$curl_message = curl_error($ch);
		$curl_errno = curl_errno($ch);

		$message = sprintf("cURL Error: %s (%d)", $curl_message, $curl_errno);

		parent::__construct($message, $curl_errno);
	}
}

class CurlMultiException extends CurlException {

	private static ImmMap<int, string> $errorMessage = ImmMap {
		CURLM_OK => 'Ok',
		CURLM_BAD_HANDLE => 'Passed-in multi handle was not valid',
		CURLM_BAD_EASY_HANDLE => 'Passed-in handle was not valid',
		CURLM_OUT_OF_MEMORY => 'Out of memory',
		CURLM_INTERNAL_ERROR => 'Internal Error',
		CURLM_BAD_SOCKET => 'Invalid Socked',
		CURLM_UNKNOWN_OPTION => 'Unknown Option',
		CURLM_ADDED_ALREADY => 'Handle already added'
	};

	public function __construct(int $curlm_code) {

		invariant($curlm_code > 0, '$curlm_code is not an error value');

		$curlm_message = self::$errorMessage[$curlm_code];
		$message = sprintf("cURL Multi Error: %s (%d)", $curlm_message, $curlm_code);

		parent::__construct($message, $curlm_code);
	}
}
