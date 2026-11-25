import { t as e } from "./ImpexFlash-0zPnKlOX.js";
import { t } from "./ImpexState-C2BI291k.js";
import { t as n } from "./ImpexStatusBadge-0Fh6yTGt.js";
import { S as ee, _ as r, g as te, h as i, p as a, w as o, y as s } from "./shared-_UUfUMm3.js";
import { AtriumDetails as ne, AtriumIntegrationScreen as re, AtriumPageHeader as ie, integrationData as c } from "@refactor-circus/atrium";
import { UBadge as l, UButton as ae, UCard as oe, UForm as se, UInput as ce, ULink as le } from "@refactor-circus/atrium/ui";
import { Fragment as u, computed as d, createBlock as f, createCommentVNode as p, createElementBlock as m, createElementVNode as h, createTextVNode as g, createVNode as _, defineComponent as v, openBlock as y, reactive as b, renderList as x, toDisplayString as S, unref as C, withCtx as w } from "vue";
import { useI18n as T } from "vue-i18n";
import { useRoute as E } from "vue-router";
// src/pages/runs/[run].vue?vue&type=script&setup=true&lang.ts
var D = {
	class: "space-y-4",
	"data-testid": "impex-run"
}, O = {
	key: 0,
	class: "space-y-4"
}, k = { class: "truncate" }, A = { class: "flex flex-wrap gap-1" }, j = { class: "overflow-x-auto rounded-md bg-elevated p-2 text-xs" }, M = { class: "font-medium" }, N = { class: "font-medium" }, P = {
	key: 0,
	class: "text-sm text-muted"
}, F = {
	key: 1,
	class: "divide-y divide-default text-sm",
	"data-testid": "impex-run-steps"
}, I = ["data-testid"], L = { class: "flex items-center gap-2" }, R = { class: "text-muted tabular-nums" }, z = { class: "font-medium" }, B = { class: "text-xs text-muted" }, V = { class: "flex items-center gap-2 text-xs text-muted" }, ue = { key: 0 }, H = { key: 1 }, U = { class: "font-medium" }, de = {
	key: 0,
	class: "text-sm text-muted"
}, fe = {
	key: 1,
	class: "divide-y divide-default text-sm",
	"data-testid": "impex-run-messages"
}, pe = { class: "text-xs text-muted" }, W = /* @__PURE__ */ v({
	__name: "[run]",
	setup(v) {
		let { t: W } = T(), me = E(), G = d(() => String(me.params.run ?? "")), K = o(async (e) => c(await e.GET("/runs/{run}", { params: { path: { run: G.value } } })).data, G), q = o(async (e) => c(await e.GET("/runs/{run}/steps", { params: { path: { run: G.value } } })).data, G), J = o(async (e) => c(await e.GET("/messages", { params: { query: { run: G.value } } })).data, G), Y = ee(), X = b({
			name: "",
			payload: ""
		}), Z = d(() => [
			"completed",
			"failed",
			"cancelled"
		].includes(K.data.value?.status ?? "")), he = d(() => {
			let e = K.data.value;
			return e ? [
				{
					key: "id",
					label: W("impex.run.id"),
					value: e.id,
					mono: !0
				},
				{
					key: "trigger",
					label: W("impex.runs.trigger"),
					value: e.trigger
				},
				{
					key: "started",
					label: W("impex.runs.started"),
					value: a(e.started_at)
				},
				{
					key: "finished",
					label: W("impex.runs.finished"),
					value: a(e.finished_at)
				},
				...e.idempotency_key ? [{
					key: "idempotency_key",
					label: W("impex.run.idempotency_key"),
					value: e.idempotency_key,
					mono: !0
				}] : [],
				...e.parent_run_id ? [{
					key: "parent",
					label: W("impex.run.parent"),
					value: e.parent_run_id,
					href: s(e.parent_run_id),
					mono: !0
				}] : [],
				...e.tags && Object.keys(e.tags).length ? [{
					key: "tags",
					label: W("impex.run.tags"),
					wide: !0
				}] : [],
				...e.error ? [{
					key: "error",
					label: W("impex.run.error"),
					wide: !0
				}] : []
			] : [];
		});
		async function Q() {
			await Promise.all([
				K.reload(),
				q.reload(),
				J.reload()
			]);
		}
		async function ge() {
			let e = K.client;
			e && await Y.run(async () => c(await e.POST("/runs/{run}/cancel", {
				params: { path: { run: G.value } },
				body: { reason: W("impex.run.cancelled_from_dashboard") }
			})), async () => (await Q(), W("impex.run.cancelled")));
		}
		async function $() {
			let e = K.client;
			e && await Y.run(async () => c(await e.POST("/runs/{run}/retry", { params: { path: { run: G.value } } })), async () => (await Q(), W("impex.run.retried")));
		}
		async function _e() {
			let e = K.client, t = r(X.payload);
			if (!e) return;
			if (t && !("value" in t)) {
				Y.invalid("payload", W("impex.run.payload_invalid"));
				return;
			}
			let n = i({
				name: X.name,
				...t && "value" in t ? { payload: t.value } : {}
			});
			await Y.run(async () => c(await e.POST("/runs/{run}/signals", {
				params: { path: { run: G.value } },
				body: n
			})), async (e) => (X.name = "", X.payload = "", await Q(), "data" in e && e.data ? W("impex.run.signal_sent") : W("impex.run.signal_ignored")));
		}
		return (ee, r) => {
			let i = ae, o = ie, s = l, c = ne, d = oe, v = ce, b = se, T = le, E = re;
			return y(), m("div", D, [_(E, {
				"screen-key": "impex.run",
				record: C(G)
			}, {
				default: w(() => [_(t, {
					error: C(K).error.value,
					ready: C(K).data.value !== null
				}, {
					default: w(() => [C(K).data.value ? (y(), m("div", O, [
						_(o, { title: C(K).data.value.flow }, {
							title: w(() => [h("span", k, S(C(K).data.value.flow), 1), _(n, { status: C(K).data.value.status }, null, 8, ["status"])]),
							default: w(() => [C(Z) ? p("", !0) : (y(), f(i, {
								key: 0,
								variant: "outline",
								icon: "i-lucide-circle-stop",
								loading: C(Y).busy.value,
								"data-testid": "impex-run-cancel",
								onClick: ge
							}, {
								default: w(() => [g(S(C(W)("impex.run.cancel")), 1)]),
								_: 1
							}, 8, ["loading"])), C(K).data.value.status === "failed" ? (y(), f(i, {
								key: 1,
								variant: "outline",
								icon: "i-lucide-rotate-cw",
								loading: C(Y).busy.value,
								"data-testid": "impex-run-retry",
								onClick: $
							}, {
								default: w(() => [g(S(C(W)("impex.run.retry")), 1)]),
								_: 1
							}, 8, ["loading"])) : p("", !0)]),
							_: 1
						}, 8, ["title"]),
						_(e, { action: C(Y) }, null, 8, ["action"]),
						_(d, null, {
							default: w(() => [_(c, {
								items: C(he),
								columns: 2,
								empty: C(W)("impex.common.none"),
								"data-testid": "impex-run-details"
							}, {
								tags: w(() => [h("span", A, [(y(!0), m(u, null, x(C(K).data.value.tags, (e, t) => (y(), f(s, {
									key: t,
									variant: "outline",
									color: "neutral"
								}, {
									default: w(() => [g(S(t) + ": " + S(e), 1)]),
									_: 2
								}, 1024))), 128))])]),
								error: w(() => [h("pre", j, S(JSON.stringify(C(K).data.value.error, null, 2)), 1)]),
								_: 1
							}, 8, ["items", "empty"])]),
							_: 1
						}),
						C(Z) ? p("", !0) : (y(), f(d, { key: 0 }, {
							header: w(() => [h("h2", M, S(C(W)("impex.run.signal_title")), 1)]),
							default: w(() => [_(b, {
								state: C(X),
								class: "flex flex-wrap items-end gap-3",
								"data-testid": "impex-run-signal",
								onSubmit: _e
							}, {
								default: w(() => [
									_(v, {
										modelValue: C(X).name,
										"onUpdate:modelValue": r[0] ||= (e) => C(X).name = e,
										name: "name",
										placeholder: C(W)("impex.run.signal_name"),
										required: "",
										class: "w-56"
									}, null, 8, ["modelValue", "placeholder"]),
									_(v, {
										modelValue: C(X).payload,
										"onUpdate:modelValue": r[1] ||= (e) => C(X).payload = e,
										name: "payload",
										placeholder: C(W)("impex.run.signal_payload"),
										class: "w-72"
									}, null, 8, ["modelValue", "placeholder"]),
									_(i, {
										type: "submit",
										icon: "i-lucide-send",
										loading: C(Y).busy.value
									}, {
										default: w(() => [g(S(C(W)("impex.run.send")), 1)]),
										_: 1
									}, 8, ["loading"])
								]),
								_: 1
							}, 8, ["state"])]),
							_: 1
						})),
						_(d, null, {
							header: w(() => [h("h2", N, S(C(W)("impex.run.steps")), 1)]),
							default: w(() => [_(t, {
								error: C(q).error.value,
								ready: C(q).data.value !== null
							}, {
								default: w(() => [(C(q).data.value ?? []).length === 0 ? (y(), m("p", P, S(C(W)("impex.run.no_steps")), 1)) : (y(), m("ol", F, [(y(!0), m(u, null, x(C(q).data.value, (e) => (y(), m("li", {
									key: e.id,
									class: "flex flex-wrap items-center justify-between gap-2 py-2",
									"data-testid": `impex-step-${e.sequence}`
								}, [h("span", L, [
									h("span", R, S(e.sequence), 1),
									h("span", z, S(e.name), 1),
									h("span", B, S(e.type), 1),
									e.phase === "rollback" ? (y(), f(s, {
										key: 0,
										variant: "outline",
										color: "warning"
									}, {
										default: w(() => [g(S(C(W)("impex.run.rollback")), 1)]),
										_: 1
									})) : p("", !0),
									e.undone ? (y(), f(s, {
										key: 1,
										variant: "outline",
										color: "neutral"
									}, {
										default: w(() => [g(S(C(W)("impex.run.undone")), 1)]),
										_: 1
									})) : p("", !0)
								]), h("span", V, [
									h("span", null, S(C(W)("impex.run.attempts", { count: e.attempts })), 1),
									e.resumptions > 0 ? (y(), m("span", ue, S(C(W)("impex.run.resumed", { count: e.resumptions })), 1)) : p("", !0),
									e.result_artifact_id ? (y(), m("span", H, S(C(W)("impex.run.result_on_disk")), 1)) : p("", !0),
									_(n, { status: e.status }, null, 8, ["status"])
								])], 8, I))), 128))]))]),
								_: 1
							}, 8, ["error", "ready"])]),
							_: 1
						}),
						_(d, null, {
							header: w(() => [h("h2", U, S(C(W)("impex.run.messages")), 1)]),
							default: w(() => [_(t, {
								error: C(J).error.value,
								ready: C(J).data.value !== null
							}, {
								default: w(() => [(C(J).data.value ?? []).length === 0 ? (y(), m("p", de, S(C(W)("impex.run.no_messages")), 1)) : (y(), m("ul", fe, [(y(!0), m(u, null, x(C(J).data.value, (e) => (y(), m("li", {
									key: e.id,
									class: "flex justify-between gap-2 py-2"
								}, [_(T, { to: C(te)(e.id) }, {
									default: w(() => [g(S(e.direction) + " · " + S(e.channel), 1)]),
									_: 2
								}, 1032, ["to"]), h("span", pe, S(C(a)(e.occurred_at)), 1)]))), 128))]))]),
								_: 1
							}, 8, ["error", "ready"])]),
							_: 1
						})
					])) : p("", !0)]),
					_: 1
				}, 8, ["error", "ready"])]),
				_: 1
			}, 8, ["record"])]);
		};
	}
});
export { W as default };
