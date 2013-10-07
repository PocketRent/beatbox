<?php

namespace pr\base\mailer;

require_once 'Mail.php';
require_once 'Mail/mime.php';

class Sendmail implements Mailer {
	public static function send(\string $to, \string $from, \string $subject, \string $htmlContent, \Map $attachments) {
		$email = new \Mail_mime([
			'html_charset' => 'utf8',
			'text_charset' => 'utf8',
		]);
		foreach($attachments as $name => $type) {
			$email->addAttachment($name, $type);
		}
		$email->setHTMLBody($htmlContent);

		$headers = [
			'From' => $from,
			'Subject' => $subject
		];

		$body = $email->get();
		$headers = $email->headers($headers);

		$mail = \Mail::factory('sendmail');
		$mail->send($to, $headers, $body);
	}
}
