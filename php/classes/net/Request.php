<?hh

namespace beatbox\net;

newtype BoolOpt   = \int;
newtype IntOpt    = \int;
newtype StringOpt = \int;

final class RequestBuilder {
	private ?\resource $handle;

	private Map<\string, \string> $headers = Map {};
	private Vector<\string> $extra_headers = Vector {};
	private int $contentLength = 0;

	private function __construct() {
		$this->handle = curl_init();
		$this->setStringOption(ReqOptions::ENCODING, "UTF-8");
	}

	/**
	 * Create a new builder
	 */
	public static function create() {
		return new RequestBuilder();
	}

	/**
	 * Set the value of a boolean option, defaults to setting it to true
	 */
	public function setBoolOption(BoolOpt $option, \bool $value=true) : this {
		$this->setopt($option, $value);
		return $this;
	}

	/**
	 * Sets the given boolean option to false
	 */
	public function unsetBoolOption(BoolOpt $option) : this {
		return $this->setBoolOption($option, false);
	}

	/**
	 * Sets the value of the given string option
	 */
	public function setStringOption(StringOpt $option, ?\string $value) : this {
		$this->setopt($option, $value);
		return $this;
	}

	/**
	 * Sets the value of the given integer option
	 */
	public function setIntOption(IntOpt $option, \int $value) : this {
		$this->setopt($option, $value);
		return $this;
	}

	/**
	 * Sets the URL you intend to request
	 */
	public function setURL(\string $url) : this {
		return $this->setStringOption(ReqOptions::URL, $url);
	}

	/**
	 * Sets the request to be a POST request and sets the data to be sent to
	 * the given data
	 */
	public function POST(KeyedTraversable<\string, \string> $data) : this {
		$post_data = url_query_string($data);

		return $this->setRequestMethod('POST')
			->setStringOption(ReqOptions::POSTFIELDS, $post_data);
	}

	/**
	 * Sets the request to be a PUT request and sets the given data to be uploaded
	 */
	public function PUT(\string $data) : this {
		$file = fopen('php://memory', 'w+');
		fwrite($file, $data);
		rewind($file);
		return $this->PUTFile($file, strlen($data));
	}

	/**
	 * Sets the request to be PUT request and sets the given file to be uploaded.
	 *
	 * Pass along the optional $size parameter if it is not possible to get the size
	 * from $file
	 */
	public function PUTFile(\resource $file, ?\int $size = null) : this {
		if ($size == null) {
			$stat = @fstat($file);
			$size = $stat ? $stat['size'] : null;
		}
		if ($size == null) {
			throw new beatbox\errors\Exception("Cannot find out size of given file".
				" and none given");
		}

		$this->setRequestMethod('PUT');
		$this->setopt(CURLOPT_INFILE, $file);
		return $this->setIntOption(ReqOptions::INFILESIZE, $size);
	}

	/**
	 * Sets the headers for the request to the given set.
	 *
	 * The keys are the header names, the values are the header values.
	 */
	public function setHeaders(KeyedTraversable<\string, \string> $headers) : this {
		$this->headers = new Map($headers);
		return $this;
	}

	/**
	 * Adds all the given headers. Existing headers with the same name will be overwritten
	 */
	public function addHeaders(KeyedTraversable<\string, \string> $headers) : this {
		$this->headers->setAll($headers);
		return $this;
	}

	/**
	 * Sets the value of the given header.
	 *
	 * $value should be a type that can be converted to a string
	 */
	public function setHeader(\string $header, \mixed $value) : this {
		$this->headers[$header] = (string)$value;
		return $this;
	}

	/**
	 * Removes the given header
	 */
	public function removeHeader(\string $header) : this {
		$this->headers->remove($header);
		return $this;
	}

	/**
	 * Adds a custom header.
	 *
	 * This allows you to add a custom header line, which can be useful for when
	 * you need the same header name multiple times with different values
	 */
	public function addCustomHeader(\string $header) : this {
		$this->extra_headers->add($header);
		return $this;
	}

	/**
	 * Sets the request method.
	 *
	 * This method can cause the built request to be invalid if you do not have
	 * the appropriate options set
	 */
	public function setRequestMethod(\string $method) : this {
		switch ($method) {
		case 'GET':
			return $this->setBoolOption(ReqOptions::HTTPGET, true);
		case 'POST':
			return $this->setBoolOption(ReqOptions::POST, true);
		case 'PUT':
			return $this->setBoolOption(ReqOptions::PUT, true);
		default:
			return $this->setStringOption(ReqOptions::CUSTOMREQUEST, $method);
		}
	}

