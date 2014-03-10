<?hh

namespace beatbox\test;

use beatbox;

class OAuthTest extends beatbox\Test {

	/**
	 * @group fast
	 */
	public function testHMAC() {
		$oauth = new beatbox\net\OAuth('http://host.net/resource');
		$oauth->setOAuthParameter('nonce', 'wT9Vpkfmnxs');
		$oauth->setOAuthParameter('timestamp', '1394401248');

		$oauth->setOAuthParameter('consumer_key', 'abcd');
		$oauth->setHMAC('efgh');
		$oauth->setToken('ijkl', 'mnop');

		$signature = $oauth->generateSignature();

		$this->assertEquals('DPyGqTaIjZ0qbUAkhKvE1BBx5SA=', $signature);

		$header = $oauth->getHeaderString();
		$this->assertEquals('OAuth oauth_nonce="wT9Vpkfmnxs", '.
			'oauth_timestamp="1394401248", oauth_consumer_key="abcd", oauth_token="ijkl", '.
			'oauth_version="1.0", oauth_signature_method="HMAC-SHA1", '.
			'oauth_signature="DPyGqTaIjZ0qbUAkhKvE1BBx5SA%3D"', $header);
	}


}
