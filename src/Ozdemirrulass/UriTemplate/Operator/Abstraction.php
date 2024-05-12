<?php

declare(strict_types=1);

namespace Ozdemirrulass\UriTemplate\Operator;

use Exception;
use Ozdemirrulass\UriTemplate\Node\Variable;

abstract class Abstraction
{

    protected static array $loaded = [];
    protected const TYPES = [
        '' => [
            'sep' => ',',
            'named' => false,
            'empty' => '',
            'reserved' => false,
            'start' => 0,
            'first' => null,
        ],
        '+' => [
            'sep' => ',',
            'named' => false,
            'empty' => '',
            'reserved' => true,
            'start' => 1,
            'first' => null,
        ],
        '.' => [
            'sep' => '.',
            'named' => false,
            'empty' => '',
            'reserved' => false,
            'start' => 1,
            'first' => '.',
        ],
        '/' => [
            'sep' => '/',
            'named' => false,
            'empty' => '',
            'reserved' => false,
            'start' => 1,
            'first' => '/',
        ],
        ';' => [
            'sep' => ';',
            'named' => true,
            'empty' => '',
            'reserved' => false,
            'start' => 1,
            'first' => ';',
        ],
        '?' => [
            'sep' => '&',
            'named' => true,
            'empty' => '=',
            'reserved' => false,
            'start' => 1,
            'first' => '?',
        ],
        '&' => [
            'sep' => '&',
            'named' => true,
            'empty' => '=',
            'reserved' => false,
            'start' => 1,
            'first' => '&',
        ],
        '#' => [
            'sep' => ',',
            'named' => false,
            'empty' => '',
            'reserved' => true,
            'start' => 1,
            'first' => '#',
        ],
    ];

    public const RESERVED_CHARS = [
        '%3A' => ':',
        '%2F' => '/',
        '%3F' => '?',
        '%23' => '#',
        '%5B' => '[',
        '%5D' => ']',
        '%40' => '@',
        '%21' => '!',
        '%24' => '$',
        '%26' => '&',
        '%27' => "'",
        '%28' => '(',
        '%29' => ')',
        '%2A' => '*',
        '%2B' => '+',
        '%2C' => ',',
        '%3B' => ';',
        '%3D' => '=',
    ];
    protected const PATH_REGEX = '(?:[a-zA-Z0-9\-\._~!\$&\'\(\)\*\+,;=%:@]+|%(?![A-Fa-f0-9]{2}))';
    protected const QUERY_REGEX = '(?:[a-zA-Z0-9\-\._~!\$\'\(\)\*\+,;=%:@\/\?]+|%(?![A-Fa-f0-9]{2}))';

    public function __construct(
        public string $id,
        public bool $named,
        public string $sep,
        public string $empty,
        public bool $reserved,
        public ?int $start,
        public ?string $first
    ) {
    }

    abstract public function toRegex(Variable $var);

    public function expand(Variable $var, array $params = array()): ?string
    {
        $options = $var->options;
        $name = $var->name;
        $is_explode = in_array($options['modifier'], array('*', '%'));

        if (!isset($params[$name])) {
            return null;
        }

        $val = $params[$name];

        if (!is_array($val)) {
            return $this->expandString($var, $val);
        } elseif (!$is_explode) {
            return $this->expandNonExplode($var, $val);
        } else {
            return $this->expandExplode($var, $val);
        }
    }

    public function expandExplode(Variable $var, array $val): ?string
    {
        if (empty($val)) {
            return null;
        }

        return $this->encode($var, $val);
    }

    public function expandString(Variable $var, $val): string
    {
        $val = (string)$val;
        $options = $var->options;

        if ($options['modifier'] === ':') {
            $val = substr($val, 0, (int)$options['value']);
        }

        return $this->encode($var, $val);
    }

    public function expandNonExplode(Variable $var, array $val): ?string
    {
        if (empty($val)) {
            return null;
        }

        return $this->encode($var, $val);
    }


    public function encode(Variable $var, $values): string
    {
        $values = (array)$values;
        $list = isset($values[0]);
        $reserved = $this->reserved;
        $maps = static::RESERVED_CHARS;
        $sep = $this->sep;
        $assoc_sep = '=';

        if ($var->options['modifier'] !== '*') {
            $assoc_sep = $sep = ',';
        }

        array_walk($values, function (&$v, $k) use ($assoc_sep, $reserved, $list, $maps) {
            $encoded = rawurlencode($v);

            if (!$list) {
                $encoded = rawurlencode($k).$assoc_sep.$encoded;
            }

            if (!$reserved) {
                $v = $encoded;
            } else {
                $v = str_replace(
                    array_keys($maps),
                    $maps,
                    $encoded
                );
            }
        });

        return implode($sep, $values);
    }

    public function decode($values)
    {
        $single = !is_array($values);
        $values = (array)$values;

        array_walk($values, function (&$v) {
            $v = rawurldecode($v);
        });

        return $single ? reset($values) : $values;
    }

    public function extract(Variable $var, $data)
    {
        $value = $data;
        $vals = array_filter(explode($this->sep, $data));
        $options = $var->options;

        switch ($options['modifier']) {
            case '*':
                $data = array();
                foreach ($vals as $val) {
                    if (str_contains($val, '=')) {
                        list($k, $v) = explode('=', $val);
                        $data[$k] = $v;
                    } else {
                        $data[] = $val;
                    }
                }

                break;
            case ':':
                break;
            default:
                $data = str_contains($data, $this->sep) ? $vals : $value;
        }

        return $this->decode($data);
    }

    public static function createById($id)
    {
        if (!isset(static::TYPES[$id])) {
            throw new Exception("Invalid operator [$id]");
        }

        if (isset(static::$loaded[$id])) {
            return static::$loaded[$id];
        }

        $op = static::TYPES[$id];
        $class = __NAMESPACE__.'\\'.($op['named'] ? 'Named' : 'UnNamed');

        return static::$loaded[$id] = new $class(
            $id,
            $op['named'],
            $op['sep'],
            $op['empty'],
            $op['reserved'],
            $op['start'],
            $op['first']
        );
    }

    public static function isValid($id): bool
    {
        return isset(static::TYPES[$id]);
    }

    /**
     * @return string
     */
    protected function getRegex(): string
    {
        return match ($this->id) {
            '?', '&', '#' => self::QUERY_REGEX,
            default => self::PATH_REGEX,
        };
    }


}