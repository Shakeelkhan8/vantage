<?php

declare(strict_types=1);

namespace App\Profiles\Exceptions;

use RuntimeException;

/**
 * The uploaded file could not be turned into text.
 *
 * Messages on this exception are shown to the user, so they say what to do
 * next rather than what went wrong internally.
 */
final class UnreadableCv extends RuntimeException {}
