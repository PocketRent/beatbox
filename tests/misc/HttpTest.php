<?hh

namespace beatbox\test;

use beatbox;

class HttpFunctionsTest extends beatbox\Test {
	/**
	 * @group sanity
	 */
	public function testHttpBuildUrl() {
		$_SERVER['QUERY_STRING'] = 's=b&i=0&e=&a[]=1&a[]=2';
		$_SERVER['SERVER_PORT'] = 55555;
		$_SERVER['HTTP_HOST'] = 'example.com';
		unset($_SERVER['HTTPS'], $_SERVER['SCRIPT_URL']);

		$this->assertEquals('http://example.com:55555/?s=b&i=0&e=&a[]=1&a[]=2', http_build_url());
		$this->assertEquals('http://example.com:55555/index?s=b&i=0&e=&a[]=1&a[]=2', http_build_url('other', 'index'));
		$this->assertEquals('https://example.com/?s=b&i=0&e=&a[]=1&a[]=2', http_build_url(['scheme' => 'https', 'port' => 443]));
		$this->assertEquals('http://example.com:55555/index.php/', http_build_url(array("path" => "/./up/../down/../././//index.php/.", "query" => null), null, HTTP_URL_SANITIZE_PATH|HTTP_URL_FROM_ENV));
		$this->assertEquals('http://localhost/', http_build_url(null, null, 0));
	}
}
