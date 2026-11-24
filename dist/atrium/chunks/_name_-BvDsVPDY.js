import { t as e } from "./ImpexChannelFields-BefAUWod.js";
import { t } from "./ImpexFlash-0zPnKlOX.js";
import { t as n } from "./ImpexState-C2BI291k.js";
import { t as r } from "./ImpexStatusBadge-0Fh6yTGt.js";
import { S as i, d as a, h as o, l as s, w as c } from "./shared-_UUfUMm3.js";
import { AtriumDetails as l, AtriumIntegrationScreen as u, AtriumPageHeader as d, integrationData as f } from "@refactor-circus/atrium";
import { UButton as p, UCard as m, UForm as h } from "@refactor-circus/atrium/ui";
import { Fragment as g, computed as _, createBlock as v, createCommentVNode as y, createElementBlock as b, createElementVNode as x, createTextVNode as S, createVNode as C, defineComponent as w, openBlock as T, reactive as E, ref as D, toDisplayString as O, unref as k, watch as A, withCtx as j } from "vue";
import { useI18n as M } from "vue-i18n";
import { useRoute as N, useRouter as P } from "vue-router";
// src/pages/channels/[name].vue?vue&type=script&setup=true&lang.ts
var F = {
	class: "space-y-4",
	"data-testid": "impex-channel"
}, I = {
	key: 0,
	class: "space-y-4"
}, L = { class: "font-medium" }, R = { class: "flex flex-wrap items-start gap-3" }, z = { class: "font-medium" }, B = {
	class: "overflow-x-auto rounded-md bg-elevated p-2 text-xs",
	"data-testid": "impex-channel-options"
}, V = { class: "mt-2 text-sm text-muted" }, H = /* @__PURE__ */ w({
	__name: "[name]",
	setup(w) {
		let { t: H } = M(), U = N(), W = P(), G = _(() => String(U.params.name ?? "")), K = c(async (e) => f(await e.GET("/channels/{name}", { params: { path: { name: G.value } } })).data, G), q = i(), J = E(a()), Y = D(!1), X = _(() => {
			let e = K.data.value;
			return e ? [
				{
					key: "status",
					label: H("impex.channels.status")
				},
				{
					key: "body_policy",
					label: H("impex.channel.body_policy"),
					value: H(`impex.body_policies.${e.body_policy}`)
				},
				{
					key: "signature",
					label: H("impex.message.signature"),
					value: e.verifies_signatures ? H("impex.message.verified") : H("impex.message.unsigned")
				},
				...e.flow ? [{
					key: "flow",
					label: H("impex.channel.flow"),
					value: e.flow
				}] : [],
				{
					key: "source",
					label: H("impex.channels.source"),
					value: e.stored ? H("impex.channels.stored") : H("impex.channels.from_config")
				}
			] : [];
		});
		A(() => K.data.value, (e) => {
			e && Object.assign(J, a(e));
		});
		async function Z() {
			let e = K.client, t = s(J);
			if (!e) return;
			if (t === null) {
				q.invalid("options", H("impex.channel.options_invalid"));
				return;
			}
			let n = o(t);
			await q.run(async () => f(await e.PATCH("/channels/{channel}", {
				params: { path: { channel: G.value } },
				body: n
			})), async () => (await K.reload(), H("impex.channel.updated")));
		}
		async function Q() {
			let e = K.client;
			e && await q.run(async () => f(await e.POST("/channels/{channel}/rotate-secret", { params: { path: { channel: G.value } } })), async (e) => (await K.reload(), H("impex.channel.secret_rotated", { secret: e.secret })));
		}
		async function $() {
			let e = K.client;
			e && (Y.value = !1, await q.run(async () => f(await e.DELETE("/channels/{channel}", { params: { path: { channel: G.value } } })), async () => {
				await W.push("/impex/channels");
			}));
		}
		return (i, a) => {
			let o = p, s = d, c = l, f = m, _ = h, w = u;
			return T(), b("div", F, [C(w, {
				"screen-key": "impex.channel",
				record: k(G)
			}, {
				default: j(() => [C(n, {
					error: k(K).error.value,
					ready: k(K).data.value !== null
				}, {
					default: j(() => [k(K).data.value ? (T(), b("div", I, [
						C(s, {
							title: k(K).data.value.name,
							description: `${k(H)(`impex.directions.${k(K).data.value.direction}`)} · ${k(K).data.value.transport}`
						}, {
							default: j(() => [k(K).data.value.stored ? (T(), b(g, { key: 0 }, [C(o, {
								variant: "outline",
								icon: "i-lucide-key-round",
								loading: k(q).busy.value,
								"data-testid": "impex-channel-rotate",
								onClick: Q
							}, {
								default: j(() => [S(O(k(H)("impex.channel.rotate_secret")), 1)]),
								_: 1
							}, 8, ["loading"]), k(Y) ? (T(), b(g, { key: 1 }, [C(o, {
								color: "error",
								icon: "i-lucide-trash-2",
								loading: k(q).busy.value,
								"data-testid": "impex-channel-delete-confirm",
								onClick: $
							}, {
								default: j(() => [S(O(k(H)("impex.channel.delete_confirm")), 1)]),
								_: 1
							}, 8, ["loading"]), C(o, {
								variant: "ghost",
								"data-testid": "impex-channel-delete-cancel",
								onClick: a[1] ||= () => {
									Y.value = !1;
								}
							}, {
								default: j(() => [S(O(k(H)("impex.common.cancel")), 1)]),
								_: 1
							})], 64)) : (T(), v(o, {
								key: 0,
								variant: "outline",
								color: "error",
								icon: "i-lucide-trash-2",
								"data-testid": "impex-channel-delete",
								onClick: a[0] ||= () => {
									Y.value = !0;
								}
							}, {
								default: j(() => [S(O(k(H)("impex.channel.delete")), 1)]),
								_: 1
							}))], 64)) : y("", !0)]),
							_: 1
						}, 8, ["title", "description"]),
						C(t, { action: k(q) }, null, 8, ["action"]),
						C(f, null, {
							default: j(() => [C(c, {
								items: k(X),
								columns: 2,
								"data-testid": "impex-channel-details"
							}, {
								status: j(() => [C(r, { status: k(K).data.value.status }, null, 8, ["status"])]),
								_: 1
							}, 8, ["items"])]),
							_: 1
						}),
						k(K).data.value.stored ? (T(), v(f, { key: 0 }, {
							header: j(() => [x("h2", L, O(k(H)("impex.channel.edit")), 1)]),
							default: j(() => [C(_, {
								state: k(J),
								class: "flex flex-col gap-4",
								"data-testid": "impex-channel-update",
								onSubmit: Z
							}, {
								default: j(() => [x("div", R, [C(e, { form: k(J) }, null, 8, ["form"])]), x("div", null, [C(o, {
									type: "submit",
									loading: k(q).busy.value
								}, {
									default: j(() => [S(O(k(H)("impex.common.save")), 1)]),
									_: 1
								}, 8, ["loading"])])]),
								_: 1
							}, 8, ["state"])]),
							_: 1
						})) : (T(), v(f, { key: 1 }, {
							header: j(() => [x("h2", z, O(k(H)("impex.channel.options")), 1)]),
							default: j(() => [x("pre", B, O(JSON.stringify(k(K).data.value.options, null, 2)), 1), x("p", V, O(k(H)("impex.channel.configured_hint")), 1)]),
							_: 1
						}))
					])) : y("", !0)]),
					_: 1
				}, 8, ["error", "ready"])]),
				_: 1
			}, 8, ["record"])]);
		};
	}
});
export { H as default };
