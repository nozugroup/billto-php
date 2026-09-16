<?php

declare(strict_types=1);

namespace BillTo\Exceptions;

/**
 * Common base class of every SDK exception - `catch (BillToException $e)` catches them all.
 */
class BillToException extends \RuntimeException {}
