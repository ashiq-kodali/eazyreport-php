<?php

namespace EazyReport\Expressions;

class ExpressionEngine
{
    private static array $helpers = [];
    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) return;
        self::$initialized = true;
        self::registerDefaultHelpers();
    }

    public static function registerHelper(string $name, callable $fn): void
    {
        self::$helpers[strtolower($name)] = $fn;
    }

    public static function getPath(mixed $root, ?string $path): mixed
    {
        if ($root === null || $path === null || trim($path) === '') {
            return $root;
        }

        $parts = explode('.', $path);
        $curr = $root;

        foreach ($parts as $part) {
            if ($curr === null) return null;

            if (is_array($curr)) {
                if (array_key_exists($part, $curr)) {
                    $curr = $curr[$part];
                } else {
                    // Case-insensitive lookup
                    $found = false;
                    foreach ($curr as $k => $v) {
                        if (strcasecmp((string)$k, $part) === 0) {
                            $curr = $v;
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) return null;
                }
            } elseif (is_object($curr)) {
                if (isset($curr->{$part})) {
                    $curr = $curr->{$part};
                } elseif (method_exists($curr, $part)) {
                    $curr = $curr->{$part}();
                } else {
                    $getter = 'get' . ucfirst($part);
                    if (method_exists($curr, $getter)) {
                        $curr = $curr->{$getter}();
                    } else {
                        return null;
                    }
                }
            } else {
                return null;
            }
        }

        return $curr;
    }

    public static function evaluateExpression(string $expr, mixed $ctx): mixed
    {
        self::init();
        $expr = trim($expr);
        if ($expr === '') return null;

        $tokens = self::tokenize($expr);
        if (empty($tokens)) return null;

        $first = strtolower($tokens[0]['text']);

        if (isset(self::$helpers[$first])) {
            $args = [];
            for ($i = 1; $i < count($tokens); $i++) {
                $args[] = self::evaluateToken($tokens[$i], $ctx);
            }
            return call_user_func(self::$helpers[$first], $args);
        }

        return self::evaluateToken($tokens[0], $ctx);
    }

    public static function evaluateCondition(?string $expr, mixed $ctx): bool
    {
        if ($expr === null || trim($expr) === '') return true;
        $val = self::evaluateExpression($expr, $ctx);
        return self::isTruthy($val);
    }

    public static function evaluateTemplate(string $template, mixed $ctx): string
    {
        self::init();
        if ($template === '') return '';

        // Handle {{#if ...}} ... {{else}} ... {{/if}}
        $processed = preg_replace_callback('/\{\{#if\s+([^\}]+)\}\}(.*?)(?:\{\{else\}\}(.*?))?\{\{\/if\}\}/s', function ($m) use ($ctx) {
            $cond = trim($m[1]);
            $thenBlock = $m[2];
            $elseBlock = $m[3] ?? '';
            return self::evaluateCondition($cond, $ctx) ? self::evaluateTemplate($thenBlock, $ctx) : self::evaluateTemplate($elseBlock, $ctx);
        }, $template);

        // Handle {{#each ...}} ... {{/each}}
        $processed = preg_replace_callback('/\{\{#each\s+([^\}]+)\}\}(.*?)\{\{\/each\}\}/s', function ($m) use ($ctx) {
            $listExpr = trim($m[1]);
            $body = $m[2];
            $list = self::evaluateExpression($listExpr, $ctx);

            if (!is_iterable($list)) return '';

            $sb = '';
            $idx = 0;
            foreach ($list as $item) {
                $itemCtx = is_array($ctx) ? $ctx : [];
                $itemCtx['this'] = $item;
                $itemCtx['item'] = $item;
                $itemCtx['@index'] = $idx;
                $itemCtx['@first'] = ($idx === 0);

                if (is_array($item)) {
                    foreach ($item as $ik => $iv) {
                        $itemCtx[$ik] = $iv;
                    }
                }

                $sb .= self::evaluateTemplate($body, $itemCtx);
                $idx++;
            }
            return $sb;
        }, $processed);

        // Handle {{expression}} and {{{expression}}}
        return preg_replace_callback('/\{\{\{?([^\}]+)\}?\}\}/', function ($m) use ($ctx) {
            $inner = trim($m[1]);
            $val = self::evaluateExpression($inner, $ctx);
            return is_scalar($val) ? (string)$val : '';
        }, $processed);
    }

    private static function evaluateToken(array $token, mixed $ctx): mixed
    {
        if ($token['isSubExpr']) {
            return self::evaluateExpression($token['text'], $ctx);
        }

        $raw = $token['text'];

        if ((str_starts_with($raw, '"') && str_ends_with($raw, '"')) ||
            (str_starts_with($raw, "'") && str_ends_with($raw, "'"))) {
            return substr($raw, 1, -1);
        }

        if (strtolower($raw) === 'true') return true;
        if (strtolower($raw) === 'false') return false;
        if (strtolower($raw) === 'null') return null;
        if (is_numeric($raw)) return str_contains($raw, '.') ? (float)$raw : (int)$raw;

        return self::getPath($ctx, $raw);
    }

    public static function isTruthy(mixed $v): bool
    {
        if ($v === null) return false;
        if (is_bool($v)) return $v;
        if (is_numeric($v)) return (float)$v != 0.0;
        if (is_string($v)) {
            $t = strtolower(trim($v));
            return ($t !== '' && $t !== 'false' && $t !== '0' && $t !== 'null' && $t !== 'undefined');
        }
        if (is_countable($v)) return count($v) > 0;
        return true;
    }

    private static function tokenize(string $input): array
    {
        $tokens = [];
        $i = 0;
        $n = strlen($input);

        while ($i < $n) {
            while ($i < $n && ctype_space($input[$i])) $i++;
            if ($i >= $n) break;

            $ch = $input[$i];

            if ($ch === '"' || $ch === "'") {
                $quote = $ch;
                $i++;
                $sb = '';
                while ($i < $n && $input[$i] !== $quote) {
                    if ($input[$i] === '\\' && $i + 1 < $n) {
                        $i++;
                        $sb .= $input[$i];
                    } else {
                        $sb .= $input[$i];
                    }
                    $i++;
                }
                if ($i < $n) $i++; // close quote
                $tokens[] = ['text' => "\"{$sb}\"", 'isSubExpr' => false];
                continue;
            }

            if ($ch === '(') {
                $i++;
                $depth = 1;
                $sb = '';
                while ($i < $n && $depth > 0) {
                    if ($input[$i] === '(') $depth++;
                    elseif ($input[$i] === ')') $depth--;

                    if ($depth > 0) $sb .= $input[$i];
                    $i++;
                }
                $tokens[] = ['text' => trim($sb), 'isSubExpr' => true];
                continue;
            }

            $word = '';
            while ($i < $n && !ctype_space($input[$i]) && $input[$i] !== ')' && $input[$i] !== '"' && $input[$i] !== "'") {
                $word .= $input[$i];
                $i++;
            }
            $tokens[] = ['text' => $word, 'isSubExpr' => false];
        }

        return $tokens;
    }

    private static function registerDefaultHelpers(): void
    {
        // Formatting
        self::registerHelper('formatcurrency', function (array $args) {
            $val = $args[0] ?? null;
            $cur = (string)($args[1] ?? 'USD');
            $dec = (int)Formatters::toDouble($args[2] ?? 2);
            return Formatters::formatCurrency($val, $cur, $dec);
        });

        self::registerHelper('formatnumber', function (array $args) {
            $val = $args[0] ?? null;
            $dec = (int)Formatters::toDouble($args[1] ?? 2);
            $thousands = !isset($args[2]) || self::isTruthy($args[2]);
            return Formatters::formatNumber($val, $dec, $thousands);
        });

        self::registerHelper('formatdate', function (array $args) {
            $val = $args[0] ?? null;
            $pattern = (string)($args[1] ?? 'd/m/Y');
            $dt = Formatters::toDate($val);
            return $dt ? Formatters::formatDatePattern($dt, $pattern) : (string)($val ?? '');
        });

        self::registerHelper('formatpercent', function (array $args) {
            $val = Formatters::toDouble($args[0] ?? 0);
            $dec = (int)Formatters::toDouble($args[1] ?? 0);
            return number_format($val, $dec, '.', '') . '%';
        });

        self::registerHelper('inwords', function (array $args) {
            $val = Formatters::toDouble($args[0] ?? 0);
            $cur = (string)($args[1] ?? '');
            return NumberToWords::convert($val, $cur);
        });

        self::registerHelper('numbertowords', function (array $args) {
            $val = Formatters::toDouble($args[0] ?? 0);
            $cur = (string)($args[1] ?? '');
            return NumberToWords::convert($val, $cur);
        });

        // Math
        self::registerHelper('add', fn($args) => count($args) < 2 ? 0.0 : Formatters::toDouble($args[0]) + Formatters::toDouble($args[1]));
        self::registerHelper('sub', fn($args) => count($args) < 2 ? 0.0 : Formatters::toDouble($args[0]) - Formatters::toDouble($args[1]));
        self::registerHelper('mul', fn($args) => count($args) < 2 ? 0.0 : Formatters::toDouble($args[0]) * Formatters::toDouble($args[1]));
        self::registerHelper('div', function ($args) {
            if (count($args) < 2) return 0.0;
            $d = Formatters::toDouble($args[1]);
            return $d == 0.0 ? 0.0 : Formatters::toDouble($args[0]) / $d;
        });
        self::registerHelper('round', fn($args) => round(Formatters::toDouble($args[0] ?? 0), (int)Formatters::toDouble($args[1] ?? 0)));
        self::registerHelper('abs', fn($args) => abs(Formatters::toDouble($args[0] ?? 0)));

        // Logic
        self::registerHelper('eq', fn($args) => count($args) >= 2 && $args[0] == $args[1]);
        self::registerHelper('neq', fn($args) => count($args) < 2 || $args[0] != $args[1]);
        self::registerHelper('gt', fn($args) => count($args) >= 2 && Formatters::toDouble($args[0]) > Formatters::toDouble($args[1]));
        self::registerHelper('gte', fn($args) => count($args) >= 2 && Formatters::toDouble($args[0]) >= Formatters::toDouble($args[1]));
        self::registerHelper('lt', fn($args) => count($args) >= 2 && Formatters::toDouble($args[0]) < Formatters::toDouble($args[1]));
        self::registerHelper('lte', fn($args) => count($args) >= 2 && Formatters::toDouble($args[0]) <= Formatters::toDouble($args[1]));
        self::registerHelper('and', function ($args) {
            foreach ($args as $a) if (!self::isTruthy($a)) return false;
            return true;
        });
        self::registerHelper('or', function ($args) {
            foreach ($args as $a) if (self::isTruthy($a)) return true;
            return false;
        });
        self::registerHelper('not', fn($args) => !self::isTruthy($args[0] ?? false));
        self::registerHelper('if', fn($args) => self::isTruthy($args[0] ?? null) ? ($args[1] ?? null) : ($args[2] ?? null));

        // Strings
        self::registerHelper('uppercase', fn($args) => strtoupper((string)($args[0] ?? '')));
        self::registerHelper('lowercase', fn($args) => strtolower((string)($args[0] ?? '')));
        self::registerHelper('trim', fn($args) => trim((string)($args[0] ?? '')));
        self::registerHelper('concat', fn($args) => implode('', array_map('strval', $args)));
        self::registerHelper('replace', fn($args) => str_replace((string)($args[1] ?? ''), (string)($args[2] ?? ''), (string)($args[0] ?? '')));

        // Aggregates
        self::registerHelper('sum', function ($args) {
            $list = $args[0] ?? [];
            if (!is_iterable($list)) return 0.0;
            $prop = (string)($args[1] ?? '');
            $total = 0.0;
            foreach ($list as $item) {
                $val = ($prop === '') ? $item : self::getPath($item, $prop);
                $total += Formatters::toDouble($val);
            }
            return $total;
        });

        self::registerHelper('count', fn($args) => is_countable($args[0] ?? null) ? count($args[0]) : 0);
    }
}
