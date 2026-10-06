<?php

namespace App\Support\Import;

use App\Models\GradeLevel;
use App\Support\ArabicName;
use Illuminate\Support\Collection;

/**
 * Understands the many ways Saudi files write a grade: the full name
 * ("الصف الأول الابتدائي"), short forms ("أول متوسط", "ثالث ثانوي",
 * "1 متوسط"), feminine forms ("الأولى", "ثانية"), or the overall grade
 * number ("الصف 7", "7"). KG levels: "روضة أولى", "المستوى الثاني", "KG 3".
 */
class GradeNameResolver
{
    private const ORDINALS = [
        'اول' => 1, 'اولي' => 1, 'الاول' => 1, 'الاولي' => 1,
        'ثاني' => 2, 'ثانيه' => 2, 'الثاني' => 2, 'الثانيه' => 2,
        'ثالث' => 3, 'ثالثه' => 3, 'الثالث' => 3, 'الثالثه' => 3,
        'رابع' => 4, 'رابعه' => 4, 'الرابع' => 4, 'الرابعه' => 4,
        'خامس' => 5, 'خامسه' => 5, 'الخامس' => 5, 'الخامسه' => 5,
        'سادس' => 6, 'سادسه' => 6, 'السادس' => 6, 'السادسه' => 6,
    ];

    private const STAGES = [
        'kg' => ['روضه', 'الروضه', 'رياض', 'تمهيدي', 'التمهيدي', 'المستوي', 'مستوي', 'kg'],
        'primary' => ['ابتدائي', 'الابتدائي', 'ابتدائيه', 'الابتدائيه'],
        'intermediate' => ['متوسط', 'المتوسط', 'متوسطه', 'المتوسطه'],
        'secondary' => ['ثانوي', 'الثانوي', 'ثانويه', 'الثانويه'],
    ];

    /** @param  Collection<int, GradeLevel>  $grades  with stage loaded */
    public function __construct(private Collection $grades) {}

    public function resolve(string $value): ?GradeLevel
    {
        $wanted = ArabicName::normalize(strtr($value, self::DIGITS));
        if ($wanted === '') {
            return null;
        }

        // 1. Exact name match (Arabic or English).
        $exact = $this->grades->first(fn (GradeLevel $g) => ArabicName::normalize($g->name_ar) === $wanted
            || ($g->name_en && ArabicName::normalize($g->name_en) === $wanted));
        if ($exact) {
            return $exact;
        }

        $words = preg_split('/[\s\-_]+/u', str_replace('الصف', ' ', $wanted), -1, PREG_SPLIT_NO_EMPTY);
        $stage = $this->stageIn($words);
        $number = $this->numberIn($words);
        if ($number === null) {
            return null;
        }

        // 2. Ordinal within a stage: "أول متوسط" → intermediate, sequence 1.
        if ($stage !== null) {
            return $this->grades->first(fn (GradeLevel $g) => $g->stage->code === $stage && (int) $g->sequence === $number);
        }

        // 3. A bare number is the overall general-education grade (1–12).
        $general = $this->grades->filter(fn (GradeLevel $g) => $g->stage->code !== 'kg')
            ->sortBy([fn ($a, $b) => $a->stage->sequence <=> $b->stage->sequence, fn ($a, $b) => $a->sequence <=> $b->sequence])
            ->values();

        return $general[$number - 1] ?? null;
    }

    private function stageIn(array $words): ?string
    {
        foreach (self::STAGES as $code => $names) {
            if (array_intersect($words, $names) !== []) {
                return $code;
            }
        }

        return null;
    }

    private function numberIn(array $words): ?int
    {
        foreach ($words as $word) {
            if (ctype_digit($word) && (int) $word >= 1 && (int) $word <= 12) {
                return (int) $word;
            }
            if (isset(self::ORDINALS[$word])) {
                return self::ORDINALS[$word];
            }
        }

        return null;
    }

    private const DIGITS = ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'];
}
