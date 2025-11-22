<?php

namespace App\Services;

class AbiEncoder
{
    public static function encodeFunctionSelector(string $signature): string
    {
        return substr(keccak256($signature), 0, 8);
    }

    public static function encodeAddress(string $addr): string
    {
        $clean = strtolower(str_replace('0x', '', $addr));
        return str_pad($clean, 64, '0', STR_PAD_LEFT);
    }

    public static function encodeUint(int $value): string
    {
        return str_pad(dechex($value), 64, '0', STR_PAD_LEFT);
    }

    public static function encodeString(string $value): string
    {
        $hex = bin2hex($value);
        $len = strlen($value);

        $lenEncoded = self::encodeUint($len);

        $padded = $hex;
        while (strlen($padded) % 64 !== 0) {
            $padded .= '0';
        }

        return $lenEncoded . $padded;
    }

    public static function encodeMintTo(string $to, string $uri): string
    {
        $selector = self::encodeFunctionSelector("mintTo(address,string)");

        // 32-byte offset for string (address is first argument, string second)
        $address = self::encodeAddress($to);
        $offset = self::encodeUint(32); // string offset = 32 bytes after first arg

        $string = self::encodeString($uri);

        return "0x" . $selector . $address . $offset . $string;
    }
}

function keccak256($input)
{
    return bin2hex(\kornrunner\Keccak::hash($input, 256));
}
