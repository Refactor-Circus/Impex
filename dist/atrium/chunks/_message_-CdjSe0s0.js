import { t as e } from "./ImpexState-C2BI291k.js";
import { p as t, w as n, y as r } from "./shared-_UUfUMm3.js";
import { AtriumDetails as i, AtriumIntegrationScreen as a, AtriumPageHeader as o, integrationData as s } from "@refactor-circus/atrium";
import { UCard as c, ULink as l } from "@refactor-circus/atrium/ui";
import { computed as u, createBlock as d, createCommentVNode as f, createElementBlock as p, createElementVNode as m, createTextVNode as h, createVNode as g, defineComponent as _, openBlock as v, toDisplayString as y, unref as b, withCtx as x } from "vue";
import { useI18n as S } from "vue-i18n";
import { useRoute as C } from "vue-router";
// src/pages/messages/[message].vue?vue&type=script&setup=true&lang.ts
var w = {
	class: "space-y-4",
	"data-testid": "impex-message"
}, T = {
	key: 0,
	class: "space-y-4"
}, E = { class: "font-mono" }, D = { class: "font-medium" }, O = { class: "overflow-x-auto text-xs" }, k = { class: "font-medium" }, A = {
	class: "overflow-x-auto whitespace-pre-wrap text-xs",
	"data-testid": "impex-message-body"
}, j = {
	key: 0,
	class: "mt-2 text-sm text-muted"
}, M = { class: "font-medium" }, N = { class: "overflow-x-auto text-xs" }, P = /* @__PURE__ */ _({
	__name: "[message]",
	setup(_) {
		let { t: P } = S(), F = C(), I = u(() => String(F.params.message ?? "")), L = n(async (e) => s(await e.GET("/messages/{message}", { params: { path: { message: I.value } } })).data, I), R = u(() => {
			let e = L.data.value?.signature_valid;
			return P(e === !0 ? "impex.message.verified" : e === !1 ? "impex.message.bad_signature" : "impex.message.unsigned");
		}), z = u(() => {
			let e = L.data.value;
			return e ? [
				{
					key: "when",
					label: P("impex.messages.when"),
					value: t(e.occurred_at)
				},
				{
					key: "transport",
					label: P("impex.messages.transport"),
					value: e.transport
				},
				{
					key: "status_code",
					label: P("impex.messages.status_code"),
					value: e.status_code
				},
				{
					key: "duration",
					label: P("impex.message.duration"),
					value: e.duration_ms === null ? null : P("impex.message.milliseconds", { count: e.duration_ms })
				},
				{
					key: "size",
					label: P("impex.message.size"),
					value: P("impex.message.bytes", { count: e.bytes })
				},
				{
					key: "signature",
					label: P("impex.message.signature"),
					value: R.value,
					testid: "impex-message-signature"
				},
				...e.run_id ? [{
					key: "run",
					label: P("impex.message.run"),
					wide: !0
				}] : []
			] : [];
		});
		return (t, n) => {
			let s = o, u = l, _ = i, S = c, C = a;
			return v(), p("div", w, [g(C, {
				"screen-key": "impex.message",
				record: b(I)
			}, {
				default: x(() => [g(e, {
					error: b(L).error.value,
					ready: b(L).data.value !== null
				}, {
					default: x(() => [b(L).data.value ? (v(), p("div", T, [
						g(s, { title: `${b(P)(`impex.directions.${b(L).data.value.direction}`)} · ${b(L).data.value.channel}` }, {
							meta: x(() => [m("p", E, y(b(L).data.value.method) + " " + y(b(L).data.value.endpoint), 1)]),
							_: 1
						}, 8, ["title"]),
						g(S, null, {
							default: x(() => [g(_, {
								items: b(z),
								columns: 2,
								empty: b(P)("impex.common.none"),
								"data-testid": "impex-message-details"
							}, {
								run: x(() => [g(u, {
									to: b(r)(b(L).data.value.run_id ?? ""),
									class: "atrium-mono",
									"data-testid": "impex-message-run"
								}, {
									default: x(() => [h(y(b(L).data.value.run_id), 1)]),
									_: 1
								}, 8, ["to"])]),
								_: 1
							}, 8, ["items", "empty"])]),
							_: 1
						}),
						b(L).data.value.headers && Object.keys(b(L).data.value.headers).length ? (v(), d(S, { key: 0 }, {
							header: x(() => [m("h2", D, y(b(P)("impex.message.headers")), 1)]),
							default: x(() => [m("pre", O, y(JSON.stringify(b(L).data.value.headers, null, 2)), 1)]),
							_: 1
						})) : f("", !0),
						g(S, null, {
							header: x(() => [m("h2", k, y(b(P)("impex.message.body")), 1)]),
							default: x(() => [m("pre", A, y(b(L).data.value.body_preview || b(P)("impex.message.empty_body")), 1), b(L).data.value.body_artifact_id ? (v(), p("p", j, y(b(P)("impex.message.body_on_disk", { id: b(L).data.value.body_artifact_id })), 1)) : f("", !0)]),
							_: 1
						}),
						b(L).data.value.error ? (v(), d(S, { key: 1 }, {
							header: x(() => [m("h2", M, y(b(P)("impex.run.error")), 1)]),
							default: x(() => [m("pre", N, y(JSON.stringify(b(L).data.value.error, null, 2)), 1)]),
							_: 1
						})) : f("", !0)
					])) : f("", !0)]),
					_: 1
				}, 8, ["error", "ready"])]),
				_: 1
			}, 8, ["record"])]);
		};
	}
});
export { P as default };
