import { usePage } from '@inertiajs/vue3';

/**
 * UI strings use English text as the key, like Laravel's __() with
 * lang/ar.json. Missing keys fall back to the English text.
 *
 *   const t = useT();  t('Students')  t('Hello :name', { name })
 */
export function useT() {
    const page = usePage();

    return (key, replacements = {}) => {
        let text = page.props.translations?.[key] ?? key;
        for (const [name, value] of Object.entries(replacements)) {
            text = text.replaceAll(`:${name}`, value);
        }
        return text;
    };
}

/** Formats a Y-m-d date for display in the active language (Gregorian). */
export function formatDate(value, locale) {
    if (!value) return '';
    return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-SA-u-ca-gregory-nu-latn' : 'en-GB', {
        day: 'numeric', month: 'short', year: 'numeric',
    }).format(new Date(`${value}T00:00:00`));
}
