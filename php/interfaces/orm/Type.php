<?php

namespace beatbox\orm;

interface Type {
	function toDBString(Connection $conn): \string;
}

