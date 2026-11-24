import { t as e } from "./ImpexChannelFields-DkiuuJQP.js";
import { t } from "./ImpexFlash-0zPnKlOX.js";
import { t as n } from "./ImpexState-C2BI291k.js";
import { t as r } from "./ImpexStatusBadge-DCp4DAuy.js";
import { S as ee, d as i, h as a, l as o, w as s } from "./shared-Bu5fYXbP.js";
import { AtriumIntegrationScreen as c, AtriumPageHeader as l, integrationData as u } from "@refactor-circus/atrium";
import { UButton as d, UCard as f, UForm as p } from "@refactor-circus/atrium/ui";
import { Fragment as m, computed as h, createBlock as g, createCommentVNode as _, createElementBlock as v, createElementVNode as y, createTextVNode as b, createVNode as x, defineComponent as S, openBlock as C, reactive as w, ref as te, toDisplayString as T, unref as E, watch as D, withCtx as O } from "vue";
import { useI18n as k } from "vue-i18n";
import { useRoute as A, useRouter as j } from "vue-router";
// src/pages/channels/[name].vue?vue&type=script&setup=true&lang.ts
var M = {
	class: "space-y-4",
	"data-testid": "impex-channel"
}, N = {
	key: 0,
	class: "space-y-4"
}, P = {
	class: "grid gap-3 text-sm sm:grid-cols-2",
	"data-testid": "impex-channel-details"
}, F = { class: "text-muted" }, I = { class: "text-muted" }, L = { class: "text-muted" }, R = { key: 0 }, z = { class: "text-muted" }, B = { class: "text-muted" }, V = { class: "font-medium" }, H = { class: "flex flex-wrap items-end gap-3" }, U = { class: "font-medium" }, W = {
	class: "overflow-x-auto rounded-md bg-elevated p-2 text-xs",
	"data-testid": "impex-channel-options"
}, G = { class: "mt-2 text-sm text-muted" }, K = /* @__PURE__ */ S({
	__name: "[name]",
	setup(S) {
		let { t: K } = k(), q = A(), J = j(), Y = h(() => String(q.params.name ?? "")), X = s(async (e) => u(await e.GET("/channels/{name}", { params: { path: { name: Y.value } } })).data, Y), Z = ee(), Q = w(i()), $ = te(!1);
		D(() => X.data.value, (e) => {
			e && Object.assign(Q, i(e));
		});
		async function ne() {
			let e = X.client, t = o(Q);
			if (!e) return;
			if (t === null) {
				Z.invalid("options", K("impex.channel.options_invalid"));
				return;
			}
			let n = a(t);
			await Z.run(async () => u(await e.PATCH("/channels/{channel}", {
				params: { path: { channel: Y.value } },
				body: n
			})), async () => (await X.reload(), K("impex.channel.updated")));
		}
		async function re() {
			let e = X.client;
			e && await Z.run(async () => u(await e.POST("/channels/{channel}/rotate-secret", { params: { path: { channel: Y.value } } })), async (e) => (await X.reload(), K("impex.channel.secret_rotated", { secret: e.secret })));
		}
		async function ie() {
			let e = X.client;
			e && ($.value = !1, await Z.run(async () => u(await e.DELETE("/channels/{channel}", { params: { path: { channel: Y.value } } })), async () => {
				await J.push("/impex/channels");
			}));
		}
		return (ee, i) => {
			let a = d, o = l, s = f, u = p, h = c;
			return C(), v("div", M, [x(h, {
				"screen-key": "impex.channel",
				record: E(Y)
			}, {
				default: O(() => [x(n, {
					error: E(X).error.value,
					ready: E(X).data.value !== null
				}, {
					default: O(() => [E(X).data.value ? (C(), v("div", N, [
						x(o, {
							title: E(X).data.value.name,
							description: `${E(K)(`impex.directions.${E(X).data.value.direction}`)} · ${E(X).data.value.transport}`
						}, {
							default: O(() => [E(X).data.value.stored ? (C(), v(m, { key: 0 }, [x(a, {
								variant: "outline",
								icon: "i-lucide-key-round",
								loading: E(Z).busy.value,
								"data-testid": "impex-channel-rotate",
								onClick: re
							}, {
								default: O(() => [b(T(E(K)("impex.channel.rotate_secret")), 1)]),
								_: 1
							}, 8, ["loading"]), E($) ? (C(), v(m, { key: 1 }, [x(a, {
								color: "error",
								icon: "i-lucide-trash-2",
								loading: E(Z).busy.value,
								"data-testid": "impex-channel-delete-confirm",
								onClick: ie
							}, {
								default: O(() => [b(T(E(K)("impex.channel.delete_confirm")), 1)]),
								_: 1
							}, 8, ["loading"]), x(a, {
								variant: "ghost",
								"data-testid": "impex-channel-delete-cancel",
								onClick: i[1] ||= () => {
									$.value = !1;
								}
							}, {
								default: O(() => [b(T(E(K)("impex.common.cancel")), 1)]),
								_: 1
							})], 64)) : (C(), g(a, {
								key: 0,
								variant: "outline",
								color: "error",
								icon: "i-lucide-trash-2",
								"data-testid": "impex-channel-delete",
								onClick: i[0] ||= () => {
									$.value = !0;
								}
							}, {
								default: O(() => [b(T(E(K)("impex.channel.delete")), 1)]),
								_: 1
							}))], 64)) : _("", !0)]),
							_: 1
						}, 8, ["title", "description"]),
						x(t, { action: E(Z) }, null, 8, ["action"]),
						x(s, null, {
							default: O(() => [y("dl", P, [
								y("div", null, [y("dt", F, T(E(K)("impex.channels.status")), 1), y("dd", null, [x(r, { status: E(X).data.value.status }, null, 8, ["status"])])]),
								y("div", null, [y("dt", I, T(E(K)("impex.channel.body_policy")), 1), y("dd", null, T(E(K)(`impex.body_policies.${E(X).data.value.body_policy}`)), 1)]),
								y("div", null, [y("dt", L, T(E(K)("impex.message.signature")), 1), y("dd", null, T(E(X).data.value.verifies_signatures ? E(K)("impex.message.verified") : E(K)("impex.message.unsigned")), 1)]),
								E(X).data.value.flow ? (C(), v("div", R, [y("dt", z, T(E(K)("impex.channel.flow")), 1), y("dd", null, T(E(X).data.value.flow), 1)])) : _("", !0),
								y("div", null, [y("dt", B, T(E(K)("impex.channels.source")), 1), y("dd", null, T(E(X).data.value.stored ? E(K)("impex.channels.stored") : E(K)("impex.channels.from_config")), 1)])
							])]),
							_: 1
						}),
						E(X).data.value.stored ? (C(), g(s, { key: 0 }, {
							header: O(() => [y("h2", V, T(E(K)("impex.channel.edit")), 1)]),
							default: O(() => [x(u, {
								state: E(Q),
								class: "flex flex-col gap-4",
								"data-testid": "impex-channel-update",
								onSubmit: ne
							}, {
								default: O(() => [y("div", H, [x(e, { form: E(Q) }, null, 8, ["form"])]), y("div", null, [x(a, {
									type: "submit",
									loading: E(Z).busy.value
								}, {
									default: O(() => [b(T(E(K)("impex.common.save")), 1)]),
									_: 1
								}, 8, ["loading"])])]),
								_: 1
							}, 8, ["state"])]),
							_: 1
						})) : (C(), g(s, { key: 1 }, {
							header: O(() => [y("h2", U, T(E(K)("impex.channel.options")), 1)]),
							default: O(() => [y("pre", W, T(JSON.stringify(E(X).data.value.options, null, 2)), 1), y("p", G, T(E(K)("impex.channel.configured_hint")), 1)]),
							_: 1
						}))
					])) : _("", !0)]),
					_: 1
				}, 8, ["error", "ready"])]),
				_: 1
			}, 8, ["record"])]);
		};
	}
});
export { K as default };
