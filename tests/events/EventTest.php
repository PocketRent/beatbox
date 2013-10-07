<?php

namespace beatbox\test;

use beatbox, beatbox\Event;

class EventTest extends beatbox\Test {
	use beatbox\Test\Redis;

	protected $called = [];
	protected $args = [];

	protected function setUp() {
		Event::reset();

		// Add in default listeners
		Event::attach_listener(function() {
			$this->called[] = 'prefix';
			$this->args[] = func_get_args();
			return 1;
		}, 'prefix', true);

		Event::attach_listener(function() {
			$this->called[] = 'prefix:suffix';
			$this->args[] = func_get_args();
			return 2;
		}, 'prefix:suffix', false);

		Event::attach_listener(function() {
			$this->called[] = 'test';
			$this->args[] = func_get_args();
			return 3;
		}, 'test', false);

		$this->called = [];
		$this->args = [];
	}

	/**
	 * @group fast
	 */
	public function testBlockSend() {
		$event = new Event('test', 'ing');
		$vals = $event->blockSend();

		$this->assertEquals(['test'], $this->called);
		$this->assertEquals([['ing']], $this->args);
		$this->assertEquals([3], $vals);

		$event = new Event('prefix', 'something', 'else');
		$vals = $event->blockSend();

		$this->assertEquals(['test', 'prefix'], $this->called);
		$this->assertEquals([['ing'], ['something', 'else']], $this->args);
		$this->assertEquals([1], $vals);
	}

	/**
	 * @group fast
	 */
	public function testSend() {
		$event = new Event('test', 'ing');
		$event->send();

		beatbox\Task::run();

		$this->assertEquals(['test'], $this->called);
		$this->assertEquals([['ing']], $this->args);

		$event = new Event('prefix', 'something', 'else');
		$event->send();

		beatbox\Task::run();

		$this->assertEquals(['test', 'prefix'], $this->called);
		$this->assertEquals([['ing'], ['something', 'else']], $this->args);
	}

	/**
	 * @group fast
	 * @depends testBlockSend
	 */
	public function testPrefixMatching() {
		$event = new Event('testing');
		$vals = $event->blockSend();

		$this->assertEquals([], $this->called);
		$this->assertEquals([], $this->args);
		$this->assertEquals([], $vals);

		$event = new Event('prefix:suffix', 'first');
		$vals = $event->blockSend();

		$this->assertSetsEquals(['prefix', 'prefix:suffix'], $this->called);
		$this->assertEquals([['first'], ['first']], $this->args);
		$this->assertSetsEquals([1, 2], $vals);
	}
}
