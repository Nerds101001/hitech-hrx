<?php

namespace App\Exceptions;

use RuntimeException;

/** Tally could not be reached, refused the request, or the wrong company is loaded. Never fatal to the app: callers report it and stop. */
class TallyGatewayException extends RuntimeException
{
}
