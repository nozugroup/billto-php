<?php

declare(strict_types=1);

namespace BillTo\Exceptions;

/** 401 - missing, inactive or revoked token. */
final class AuthenticationException extends ApiException {}