	/**
	 * Builds the actual request.
	 *
	 * This makes a copy of the existing handle, leaving the builder available
	 * for further changes without affecting the Request object
	 */
	public function get() : Request {
		$handle = nullthrows($this->handle);
		$new_handle = curl_copy_handle($handle);

		$headers = [];
		foreach ($this->headers as $k => $v) {
			$headers[] = trim("$k: $v");
		}

		foreach ($this->extra_headers as $v) {
			$headers[] = trim($v);
		}

		if (!curl_setopt($new_handle, CURLOPT_HTTPHEADER, $headers))
			throw new CurlHandleException($new_handle);

		return new Request($new_handle);
	}

	public function close() {
		if (is_resource($this->handle)) {
			curl_close($this->handle);
			$this->handle = null;
		}
	}

	private function setopt(\int $option, ?\mixed $value) : \void {
		$handle = nullthrows($this->handle);
		if (!curl_setopt($handle, $option, $value))
			throw new CurlHandleException($handle);
	}

	public function __destruct() {
		$this->close();
	}
}

final class Request {
	const int POLL_TIME = 50;//ms

	private ?\resource $handle;

	private ?resource $stderr;
	private ?\string $stderrData;

	private Map<\string, \string> $headers = Map {};
	private ?\string $data = null;
	private \bool $done = false;

	private ?\string $effectiveURL;
	private ?\int $httpCode;
	private ?\int $filetime;
	private ?\float $totalTime;
	private ?\float $lookupTime;
	private ?\float $connectTime;
	private ?\float $pretransferTime;
	private ?\float $startTransferTime;
	private ?\int $redirectCount;
	private ?\float $redirectTime;
	private ?\int $sizeUpload;
	private ?\int $sizeDownload;
	private ?\float $speedUpload;
	private ?\float $speedDownload;
	private ?\int $requestSize;

	private static Map<\string, Request> $requests = Map {};
	private static ?\resource $multi_handle = null;

	/**
	 * Returns a RequestBuilder object that can be used to build a Request.
	 *
	 * If $url is given, the request's url is set
	 */
	public static function build(?\string $url = null) : RequestBuilder {
		$builder = RequestBuilder::create();
		if ($url) $builder->setURL($url);
		return $builder;
	}

	public function __construct(\resource $handle) {
		$this->handle = $handle;

		if (in_dev()) {
			$this->stderr = fopen("php://memory", 'r+');
			$this->setopt(CURLOPT_STDERR, $this->stderr);

			$this->setopt(CURLOPT_VERBOSE, true);
		} else {
			$this->setopt(CURLOPT_VERBOSE, false);
		}

		$this->setopt(CURLOPT_RETURNTRANSFER, true);
		$this->setopt(CURLOPT_HEADER, false);
		$this->setopt(CURLOPT_HEADERFUNCTION, inst_meth($this, '_handleHeader'));
		$this->setopt(CURLOPT_WRITEFUNCTION, inst_meth($this, '_handleData'));

		$mh = self::getMultiHandle();
		self::$requests[(string)$handle] = $this;

		$code = curl_multi_add_handle($mh, $handle);
		if ($code > 0)
			throw new CurlMultiException($code);


		$running = false;
		curl_multi_exec($mh, $running);
		if (!$running) {
			$this->done = true;
			self::removeRequest($this);
		}
	}

	/**
	 * Executes the request.
	 *
	 * This is asynchronous, so use `await` or `wait()` to wait for the
	 * request to finish.
	 */
	public async function exec() : \Awaitable<this> {
		if ($this->done) return $this;

		while (!$this->done) {
			self::run();
			await \SleepWaitHandle::create(self::POLL_TIME*1000);
		}

		self::removeRequest($this);
		return $this;
	}

