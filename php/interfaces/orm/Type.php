<?hh

namespace beatbox\orm;

interface Type {
	public function toDBString(Connection $conn): \string;
}

