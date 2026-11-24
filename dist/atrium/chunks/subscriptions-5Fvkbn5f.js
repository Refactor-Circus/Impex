import { t as e } from "./ImpexState-C2BI291k.js";
import { t } from "./ImpexStatusBadge-DCp4DAuy.js";
import { C as n, a as r, c as i, f as a, p as o, v as s, x as c } from "./shared-Bu5fYXbP.js";
import { AtriumIntegrationScreen as l, AtriumPageHeader as u, AtriumState as d, integrationData as f } from "@refactor-circus/atrium";
import { UButton as p, UForm as m, UInput as h, ULink as g, USelect as _, UTable as v } from "@refactor-circus/atrium/ui";
import { computed as y, createBlock as b, createCommentVNode as x, createElementBlock as S, createElementVNode as C, createTextVNode as w, createVNode as T, defineComponent as E, openBlock as D, reactive as O, toDisplayString as k, unref as A, watch as j, withCtx as M } from "vue";
import { useI18n as N } from "vue-i18n";
import { useRoute as P, useRouter as F } from "vue-router";
// src/pages/subscriptions/index.vue?vue&type=script&setup=true&lang.ts
var I = {
	class: "space-y-4",
	"data-testid": "impex-subscriptions"
}, L = { class: "space-y-4" }, R = /* @__PURE__ */ E({
	__name: "index",
	setup(E) {
		let { t: R } = N(), z = P(), B = F(), V = y(() => ({
			stream: s(z.query.stream),
			status: s(z.query.status)
		})), H = O({ ...V.value });
		j(V, (e) => {
			Object.assign(H, e);
		});
		let U = n(async (e, t) => f(await e.GET("/subscriptions", { params: { query: {
			...V.value,
			cursor: t
		} } })), V), W = y(() => [
			{
				accessorKey: "stream",
				header: R("impex.subscriptions.stream")
			},
			{
				accessorKey: "subscriber_id",
				header: R("impex.subscriptions.subscriber"),
				meta: { class: { td: "font-mono text-xs" } }
			},
			{
				accessorKey: "endpoint",
				header: R("impex.subscriptions.endpoint"),
				meta: { class: { td: "max-w-xs truncate" } }
			},
			{
				accessorKey: "status",
				header: R("impex.subscriptions.status")
			},
			{
				accessorKey: "failures",
				header: R("impex.subscriptions.failures"),
				meta: r
			},
			{
				accessorKey: "last_delivered_at",
				header: R("impex.subscriptions.last_delivered")
			}
		]), G = y(() => i.map((e) => ({
			label: R(`impex.statuses.${e}`),
			value: e
		})));
		function K() {
			B.replace({ query: {
				...z.query,
				stream: H.stream || void 0,
				status: H.status || void 0
			} });
		}
		function q() {
			B.replace({ query: {
				...z.query,
				stream: void 0,
				status: void 0
			} });
		}
		return (n, r) => {
			let i = u, s = h, f = _, y = p, E = m, O = g, j = d, N = v, P = l;
			return D(), S("div", I, [T(i, { title: A(R)("impex.subscriptions.title") }, null, 8, ["title"]), T(P, { "screen-key": "impex.subscriptions" }, {
				default: M(() => [C("div", L, [T(E, {
					state: A(H),
					class: "flex flex-wrap items-center gap-2",
					"data-testid": "impex-subscriptions-filters",
					onSubmit: K
				}, {
					default: M(() => [
						T(s, {
							modelValue: A(H).stream,
							"onUpdate:modelValue": r[0] ||= (e) => A(H).stream = e,
							name: "stream",
							placeholder: A(R)("impex.subscriptions.stream"),
							class: "w-48"
						}, null, 8, ["modelValue", "placeholder"]),
						T(f, {
							modelValue: A(H).status,
							"onUpdate:modelValue": r[1] ||= (e) => A(H).status = e,
							items: A(G),
							placeholder: A(R)("impex.subscriptions.all_statuses"),
							class: "w-44",
							"aria-label": A(R)("impex.subscriptions.status")
						}, null, 8, [
							"modelValue",
							"items",
							"placeholder",
							"aria-label"
						]),
						T(y, {
							type: "submit",
							icon: "i-lucide-filter",
							"data-testid": "impex-subscriptions-filter"
						}, {
							default: M(() => [w(k(A(R)("impex.common.filter")), 1)]),
							_: 1
						}),
						T(y, {
							variant: "ghost",
							"data-testid": "impex-subscriptions-clear",
							onClick: q
						}, {
							default: M(() => [w(k(A(R)("impex.common.clear")), 1)]),
							_: 1
						})
					]),
					_: 1
				}, 8, ["state"]), T(e, {
					error: A(U).error.value,
					ready: !A(U).pending.value || A(U).rows.value.length > 0
				}, {
					default: M(() => [T(N, {
						data: A(U).rows.value,
						columns: A(W),
						loading: A(U).pending.value,
						"data-testid": "impex-subscriptions-table"
					}, {
						"stream-cell": M(({ row: e }) => [T(O, {
							to: A(c)(e.original.id),
							class: "font-medium",
							"data-testid": `impex-subscription-row-${e.original.id}`
						}, {
							default: M(() => [w(k(e.original.stream), 1)]),
							_: 2
						}, 1032, ["to", "data-testid"])]),
						"endpoint-cell": M(({ row: e }) => [w(k(A(a)(e.original.endpoint) ?? A(R)("impex.subscriptions.feed_only")), 1)]),
						"status-cell": M(({ row: e }) => [T(t, { status: e.original.status }, null, 8, ["status"])]),
						"last_delivered_at-cell": M(({ row: e }) => [w(k(A(o)(e.original.last_delivered_at) ?? A(R)("impex.common.none")), 1)]),
						empty: M(() => [T(j, {
							compact: "",
							title: A(R)("impex.subscriptions.empty"),
							"data-testid": "impex-subscriptions-empty"
						}, null, 8, ["title"])]),
						_: 1
					}, 8, [
						"data",
						"columns",
						"loading"
					]), A(U).nextCursor.value ? (D(), b(y, {
						key: 0,
						variant: "outline",
						loading: A(U).pending.value,
						"data-testid": "impex-load-more",
						onClick: A(U).loadMore
					}, {
						default: M(() => [w(k(A(R)("impex.common.load_more")), 1)]),
						_: 1
					}, 8, ["loading", "onClick"])) : x("", !0)]),
					_: 1
				}, 8, ["error", "ready"])])]),
				_: 1
			})]);
		};
	}
});
export { R as default };