	private static function run() {
		$mh = self::getMultiHandle();
		$running = null;
		do {
			$code = curl_multi_exec($mh, $running);
		} while ($code == CURLM_CALL_MULTI_PERFORM);

		if ($code > 0) throw new CurlMultiException($code);

		if (curl_multi_select($mh) != -1) {
			do {
				$code = curl_multi_exec($mh, $running);
			} while ($code == CURLM_CALL_MULTI_PERFORM);
		}

		while ($done = curl_multi_info_read($mh)) {
			assert($done['msg'] == CURLMSG_DONE);

			if ($done['result'] != CURLE_OK)
				throw new CurlHandleException($done['handle']);

			$ch = $done['handle'];
			$req = self::$requests->get((string)$ch);
			if ($req) {
				$req->done = true;
				$req->effectiveURL		= (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
				$req->httpCode			= ( int  )curl_getinfo($ch, CURLINFO_HTTP_CODE);
				$req->filetime			= ( int  )curl_getinfo($ch, CURLINFO_FILETIME);

				$req->totalTime			= (float )curl_getinfo($ch, CURLINFO_TOTAL_TIME);
				$req->lookupTime		= (float )curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME);
				$req->connectTime		= (float )curl_getinfo($ch, CURLINFO_CONNECT_TIME);
				$req->pretransferTime	= (float )curl_getinfo($ch, CURLINFO_PRETRANSFER_TIME);
				$req->startTransferTime	= (float )curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME);

				$req->redirectCount		= ( int  )curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
				$req->redirectTime		= (float )curl_getinfo($ch, CURLINFO_REDIRECT_TIME);

				$req->sizeUpload		= ( int  )curl_getinfo($ch, CURLINFO_SIZE_UPLOAD);
				$req->sizeDownload		= ( int  )curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
				$req->speedUpload		= (float )curl_getinfo($ch, CURLINFO_SPEED_UPLOAD);
				$req->speedDownload		= (float )curl_getinfo($ch, CURLINFO_SPEED_DOWNLOAD);

				$req->requestSize		= ( int  )curl_getinfo($ch, CURLINFO_REQUEST_SIZE);
			}
		}
	}

	/**
	 * Gets the value of the given header, or null if it doesn't exist.
	 * Returns null if the request has not been executed.
	 *
	 * $name is case-insensitive
	 */
	public function getHeader(\string $name) : ?\string {
		if (!$this->done) return null;
		$name = strtolower($name);
		return $this->headers->get($name);
	}

	/**
	 * Gets the returned body of the request, or null if there isn't one.
	 * Returns null if the request has not been executed.
	 */
	public function getBody() : ?\string {
		if (!$this->done) return null;
		return $this->data;
	}

	/**
	 * Gets the returned HTTP code, or 0 if the request has not been executed.
	 */
	public function getHTTPCode() : \int {
		return $this->httpCode ? $this->httpCode : 0;
	}

	/**
	 * Gets the effective URL used for the request.
	 *
	 * Returns null if the request has not been executed.
	 */
	public function getEffectiveURL() : ?\string {
		return $this->effectiveURL;
	}

	/**
	 * Gets the remote time of the retrived document.
	 *
	 * Returns null if the time is not available.
	 */
	public function getFiletime() : ?\DateTime {
		if ($this->filetime == null || $this->filetime == -1)
			return null;
		else
			return new \DateTime('@'.$this->filetime);
	}

	/**
	 * Gets the total time the request took, in seconds.
	 *
	 * Returns -0.0 if the request has not been executed.
	 */
	public function getTotalTime() : \float {
		return $this->totalTime ? $this->totalTime : -0.0;
	}

	public function getVerboseOutput() : ?\string {
		if ($this->stderrData == null && $this->stderr != null) {
			rewind($this->stderr);
			$this->stderrData = stream_get_contents($this->stderr);
			fclose($this->stderr);
			$this->stderr = null;
		}

		return $this->stderrData;
	}

	/**
	 * INTERNAL FUNCTION
	 * This function is public because it is used as a callback
	 */
	public function _handleHeader(\resource $ch, \string $header) {
		$len = strlen($header);
		$header = trim($header);

		$colon = strpos($header, ':');
		if ($colon > 0) {
			$headerName = strtolower(substr($header, 0, $colon));
			$headerValue = preg_replace('/^\W+/', '', substr($header, $colon));

			$this->headers[$headerName] = $headerValue;
		}

		return $len;
	}

	/**
	 * INTERNAL FUNCTION
	 * This function is public because it is used as a callback
	 */
	public function _handleData(\resource $ch, \string $data) {
		if ($this->data == null)
			$this->data = $data;
		else
			$this->data .= $data;

		return strlen($data);
	}

	private function setopt(\int $option, \mixed $value) : void {
		$handle = nullthrows($this->handle);
		if (!curl_setopt($handle, $option, $value))
			throw new CurlHandleException($handle);
	}

	private static function removeRequest(Request $r) {
		$handle = $r->handle;

		if ($handle) {
			$key = (string)$handle;

			$mh = self::getMultiHandle();

			self::$requests->removeKey($key);

			$r->handle = null;

			curl_multi_remove_handle($mh, $handle);
			curl_close($handle);
		}
	}

	private static function getMultiHandle() : \resource {
		if (self::$multi_handle === null)
			self::$multi_handle = curl_multi_init();

		return self::$multi_handle;
	}

	public function close() {
		if ($this->handle) {
			if (self::$requests->containsKey((string)$this->handle)) {
				curl_multi_remove_handle(self::getMultiHandle(), $this->handle);
				curl_close($this->handle);
				$this->handle = null;
			}
		}
		if ($this->stderr) {
			if ($this->stderrData == null) $this->getVerboseOutput();
			fclose($this->stderr);
			$this->stderr = null;
		}
	}

	public function __destruct() {
		$this->close();
	}
}

