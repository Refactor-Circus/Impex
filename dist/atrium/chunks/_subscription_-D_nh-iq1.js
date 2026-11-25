import { t as e } from "./ImpexFlash-0zPnKlOX.js";
import { t } from "./ImpexState-C2BI291k.js";
import { t as n } from "./ImpexStatusBadge-0Fh6yTGt.js";
import { S as r, a as i, f as a, g as o, m as s, p as c, w as l } from "./shared-_UUfUMm3.js";
import { AtriumDetails as u, AtriumIntegrationScreen as d, AtriumPageHeader as f, AtriumState as p, integrationData as m } from "@refactor-circus/atrium";
import { UButton as ee, UCard as h, UForm as g, UFormField as _, UInput as te, ULink as ne, UTable as v } from "@refactor-circus/atrium/ui";
import { computed as y, createBlock as b, createCommentVNode as x, createElementBlock as S, createElementVNode as C, createTextVNode as w, createVNode as T, defineComponent as E, isRef as D, openBlock as O, ref as k, toDisplayString as A, unref as j, withCtx as M } from "vue";
import { useI18n as N } from "vue-i18n";
import { useRoute as P } from "vue-router";
// src/pages/subscriptions/[subscription].vue?vue&type=script&setup=true&lang.ts
var F = {
	class: "space-y-4",
	"data-testid": "impex-subscription"
}, I = {
	key: 0,
	class: "space-y-4"
}, L = { class: "truncate" }, R = { class: "font-medium" }, z = ["data-testid"], B = {
	key: 0,
	class: "text-xs text-muted"
}, V = { key: 1 }, H = /* @__PURE__ */ E({
	__name: "[subscription]",
	setup(E) {
		let { t: H } = N(), U = P(), W = y(() => String(U.params.subscription ?? "")), G = l(async (e) => m(await e.GET("/subscriptions/{subscription}", { params: { path: { subscription: W.value } } })).data, W), K = l(async (e) => m(await e.GET("/subscriptions/{subscription}/deliveries", { params: {
			path: { subscription: W.value },
			query: { per_page: 20 }
		} })).data, W), q = r(), J = k(""), Y = y(() => [
			{
				accessorKey: "status",
				header: H("impex.subscriptions.status")
			},
			{
				accessorKey: "events",
				header: H("impex.subscription.events"),
				meta: i
			},
			{
				accessorKey: "duration_ms",
				header: H("impex.message.duration"),
				meta: i
			},
			{
				accessorKey: "attempted_at",
				header: H("impex.messages.when")
			}
		]), X = y(() => a(G.data.value?.endpoint)), Z = y(() => {
			let e = G.data.value?.last_error?.message;
			return typeof e == "string" ? e : null;
		}), re = y(() => {
			let e = G.data.value;
			return e ? [
				{
					key: "topics",
					label: H("impex.subscription.topics"),
					value: e.topics.join(", ")
				},
				{
					key: "selection",
					label: H("impex.subscription.selection"),
					value: e.selection
				},
				{
					key: "format",
					label: H("impex.subscription.format"),
					value: e.format
				},
				{
					key: "endpoint",
					label: H("impex.subscriptions.endpoint"),
					value: X.value ?? H("impex.subscriptions.feed_only")
				},
				{
					key: "cursor",
					label: H("impex.subscription.cursor"),
					value: e.cursor,
					mono: !0
				},
				{
					key: "failures",
					label: H("impex.subscriptions.failures"),
					value: e.failures,
					mono: !0
				},
				...e.paused_until ? [{
					key: "paused_until",
					label: H("impex.subscription.paused_until"),
					value: c(e.paused_until)
				}] : [],
				...Z.value ? [{
					key: "last_error",
					label: H("impex.subscription.last_error"),
					value: Z.value,
					wide: !0
				}] : []
			] : [];
		});
		async function Q() {
			await Promise.all([G.reload(), K.reload()]);
		}
		async function $(e, t) {
			let n = G.client;
			n && await q.run(async () => m(await n.PATCH("/subscriptions/{subscription}", {
				params: { path: { subscription: W.value } },
				body: e
			})), async () => (await Q(), t));
		}
		async function ie() {
			await $({ status: "paused" }, H("impex.subscription.paused"));
		}
		async function ae() {
			await $({ status: "active" }, H("impex.subscription.resumed"));
		}
		async function oe() {
			let e = String(J.value).trim(), t = Number(e);
			e === "" || !Number.isInteger(t) || t < 0 ? q.invalid("replay_from", H("impex.subscription.replay_invalid")) : (await $({ replay_from: t }, H("impex.subscription.replaying")), J.value = "");
		}
		async function se() {
			let e = G.client;
			e && await q.run(async () => m(await e.POST("/subscriptions/{subscription}/ping", { params: { path: { subscription: W.value } } })), async (e) => (await Q(), s(e.data.successful) ? H("impex.subscription.ping_succeeded") : H("impex.subscription.ping_failed", { error: String(e.data.error ?? "") })));
		}
		return (r, i) => {
			let a = ee, s = f, l = u, m = h, y = te, E = _, k = g, N = ne, P = p, U = v, Z = d;
			return O(), S("div", F, [T(Z, {
				"screen-key": "impex.subscription",
				record: j(W)
			}, {
				default: M(() => [T(t, {
					error: j(G).error.value,
					ready: j(G).data.value !== null
				}, {
					default: M(() => [j(G).data.value ? (O(), S("div", I, [
						T(s, { title: j(G).data.value.stream }, {
							title: M(() => [C("span", L, A(j(G).data.value.stream), 1), T(n, { status: j(G).data.value.status }, null, 8, ["status"])]),
							default: M(() => [j(G).data.value.status === "active" ? (O(), b(a, {
								key: 0,
								variant: "outline",
								icon: "i-lucide-pause",
								loading: j(q).busy.value,
								"data-testid": "impex-subscription-pause",
								onClick: ie
							}, {
								default: M(() => [w(A(j(H)("impex.subscription.pause")), 1)]),
								_: 1
							}, 8, ["loading"])) : (O(), b(a, {
								key: 1,
								variant: "outline",
								icon: "i-lucide-play",
								loading: j(q).busy.value,
								"data-testid": "impex-subscription-resume",
								onClick: ae
							}, {
								default: M(() => [w(A(j(H)("impex.subscription.resume")), 1)]),
								_: 1
							}, 8, ["loading"])), j(X) ? (O(), b(a, {
								key: 2,
								variant: "outline",
								icon: "i-lucide-send",
								loading: j(q).busy.value,
								"data-testid": "impex-subscription-ping",
								onClick: se
							}, {
								default: M(() => [w(A(j(H)("impex.subscription.ping")), 1)]),
								_: 1
							}, 8, ["loading"])) : x("", !0)]),
							_: 1
						}, 8, ["title"]),
						T(e, { action: j(q) }, null, 8, ["action"]),
						T(m, null, {
							default: M(() => [T(l, {
								items: j(re),
								columns: 2,
								empty: j(H)("impex.common.none"),
								"data-testid": "impex-subscription-details"
							}, null, 8, ["items", "empty"])]),
							_: 1
						}),
						T(m, null, {
							default: M(() => [T(k, {
								state: { replay_from: j(J) },
								class: "flex flex-wrap items-start gap-3",
								"data-testid": "impex-subscription-replay",
								onSubmit: oe
							}, {
								default: M(() => [T(E, {
									label: j(H)("impex.subscription.replay_from"),
									class: "w-48"
								}, {
									default: M(() => [T(y, {
										modelValue: j(J),
										"onUpdate:modelValue": i[0] ||= (e) => D(J) ? J.value = e : null,
										name: "replay_from",
										type: "number",
										min: "0"
									}, null, 8, ["modelValue"])]),
									_: 1
								}, 8, ["label"]), T(a, {
									type: "submit",
									icon: "i-lucide-rotate-ccw",
									loading: j(q).busy.value,
									class: "mt-field-label"
								}, {
									default: M(() => [w(A(j(H)("impex.subscription.replay")), 1)]),
									_: 1
								}, 8, ["loading"])]),
								_: 1
							}, 8, ["state"])]),
							_: 1
						}),
						T(m, null, {
							header: M(() => [C("h2", R, A(j(H)("impex.subscription.deliveries")), 1)]),
							default: M(() => [T(t, {
								error: j(K).error.value,
								ready: j(K).data.value !== null
							}, {
								default: M(() => [T(U, {
									data: j(K).data.value ?? [],
									columns: j(Y),
									"data-testid": "impex-subscription-deliveries"
								}, {
									"status-cell": M(({ row: e }) => [C("span", {
										class: "flex items-center gap-1.5",
										"data-testid": `impex-delivery-${e.original.id}`
									}, [T(n, { status: e.original.status }, null, 8, ["status"]), e.original.status_code ? (O(), S("span", B, A(e.original.status_code), 1)) : x("", !0)], 8, z)]),
									"duration_ms-cell": M(({ row: e }) => [w(A(e.original.duration_ms === null ? j(H)("impex.common.none") : j(H)("impex.message.milliseconds", { count: e.original.duration_ms })), 1)]),
									"attempted_at-cell": M(({ row: e }) => [e.original.message_id ? (O(), b(N, {
										key: 0,
										to: j(o)(e.original.message_id)
									}, {
										default: M(() => [w(A(j(c)(e.original.attempted_at)), 1)]),
										_: 2
									}, 1032, ["to"])) : (O(), S("span", V, A(j(c)(e.original.attempted_at)), 1))]),
									empty: M(() => [T(P, {
										compact: "",
										title: j(H)("impex.subscription.no_deliveries"),
										"data-testid": "impex-subscription-no-deliveries"
									}, null, 8, ["title"])]),
									_: 1
								}, 8, ["data", "columns"])]),
								_: 1
							}, 8, ["error", "ready"])]),
							_: 1
						})
					])) : x("", !0)]),
					_: 1
				}, 8, ["error", "ready"])]),
				_: 1
			}, 8, ["record"])]);
		};
	}
});
export { H as default };
