<?php

/**
 * Send an asynchronous event with the given name and arguments
 */
function send_event($name /*, $args ... */) {
	return (new ReflectionClass('pr\base\Event'))->newInstanceArgs(func_get_args())->send();
}
