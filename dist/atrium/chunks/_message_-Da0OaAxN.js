import { t as e } from "./ImpexState-C2BI291k.js";
import { p as t, w as n, y as r } from "./shared-Bu5fYXbP.js";
import { AtriumIntegrationScreen as i, AtriumPageHeader as a, integrationData as o } from "@refactor-circus/atrium";
import { UCard as s, ULink as c } from "@refactor-circus/atrium/ui";
import { computed as l, createBlock as u, createCommentVNode as d, createElementBlock as f, createElementVNode as p, createTextVNode as m, createVNode as h, defineComponent as g, openBlock as _, toDisplayString as v, unref as y, withCtx as b } from "vue";
import { useI18n as x } from "vue-i18n";
import { useRoute as S } from "vue-router";
// src/pages/messages/[message].vue?vue&type=script&setup=true&lang.ts
var C = {
	class: "space-y-4",
	"data-testid": "impex-message"
}, w = {
	key: 0,
	class: "space-y-4"
}, T = { class: "font-mono" }, E = {
	class: "grid gap-3 text-sm sm:grid-cols-2",
	"data-testid": "impex-message-details"
}, D = { class: "text-muted" }, O = { class: "text-muted" }, k = { class: "text-muted" }, A = { class: "text-muted" }, j = { class: "text-muted" }, M = { class: "text-muted" }, N = { "data-testid": "impex-message-signature" }, P = {
	key: 0,
	class: "sm:col-span-2"
}, F = { class: "text-muted" }, I = { class: "font-medium" }, L = { class: "overflow-x-auto text-xs" }, R = { class: "font-medium" }, z = {
	class: "overflow-x-auto whitespace-pre-wrap text-xs",
	"data-testid": "impex-message-body"
}, B = {
	key: 0,
	class: "mt-2 text-sm text-muted"
}, V = { class: "font-medium" }, H = { class: "overflow-x-auto text-xs" }, U = /* @__PURE__ */ g({
	__name: "[message]",
	setup(g) {
		let { t: U } = x(), W = S(), G = l(() => String(W.params.message ?? "")), K = n(async (e) => o(await e.GET("/messages/{message}", { params: { path: { message: G.value } } })).data, G), q = l(() => {
			let e = K.data.value?.signature_valid;
			return U(e === !0 ? "impex.message.verified" : e === !1 ? "impex.message.bad_signature" : "impex.message.unsigned");
		});
		return (n, o) => {
			let l = a, g = c, x = s, S = i;
			return _(), f("div", C, [h(S, {
				"screen-key": "impex.message",
				record: y(G)
			}, {
				default: b(() => [h(e, {
					error: y(K).error.value,
					ready: y(K).data.value !== null
				}, {
					default: b(() => [y(K).data.value ? (_(), f("div", w, [
						h(l, { title: `${y(U)(`impex.directions.${y(K).data.value.direction}`)} · ${y(K).data.value.channel}` }, {
							meta: b(() => [p("p", T, v(y(K).data.value.method) + " " + v(y(K).data.value.endpoint), 1)]),
							_: 1
						}, 8, ["title"]),
						h(x, null, {
							default: b(() => [p("dl", E, [
								p("div", null, [p("dt", D, v(y(U)("impex.messages.when")), 1), p("dd", null, v(y(t)(y(K).data.value.occurred_at)), 1)]),
								p("div", null, [p("dt", O, v(y(U)("impex.messages.transport")), 1), p("dd", null, v(y(K).data.value.transport), 1)]),
								p("div", null, [p("dt", k, v(y(U)("impex.messages.status_code")), 1), p("dd", null, v(y(K).data.value.status_code ?? y(U)("impex.common.none")), 1)]),
								p("div", null, [p("dt", A, v(y(U)("impex.message.duration")), 1), p("dd", null, v(y(K).data.value.duration_ms === null ? y(U)("impex.common.none") : y(U)("impex.message.milliseconds", { count: y(K).data.value.duration_ms })), 1)]),
								p("div", null, [p("dt", j, v(y(U)("impex.message.size")), 1), p("dd", null, v(y(U)("impex.message.bytes", { count: y(K).data.value.bytes })), 1)]),
								p("div", null, [p("dt", M, v(y(U)("impex.message.signature")), 1), p("dd", N, v(y(q)), 1)]),
								y(K).data.value.run_id ? (_(), f("div", P, [p("dt", F, v(y(U)("impex.message.run")), 1), p("dd", null, [h(g, {
									to: y(r)(y(K).data.value.run_id),
									class: "font-mono",
									"data-testid": "impex-message-run"
								}, {
									default: b(() => [m(v(y(K).data.value.run_id), 1)]),
									_: 1
								}, 8, ["to"])])])) : d("", !0)
							])]),
							_: 1
						}),
						y(K).data.value.headers && Object.keys(y(K).data.value.headers).length ? (_(), u(x, { key: 0 }, {
							header: b(() => [p("h2", I, v(y(U)("impex.message.headers")), 1)]),
							default: b(() => [p("pre", L, v(JSON.stringify(y(K).data.value.headers, null, 2)), 1)]),
							_: 1
						})) : d("", !0),
						h(x, null, {
							header: b(() => [p("h2", R, v(y(U)("impex.message.body")), 1)]),
							default: b(() => [p("pre", z, v(y(K).data.value.body_preview || y(U)("impex.message.empty_body")), 1), y(K).data.value.body_artifact_id ? (_(), f("p", B, v(y(U)("impex.message.body_on_disk", { id: y(K).data.value.body_artifact_id })), 1)) : d("", !0)]),
							_: 1
						}),
						y(K).data.value.error ? (_(), u(x, { key: 1 }, {
							header: b(() => [p("h2", V, v(y(U)("impex.run.error")), 1)]),
							default: b(() => [p("pre", H, v(JSON.stringify(y(K).data.value.error, null, 2)), 1)]),
							_: 1
						})) : d("", !0)
					])) : d("", !0)]),
					_: 1
				}, 8, ["error", "ready"])]),
				_: 1
			}, 8, ["record"])]);
		};
	}
});
export { U as default };
