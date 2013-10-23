<?hh

namespace beatbox\test;

use beatbox;

class AssetTest extends beatbox\Test {

	/**
	 * @group fast
	 */
	public function testLoadFile() {
		$file = tempnam("/tmp", "AssetTest");
		$fd = fopen($file, "w");
		fwrite($fd, "Test File");
		fclose($fd);

		$asset = new beatbox\Asset;
		$asset->setName("TestFile.txt");
		$asset->setMIME('text/plain');
		$asset->loadSourceFile($file);

		$this->assertFalse(file_exists($file));
	}

	/**
	 * @group fast
	 */
	public function testFallback() {
		$asset = new beatbox\Asset;
		$this->assertEquals('fallback', $asset->getURI('fallback'));
	}
}
