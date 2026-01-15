<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Exception thrown when attempting an invalid tournament status transition.
 */
final class InvalidStatusTransitionException extends \RuntimeException
{
}
