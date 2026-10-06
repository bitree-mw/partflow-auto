<?php

namespace App\Exceptions;

use RuntimeException;

// A request was valid but a business rule refused it; the message is safe to show to the user.
class BusinessRuleException extends RuntimeException {}
