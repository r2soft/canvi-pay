<?php
namespace R2Soft\Canvi\Support; final class Idempotency{ public static function key(string $p='idem'):string{ return $p.'-'.bin2hex(random_bytes(8)); }}