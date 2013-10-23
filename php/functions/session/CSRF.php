<?hh

/**
 * Check if the provided token matches the expected one
 */
function check_csrf_token(string $token) : bool {
	return check_token(session_get("CSRF"), $token);
}

/**
 * Returns the current CSRF token, generating one if needed
 */
function get_csrf_token() : string {
	return session_get('CSRF');
}
