<?php

declare(strict_types=1);

namespace Ozdemirrulass\UriTemplate;

use Ozdemirrulass\UriTemplate\Node\Expression;
use Ozdemirrulass\UriTemplate\Node\Literal;
use Ozdemirrulass\UriTemplate\Node\Variable;
use Ozdemirrulass\UriTemplate\Operator\UnNamed;

class Parser
{
    public const REGEX_VARNAME = '(?:[A-z0-9_\.]|%[0-9a-fA-F]{2})';

    public function parse(string $template): array
    {
        $parts = preg_split('#(\{[^\}]+\})#', $template, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $nodes = array();

        foreach ($parts as $part) {
            $node = $this->createNode($part);

            // if current node has dot separator that requires a forward lookup
            // for the previous node iff previous node's operator is UnNamed
            if ($node instanceof Expression && $node->getOperator()->id === '.') {
                if (sizeof($nodes) > 0) {
                    $previousNode = $nodes[sizeof($nodes) - 1];
                    if ($previousNode instanceof Expression && $previousNode->getOperator() instanceof UnNamed) {
                        $previousNode->setForwardLookupSeparator($node->getOperator()->id);
                    }
                }
            }

            $nodes[] = $node;
        }

        return $nodes;
    }

    private function createNode(string $token): Literal|Expression
    {
        if ($token[0] !== '{') {
            $node = $this->createLiteralNode($token);
        } else {
            $node = $this->parseExpression(substr($token, 1, -1));
        }

        return $node;
    }

    protected function createLiteralNode(string $token): Literal
    {
        return new Literal($token);
    }

    protected function parseExpression(string $expression): Node\Expression
    {
        $token = $expression;
        $prefix = $token[0];

        if (!Operator\Abstraction::isValid($prefix)) {
            if (!preg_match('#'.self::REGEX_VARNAME.'#', $token)) {
                throw new \Exception("Invalid operator [$prefix] found at {$token}");
            }

            $prefix = null;
        }

        if ($prefix) {
            $token = substr($token, 1);
        }

        $vars = array();
        foreach (explode(',', $token) as $var) {
            $vars[] = $this->parseVariable($var);
        }

        return $this->createExpressionNode(
            $token,
            $this->createOperatorNode((string)$prefix),
            $vars
        );
    }

    protected function createOperatorNode(string $token)
    {
        return Operator\Abstraction::createById($token);
    }

    protected function createExpressionNode(
        $token,
        Operator\Abstraction $operator = null,
        array $vars = array()
    ): Node\Expression {
        return new Node\Expression($token, $operator, $vars);
    }

    protected function parseVariable($var): Variable
    {
        $var = trim($var);
        $val = null;
        $modifier = null;

        if (str_contains($var, ':')) {
            $modifier = ':';
            list($varname, $val) = explode(':', $var);

            if (!is_numeric($val)) {
                throw new \Exception("Value for `:` modifier must be numeric value [$varname:$val]");
            }
        }

        switch ($last = substr($var, -1)) {
            case '*':
            case '%':

                if ($modifier) {
                    throw new \Exception("Multiple modifiers per variable are not allowed [$var]");
                }

                $modifier = $last;
                $var = substr($var, 0, -1);
                break;
        }

        return $this->createVariableNode(
            $var,
            array(
                'modifier' => $modifier,
                'value' => $val,
            )
        );
    }

    protected function createVariableNode(string $token, array $options = array()): Variable
    {
        return new Variable($token, $options);
    }

}