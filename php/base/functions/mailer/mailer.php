<?php

function send_email(\string $to, \string $subject, :x:base $content, \string $from = null) {
	(new pr\base\mailer\Outbound($to, $subject, $content, $from))->send();
}
