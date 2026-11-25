import { t as e } from "./ImpexState-C2BI291k.js";
import { t } from "./ImpexStatusBadge-0Fh6yTGt.js";
import { C as n, o as r, p as i, s as a, v as o, y as s } from "./shared-_UUfUMm3.js";
import { AtriumIntegrationScreen as c, AtriumPageHeader as l, AtriumSelect as u, AtriumState as d, integrationData as f } from "@refactor-circus/atrium";
import { UButton as p, UForm as m, UInput as h, ULink as g, UTable as _ } from "@refactor-circus/atrium/ui";
import { computed as v, createBlock as y, createCommentVNode as b, createElementBlock as x, createElementVNode as S, createTextVNode as C, createVNode as w, defineComponent as T, openBlock as E, reactive as D, toDisplayString as O, unref as k, watch as A, withCtx as j } from "vue";
import { useI18n as M } from "vue-i18n";
import { useRoute as N, useRouter as P } from "vue-router";
// src/pages/runs/index.vue?vue&type=script&setup=true&lang.ts
var F = {
	class: "space-y-4",
	"data-testid": "impex-runs"
}, I = { class: "space-y-4" }, L = /* @__PURE__ */ T({
	__name: "index",
	setup(T) {
		let { t: L } = M(), R = N(), z = P(), B = v(() => ({
			status: o(R.query.status),
			trigger: o(R.query.trigger),
			flow: o(R.query.flow)
		})), V = D({ ...B.value });
		A(B, (e) => {
			Object.assign(V, e);
		});
		let H = n(async (e, t) => f(await e.GET("/runs", { params: { query: {
			...B.value,
			cursor: t
		} } })), B), U = v(() => [
			{
				accessorKey: "flow",
				header: L("impex.runs.flow")
			},
			{
				accessorKey: "status",
				header: L("impex.runs.status")
			},
			{
				accessorKey: "trigger",
				header: L("impex.runs.trigger")
			},
			{
				accessorKey: "started_at",
				header: L("impex.runs.started")
			},
			{
				accessorKey: "finished_at",
				header: L("impex.runs.finished")
			}
		]), W = v(() => r.map((e) => ({
			label: L(`impex.statuses.${e}`),
			value: e
		}))), G = v(() => a.map((e) => ({
			label: e,
			value: e
		})));
		function K() {
			z.replace({ query: {
				...R.query,
				status: V.status || void 0,
				trigger: V.trigger || void 0,
				flow: V.flow || void 0
			} });
		}
		function q() {
			z.replace({ query: {
				...R.query,
				status: void 0,
				trigger: void 0,
				flow: void 0
			} });
		}
		return (n, r) => {
			let a = l, o = u, f = h, v = p, T = m, D = g, A = d, M = _, N = c;
			return E(), x("div", F, [w(a, { title: k(L)("impex.runs.title") }, null, 8, ["title"]), w(N, { "screen-key": "impex.runs" }, {
				default: j(() => [S("div", I, [w(T, {
					state: k(V),
					class: "flex flex-wrap items-center gap-2",
					"data-testid": "impex-runs-filters",
					onSubmit: K
				}, {
					default: j(() => [
						w(o, {
							modelValue: k(V).status,
							"onUpdate:modelValue": r[0] ||= (e) => k(V).status = e,
							items: k(W),
							blank: k(L)("impex.runs.all_statuses"),
							class: "w-44",
							"aria-label": k(L)("impex.runs.status"),
							"data-testid": "impex-runs-status"
						}, null, 8, [
							"modelValue",
							"items",
							"blank",
							"aria-label"
						]),
						w(o, {
							modelValue: k(V).trigger,
							"onUpdate:modelValue": r[1] ||= (e) => k(V).trigger = e,
							items: k(G),
							blank: k(L)("impex.runs.all_triggers"),
							class: "w-40",
							"aria-label": k(L)("impex.runs.trigger"),
							"data-testid": "impex-runs-trigger"
						}, null, 8, [
							"modelValue",
							"items",
							"blank",
							"aria-label"
						]),
						w(f, {
							modelValue: k(V).flow,
							"onUpdate:modelValue": r[2] ||= (e) => k(V).flow = e,
							name: "flow",
							placeholder: k(L)("impex.runs.flow"),
							class: "w-48"
						}, null, 8, ["modelValue", "placeholder"]),
						w(v, {
							type: "submit",
							icon: "i-lucide-filter",
							"data-testid": "impex-runs-filter"
						}, {
							default: j(() => [C(O(k(L)("impex.common.filter")), 1)]),
							_: 1
						}),
						w(v, {
							variant: "ghost",
							"data-testid": "impex-runs-clear",
							onClick: q
						}, {
							default: j(() => [C(O(k(L)("impex.common.clear")), 1)]),
							_: 1
						})
					]),
					_: 1
				}, 8, ["state"]), w(e, {
					error: k(H).error.value,
					ready: !k(H).pending.value || k(H).rows.value.length > 0
				}, {
					default: j(() => [w(M, {
						data: k(H).rows.value,
						columns: k(U),
						loading: k(H).pending.value,
						"data-testid": "impex-runs-table"
					}, {
						"flow-cell": j(({ row: e }) => [w(D, {
							to: k(s)(e.original.id),
							class: "font-medium",
							"data-testid": `impex-run-row-${e.original.id}`
						}, {
							default: j(() => [C(O(e.original.flow), 1)]),
							_: 2
						}, 1032, ["to", "data-testid"])]),
						"status-cell": j(({ row: e }) => [w(t, { status: e.original.status }, null, 8, ["status"])]),
						"started_at-cell": j(({ row: e }) => [C(O(k(i)(e.original.started_at) ?? k(L)("impex.common.none")), 1)]),
						"finished_at-cell": j(({ row: e }) => [C(O(k(i)(e.original.finished_at) ?? k(L)("impex.common.none")), 1)]),
						empty: j(() => [w(A, {
							compact: "",
							title: k(L)("impex.runs.empty"),
							"data-testid": "impex-runs-empty"
						}, null, 8, ["title"])]),
						_: 1
					}, 8, [
						"data",
						"columns",
						"loading"
					]), k(H).nextCursor.value ? (E(), y(v, {
						key: 0,
						variant: "outline",
						loading: k(H).pending.value,
						"data-testid": "impex-load-more",
						onClick: k(H).loadMore
					}, {
						default: j(() => [C(O(k(L)("impex.common.load_more")), 1)]),
						_: 1
					}, 8, ["loading", "onClick"])) : b("", !0)]),
					_: 1
				}, 8, ["error", "ready"])])]),
				_: 1
			})]);
		};
	}
});
export { L as default };
