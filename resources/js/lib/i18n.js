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

/** Wraps codes like 1449-0006 so they keep their order inside Arabic text. */
export const ltr = (value) => `\u2066${value}\u2069`;

/** Formats an ISO date-time for display in the active language (Gregorian, Latin digits). */
export function formatDateTime(value, locale, withYear = false) {
    if (!value) return '';
    return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-SA-u-ca-gregory-nu-latn' : 'en-GB', {
        weekday: 'short', day: 'numeric', month: 'short', ...(withYear ? { year: 'numeric' } : {}), hour: '2-digit', minute: '2-digit',
    }).format(new Date(value));
}
