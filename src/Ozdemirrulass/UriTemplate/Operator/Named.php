<?php

declare(strict_types=1);

namespace Ozdemirrulass\UriTemplate\Operator;

use Ozdemirrulass\UriTemplate\Node\Variable;

class Named extends Abstraction
{
    public function toRegex(Variable $var): string
    {
        $name = $var->name;
        $value = $this->getRegex();
        $options = $var->options;

        if ($options['modifier']) {
            switch ($options['modifier']) {
                case '*':
                    $regex = "{$name}+=(?:{$value}+(?:{$this->sep}{$name}+={$value}*)*)"
                        ."|{$value}+=(?:{$value}+(?:{$this->sep}{$value}+={$value}*)*)";
                    break;
                case ':':
                    $regex = "{$value}\{0,{$options['value']}\}";
                    break;

                case '%':
                    $name = $name.'+(?:%5B|\[)[^=]*=';
                    $regex = "{$name}(?:{$value}+(?:{$this->sep}{$name}{$value}*)*)";
                    break;
                default:
                    throw new \Exception("Unknown modifier `{$options['modifier']}`");
            }
        } else {
            $regex = "{$name}=(?:{$value}+(?:,{$value}+)*)*";
        }

        return '(?:&)?'.$regex;
    }

    public function expandString(Variable $var, $val): string
    {
        $val = (string)$val;
        $options = $var->options;
        $result = $this->encode($var, $var->name);

        if ($val === '') {
            return $result.$this->empty;
        } else {
            $result .= '=';
        }

        if ($options['modifier'] === ':') {
            $val = mb_substr($val, 0, (int)$options['value']);
        }

        return $result.$this->encode($var, $val);
    }

    public function expandNonExplode(Variable $var, array $val): ?string
    {
        if (empty($val)) {
            return null;
        }

        $result = $this->encode($var, $var->name);

        $result .= '=';

        return $result.$this->encode($var, $val);
    }

    public function expandExplode(Variable $var, array $val): ?string
    {
        if (empty($val)) {
            return null;
        }
        $list = isset($val[0]);
        $data = array();
        foreach ($val as $k => $v) {
            $key = $list ? $var->name : $k;
            if ($list) {
                $data[$key][] = $v;
            } else {
                $data[$key] = $v;
            }
        }

        if (!$list and $var->options['modifier'] === '%') {
            $data = array($var->name => $data);
        }

        return $this->encodeExplodeVars($var, $data);
    }

    public function extract(Variable $var, $data)
    {
        if ($data[0] === '&') {
            $data = substr($data, 1);
        }

        $value = $data;
        $vals = explode($this->sep, $data);
        $options = $var->options;

        switch ($options['modifier']) {
            case '%':
                parse_str($data, $query);

                return $query[$var->name];

            case '*':
                $data = array();

                foreach ($vals as $val) {
                    list($k, $v) = explode('=', $val);

                    // 2
                    if ($k === $var->getToken()) {
                        $data[] = $v;
                    } // 4
                    else {
                        $data[$k] = $v;
                    }
                }

                break;
            case ':':
                break;
            default:
                $value = str_replace($var->getToken().'=', '', $value);
                $data = explode(',', $value);

                if (sizeof($data) === 1) {
                    $data = current($data);
                }
        }

        return $this->decode($data);
    }

    public function encodeExplodeVars(Variable $var, $data): array|string|null
    {
        $query = http_build_query($data, '', $this->sep);
        $query = str_replace('+', '%20', $query);

        if ($var->options['modifier'] === '%') {
            $query = preg_replace('#%5B\d+%5D#', '%5B%5D', $query);
        } else {
            $query = preg_replace('#%5B\d+%5D#', '', $query);
        }

        if ($this->reserved) {
            $query = str_replace(
                array_keys(static::RESERVED_CHARS),
                static::RESERVED_CHARS,
                $query
            );
        }

        return $query;
    }
}
