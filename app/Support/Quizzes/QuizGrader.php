<?php

namespace App\Support\Quizzes;

use App\Models\QuizQuestion;
use Illuminate\Support\Collection;

/**
 * Marks answers automatically. Choice questions must match exactly (all
 * right options and no wrong ones for "multiple"); short answers match any
 * accepted answer after normalising Arabic spelling (diacritics, alef
 * forms, taa marbuta, alef maqsura) and spaces.
 */
class QuizGrader
{
    /**
     * @param  Collection<int, QuizQuestion>  $questions
     * @param  array<int|string, mixed>  $answers  keyed by question id
     * @return array{score: float, max: float, results: array<int, bool>}
     */
    public static function grade(Collection $questions, array $answers): array
    {
        $score = 0.0;
        $results = [];

        foreach ($questions as $question) {
            $correct = self::isCorrect($question, $answers[$question->id] ?? null);
            $results[$question->id] = $correct;
            if ($correct) {
                $score += $question->points;
            }
        }

        return ['score' => round($score, 2), 'max' => round((float) $questions->sum('points'), 2), 'results' => $results];
    }

    public static function isCorrect(QuizQuestion $question, mixed $answer): bool
    {
        if ($answer === null || $answer === '' || $answer === []) {
            return false;
        }

        return match ($question->type) {
            'single' => is_numeric($answer) && (int) $answer === (int) ($question->correct[0] ?? -1),
            'true_false' => filter_var($answer, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === (bool) ($question->correct[0] ?? null),
            'multiple' => is_array($answer) && self::sameSet($answer, $question->correct),
            'short' => is_string($answer) && in_array(self::normalize($answer), array_map(self::normalize(...), $question->correct), true),
            default => false,
        };
    }

    public static function normalize(string $text): string
    {
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text); // diacritics, tatweel
        $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي']);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);

        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text)));
    }

    /** @param  array<int, mixed>  $a */
    private static function sameSet(array $a, array $b): bool
    {
        $a = array_map('intval', $a);
        $b = array_map('intval', $b);
        sort($a);
        sort($b);

        return array_values(array_unique($a)) === array_values(array_unique($b));
    }
}
