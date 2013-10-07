<?php

/**
 * Create a task and add it to the queue
 */
function add_task(callable $callback/*, $arguments...*/) {
	return (new ReflectionClass('pr\base\Task'))->newInstanceArgs(func_get_args())->queue();
}
