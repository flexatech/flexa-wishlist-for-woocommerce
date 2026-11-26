import { createRequire } from "node:module";
import type { Plugin } from "vite";

/**
 * Load React from WordPress instead of bundling it.
 *
 * WordPress already ships React (script handles "react", "react-dom" and, from
 * 6.6, "react-jsx-runtime"), and a bundled copy would be a second React that
 * breaks once WordPress moves to React 19 (Plugin Check: inlined_react,
 * inlined_react_dom, inlined_react_jsx_runtime). The bundle is an ES module,
 * so rollup's `external` + `globals` does not apply; instead every import of
 * these packages, from our code or from a library, resolves to a small module
 * that hands back the global WordPress set up. Enqueue.php adds the matching
 * script dependencies.
 *
 * Named exports are read from the installed packages at build time, so
 * `import { useState } from "react"` keeps working without a hand-kept list.
 */

const PREFIX = "\0wp-react-global:";

const require = createRequire(import.meta.url);

/**
 * The names a package exports. Double-underscore names (React's private
 * internals, __esModule) are left out: nothing outside React may use them.
 */
function exportNames(pkg: string): string[] {
    return Object.keys(require(pkg) as Record<string, unknown>).filter(
        (name) => name !== "default" && !name.startsWith("__") && /^[A-Za-z_$][\w$]*$/.test(name),
    );
}

function reexport(global: string, names: string[]): string {
    const lines = [`const mod = ${global};`, "export default mod;"];
    for (const name of names) {
        lines.push(`export const ${name} = mod.${name};`);
    }
    return lines.join("\n");
}

/**
 * WordPress before 6.6 has no "react-jsx-runtime" script, so the runtime
 * falls back to React.createElement, which is what the automatic JSX runtime
 * does under the hood.
 */
const JSX_RUNTIME = `
const React = window.React;
function create(type, props, key, spread) {
    const { children, ...rest } = props || {};
    if (key !== undefined) {
        rest.key = key;
    }
    if (children === undefined) {
        return React.createElement(type, rest);
    }
    return spread && Array.isArray(children)
        ? React.createElement(type, rest, ...children)
        : React.createElement(type, rest, children);
}
const runtime = window.ReactJSXRuntime || {
    Fragment: React.Fragment,
    jsx: (type, props, key) => create(type, props, key, false),
    jsxs: (type, props, key) => create(type, props, key, true),
};
export const Fragment = runtime.Fragment;
export const jsx = runtime.jsx;
export const jsxs = runtime.jsxs;
`;

export function wpReactGlobals(): Plugin {
    const modules: Record<string, () => string> = {
        react: () => reexport("window.React", exportNames("react")),
        "react-dom": () => reexport("window.ReactDOM", exportNames("react-dom")),
        "react-dom/client": () => reexport("window.ReactDOM", exportNames("react-dom/client")),
        "react/jsx-runtime": () => JSX_RUNTIME,
    };

    return {
        name: "wp-react-globals",
        // The dev server keeps its own React so Fast Refresh works.
        apply: "build",
        enforce: "pre",
        resolveId(id) {
            return id in modules ? PREFIX + id : null;
        },
        load(id) {
            if (!id.startsWith(PREFIX)) {
                return null;
            }
            const build = modules[id.slice(PREFIX.length)];
            return build ? build() : null;
        },
    };
}
