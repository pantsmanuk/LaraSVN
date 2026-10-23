<?php

namespace App\Svn;

final class Limits
{
    public static function get(string $name): int
    {
        $value = filter_var(config('svn.limits.'.$name), FILTER_VALIDATE_INT);
        abort_if($value === false || $value < 1 || $value > 2147483647, 503, 'Invalid SVN resource limit: '.$name.'. Use a positive integer.');

        return $value;
    }
}
