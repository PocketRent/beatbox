<?php

namespace pr\base\orm;

interface Type {
	function toDBString(Connection $conn): \string;
}

