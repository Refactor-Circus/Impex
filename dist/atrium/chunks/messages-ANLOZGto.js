import { t as e } from "./ImpexState-C2BI291k.js";
import { C as t, a as n, g as r, n as i, p as a, v as o } from "./shared-Bu5fYXbP.js";
import { AtriumIntegrationScreen as s, AtriumPageHeader as c, AtriumState as l, integrationData as u } from "@refactor-circus/atrium";
import { UButton as d, UForm as f, UInput as p, ULink as m, USelect as h, UTable as g } from "@refactor-circus/atrium/ui";
import { computed as _, createBlock as v, createCommentVNode as y, createElementBlock as b, createElementVNode as x, createTextVNode as S, createVNode as C, defineComponent as w, openBlock as T, reactive as E, toDisplayString as D, unref as O, watch as k, withCtx as A } from "vue";
import { useI18n as j } from "vue-i18n";
import { useRoute as M, useRouter as N } from "vue-router";
// src/pages/messages/index.vue?vue&type=script&setup=true&lang.ts
var P = {
	class: "space-y-4",
	"data-testid": "impex-messages"
}, F = { class: "space-y-4" }, I = /* @__PURE__ */ w({
	__name: "index",
	setup(w) {
		let { t: I } = j(), L = M(), R = N(), z = _(() => ({
			direction: o(L.query.direction),
			channel: o(L.query.channel)
		})), B = E({ ...z.value });
		k(z, (e) => {
			Object.assign(B, e);
		});
		let V = t(async (e, t) => u(await e.GET("/messages", { params: { query: {
			...z.value,
			cursor: t
		} } })), z), H = _(() => [
			{
				accessorKey: "direction",
				header: I("impex.messages.direction")
			},
			{
				accessorKey: "channel",
				header: I("impex.messages.channel")
			},
			{
				accessorKey: "endpoint",
				header: I("impex.messages.endpoint"),
				meta: { class: { td: "max-w-xs truncate font-mono text-xs" } }
			},
			{
				accessorKey: "status_code",
				header: I("impex.messages.status_code"),
				meta: n
			},
			{
				accessorKey: "occurred_at",
				header: I("impex.messages.when")
			}
		]), U = _(() => i.map((e) => ({
			label: I(`impex.directions.${e}`),
			value: e
		})));
		function W() {
			R.replace({ query: {
				...L.query,
				direction: B.direction || void 0,
				channel: B.channel || void 0
			} });
		}
		function G() {
			R.replace({ query: {
				...L.query,
				direction: void 0,
				channel: void 0
			} });
		}
		return (t, n) => {
			let i = c, o = h, u = p, _ = d, w = f, E = m, k = l, j = g, M = s;
			return T(), b("div", P, [C(i, { title: O(I)("impex.messages.title") }, null, 8, ["title"]), C(M, { "screen-key": "impex.messages" }, {
				default: A(() => [x("div", F, [C(w, {
					state: O(B),
					class: "flex flex-wrap items-center gap-2",
					"data-testid": "impex-messages-filters",
					onSubmit: W
				}, {
					default: A(() => [
						C(o, {
							modelValue: O(B).direction,
							"onUpdate:modelValue": n[0] ||= (e) => O(B).direction = e,
							items: O(U),
							placeholder: O(I)("impex.messages.all_directions"),
							class: "w-44",
							"aria-label": O(I)("impex.messages.direction")
						}, null, 8, [
							"modelValue",
							"items",
							"placeholder",
							"aria-label"
						]),
						C(u, {
							modelValue: O(B).channel,
							"onUpdate:modelValue": n[1] ||= (e) => O(B).channel = e,
							name: "channel",
							placeholder: O(I)("impex.messages.channel"),
							class: "w-48"
						}, null, 8, ["modelValue", "placeholder"]),
						C(_, {
							type: "submit",
							icon: "i-lucide-filter",
							"data-testid": "impex-messages-filter"
						}, {
							default: A(() => [S(D(O(I)("impex.common.filter")), 1)]),
							_: 1
						}),
						C(_, {
							variant: "ghost",
							"data-testid": "impex-messages-clear",
							onClick: G
						}, {
							default: A(() => [S(D(O(I)("impex.common.clear")), 1)]),
							_: 1
						})
					]),
					_: 1
				}, 8, ["state"]), C(e, {
					error: O(V).error.value,
					ready: !O(V).pending.value || O(V).rows.value.length > 0
				}, {
					default: A(() => [C(j, {
						data: O(V).rows.value,
						columns: O(H),
						loading: O(V).pending.value,
						"data-testid": "impex-messages-table"
					}, {
						"direction-cell": A(({ row: e }) => [C(E, {
							to: O(r)(e.original.id),
							class: "font-medium",
							"data-testid": `impex-message-row-${e.original.id}`
						}, {
							default: A(() => [S(D(O(I)(`impex.directions.${e.original.direction}`)), 1)]),
							_: 2
						}, 1032, ["to", "data-testid"])]),
						"endpoint-cell": A(({ row: e }) => [S(D(e.original.method) + " " + D(e.original.endpoint), 1)]),
						"status_code-cell": A(({ row: e }) => [S(D(e.original.status_code ?? O(I)("impex.common.none")), 1)]),
						"occurred_at-cell": A(({ row: e }) => [S(D(O(a)(e.original.occurred_at)), 1)]),
						empty: A(() => [C(k, {
							compact: "",
							title: O(I)("impex.messages.empty"),
							"data-testid": "impex-messages-empty"
						}, null, 8, ["title"])]),
						_: 1
					}, 8, [
						"data",
						"columns",
						"loading"
					]), O(V).nextCursor.value ? (T(), v(_, {
						key: 0,
						variant: "outline",
						loading: O(V).pending.value,
						"data-testid": "impex-load-more",
						onClick: O(V).loadMore
					}, {
						default: A(() => [S(D(O(I)("impex.common.load_more")), 1)]),
						_: 1
					}, 8, ["loading", "onClick"])) : y("", !0)]),
					_: 1
				}, 8, ["error", "ready"])])]),
				_: 1
			})]);
		};
	}
});
export { I as default };
