import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Settings } from "@/types/global";

/** GET/POST /settings both return the full object under `data.settings`. */
interface SettingsEnvelope {
    settings: Settings;
}

const SETTINGS_KEY = ["settings"] as const;

export function useSettings() {
    return useQuery<Settings>({
        queryKey: SETTINGS_KEY,
        queryFn: async () => {
            const res = await api.get<SettingsEnvelope>("settings");
            return res.settings;
        },
    });
}

/** A partial payload: any subset of settings groups. PHP merges over stored. */
export type SettingsPatch = Partial<{
    [K in keyof Settings]: Partial<Settings[K]>;
}>;

/** Merge a per-group partial patch over a full settings object, key-correlated
 *  so each group keeps its own type. */
function mergePatch(base: Settings, patch: SettingsPatch): Settings {
    const next: Record<string, unknown> = { ...base };
    for (const group of Object.keys(patch)) {
        next[group] = {
            ...(base[group as keyof Settings] as object),
            ...(patch[group as keyof Settings] as object),
        };
    }
    return next as unknown as Settings;
}

export function useSaveSettings() {
    const qc = useQueryClient();
    return useMutation({
        // Partial payload: only the changed groups. The PHP controller merges
        // the sanitized payload over what is stored, so sending the whole blob
        // would race a concurrent edit.
        mutationFn: async (input: SettingsPatch) => {
            const res = await api.post<SettingsEnvelope>(
                "settings",
                input as Record<string, unknown>,
            );
            return res.settings;
        },
        onMutate: async (input) => {
            await qc.cancelQueries({ queryKey: SETTINGS_KEY });
            const prev = qc.getQueryData<Settings>(SETTINGS_KEY);
            if (prev) {
                // Merge each changed group over the previous settings.
                const next = mergePatch(prev, input);
                qc.setQueryData<Settings>(SETTINGS_KEY, next);
            }
            return { prev };
        },
        onError: (_err, _vars, ctx) => {
            if (ctx?.prev) {
                qc.setQueryData(SETTINGS_KEY, ctx.prev);
            }
        },
        onSuccess: (next) => {
            qc.setQueryData(SETTINGS_KEY, next);
        },
    });
}

export function useResetAllData() {
    const qc = useQueryClient();
    return useMutation({
        // The route is /settings/reset; the server runs Resetter::reset_all()
        // and wipes data. We still send the confirm phrase for symmetry with
        // the typed-confirmation UX.
        mutationFn: (confirm: string) =>
            api.post<unknown>("settings/reset", { confirm }),
        onSuccess: () => {
            void qc.invalidateQueries();
        },
    });
}
