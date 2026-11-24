import { t as e } from "./ImpexFlash-0zPnKlOX.js";
import { t } from "./ImpexState-C2BI291k.js";
import { t as n } from "./ImpexStatusBadge-DCp4DAuy.js";
import { S as ee, a as r, f as i, g as te, m as a, p as o, w as s } from "./shared-Bu5fYXbP.js";
import { AtriumIntegrationScreen as ne, AtriumPageHeader as c, AtriumState as re, integrationData as l } from "@refactor-circus/atrium";
import { UButton as ie, UCard as ae, UForm as oe, UFormField as se, UInput as ce, ULink as le, UTable as ue } from "@refactor-circus/atrium/ui";
import { computed as u, createBlock as d, createCommentVNode as f, createElementBlock as p, createElementVNode as m, createTextVNode as h, createVNode as g, defineComponent as _, isRef as de, openBlock as v, ref as y, toDisplayString as b, unref as x, withCtx as S } from "vue";
import { useI18n as C } from "vue-i18n";
import { useRoute as w } from "vue-router";
// src/pages/subscriptions/[subscription].vue?vue&type=script&setup=true&lang.ts
var T = {
	class: "space-y-4",
	"data-testid": "impex-subscription"
}, E = {
	key: 0,
	class: "space-y-4"
}, D = {
	class: "grid gap-3 text-sm sm:grid-cols-2",
	"data-testid": "impex-subscription-details"
}, O = { class: "text-muted" }, k = { class: "text-muted" }, A = { class: "text-muted" }, j = { class: "text-muted" }, M = { class: "text-muted" }, N = { class: "font-mono" }, P = { class: "text-muted" }, F = { class: "tabular-nums" }, I = { key: 0 }, L = { class: "text-muted" }, R = {
	key: 1,
	class: "sm:col-span-2"
}, z = { class: "text-muted" }, fe = { class: "font-medium" }, B = ["data-testid"], V = {
	key: 0,
	class: "text-xs text-muted"
}, pe = { key: 1 }, H = /* @__PURE__ */ _({
	__name: "[subscription]",
	setup(_) {
		let { t: H } = C(), U = w(), W = u(() => String(U.params.subscription ?? "")), G = s(async (e) => l(await e.GET("/subscriptions/{subscription}", { params: { path: { subscription: W.value } } })).data, W), K = s(async (e) => l(await e.GET("/subscriptions/{subscription}/deliveries", { params: {
			path: { subscription: W.value },
			query: { per_page: 20 }
		} })).data, W), q = ee(), J = y(""), me = u(() => [
			{
				accessorKey: "status",
				header: H("impex.subscriptions.status")
			},
			{
				accessorKey: "events",
				header: H("impex.subscription.events"),
				meta: r
			},
			{
				accessorKey: "duration_ms",
				header: H("impex.message.duration"),
				meta: r
			},
			{
				accessorKey: "attempted_at",
				header: H("impex.messages.when")
			}
		]), Y = u(() => i(G.data.value?.endpoint)), X = u(() => {
			let e = G.data.value?.last_error?.message;
			return typeof e == "string" ? e : null;
		});
		async function Z() {
			await Promise.all([G.reload(), K.reload()]);
		}
		async function Q(e, t) {
			let n = G.client;
			n && await q.run(async () => l(await n.PATCH("/subscriptions/{subscription}", {
				params: { path: { subscription: W.value } },
				body: e
			})), async () => (await Z(), t));
		}
		async function he() {
			await Q({ status: "paused" }, H("impex.subscription.paused"));
		}
		async function ge() {
			await Q({ status: "active" }, H("impex.subscription.resumed"));
		}
		async function $() {
			let e = String(J.value).trim(), t = Number(e);
			e === "" || !Number.isInteger(t) || t < 0 ? q.invalid("replay_from", H("impex.subscription.replay_invalid")) : (await Q({ replay_from: t }, H("impex.subscription.replaying")), J.value = "");
		}
		async function _e() {
			let e = G.client;
			e && await q.run(async () => l(await e.POST("/subscriptions/{subscription}/ping", { params: { path: { subscription: W.value } } })), async (e) => (await Z(), a(e.data.successful) ? H("impex.subscription.ping_succeeded") : H("impex.subscription.ping_failed", { error: String(e.data.error ?? "") })));
		}
		return (ee, r) => {
			let i = ie, a = c, s = ae, l = ce, u = se, _ = oe, y = le, C = re, w = ue, U = ne;
			return v(), p("div", T, [g(U, {
				"screen-key": "impex.subscription",
				record: x(W)
			}, {
				default: S(() => [g(t, {
					error: x(G).error.value,
					ready: x(G).data.value !== null
				}, {
					default: S(() => [x(G).data.value ? (v(), p("div", E, [
						g(a, { title: x(G).data.value.stream }, {
							meta: S(() => [g(n, { status: x(G).data.value.status }, null, 8, ["status"])]),
							default: S(() => [x(G).data.value.status === "active" ? (v(), d(i, {
								key: 0,
								variant: "outline",
								icon: "i-lucide-pause",
								loading: x(q).busy.value,
								"data-testid": "impex-subscription-pause",
								onClick: he
							}, {
								default: S(() => [h(b(x(H)("impex.subscription.pause")), 1)]),
								_: 1
							}, 8, ["loading"])) : (v(), d(i, {
								key: 1,
								variant: "outline",
								icon: "i-lucide-play",
								loading: x(q).busy.value,
								"data-testid": "impex-subscription-resume",
								onClick: ge
							}, {
								default: S(() => [h(b(x(H)("impex.subscription.resume")), 1)]),
								_: 1
							}, 8, ["loading"])), x(Y) ? (v(), d(i, {
								key: 2,
								variant: "outline",
								icon: "i-lucide-send",
								loading: x(q).busy.value,
								"data-testid": "impex-subscription-ping",
								onClick: _e
							}, {
								default: S(() => [h(b(x(H)("impex.subscription.ping")), 1)]),
								_: 1
							}, 8, ["loading"])) : f("", !0)]),
							_: 1
						}, 8, ["title"]),
						g(e, { action: x(q) }, null, 8, ["action"]),
						g(s, null, {
							default: S(() => [m("dl", D, [
								m("div", null, [m("dt", O, b(x(H)("impex.subscription.topics")), 1), m("dd", null, b(x(G).data.value.topics.join(", ") || x(H)("impex.common.none")), 1)]),
								m("div", null, [m("dt", k, b(x(H)("impex.subscription.selection")), 1), m("dd", null, b(x(G).data.value.selection), 1)]),
								m("div", null, [m("dt", A, b(x(H)("impex.subscription.format")), 1), m("dd", null, b(x(G).data.value.format), 1)]),
								m("div", null, [m("dt", j, b(x(H)("impex.subscriptions.endpoint")), 1), m("dd", null, b(x(Y) ?? x(H)("impex.subscriptions.feed_only")), 1)]),
								m("div", null, [m("dt", M, b(x(H)("impex.subscription.cursor")), 1), m("dd", N, b(x(G).data.value.cursor), 1)]),
								m("div", null, [m("dt", P, b(x(H)("impex.subscriptions.failures")), 1), m("dd", F, b(x(G).data.value.failures), 1)]),
								x(G).data.value.paused_until ? (v(), p("div", I, [m("dt", L, b(x(H)("impex.subscription.paused_until")), 1), m("dd", null, b(x(o)(x(G).data.value.paused_until)), 1)])) : f("", !0),
								x(X) ? (v(), p("div", R, [m("dt", z, b(x(H)("impex.subscription.last_error")), 1), m("dd", null, b(x(X)), 1)])) : f("", !0)
							])]),
							_: 1
						}),
						g(s, null, {
							default: S(() => [g(_, {
								state: { replay_from: x(J) },
								class: "flex flex-wrap items-end gap-3",
								"data-testid": "impex-subscription-replay",
								onSubmit: $
							}, {
								default: S(() => [g(u, {
									label: x(H)("impex.subscription.replay_from"),
									class: "w-48"
								}, {
									default: S(() => [g(l, {
										modelValue: x(J),
										"onUpdate:modelValue": r[0] ||= (e) => de(J) ? J.value = e : null,
										name: "replay_from",
										type: "number",
										min: "0"
									}, null, 8, ["modelValue"])]),
									_: 1
								}, 8, ["label"]), g(i, {
									type: "submit",
									icon: "i-lucide-rotate-ccw",
									loading: x(q).busy.value
								}, {
									default: S(() => [h(b(x(H)("impex.subscription.replay")), 1)]),
									_: 1
								}, 8, ["loading"])]),
								_: 1
							}, 8, ["state"])]),
							_: 1
						}),
						g(s, null, {
							header: S(() => [m("h2", fe, b(x(H)("impex.subscription.deliveries")), 1)]),
							default: S(() => [g(t, {
								error: x(K).error.value,
								ready: x(K).data.value !== null
							}, {
								default: S(() => [g(w, {
									data: x(K).data.value ?? [],
									columns: x(me),
									"data-testid": "impex-subscription-deliveries"
								}, {
									"status-cell": S(({ row: e }) => [m("span", {
										class: "flex items-center gap-1.5",
										"data-testid": `impex-delivery-${e.original.id}`
									}, [g(n, { status: e.original.status }, null, 8, ["status"]), e.original.status_code ? (v(), p("span", V, b(e.original.status_code), 1)) : f("", !0)], 8, B)]),
									"duration_ms-cell": S(({ row: e }) => [h(b(e.original.duration_ms === null ? x(H)("impex.common.none") : x(H)("impex.message.milliseconds", { count: e.original.duration_ms })), 1)]),
									"attempted_at-cell": S(({ row: e }) => [e.original.message_id ? (v(), d(y, {
										key: 0,
										to: x(te)(e.original.message_id)
									}, {
										default: S(() => [h(b(x(o)(e.original.attempted_at)), 1)]),
										_: 2
									}, 1032, ["to"])) : (v(), p("span", pe, b(x(o)(e.original.attempted_at)), 1))]),
									empty: S(() => [g(C, {
										compact: "",
										title: x(H)("impex.subscription.no_deliveries"),
										"data-testid": "impex-subscription-no-deliveries"
									}, null, 8, ["title"])]),
									_: 1
								}, 8, ["data", "columns"])]),
								_: 1
							}, 8, ["error", "ready"])]),
							_: 1
						})
					])) : f("", !0)]),
					_: 1
				}, 8, ["error", "ready"])]),
				_: 1
			}, 8, ["record"])]);
		};
	}
});
export { H as default };
