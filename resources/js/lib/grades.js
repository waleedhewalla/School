/**
 * Mirrors App\Support\Grades\TermResults::subjectResult for live feedback
 * while typing marks. The server's calculation is the one that counts.
 */
export function subjectResult(components, scores, scale) {
    const totalWeight = components.reduce((sum, c) => sum + Number(c.weight), 0);
    if (!totalWeight) return null;

    let earned = 0;
    for (const c of components) {
        const value = scores[c.id];
        if (value === null || value === undefined || value === '') return null;
        const score = value === 'absent' ? 0 : Number(value);
        if (Number.isNaN(score) || score < 0 || score > Number(c.max_score)) return null;
        earned += (score / Number(c.max_score)) * Number(c.weight);
    }

    const percent = Math.round((earned / totalWeight) * 10000) / 100;
    const band = scale?.bands?.find((b) => percent >= Number(b.min_percent));

    return { percent, grade: band?.label ?? null, passed: scale ? percent >= Number(scale.pass_percent) : null };
}

/** "غ", "a" or "absent" typed in a mark cell means the student was absent. */
export function parseMark(text) {
    const value = String(text ?? '').trim().toLowerCase();
    if (value === '') return null;
    if (['غ', 'غائب', 'a', 'abs', 'absent'].includes(value)) return 'absent';
    return value.replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).replace('٫', '.');
}
