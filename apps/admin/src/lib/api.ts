import { getPluginGlobal } from "./wp";

type Json = Record<string, unknown> | unknown[] | string | number | boolean | null;

/** The envelope every REST route returns (see BaseRestController). */
interface Envelope<T> {
    success: boolean;
    message?: string;
    data?: T;
}

export class ApiError extends Error {
    constructor(
        message: string,
        public readonly status: number,
        public readonly code?: string,
    ) {
        super(message);
        this.name = "ApiError";
    }
}

function baseUrl(): string {
    const { restUrl, restBase } = getPluginGlobal();
    return restUrl.replace(/\/$/, "") + "/" + restBase.replace(/^\/|\/$/g, "") + "/";
}

async function request<T>(
    method: "GET" | "POST" | "PATCH" | "DELETE",
    path: string,
    body?: Json,
): Promise<T> {
    const { restNonce } = getPluginGlobal();
    const url = baseUrl() + path.replace(/^\//, "");

    const response = await fetch(url, {
        method,
        credentials: "same-origin",
        headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": restNonce,
        },
        body: body !== undefined ? JSON.stringify(body) : undefined,
    });

    const parsed = (await response.json().catch(() => null)) as
        | Envelope<T>
        | { code?: string; message?: string }
        | null;

    // Transport-level failure (non-2xx): surface the WP error message/code.
    if (!response.ok) {
        const message =
            (parsed && typeof parsed === "object" && "message" in parsed &&
            typeof parsed.message === "string"
                ? parsed.message
                : null) ?? response.statusText;
        const code =
            parsed && typeof parsed === "object" && "code" in parsed &&
            typeof parsed.code === "string"
                ? parsed.code
                : undefined;
        throw new ApiError(message, response.status, code);
    }

    // Application-level envelope: `{ success, message?, data }`.
    const envelope = parsed as Envelope<T> | null;
    if (!envelope || typeof envelope !== "object" || !("success" in envelope)) {
        throw new ApiError("Unexpected response from the server.", response.status);
    }
    if (!envelope.success) {
        throw new ApiError(envelope.message ?? "Request failed.", response.status);
    }

    return envelope.data as T;
}

export const api = {
    get: <T>(path: string) => request<T>("GET", path),
    post: <T>(path: string, body?: Json) => request<T>("POST", path, body),
    patch: <T>(path: string, body?: Json) => request<T>("PATCH", path, body),
    delete: <T>(path: string, body?: Json) => request<T>("DELETE", path, body),
};
