<?php

namespace App\Support;

use App\Models\BehaviourIncident;
use Illuminate\Support\Collection;

/**
 * Behaviour score per student for a year: the starting score (100) minus
 * points for violations plus points for distinguished behaviour, kept
 * between 0 and the starting score.
 */
class BehaviourScore
{
    /**
     * @param  iterable<int>  $studentIds
     * @return Collection<int, array{score: int, negative: int, positive: int}> keyed by student id
     */
    public static function forStudents(iterable $studentIds, int $yearId): Collection
    {
        $start = (int) config('madrasa_behaviour.starting_score', 100);
        $rows = BehaviourIncident::query()
            ->join('behaviour_categories', 'behaviour_categories.id', '=', 'behaviour_incidents.behaviour_category_id')
            ->whereIn('behaviour_incidents.student_id', collect($studentIds)->all())
            ->where('behaviour_incidents.academic_year_id', $yearId)
            ->selectRaw('behaviour_incidents.student_id, behaviour_categories.kind, sum(behaviour_categories.points) as total')
            ->groupBy('behaviour_incidents.student_id', 'behaviour_categories.kind')
            ->get();

        return collect($studentIds)->mapWithKeys(function (int $id) use ($rows, $start) {
            $negative = (int) $rows->where('student_id', $id)->where('kind', 'negative')->sum('total');
            $positive = (int) $rows->where('student_id', $id)->where('kind', 'positive')->sum('total');

            return [$id => ['score' => max(0, min($start, $start - $negative + $positive)), 'negative' => $negative, 'positive' => $positive]];
        });
    }
}
