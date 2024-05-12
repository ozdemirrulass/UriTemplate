<?php

declare(strict_types=1);

namespace Ozdemirrulass\UriTemplate\Node;

use Ozdemirrulass\UriTemplate\Parser;

abstract class Abstraction
{
    public function __construct(private readonly string $token)
    {
    }

    public function expand(array $params = array()): ?string
    {
        return $this->token;
    }

    public function match(Parser $parser, string $uri, array $params = array(), bool $strict = false): ?array
    {
        $length = strlen($this->token);
        if (substr($uri, 0, $length) === $this->token) {
            $uri = substr($uri, $length);
        } elseif ($strict) {
            return null;
        }

        return array($uri, $params);
    }

    public function getToken(): string
    {
        return $this->token;
    }
}
