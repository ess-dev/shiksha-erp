export type JsonResult<T> = {
    ok: boolean;
    status: number;
    data: T | null;
    /** Validation or rule messages from a 422 response, first message of each field. */
    errors: Record<string, string>;
};

/**
 * Laravel's CSRF token, read from the XSRF-TOKEN cookie it sets for the page.
 */
function xsrfToken(): string {
    const entry = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='));

    return entry === undefined
        ? ''
        : decodeURIComponent(entry.slice('XSRF-TOKEN='.length));
}

/**
 * A plain JSON request that does not change the page (the import preview, the
 * sibling search). Page navigations and saves go through Inertia instead.
 */
export async function requestJson<T>(
    url: string,
    init: { method?: 'GET' | 'POST'; body?: FormData } = {},
): Promise<JsonResult<T>> {
    const response = await fetch(url, {
        method: init.method ?? 'GET',
        body: init.body,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
    });

    const text = await response.text();
    let json: unknown = null;

    try {
        json = text === '' ? null : JSON.parse(text);
    } catch {
        json = null;
    }

    const errors: Record<string, string> = {};

    if (
        !response.ok &&
        typeof json === 'object' &&
        json !== null &&
        'errors' in json &&
        typeof json.errors === 'object' &&
        json.errors !== null
    ) {
        for (const [field, messages] of Object.entries(json.errors)) {
            if (Array.isArray(messages) && typeof messages[0] === 'string') {
                errors[field] = messages[0];
            }
        }
    }

    return {
        ok: response.ok,
        status: response.status,
        data: response.ok ? (json as T) : null,
        errors,
    };
}
