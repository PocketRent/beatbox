<?hh

function send_email(\string $to, \string $subject, :x:base $content, ?\string $from = null) : void {
	(new beatbox\mailer\Outbound($to, $subject, $content, $from))->send();
}
