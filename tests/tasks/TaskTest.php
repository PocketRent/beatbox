<?hh

namespace beatbox\test;

use beatbox, beatbox\Task;

class TaskTest extends beatbox\Test {
	use beatbox\Test\Redis;

	/**
	 * @group fast
	 */
	public function testEnqueue() {
		// Make sure the queue's empty
		$this->assertEquals(0, self::redis()->llen(Task::QUEUE_NAME));

		$this->assertEquals(1, add_task('strtolower', 'hello'));
		$this->assertEquals(1, self::redis()->llen(Task::QUEUE_NAME));

		$task = new Task('strtolower', 'goodbye');
		$this->assertEquals(2, $task->queue());
		$this->assertEquals(2, self::redis()->llen(Task::QUEUE_NAME));
	}

	/**
	 * @group fast
	 * @depends testEnqueue
	 */
	public function testRunning() {
		$this->assertEquals(0, self::redis()->llen(Task::QUEUE_NAME));

		$this->assertEquals(1, add_task('strtolower', 'Hello'));

		$this->assertEquals('hello', Task::run());

		$this->assertEquals(0, self::redis()->llen(Task::QUEUE_NAME));
		$this->assertNull(Task::run());
		$this->assertEquals(0, self::redis()->llen(Task::QUEUE_NAME));

		// Make sure it is a queue
		$this->assertEquals(1, add_task('strtolower', 'Hello'));
		$this->assertEquals(2, add_task('strtolower', 'World'));

		$this->assertEquals('hello', Task::run());
		$this->assertEquals(1, self::redis()->llen(Task::QUEUE_NAME));
		$this->assertEquals('world', Task::run());
		$this->assertEquals(0, self::redis()->llen(Task::QUEUE_NAME));
	}

	/**
	 * @group fast
	 * @depends testRunning
	 */
	public function testConcurrency() {
		$this->assertEquals(0, self::redis()->llen(Task::QUEUE_NAME));

		$task = new Task('strtolower', 'Hello');
		$task->setConcurrent(Task::CON_ALWAYS);

		$otherTask = new Task('strtolower', 'World');
		$otherTask->setConcurrent(Task::CON_ALWAYS);

		// Both can start
		$this->assertTrue($task->canStart());
		$this->assertTrue($otherTask->canStart());

		// If one has started, they can both still start
		$task->setUp();
		$this->assertTrue($task->canStart());
		$this->assertTrue($otherTask->canStart());
		$task->tearDown();

		$otherTask->setUp();
		$this->assertTrue($task->canStart());
		$this->assertTrue($otherTask->canStart());
		$otherTask->tearDown();

		$task->setConcurrent(Task::CON_DIFF);

		// If one has started, the other can but same can't for $task
		$task->setUp();
		$this->assertFalse($task->canStart());
		$this->assertTrue($otherTask->canStart());
		$task->tearDown();

		$otherTask->setUp();
		$this->assertTrue($task->canStart());
		$this->assertTrue($otherTask->canStart());
		$otherTask->tearDown();

		$task->setConcurrent(Task::CON_NEVER);
		// If $task has started, neither can. If $otherTask has started, $task can't
		$task->setUp();
		$this->assertFalse($task->canStart());
		$this->assertFalse($otherTask->canStart());
		$task->tearDown();

		$otherTask->setUp();
		$this->assertFalse($task->canStart());
		$this->assertTrue($otherTask->canStart());
		$otherTask->tearDown();
	}

	/**
	 * @group fast
	 * @depends testConcurrency
	 */
	public function testLocks() {
		$task = new Task('strtolower', 'Hello');
		$task->setConcurrent(Task::CON_DIFF);

		$this->assertTrue($task->setUp());
		$this->assertFalse($task->setUp());

		$task->setConcurrent(Task::CON_NEVER);

		$this->assertTrue($task->setUp());
		$this->assertFalse($task->setUp());
	}
}
