<?php

declare(strict_types=1);

namespace Ozdemirrulass\UriTemplate\Node;

use Ozdemirrulass\UriTemplate\Node\Abstraction as NodeAbstraction;
use Ozdemirrulass\UriTemplate\Operator\Abstraction;
use Ozdemirrulass\UriTemplate\Parser;

class Expression extends NodeAbstraction
{

    public function __construct(
        public string $token,
        private readonly Abstraction $operator,
        private readonly ?array $variables = null,
        private ?string $forwardLookupSeparator = null
    ) {
        parent::__construct($token);
    }

    public function getOperator(): Abstraction
    {
        return $this->operator;
    }

    public function getVariables(): ?array
    {
        return $this->variables;
    }

    public function getForwardLookupSeparator(): ?string
    {
        return $this->forwardLookupSeparator;
    }

    public function setForwardLookupSeparator(string $forwardLookupSeparator): void
    {
        $this->forwardLookupSeparator = $forwardLookupSeparator;
    }

    public function expand(array $params = array()): ?string
    {
        $data = array();
        $op = $this->operator;

        foreach ($this->variables as $var) {
            $val = $op->expand($var, $params);

            if (!is_null($val)) {
                $data[] = $val;
            }
        }

        return $data ? $op->first.implode($op->sep, $data) : null;
    }

    public function match(Parser $parser, string $uri, array $params = array(), bool $strict = false): ?array
    {
        $op = $this->operator;

        if ($op->id && isset($uri[0]) && $uri[0] !== $op->id) {
            return array($uri, $params);
        }

        if ($op->id) {
            $uri = substr($uri, 1);
        }

        foreach ($this->sortVariables($this->variables) as $var) {
            $regex = '#'.$op->toRegex($var).'#';
            $val = null;

            $remainingUri = '';
            $preparedUri = $uri;
            if ($this->forwardLookupSeparator) {
                $lastOccurrenceOfSeparator = stripos($uri, $this->forwardLookupSeparator);
                $preparedUri = substr($uri, 0, $lastOccurrenceOfSeparator);
                $remainingUri = substr($uri, $lastOccurrenceOfSeparator);
            }

            if (preg_match($regex, $preparedUri, $match)) {
                $preparedUri = preg_replace($regex, '', $preparedUri, $limit = 1);
                $val = $op->extract($var, $match[0]);
            } elseif ($strict) {
                return null;
            }

            $uri = $preparedUri.$remainingUri;

            $params[$var->getToken()] = $val;
        }

        return array($uri, $params);
    }

    protected function sortVariables(array $vars): array
    {
        usort($vars, function ($a, $b) {
            return $a->options['modifier'] >= $b->options['modifier'] ? 1 : -1;
        });

        return $vars;
    }
}
