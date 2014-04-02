<?hh

/**
 * Create a task and add it to the queue
 */
function add_task((function (...): \void) $callback, ...) : \void {
	return (new ReflectionClass('beatbox\Task'))->newInstanceArgs(func_get_args())->queue();
}
