<?php

namespace Ozdemirrulass\UriTemplate\Node;

class Variable extends Abstraction
{
    public string $name;

    public array $options = array(
        'modifier' => null,
        'value' => null,
    );

    public function __construct(string $token, array $options = array())
    {
        parent::__construct($token);
        $this->options = $options + $this->options;

        $name = $token;
        if ($options['modifier'] === ':') {
            $name = substr($name, 0, strpos($name, $options['modifier']));
        }

        $this->name = $name;
    }
}
