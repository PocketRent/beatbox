<?hh

namespace beatbox\test;

use \beatbox, \beatbox\orm\Connection;

class ConnectionTest extends beatbox\Test {

	/**
	 * @group sanity
	 */
	public function testQueryBasic() {
		$conn = Connection::get();
		$result = $conn->query("SELECT 1 as \"N\";")->join();
		$val = $result->nthRow(0)['N'];
		$this->assertEquals(1, $val);

		$result = $conn->query("SELECT 't' as \"N\"; SELECT 1 as \"N\";")->join();
		$val = $result->nthRow(0)['N'];
		$this->assertEquals(1, $val);
	}

	/**
	 * @group sanity
	 */
	public function testMultiQuery() {
		$conn = Connection::get();

		$result = $conn->multiQuery("SELECT 't' as \"N\"; SELECT 1 as \"N\";")->join();

		$this->assertEquals('t', $result[0]->nthRow(0)['N']);
		$this->assertEquals(1, $result[1]->nthRow(0)['N']);
	}

	/**
	 * @group sanity
	 */
	public function testQueryAsync() {
		$conn = Connection::get();

		// This causes each query to take just enough to block
		// on pg_get_result, meaning we exercise the connection_pool
		$r1 = $conn->query("SELECT 1 as \"N\", pg_sleep(0.1)");
		$r2 = $conn->query("SELECT 2 as \"N\", pg_sleep(0.1)");
		$r3 = $conn->query("SELECT 3 as \"N\", pg_sleep(0.1)");

		$this->assertEquals(1, $r1->join()->nthRow(0)['N']);
		$this->assertEquals(2, $r2->join()->nthRow(0)['N']);
		$this->assertEquals(3, $r3->join()->nthRow(0)['N']);
	}
}
