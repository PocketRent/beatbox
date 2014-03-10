<?hh

namespace beatbox\net;

newtype SignMethod = \string;

class OAuth {

	const SignMethod PLAINTEXT = 'PLAINTEXT';
	const SignMethod HMAC_SHA1 = 'HMAC-SHA1';
	const SignMethod RSA_SHA1 = 'RSA-SHA1';

	private \string $requestMethod = 'GET';
	private SignMethod $signMethod = OAuth::PLAINTEXT;
	private Map<\string, \string> $oauthParameters = Map {};
	private ?\string $realm = null;

	private \string $scheme;
	private \string $host;
	private ?\int $port;
	private \string $path = '/';
	private Map<\string, \string> $queryParameters;

	private \bool $requestIsForm = false;
	private ?Map<\string, \string> $requestForm;
	private ?\string $requestBody;

	private ?\string $private_key;
	private \string $consumerSecret = '';
	private ?\string $tokenSecret;

	public function __construct(\string $url) {
		$url_params = parse_url($url);
		invariant(is_array($url_params), '$url is not a valid url');

		if (isset($url_params['scheme'])) {
			$this->scheme = strtolower($url_params['scheme']);
		} else {
			$this->scheme = 'http';
		}

		if (isset($url_params['host'])) {
			$this->host = strtolower($url_params['host']);
		} else {
			throw new NetException("\$url doesn't have a host?");
		}

		if (isset($url_params['port'])) {
			$this->port = (int)$url_params['port'];
		}

		if (isset($url_params['path'])) {
			$this->path = $url_params['path'];
		}

		if (isset($url_params['query']) && $url_params['query'] != '') {
			$query = [];
			parse_str($url_params['query'], $query);
			$this->queryParameters = Map::fromArray($query);
		} else {
			$this->queryParameters = Map {};
		}
	}

	public function build(RequestBuilder $builder) {
		$params = $this->getOAuthParams();

		$params['signature'] = $this->generateSignature();

		$auth_header = 'OAuth ';
		if ($this->realm) {
			$auth_header .= sprintf('realm="%s"',rawurlencode($this->realm));
			$first = false;
		} else {
			$first = true;
		}
		foreach ($params as $k => $v) {
			if (!$first) {
				$auth_header .= ',';
			}
			$auth_header .= sprintf('oauth_%s="%s"', $k, rawurlencode($v));
			$first = false;
		}

		$builder->setHeader('Authorization', $auth_header);

		$url = $this->baseStringURI();

		switch ($this->requestMethod) {
		case 'GET':
			$builder->setRequestMethod('GET')
				->setHeader('Content-Length', '0');
			break;
		case 'POST':
			if ($this->requestIsForm) {
				$builder->setHeader('Content-Type', 'application/x-www-form-urlencoded')
					->POST(nullthrows($this->requestForm));
			} else if ($this->requestBody) {
				$builder->setRequestMethod('POST')
					->setStringOption(ReqOptions::POSTFIELDS, nullthrows($this->requestBody));
			} else {
				$builder->setRequestMethod('POST')
					->setHeader('Content-Length', '0');
			}
			break;
		case 'PUT':
			if ($this->requestIsForm) {
				$builder->setRequestMethod('PUT')
					->setHeader('Content-Length', '0');
			} else if ($this->requestBody) {
				$builder->PUT($this->requestBody);
			}
			break;
		default:
			$this->setRequestMethod($this->requestMethod);
		}

		if ($this->queryParameters->count() > 0)
			$url = sprintf('%s?%s', $url, url_query_string($this->queryParameters));
		$builder->setURL($url);

	}

	public function setRequestMethod(\string $method) {
		$this->requestMethod = strtoupper($method);
	}

	public function setHMAC(\string $secret) {
		$this->signMethod = self::HMAC_SHA1;
		$this->consumerSecret = $secret;
	}

	public function setRSA(\string $private_key_file) {
		$this->signMethod = self::RSA_SHA1;
		$this->private_key = $private_key_file;
	}

	public function setBody(\string $body) {
		$this->requestIsForm = false;
		$this->requestBody = $body;
	}

	public function setForm(Map<\string,\string> $params) {
		$this->requestIsForm = true;
		$this->requestForm = $params;
	}

