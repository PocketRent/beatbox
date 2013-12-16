<?hh

namespace beatbox\test;

use \beatbox, \beatbox\orm\Connection, \beatbox\orm\Result, \beatbox\orm\QueryResult;

class ConnectionTest extends beatbox\Test {

	/**
	 * @group sanity
	 */
	public function testQueryBasic() {
		$conn = Connection::get();
		$result = wait($conn->query("SELECT 1 as \"N\";"));
		invariant($result instanceof QueryResult, '$result should be a QueryResult');
		$val = $result->nthRow(0)['N'];
		$this->assertEquals(1, $val);

		$result = wait($conn->query("SELECT 't' as \"N\"; SELECT 1 as \"N\";"));
		invariant($result instanceof QueryResult, '$result should be a QueryResult');
		$val = $result->nthRow(0)['N'];
		$this->assertEquals(1, $val);
	}

	/**
	 * @group sanity
	 */
	public function testMultiQuery() {
		$conn = Connection::get();

		$result = wait($conn->multiQuery("SELECT 't' as \"N\"; SELECT 1 as \"N\";"));

		$r1 = $result[0];
		$r2 = $result[1];
		invariant($r1 instanceof QueryResult, '$r1 should be a QueryResult');
		invariant($r2 instanceof QueryResult, '$r2 should be a QueryResult');

		$this->assertEquals('t', $r1->nthRow(0)['N']);
		$this->assertEquals(1, $r2->nthRow(0)['N']);
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

		$r1 = wait($r1);
		$r2 = wait($r2);
		$r3 = wait($r3);

		invariant($r1 instanceof QueryResult, '$r1 should be a QueryResult');
		invariant($r2 instanceof QueryResult, '$r2 should be a QueryResult');
		invariant($r3 instanceof QueryResult, '$r3 should be a QueryResult');

		$this->assertEquals(1, $r1->nthRow(0)['N']);
		$this->assertEquals(2, $r2->nthRow(0)['N']);
		$this->assertEquals(3, $r3->nthRow(0)['N']);
	}
}
