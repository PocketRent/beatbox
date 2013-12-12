<?hh

namespace beatbox\mailer;

class Outbound {
	const default_from = 'PocketRent <help@pro.pocketrent.com>';

	protected \string $to;
	protected \string $subject;
	protected \string $content;
	protected \string $from;
	protected \Map<\string, \string> $attachments = \Map {};

	/**
	 * Construct an outbound email
	 */
	public function __construct(\string $to, \string $subject, :x:base $content, \string $from = null) {
		if(!$from) {
			$from = static::default_from;
		}
		$this->to = $to;
		$this->subject = $subject;
		$this->content = (string)$content;
		$this->from = $from;
	}

	/**
	 * Set who the email is sent to.
	 */
	public function setTo(\string $to) : Outbound {
		$this->to = $to;
		return $this;
	}

	/**
	 * Add an attachment to the email
	 */
	public function addAttachment(\string $file_path) : Outbound {
		$this->attachments[$file_path] = get_mime_type($file_path);
		return $this;
	}

	/**
	 * Queue these emails ready for sending
	 */
	public function send() : void {
		add_task([get_called_class(), 'real_send'], $this->to, $this->subject, $this->content, $this->from, $this->attachments);
	}

	// Don't call this directly
	public static function real_send(\string $to, \string $subject, \string $content, \string $from, \Map $attachments) : void {
		if(strpos($content, '<body') === false) {
			$content = "<!doctype html><html><body>$content</body></html>";
		} elseif(strpos($content, '<html') === false) {
			$content = "<!doctype html><html>$content</html>";
		}
		$sender = defined('MAIL_SENDER') ? MAIL_SENDER : __NAMESPACE__ . '\Sendmail';
		$sender::send($to, $from, $subject, $content, $attachments);
	}
}
