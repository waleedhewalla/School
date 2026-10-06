<?php

namespace App\Support;

class ArabicName
{
    /** Particles that belong to the next word: عبد الله، أبو بكر، آل سعود. */
    private const JOIN_WITH_NEXT = ['عبد', 'ابو', 'أبو', 'آل'];

    /**
     * Splits a full Arabic name into the four parts used by Noor:
     * first, father, grandfather, family. Two words → first + family;
     * three → first + father + family; five or more → the tail is the family.
     *
     * @return array{first: string, father: ?string, grandfather: ?string, family: string}
     */
    public static function split(string $full): array
    {
        $words = preg_split('/\s+/u', trim(preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $full)), -1, PREG_SPLIT_NO_EMPTY);

        $parts = [];
        for ($i = 0; $i < count($words); $i++) {
            if (in_array($words[$i], self::JOIN_WITH_NEXT, true) && isset($words[$i + 1])) {
                $parts[] = $words[$i].' '.$words[++$i];
            } else {
                $parts[] = $words[$i];
            }
        }

        return match (count($parts)) {
            0 => ['first' => '', 'father' => null, 'grandfather' => null, 'family' => ''],
            1 => ['first' => $parts[0], 'father' => null, 'grandfather' => null, 'family' => ''],
            2 => ['first' => $parts[0], 'father' => null, 'grandfather' => null, 'family' => $parts[1]],
            3 => ['first' => $parts[0], 'father' => $parts[1], 'grandfather' => null, 'family' => $parts[2]],
            default => ['first' => $parts[0], 'father' => $parts[1], 'grandfather' => $parts[2], 'family' => implode(' ', array_slice($parts, 3))],
        };
    }

    /** For loose matching: removes diacritics/tatweel and unifies alef, yaa and taa marbuta forms. */
    public static function normalize(string $text): string
    {
        $text = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $text);
        $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه']);

        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text)));
    }
}
