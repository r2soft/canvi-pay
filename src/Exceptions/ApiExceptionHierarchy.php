<?php
declare(strict_types=1);

namespace R2Soft\Canvi\Exceptions;

// A classe ApiException foi movida para um arquivo separado

class UnauthorizedException extends ApiException {}
class RateLimitException extends ApiException {}
class ServerException extends ApiException {}
