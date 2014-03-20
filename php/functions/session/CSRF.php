<?hh // strict

/**
 * Check if the provided token matches the expected one
 */
function check_csrf_token(string $token) : bool {
	return check_token(get_csrf_token(), $token);
}

/**
 * Returns the current CSRF token, generating one if needed
 */
function get_csrf_token() : string {
	return (string)session_get('CSRF');
}
