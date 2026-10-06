<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case AssessmentScheduled = 'assessment_scheduled';
    case Assessed = 'assessed';
    case Offered = 'offered';
    case Waitlisted = 'waitlisted';
    case Accepted = 'accepted';
    case Enrolled = 'enrolled';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    /** @return list<self> where staff (or the family, for accept/withdraw) may move it next */
    public function next(): array
    {
        return match ($this) {
            self::Submitted => [self::UnderReview, self::Rejected, self::Withdrawn],
            self::UnderReview => [self::AssessmentScheduled, self::Offered, self::Waitlisted, self::Rejected, self::Withdrawn],
            self::AssessmentScheduled => [self::Assessed, self::Rejected, self::Withdrawn],
            self::Assessed => [self::Offered, self::Waitlisted, self::Rejected, self::Withdrawn],
            self::Waitlisted => [self::Offered, self::Rejected, self::Withdrawn],
            self::Offered => [self::Accepted, self::Rejected, self::Withdrawn],
            self::Accepted => [self::Enrolled, self::Withdrawn],
            self::Enrolled, self::Rejected, self::Withdrawn => [],
        };
    }

    public function canMoveTo(self $to): bool
    {
        return in_array($to, $this->next(), true);
    }

    /** Statuses that hold one of the window's seats. */
    public static function holdingSeat(): array
    {
        return [self::Offered, self::Accepted, self::Enrolled];
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Enrolled, self::Rejected, self::Withdrawn], true);
    }
}
