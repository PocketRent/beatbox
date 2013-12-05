<?hh

namespace beatbox\test;

use beatbox, beatbox\orm;

class ORMTest extends beatbox\Test {

	/**
	 * @group sanity
	 */
	public function testBasicQuery() {
		$q = TestTable::get()->getQueryString();
		$this->assertEquals('SELECT DISTINCT "TestTable"."ID", "TestTable"."AuxID", "TestTable"."Col1", "TestTable"."Col2" FROM "TestTable"', $q);
	}

	/**
	 * @group fast
	 * @depends testBasicQuery
	 */
	public function testConditions() {
		$q = TestTable::get()
			->filter('ID', 1);

		$str = $q->getQueryString();
		$this->assertEquals('SELECT DISTINCT "TestTable"."ID", "TestTable"."AuxID", "TestTable"."Col1", "TestTable"."Col2" FROM "TestTable"
WHERE ("TestTable"."ID"=\'1\')', $str);

		$q = $q->filter('AuxID', 5, '>');

		$str = $q->getQueryString();
		$this->assertEquals('SELECT DISTINCT "TestTable"."ID", "TestTable"."AuxID", "TestTable"."Col1", "TestTable"."Col2" FROM "TestTable"
WHERE ("TestTable"."ID"=\'1\') AND ("TestTable"."AuxID">\'5\')', $str);

		$q = $q->where('trim(both from "TestTable"."Col2") = \'Thing\'');

		$str = $q->getQueryString();
		$this->assertEquals('SELECT DISTINCT "TestTable"."ID", "TestTable"."AuxID", "TestTable"."Col1", "TestTable"."Col2" FROM "TestTable"
WHERE ("TestTable"."ID"=\'1\') AND ("TestTable"."AuxID">\'5\') AND (trim(both from "TestTable"."Col2") = \'Thing\')', $str);
	}

	/**
	 * @group fast
	 * @depends testBasicQuery
	 */
	public function testSort() {
		$q = TestTable::get()
			->sortBy('ID');

		$str = $q->getQueryString();
		$this->assertEquals('SELECT DISTINCT "TestTable"."ID", "TestTable"."AuxID", "TestTable"."Col1", "TestTable"."Col2" FROM "TestTable"
ORDER BY "TestTable"."ID" ASC', $str);

		$q1 = $q->sortBy('AuxID', 'DESC');

		$str = $q1->getQueryString();
		$this->assertEquals('SELECT DISTINCT "TestTable"."ID", "TestTable"."AuxID", "TestTable"."Col1", "TestTable"."Col2" FROM "TestTable"
ORDER BY "TestTable"."ID" ASC, "TestTable"."AuxID" DESC', $str);

		$q2 = $q->sortBy('AuxID', 'Magic'); // This should change it to ASC

		$str = $q2->getQueryString();
		$this->assertEquals('SELECT DISTINCT "TestTable"."ID", "TestTable"."AuxID", "TestTable"."Col1", "TestTable"."Col2" FROM "TestTable"
ORDER BY "TestTable"."ID" ASC, "TestTable"."AuxID" ASC', $str);

	}

	/**
	 * @group fast
	 */
	public function testValidation() {
		$q = TestTable::get();
		try {
			// This should pass
			$this->assertInstanceOf('beatbox\orm\ORM', $q->sortBy('ID'));
		} catch (orm\InvalidFieldException $e) {
			$this->fail('Valid field failed validation');
		} catch (\Exception $e) {
			throw $e;
		}

		try {
			// This should fail
			$q->sortBy('Apple');
		} catch (orm\InvalidFieldException $e) {
			$this->assertContains('Invalid field \'Apple\', expected one of ', $e->getMessage());
			return;
		}

		$this->fail('Invalid field passed validation');
	}

	/**
	 * @group fast
	 */
	public function testNullFilter() {
		$q = TestTable::get();
		$str = $q->filter('ID', null)->getQueryString();
		$this->assertEquals('SELECT DISTINCT "TestTable"."ID", "TestTable"."AuxID", "TestTable"."Col1", "TestTable"."Col2" FROM "TestTable"
WHERE ("TestTable"."ID" IS NULL)', $str);

		$str = $q->filter('ID', null, '!=')->getQueryString();
		$this->assertEquals('SELECT DISTINCT "TestTable"."ID", "TestTable"."AuxID", "TestTable"."Col1", "TestTable"."Col2" FROM "TestTable"
WHERE ("TestTable"."ID" IS NOT NULL)', $str);
	}

	/**
	 * @group fast
	 */
	public function testFromClause() {
		$q = TestTable::get();
		$this->assertEquals('"TestTable"', $q->getFrom());

		$q = $q->setFrom('someFunction()');
		$this->assertEquals('someFunction() AS "TestTable"', $q->getFrom());
	}
}

// This is normally generated code, but this means
// we can test the SQL query generation without having
// to generate code or rely on it already existing
class TestTable extends orm\DataTable {
	public function __construct($row) { }

	protected function updateFromRow($row) { }
	protected function getUpdatedColumns() : \Map { return \Map {}; }
	protected function originalValues() : \Map { return \Map {}; }

	public function toMap() : \Map { return \Map {}; }
	public function toRow() : \string { return ''; }
	public function isNew() : \bool { return true; }

	public static function getTableName() : \string {
		return 'TestTable';
	}

	public static function getColumnNames() {
		// This and `getPrimaryKeys` below normally
		// return a Set, but those aren't stable in terms
		// of ordering, so we're returning a StableMap
		// instead, since it still supports '->contains()'
		// properly, but keeps the ordering as given.
		return \StableMap {
			'ID' => 'ID',
			'AuxID' => 'AuxID',
			'Col1' => 'Col1',
			'Col2' => 'Col2'
		};
	}

	public static function getPrimaryKeys() {
		return \StableMap {
			'ID' => 'ID',
			'AuxID' => 'AuxID'
		};
	}
}
