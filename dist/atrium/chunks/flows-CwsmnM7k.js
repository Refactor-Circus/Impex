import { t as e } from "./ImpexFlash-0zPnKlOX.js";
import { t } from "./ImpexState-C2BI291k.js";
import { S as n, _ as r, h as i, w as a, y as o } from "./shared-_UUfUMm3.js";
import { AtriumIntegrationScreen as s, AtriumPageHeader as c, AtriumState as l, integrationData as u } from "@refactor-circus/atrium";
import { UBadge as d, UButton as f, UForm as p, UInput as m, UTable as h } from "@refactor-circus/atrium/ui";
import { computed as g, createElementBlock as _, createElementVNode as v, createTextVNode as y, createVNode as b, defineComponent as x, openBlock as S, reactive as C, toDisplayString as w, unref as T, withCtx as E } from "vue";
import { useI18n as D } from "vue-i18n";
import { useRouter as O } from "vue-router";
// src/pages/flows/index.vue?vue&type=script&setup=true&lang.ts
var k = {
	class: "space-y-4",
	"data-testid": "impex-flows"
}, A = { class: "space-y-4" }, j = ["data-testid"], M = { class: "font-medium" }, N = { class: "font-mono text-xs text-muted" }, P = /* @__PURE__ */ x({
	__name: "index",
	setup(x) {
		let { t: P } = D(), F = O(), I = a(async (e) => u(await e.GET("/flows")).data), L = n(), R = C({}), z = g(() => [
			{
				accessorKey: "slug",
				header: P("impex.flows.slug")
			},
			{
				accessorKey: "schedule",
				header: P("impex.flows.schedule"),
				meta: { class: { td: "font-mono text-xs" } }
			},
			{
				accessorKey: "enabled",
				header: P("impex.flows.state")
			},
			{
				id: "run",
				header: P("impex.flows.run")
			}
		]);
		async function B(e) {
			let t = I.client, n = r(R[e] ?? "");
			if (!t) return;
			let a = n && "value" in n ? n.value : void 0;
			if (n && (typeof a != "object" || !a)) {
				L.invalid("arguments", P("impex.flows.arguments_invalid"));
				return;
			}
			let s = i(a === void 0 ? {} : { arguments: a });
			await L.run(async () => u(await t.POST("/flows/{flow}/runs", {
				params: { path: { flow: e } },
				body: s
			})), async (t) => {
				R[e] = "", await F.push(o(t.data.id));
			});
		}
		return (n, r) => {
			let i = c, a = d, o = m, u = f, g = p, x = l, C = h, D = s;
			return S(), _("div", k, [b(i, { title: T(P)("impex.flows.title") }, null, 8, ["title"]), b(D, { "screen-key": "impex.flows" }, {
				default: E(() => [v("div", A, [b(e, { action: T(L) }, null, 8, ["action"]), b(t, {
					error: T(I).error.value,
					ready: T(I).data.value !== null
				}, {
					default: E(() => [b(C, {
						data: T(I).data.value ?? [],
						columns: T(z),
						"data-testid": "impex-flows-table"
					}, {
						"slug-cell": E(({ row: e }) => [v("div", { "data-testid": `impex-flow-row-${e.original.slug}` }, [v("p", M, w(e.original.slug), 1), v("p", N, w(e.original.class), 1)], 8, j)]),
						"schedule-cell": E(({ row: e }) => [y(w(e.original.schedule ?? T(P)("impex.common.none")), 1)]),
						"enabled-cell": E(({ row: e }) => [b(a, {
							color: e.original.enabled ? "success" : "neutral",
							variant: "subtle"
						}, {
							default: E(() => [y(w(e.original.enabled ? T(P)("impex.flows.enabled") : T(P)("impex.flows.disabled")), 1)]),
							_: 2
						}, 1032, ["color"])]),
						"run-cell": E(({ row: e }) => [b(g, {
							state: T(R),
							class: "flex items-center gap-2",
							"data-testid": `impex-flow-run-${e.original.slug}`,
							onSubmit: (t) => B(e.original.slug)
						}, {
							default: E(() => [b(o, {
								modelValue: T(R)[e.original.slug],
								"onUpdate:modelValue": (t) => T(R)[e.original.slug] = t,
								name: "arguments",
								placeholder: T(P)("impex.flows.arguments"),
								size: "sm",
								class: "w-56"
							}, null, 8, [
								"modelValue",
								"onUpdate:modelValue",
								"placeholder"
							]), b(u, {
								type: "submit",
								size: "sm",
								icon: "i-lucide-play",
								loading: T(L).busy.value
							}, {
								default: E(() => [y(w(T(P)("impex.flows.run")), 1)]),
								_: 1
							}, 8, ["loading"])]),
							_: 2
						}, 1032, [
							"state",
							"data-testid",
							"onSubmit"
						])]),
						empty: E(() => [b(x, {
							compact: "",
							title: T(P)("impex.flows.empty"),
							"data-testid": "impex-flows-empty"
						}, null, 8, ["title"])]),
						_: 1
					}, 8, ["data", "columns"])]),
					_: 1
				}, 8, ["error", "ready"])])]),
				_: 1
			})]);
		};
	}
});
export { P as default };
