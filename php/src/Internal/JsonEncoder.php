<?php

declare(strict_types=1);

namespace Spart\Sdk\Internal;

use Spart\Sdk\Models\Money;

/**
 * Internal — outside the SemVer BC promise.
 *
 * Encodes a payload to JSON, preserving the exact decimal lexeme of any
 * {@see Money} value (server expects unquoted JSON numerics for `decimal`
 * fields and does not coerce strings to decimals). Implementation:
 * 1. Recursively rewrite each {@see Money} into a `{value: <sentinel>,
 *    currency: <code>}` array, where the sentinel is a per-call random
 *    string (so that user-supplied string fields cannot collide).
 * 2. Encode normally.
 * 3. Substitute each quoted sentinel string token for the raw decimal lexeme.
 *
 * @internal
 */
final class JsonEncoder
{
    private const SENTINEL_PREFIX = '@@MONEY:';
    private const SENTINEL_SUFFIX = '@@';

    public static function encode(mixed $value): string
    {
        // A nonce unique to this encode call prevents collisions with user strings
        // that happen to resemble the sentinel format.
        $nonce = bin2hex(random_bytes(8));

        $lexemes = [];
        $rewritten = self::rewrite($value, $lexemes, $nonce);
        $json = json_encode($rewritten, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        foreach ($lexemes as $i => $lexeme) {
            $sentinel = '"' . self::SENTINEL_PREFIX . $nonce . ':' . $i . self::SENTINEL_SUFFIX . '"';
            // Replace exactly once (the encoded sentinel is unique per index by construction).
            $pos = strpos($json, $sentinel);
            if ($pos === false) {
                throw new \LogicException("Money sentinel #{$i} missing from JSON output.");
            }
            $json = substr_replace($json, $lexeme, $pos, strlen($sentinel));
        }

        return $json;
    }

    /**
     * @param array<int,string> $lexemes Out-param accumulator (index = sentinel id, value = decimal lexeme).
     */
    private static function rewrite(mixed $value, array &$lexemes, string $nonce): mixed
    {
        if ($value instanceof Money) {
            $i = count($lexemes);
            $lexemes[] = $value->value;
            return [
                'value' => self::SENTINEL_PREFIX . $nonce . ':' . $i . self::SENTINEL_SUFFIX,
                'currency' => $value->currency,
            ];
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = self::rewrite($v, $lexemes, $nonce);
            }
            return $out;
        }
        if (is_object($value)) {
            throw new \InvalidArgumentException(
                'JsonEncoder only accepts scalar|array|Money trees; got ' . get_class($value)
            );
        }
        return $value;
    }
}
