<?php

namespace pr\base\mailer;

interface Mailer {
	public static function send(\string $to, \string $from, \string $subject, \string $htmlSubject, \Map $attachments);
}
