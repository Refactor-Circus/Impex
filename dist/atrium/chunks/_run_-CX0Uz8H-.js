import { t as e } from "./ImpexFlash-0zPnKlOX.js";
import { t } from "./ImpexState-C2BI291k.js";
import { t as n } from "./ImpexStatusBadge-DCp4DAuy.js";
import { S as ee, _ as r, g as te, h as i, p as a, w as o, y as ne } from "./shared-Bu5fYXbP.js";
import { AtriumIntegrationScreen as re, AtriumPageHeader as s, integrationData as c } from "@refactor-circus/atrium";
import { UBadge as ie, UButton as ae, UCard as oe, UForm as se, UInput as ce, ULink as le } from "@refactor-circus/atrium/ui";
import { Fragment as l, computed as u, createBlock as d, createCommentVNode as f, createElementBlock as p, createElementVNode as m, createTextVNode as h, createVNode as g, defineComponent as _, openBlock as v, reactive as y, renderList as b, toDisplayString as x, unref as S, withCtx as C } from "vue";
import { useI18n as w } from "vue-i18n";
import { useRoute as T } from "vue-router";
// src/pages/runs/[run].vue?vue&type=script&setup=true&lang.ts
var E = {
	class: "space-y-4",
	"data-testid": "impex-run"
}, D = {
	key: 0,
	class: "space-y-4"
}, O = {
	class: "grid gap-3 text-sm sm:grid-cols-2",
	"data-testid": "impex-run-details"
}, k = { class: "text-muted" }, A = { class: "font-mono" }, j = { class: "text-muted" }, M = { class: "text-muted" }, N = { class: "text-muted" }, P = { key: 0 }, F = { class: "text-muted" }, I = { class: "font-mono" }, L = { key: 1 }, R = { class: "text-muted" }, z = {
	key: 2,
	class: "sm:col-span-2"
}, B = { class: "text-muted" }, V = { class: "flex flex-wrap gap-1" }, H = {
	key: 3,
	class: "sm:col-span-2"
}, U = { class: "text-muted" }, W = { class: "overflow-x-auto rounded-md bg-elevated p-2 text-xs" }, ue = { class: "font-medium" }, de = { class: "font-medium" }, fe = {
	key: 0,
	class: "text-sm text-muted"
}, pe = {
	key: 1,
	class: "divide-y divide-default text-sm",
	"data-testid": "impex-run-steps"
}, me = ["data-testid"], he = { class: "flex items-center gap-2" }, ge = { class: "text-muted tabular-nums" }, _e = { class: "font-medium" }, ve = { class: "text-xs text-muted" }, ye = { class: "flex items-center gap-2 text-xs text-muted" }, be = { key: 0 }, xe = { key: 1 }, Se = { class: "font-medium" }, Ce = {
	key: 0,
	class: "text-sm text-muted"
}, we = {
	key: 1,
	class: "divide-y divide-default text-sm",
	"data-testid": "impex-run-messages"
}, Te = { class: "text-xs text-muted" }, G = /* @__PURE__ */ _({
	__name: "[run]",
	setup(_) {
		let { t: G } = w(), Ee = T(), K = u(() => String(Ee.params.run ?? "")), q = o(async (e) => c(await e.GET("/runs/{run}", { params: { path: { run: K.value } } })).data, K), J = o(async (e) => c(await e.GET("/runs/{run}/steps", { params: { path: { run: K.value } } })).data, K), Y = o(async (e) => c(await e.GET("/messages", { params: { query: { run: K.value } } })).data, K), X = ee(), Z = y({
			name: "",
			payload: ""
		}), Q = u(() => [
			"completed",
			"failed",
			"cancelled"
		].includes(q.data.value?.status ?? ""));
		async function $() {
			await Promise.all([
				q.reload(),
				J.reload(),
				Y.reload()
			]);
		}
		async function De() {
			let e = q.client;
			e && await X.run(async () => c(await e.POST("/runs/{run}/cancel", {
				params: { path: { run: K.value } },
				body: { reason: G("impex.run.cancelled_from_dashboard") }
			})), async () => (await $(), G("impex.run.cancelled")));
		}
		async function Oe() {
			let e = q.client;
			e && await X.run(async () => c(await e.POST("/runs/{run}/retry", { params: { path: { run: K.value } } })), async () => (await $(), G("impex.run.retried")));
		}
		async function ke() {
			let e = q.client, t = r(Z.payload);
			if (!e) return;
			if (t && !("value" in t)) {
				X.invalid("payload", G("impex.run.payload_invalid"));
				return;
			}
			let n = i({
				name: Z.name,
				...t && "value" in t ? { payload: t.value } : {}
			});
			await X.run(async () => c(await e.POST("/runs/{run}/signals", {
				params: { path: { run: K.value } },
				body: n
			})), async (e) => (Z.name = "", Z.payload = "", await $(), "data" in e && e.data ? G("impex.run.signal_sent") : G("impex.run.signal_ignored")));
		}
		return (ee, r) => {
			let i = ae, o = s, c = le, u = ie, _ = oe, y = ce, w = se, T = re;
			return v(), p("div", E, [g(T, {
				"screen-key": "impex.run",
				record: S(K)
			}, {
				default: C(() => [g(t, {
					error: S(q).error.value,
					ready: S(q).data.value !== null
				}, {
					default: C(() => [S(q).data.value ? (v(), p("div", D, [
						g(o, { title: S(q).data.value.flow }, {
							meta: C(() => [g(n, { status: S(q).data.value.status }, null, 8, ["status"])]),
							default: C(() => [S(Q) ? f("", !0) : (v(), d(i, {
								key: 0,
								variant: "outline",
								icon: "i-lucide-circle-stop",
								loading: S(X).busy.value,
								"data-testid": "impex-run-cancel",
								onClick: De
							}, {
								default: C(() => [h(x(S(G)("impex.run.cancel")), 1)]),
								_: 1
							}, 8, ["loading"])), S(q).data.value.status === "failed" ? (v(), d(i, {
								key: 1,
								variant: "outline",
								icon: "i-lucide-rotate-cw",
								loading: S(X).busy.value,
								"data-testid": "impex-run-retry",
								onClick: Oe
							}, {
								default: C(() => [h(x(S(G)("impex.run.retry")), 1)]),
								_: 1
							}, 8, ["loading"])) : f("", !0)]),
							_: 1
						}, 8, ["title"]),
						g(e, { action: S(X) }, null, 8, ["action"]),
						g(_, null, {
							default: C(() => [m("dl", O, [
								m("div", null, [m("dt", k, x(S(G)("impex.run.id")), 1), m("dd", A, x(S(q).data.value.id), 1)]),
								m("div", null, [m("dt", j, x(S(G)("impex.runs.trigger")), 1), m("dd", null, x(S(q).data.value.trigger), 1)]),
								m("div", null, [m("dt", M, x(S(G)("impex.runs.started")), 1), m("dd", null, x(S(a)(S(q).data.value.started_at) ?? S(G)("impex.common.none")), 1)]),
								m("div", null, [m("dt", N, x(S(G)("impex.runs.finished")), 1), m("dd", null, x(S(a)(S(q).data.value.finished_at) ?? S(G)("impex.common.none")), 1)]),
								S(q).data.value.idempotency_key ? (v(), p("div", P, [m("dt", F, x(S(G)("impex.run.idempotency_key")), 1), m("dd", I, x(S(q).data.value.idempotency_key), 1)])) : f("", !0),
								S(q).data.value.parent_run_id ? (v(), p("div", L, [m("dt", R, x(S(G)("impex.run.parent")), 1), m("dd", null, [g(c, {
									to: S(ne)(S(q).data.value.parent_run_id),
									class: "font-mono"
								}, {
									default: C(() => [h(x(S(q).data.value.parent_run_id), 1)]),
									_: 1
								}, 8, ["to"])])])) : f("", !0),
								S(q).data.value.tags && Object.keys(S(q).data.value.tags).length ? (v(), p("div", z, [m("dt", B, x(S(G)("impex.run.tags")), 1), m("dd", V, [(v(!0), p(l, null, b(S(q).data.value.tags, (e, t) => (v(), d(u, {
									key: t,
									variant: "outline",
									color: "neutral"
								}, {
									default: C(() => [h(x(t) + ": " + x(e), 1)]),
									_: 2
								}, 1024))), 128))])])) : f("", !0),
								S(q).data.value.error ? (v(), p("div", H, [m("dt", U, x(S(G)("impex.run.error")), 1), m("dd", null, [m("pre", W, x(JSON.stringify(S(q).data.value.error, null, 2)), 1)])])) : f("", !0)
							])]),
							_: 1
						}),
						S(Q) ? f("", !0) : (v(), d(_, { key: 0 }, {
							header: C(() => [m("h2", ue, x(S(G)("impex.run.signal_title")), 1)]),
							default: C(() => [g(w, {
								state: S(Z),
								class: "flex flex-wrap items-end gap-3",
								"data-testid": "impex-run-signal",
								onSubmit: ke
							}, {
								default: C(() => [
									g(y, {
										modelValue: S(Z).name,
										"onUpdate:modelValue": r[0] ||= (e) => S(Z).name = e,
										name: "name",
										placeholder: S(G)("impex.run.signal_name"),
										required: "",
										class: "w-56"
									}, null, 8, ["modelValue", "placeholder"]),
									g(y, {
										modelValue: S(Z).payload,
										"onUpdate:modelValue": r[1] ||= (e) => S(Z).payload = e,
										name: "payload",
										placeholder: S(G)("impex.run.signal_payload"),
										class: "w-72"
									}, null, 8, ["modelValue", "placeholder"]),
									g(i, {
										type: "submit",
										icon: "i-lucide-send",
										loading: S(X).busy.value
									}, {
										default: C(() => [h(x(S(G)("impex.run.send")), 1)]),
										_: 1
									}, 8, ["loading"])
								]),
								_: 1
							}, 8, ["state"])]),
							_: 1
						})),
						g(_, null, {
							header: C(() => [m("h2", de, x(S(G)("impex.run.steps")), 1)]),
							default: C(() => [g(t, {
								error: S(J).error.value,
								ready: S(J).data.value !== null
							}, {
								default: C(() => [(S(J).data.value ?? []).length === 0 ? (v(), p("p", fe, x(S(G)("impex.run.no_steps")), 1)) : (v(), p("ol", pe, [(v(!0), p(l, null, b(S(J).data.value, (e) => (v(), p("li", {
									key: e.id,
									class: "flex flex-wrap items-center justify-between gap-2 py-2",
									"data-testid": `impex-step-${e.sequence}`
								}, [m("span", he, [
									m("span", ge, x(e.sequence), 1),
									m("span", _e, x(e.name), 1),
									m("span", ve, x(e.type), 1),
									e.phase === "rollback" ? (v(), d(u, {
										key: 0,
										variant: "outline",
										color: "warning"
									}, {
										default: C(() => [h(x(S(G)("impex.run.rollback")), 1)]),
										_: 1
									})) : f("", !0),
									e.undone ? (v(), d(u, {
										key: 1,
										variant: "outline",
										color: "neutral"
									}, {
										default: C(() => [h(x(S(G)("impex.run.undone")), 1)]),
										_: 1
									})) : f("", !0)
								]), m("span", ye, [
									m("span", null, x(S(G)("impex.run.attempts", { count: e.attempts })), 1),
									e.resumptions > 0 ? (v(), p("span", be, x(S(G)("impex.run.resumed", { count: e.resumptions })), 1)) : f("", !0),
									e.result_artifact_id ? (v(), p("span", xe, x(S(G)("impex.run.result_on_disk")), 1)) : f("", !0),
									g(n, { status: e.status }, null, 8, ["status"])
								])], 8, me))), 128))]))]),
								_: 1
							}, 8, ["error", "ready"])]),
							_: 1
						}),
						g(_, null, {
							header: C(() => [m("h2", Se, x(S(G)("impex.run.messages")), 1)]),
							default: C(() => [g(t, {
								error: S(Y).error.value,
								ready: S(Y).data.value !== null
							}, {
								default: C(() => [(S(Y).data.value ?? []).length === 0 ? (v(), p("p", Ce, x(S(G)("impex.run.no_messages")), 1)) : (v(), p("ul", we, [(v(!0), p(l, null, b(S(Y).data.value, (e) => (v(), p("li", {
									key: e.id,
									class: "flex justify-between gap-2 py-2"
								}, [g(c, { to: S(te)(e.id) }, {
									default: C(() => [h(x(e.direction) + " · " + x(e.channel), 1)]),
									_: 2
								}, 1032, ["to"]), m("span", Te, x(S(a)(e.occurred_at)), 1)]))), 128))]))]),
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
export { G as default };
