<?php

declare(strict_types=1);

namespace Ozdemirrulass;

use Ozdemirrulass\UriTemplate\Parser;

class UriTemplate
{
    public function __construct(
        protected string $base_uri = '',
        protected array $params = [],
        protected Parser $parser = new Parser()
    ) {
    }

    public function expand(string $uri, array $params = array()): string
    {
        $params += $this->params;
        $uri = $this->base_uri.$uri;
        $result = array();

        if ((strpos($uri, '{')) === false) {
            return $uri;
        }

        $parser = $this->parser;
        $nodes = $parser->parse($uri);

        foreach ($nodes as $node) {
            $result[] = $node->expand($params);
        }

        return implode('', $result);
    }

    public function extract(string $template, string $uri, bool $strict = false)
    {
        $params = array();
        $nodes = $this->parser->parse($template);

        foreach ($nodes as $node) {
            if ($strict && !strlen((string)$uri)) {
                return null;
            }

            $match = $node->match($this->parser, $uri, $params, $strict);

            list($uri, $params) = $match;
        }

        if ($strict && strlen((string)$uri)) {
            return null;
        }

        return $params;
    }


}