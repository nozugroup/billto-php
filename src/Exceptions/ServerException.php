<?php

declare(strict_types=1);

namespace BillTo\Exceptions;

/** 5xx - error on the BillTo side. 502/503/504 are retried automatically. */
final class ServerException extends ApiException {}