class ReqOptions {
	const BoolOpt   AUTOREFERER                 = CURLOPT_AUTOREFERER;
	const BoolOpt   BINARYTRANSFER				= CURLOPT_BINARYTRANSFER;
	const IntOpt    BUFFERSIZE					= CURLOPT_BUFFERSIZE;
	const StringOpt CAINFO						= CURLOPT_CAINFO;
	const StringOpt CAPATH						= CURLOPT_CAPATH;
	const IntOpt    CLOSEPOLICY					= CURLOPT_CLOSEPOLICY;
	const IntOpt    CONNECTTIMEOUT				= CURLOPT_CONNECTTIMEOUT;
	const IntOpt    CONNECTTIMEOUT_MS			= CURLOPT_CONNECTTIMEOUT;
	const StringOpt COOKIE						= CURLOPT_COOKIE;
	const StringOpt COOKIEFILE					= CURLOPT_COOKIEFILE;
	const StringOpt COOKIEJAR					= CURLOPT_COOKIEJAR;
	const BoolOpt   COOKIESESSION				= CURLOPT_COOKIESESSION;
	const BoolOpt   CRLF						= CURLOPT_CRLF;
	const StringOpt CUSTOMREQUEST				= CURLOPT_CUSTOMREQUEST;
	const IntOpt    DNS_CACHE_TIMEOUT			= CURLOPT_DNS_CACHE_TIMEOUT;
	const BoolOpt   DNS_USE_GLOBAL_CACHE		= CURLOPT_DNS_USE_GLOBAL_CACHE;
	const StringOpt EGDSOCKET					= CURLOPT_EGDSOCKET;
	const StringOpt ENCODING					= CURLOPT_ENCODING;
	const BoolOpt   FAILONERROR					= CURLOPT_FAILONERROR;
	const BoolOpt   FILETIME					= CURLOPT_FILETIME;
	const BoolOpt   FOLLOWLOCATION				= CURLOPT_FOLLOWLOCATION;
	const BoolOpt   FORBID_REUSE				= CURLOPT_FORBID_REUSE;
	const BoolOpt   FRESH_CONNECT				= CURLOPT_FRESH_CONNECT;
	const BoolOpt   FTPAPPEND					= CURLOPT_FTPAPPEND;
	const BoolOpt   FTPLISTONLY					= CURLOPT_FTPLISTONLY;
	const StringOpt FTPPORT						= CURLOPT_FTPPORT;
	const IntOpt    FTPSSLAUTH					= CURLOPT_FTPSSLAUTH;
	const BoolOpt   FTP_CREATE_MISSING_DIRS		= CURLOPT_FTP_CREATE_MISSING_DIRS;
	const BoolOpt   FTP_USE_EPRT				= CURLOPT_FTP_USE_EPRT;
	const BoolOpt   FTP_USE_EPSV				= CURLOPT_FTP_USE_EPSV;
	const BoolOpt   HEADER_						= CURLOPT_HEADER;
	const IntOpt    HTTPAUTH					= CURLOPT_HTTPAUTH;
	const BoolOpt   HTTPGET						= CURLOPT_HTTPGET;
	const BoolOpt   HTTPPROXYTUNNEL				= CURLOPT_HTTPPROXYTUNNEL;
	const IntOpt    HTTP_VERSION				= CURLOPT_HTTP_VERSION;
	const IntOpt    INFILESIZE					= CURLOPT_INFILESIZE;
	const StringOpt INTERFACE_					= CURLOPT_INTERFACE;
	const IntOpt    IPRESOLVE 					= CURLOPT_IPRESOLVE;
	const StringOpt KRB4LEVEL 					= CURLOPT_KRB4LEVEL;
	const IntOpt    LOW_SPEED_LIMIT				= CURLOPT_LOW_SPEED_LIMIT;
	const IntOpt    LOW_SPEED_TIME				= CURLOPT_LOW_SPEED_TIME;
	const IntOpt    MAXCONNECTS					= CURLOPT_MAXCONNECTS;
	const IntOpt    MAXREDIRS					= CURLOPT_MAXREDIRS;
	const BoolOpt   MUTE						= CURLOPT_MUTE;
	const BoolOpt   NETRC						= CURLOPT_NETRC;
	const BoolOpt   NOBODY						= CURLOPT_NOBODY;
	const BoolOpt   NOPROGRESS					= CURLOPT_NOPROGRESS;
	const BoolOpt   NOSIGNAL					= CURLOPT_NOSIGNAL;
	const IntOpt    PORT						= CURLOPT_PORT;
	const BoolOpt   POST						= CURLOPT_POST;
	const StringOpt POSTFIELDS					= CURLOPT_POSTFIELDS;
	const StringOpt PROXY						= CURLOPT_PROXY;
	const IntOpt    PROXYAUTH					= CURLOPT_PROXYAUTH;
	const IntOpt    PROXYPORT					= CURLOPT_PROXYPORT;
	const IntOpt    PROXYTYPE					= CURLOPT_PROXYTYPE;
	const StringOpt PROXYUSERPWD				= CURLOPT_PROXYUSERPWD;
	const BoolOpt   PUT							= CURLOPT_PUT;
	const StringOpt RANDOM_FILE					= CURLOPT_RANDOM_FILE;
	const StringOpt RANGE_						= CURLOPT_RANGE;
	const StringOpt REFERER						= CURLOPT_REFERER;
	const IntOpt    RESUME_FROM					= CURLOPT_RESUME_FROM;
	const BoolOpt   RETURNTRANSFER				= CURLOPT_RETURNTRANSFER;
	const StringOpt SSLCERT						= CURLOPT_SSLCERT;
	const StringOpt SSLCERTPASSWD				= CURLOPT_SSLCERTPASSWD;
	const StringOpt SSLCERTTYPE					= CURLOPT_SSLCERTTYPE;
	const StringOpt SSLENGINE					= CURLOPT_SSLENGINE;
	const StringOpt SSLENGINE_DEFAULT			= CURLOPT_SSLENGINE_DEFAULT;
	const StringOpt SSLKEY						= CURLOPT_SSLKEY;
	const StringOpt SSLKEYPASSWD				= CURLOPT_SSLKEYPASSWD;
	const StringOpt SSLKEYTYPE					= CURLOPT_SSLKEYTYPE;
	const IntOpt    SSLVERSION					= CURLOPT_SSLVERSION;
	const StringOpt SSL_CIPHER_LIST				= CURLOPT_SSL_CIPHER_LIST;
	const IntOpt    SSL_VERIFYHOST				= CURLOPT_SSL_VERIFYHOST;
	const BoolOpt   SSL_VERIFYPEER				= CURLOPT_SSL_VERIFYPEER;
	const IntOpt    TIMECONDITION				= CURLOPT_TIMECONDITION;
	const IntOpt    TIMEOUT						= CURLOPT_TIMEOUT;
	const IntOpt    TIMEOUT_MS					= CURLOPT_TIMEOUT;
	const IntOpt    TIMEVALUE					= CURLOPT_TIMEVALUE;
	const BoolOpt   TRANSFERTEXT				= CURLOPT_TRANSFERTEXT;
	const BoolOpt   UNRESTRICTED_AUTH			= CURLOPT_UNRESTRICTED_AUTH;
	const BoolOpt   UPLOAD						= CURLOPT_UPLOAD;
	const StringOpt URL							= CURLOPT_URL;
	const StringOpt USERAGENT					= CURLOPT_USERAGENT;
	const StringOpt USERPWD						= CURLOPT_USERPWD;
	const BoolOpt   VERBOSE						= CURLOPT_VERBOSE;

}
