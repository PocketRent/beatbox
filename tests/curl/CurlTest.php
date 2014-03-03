<?hh

namespace beatbox\test;

use beatbox;

class CurlTest extends beatbox\Test {
	/**
	 *
	 */
	public function testSingleRequest() {
		$req = beatbox\curl\Request::build()
			->setURL("http://example.com")
			->get();

		$req = wait($req->exec());

		$this->assertEquals($req->getHTTPCode(), 200);
	}

	/**
	 *
	 */
	public function testMultipleRequest() {
		$req1 = beatbox\curl\Request::build()
			->setURL("http://example.com")
			->get();

		$req2 = beatbox\curl\Request::build()
			->setURL("http://example.com")
			->get();

		$array = wait(genva($req1->exec(), $req2->exec()));

		$req1 = $array[0];
		$req2 = $array[1];

		$this->assertEquals($req1->getHTTPCode(), 200);
		$this->assertEquals($req2->getHTTPCode(), 200);
	}

	/**
	 *
	 */
	public function testPOSTRequest() {
		$post = beatbox\curl\Request::build()
			->setURL("http://example.com")
			->setRequestMethod('POST')
			->setStringOption(beatbox\curl\Options::POSTFIELDS, 'a=1')
			->get();

		$post = wait($post->exec());

		$this->assertEquals($post->getHTTPCode(), 200);
	}

}
