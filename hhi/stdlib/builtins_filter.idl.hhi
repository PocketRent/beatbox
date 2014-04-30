<?hh    /* -*- php -*- */
/**
 * Copyright (c) 2014, Facebook, Inc.
 * All rights reserved.
 *
 * This source code is licensed under the BSD-style license found in the
 * LICENSE file in the 'hack' directory of this source tree. An additional grant
 * of patent rights can be found in the PATENTS file in the same directory.
 *
 */
define('INPUT_POST', 0);
define('INPUT_GET', 0);
define('INPUT_COOKIE', 0);
define('INPUT_ENV', 0);
define('INPUT_SERVER', 0);
define('INPUT_SESSION', 0);
define('INPUT_REQUEST', 0);
define('FILTER_FLAG_NONE', 0);
define('FILTER_REQUIRE_SCALAR', 0);
define('FILTER_REQUIRE_ARRAY', 0);
define('FILTER_FORCE_ARRAY', 0);
define('FILTER_NULL_ON_FAILURE', 0);
define('FILTER_VALIDATE_INT', 0);
define('FILTER_VALIDATE_BOOLEAN', 0);
define('FILTER_VALIDATE_FLOAT', 0);
define('FILTER_VALIDATE_REGEXP', 0);
define('FILTER_VALIDATE_URL', 0);
define('FILTER_VALIDATE_EMAIL', 0);
define('FILTER_VALIDATE_IP', 0);
define('FILTER_VALIDATE_MAC', 0);
define('FILTER_DEFAULT', 0);
define('FILTER_UNSAFE_RAW', 0);
define('FILTER_SANITIZE_STRING', 0);
define('FILTER_SANITIZE_STRIPPED', 0);
define('FILTER_SANITIZE_ENCODED', 0);
define('FILTER_SANITIZE_SPECIAL_CHARS', 0);
define('FILTER_SANITIZE_FULL_SPECIAL_CHARS', 0);
define('FILTER_SANITIZE_EMAIL', 0);
define('FILTER_SANITIZE_URL', 0);
define('FILTER_SANITIZE_NUMBER_INT', 0);
define('FILTER_SANITIZE_NUMBER_FLOAT', 0);
define('FILTER_SANITIZE_MAGIC_QUOTES', 0);
define('FILTER_CALLBACK', 0);
define('FILTER_FLAG_ALLOW_OCTAL', 0);
define('FILTER_FLAG_ALLOW_HEX', 0);
define('FILTER_FLAG_STRIP_LOW', 0);
define('FILTER_FLAG_STRIP_HIGH', 0);
define('FILTER_FLAG_ENCODE_LOW', 0);
define('FILTER_FLAG_ENCODE_HIGH', 0);
define('FILTER_FLAG_ENCODE_AMP', 0);
define('FILTER_FLAG_NO_ENCODE_QUOTES', 0);
define('FILTER_FLAG_EMPTY_STRING_NULL', 0);
define('FILTER_FLAG_STRIP_BACKTICK', 0);
define('FILTER_FLAG_ALLOW_FRACTION', 0);
define('FILTER_FLAG_ALLOW_THOUSAND', 0);
define('FILTER_FLAG_ALLOW_SCIENTIFIC', 0);
define('FILTER_FLAG_SCHEME_REQUIRED', 0);
define('FILTER_FLAG_HOST_REQUIRED', 0);
define('FILTER_FLAG_PATH_REQUIRED', 0);
define('FILTER_FLAG_QUERY_REQUIRED', 0);
define('FILTER_FLAG_IPV4', 0);
define('FILTER_FLAG_IPV6', 0);
define('FILTER_FLAG_NO_RES_RANGE', 0);
define('FILTER_FLAG_NO_PRIV_RANGE', 0);
function filter_input_array(int $type, mixed $defintion, bool $add_empty) {}
