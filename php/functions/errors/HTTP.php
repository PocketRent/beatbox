<?hh // strict

/**
 * Throw a HTTP error
 *
 * @param int $code The HTTP status code
 * @param string $status The status description
 * @uses beatbox\errors\HTTP::error()
 */
<<NoReturn>>
function http_error(int $code, ?\string $status = null) : void {
	return beatbox\errors\HTTP::error($code, $status);
}

/**
 * Redirect to the given page, or to the fallback if the
 * given page doesn't exist or is in another domain.
 *
 * If no fallback is given, it defaults to the home page
 *
 * @param string $to the URL to redirect to
 * @param string $fallback the URL to fallback to
 * @uses beatbox\errors\HTTP::redirect()
 */
<<NoReturn>>
function redirect(string $to, ?string $fallback=null, int $code = 302) : void {
	return beatbox\errors\HTTP::redirect($to, $fallback, $code);
}

/**
 * Redirect to the previous page, or to the fallback if the
 * previous page doesn't exist or is in another domain.
 *
 * If no fallback is given, it defaults to the home page
 *
 * @param string $fallback the URL to fallback to
 * @uses beatbox\errors\HTTP::redirect_back()
 */
<<NoReturn>>
function redirect_back(?string $fallback = null) : void {
	return beatbox\errors\HTTP::redirect_back($fallback);
}
