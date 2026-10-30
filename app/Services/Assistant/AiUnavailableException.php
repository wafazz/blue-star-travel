<?php

namespace App\Services\Assistant;

use RuntimeException;

/** Raised when no provider in the chain produced a reply. */
class AiUnavailableException extends RuntimeException {}
