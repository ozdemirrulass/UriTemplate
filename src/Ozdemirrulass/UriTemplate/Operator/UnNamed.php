<?php

declare(strict_types=1);

namespace Ozdemirrulass\UriTemplate\Operator;

use Ozdemirrulass\UriTemplate\Node\Variable;

class UnNamed extends Abstraction
{
    public function toRegex(Variable $var): string
    {
        $value = $this->getRegex();
        $options = $var->options;

        if ($options['modifier']) {
            switch ($options['modifier']) {
                case '*':
                    $regex = "{$value}+(?:{$this->sep}{$value}+)*";
                    break;
                case ':':
                    $regex = $value.'{0,'.$options['value'].'}';
                    break;
                case '%':
                    throw new \Exception("% (array) modifier only works with Named type operators e.g. ;,?,&");
                default:
                    throw new \Exception("Unknown modifier `{$options['modifier']}`");
            }
        } else {
            $regex = "{$value}*(?:,{$value}+)*";
        }

        return $regex;
    }
}