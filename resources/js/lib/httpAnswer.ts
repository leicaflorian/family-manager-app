/**
 * Reading the answers of the HTTP client of Inertia (`http.getClient()`): the body as an object, whatever its kind,
 * and the status and body of a request that failed. Used by the requests that the pages make outside of a visit
 * (the scan, the field *How it shows in the bank*).
 */

/** The body of an answer read as an object. */
export type JsonBody = Record<string, unknown>;

/**
 * A body read as a JSON object.
 *
 * @param data - the body, as text or already read by the client
 * @returns the object, or null when it is not one
 */
export function jsonBody(data: unknown): JsonBody | null {
    if (data !== null && typeof data === 'object') {
        return data as JsonBody;
    }

    if (typeof data !== 'string') {
        return null;
    }

    try {
        const parsed: unknown = JSON.parse(data);

        return parsed !== null && typeof parsed === 'object'
            ? (parsed as JsonBody)
            : null;
    } catch {
        return null;
    }
}

/**
 * The status and the body of an error of the HTTP client, whatever its kind.
 *
 * @param error - what the client threw
 * @returns the status (0 without an answer) and the body read as JSON when it is
 */
export function failedAnswer(error: unknown): {
    status: number;
    body: JsonBody | null;
} {
    const response = (
        error as { response?: { status?: number; data?: unknown } } | null
    )?.response;
    const status = typeof response?.status === 'number' ? response.status : 0;

    return { status, body: jsonBody(response?.data) };
}

/**
 * The message the server wrote in its answer, if it wrote one.
 *
 * @param body - the answer read as JSON
 * @returns the message, or null
 */
export function serverMessage(body: JsonBody | null): string | null {
    return typeof body?.message === 'string' && body.message !== ''
        ? body.message
        : null;
}