	public function setOAuthParameter(\string $param, \string $value) {
		if ($param == 'token')
			trigger_error("'token' parameter should be set using `setToken`", E_USER_WARNING);
		$this->oauthParameters[$param] = $value;
	}

	public function setToken(\string $token, ?\string $secret=null) {
		$this->oauthParameters['token'] = $token;
		$this->tokenSecret = $secret;
	}

	public function setRealm(\string $realm) {
		$this->realm = $realm;
	}

	public function generateSignature() : \string {
		switch ($this->signMethod) {
		case self::HMAC_SHA1:
			$text = $this->signatureBaseString();
			$token = $this->tokenSecret ?: '';
			$key = sprintf("%s&%s",
				$this->consumerSecret, $token);

			return base64_encode(hash_hmac('sha1', $text, $key, true));

		case self::RSA_SHA1:
			$key_file = nullthrows($this->private_key);
			$private_key = file_get_contents($key_file);
			$private_key_id = openssl_pkey_get_private($private_key);

			if ($private_key_id) {
				$text = $this->signatureBaseString();

				$signature = '';
				openssl_sign($text, $signature, $private_key_id);
				openssl_free_key($private_key_id);

				return base64_encode($signature);
			} else {
				throw new NetException("Cannot access private key file '$key_file'");
			}
		case self::PLAINTEXT:
			$token = $this->tokenSecret ?: '';
			$key = sprintf("%s&%s",
				$this->consumerSecret, $token);
			return urlencode($key);
		default:
			invariant_violation("Unreachable");
		}
	}

	private function signatureBaseString() : \string {
		return sprintf("%s&%s&%s",
			$this->requestMethod,
			rawurlencode($this->baseStringURI()),
			rawurlencode($this->normalizedParameters()));
	}

	public function getHeaderString() : \string {
		$params = $this->getOAuthParams();

		$params['signature'] = $this->generateSignature();

		$auth_header = 'OAuth ';
		if ($this->realm) {
			$auth_header .= sprintf('realm="%s"',rawurlencode($this->realm));
			$first = false;
		} else {
			$first = true;
		}
		foreach ($params as $k => $v) {
			if (!$first) {
				$auth_header .= ', ';
			}
			$auth_header .= sprintf('oauth_%s="%s"', $k, rawurlencode($v));
			$first = false;
		}

		return $auth_header;
	}

	private function normalizedParameters()
			: \string {
		$params = Vector {};

		$params->addAll($this->queryParameters->items());

		$oauth_params = $this->getOAuthParams();

		$params->addAll($oauth_params->mapWithKey(($k, $v) ==> Pair { 'oauth_'.$k, $v }));

		if ($this->requestIsForm)
			$params->addAll(nullthrows($this->requestForm)->items());

		$params = $params->map(function(Pair<\string,\string> $p) : Pair<\string, \string> {
			$k = rawurlencode($p[0]);
			$v = rawurlencode($p[1]);
			return Pair { $k, $v };
		})->toVector();

		usort($params, function (Pair<\string, \string> $p1, Pair<\string, \string> $p2) : int {
			return strcmp($p1[0], $p2[0]);
		});

		return bb_join('&', $params->map($p ==> $p[0].'='.$p[1]));
	}

	private Map<\string, \string> $_fullOAuthParams = Map {};
	private function getOAuthParams() : Map<\string,\string> {
		$params = $this->_fullOAuthParams;
		$params->setAll($this->oauthParameters);

		$setMissing = function(\string $p, (function(): string) $v) : \void use($params) {
			if (!$params->containsKey($p))
				$params->set($p, $v());
		};

		$params['version'] = '1.0';
		$params['signature_method'] = $this->signMethod;

		$setMissing('nonce', () ==> substr(md5(microtime() . mt_rand()), 0, 12));
		$setMissing('timestamp', () ==> time());


		return clone $params;
	}

	private function baseStringURI() : \string {

		$port = '';
		if ($this->port) {
			if ($this->scheme == 'http' && $this->port != 80) {
				$port = ':'.$this->port;
			} else if ($this->scheme == 'https' && $this->port != 443) {
				$port = ':'.$this->port;
			} else {
				$port = ':'.$this->port;
			}
		}

		return sprintf('%s://%s%s%s', $this->scheme, $this->host, $port, $this->path);
	}
}
