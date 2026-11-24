import { t as e } from "./ImpexChannelFields-DkiuuJQP.js";
import { t } from "./ImpexFlash-0zPnKlOX.js";
import { S as n, d as r, h as i, l as a, n as o, u as s } from "./shared-Bu5fYXbP.js";
import { AtriumIntegrationScreen as c, AtriumPageHeader as l, integrationData as u, useAtriumIntegration as d } from "@refactor-circus/atrium";
import { UButton as f, UCard as p, UForm as m, UFormField as h, UInput as g, USelect as _ } from "@refactor-circus/atrium/ui";
import { computed as v, createElementBlock as y, createElementVNode as b, createTextVNode as x, createVNode as S, defineComponent as C, openBlock as w, reactive as T, toDisplayString as E, unref as D, withCtx as O } from "vue";
import { useI18n as k } from "vue-i18n";
import { useRouter as A } from "vue-router";
// src/pages/channels/create.vue?vue&type=script&setup=true&lang.ts
var j = {
	class: "space-y-4",
	"data-testid": "impex-channel-create"
}, M = { class: "space-y-4" }, N = { class: "flex flex-wrap items-end gap-3" }, P = /* @__PURE__ */ C({
	__name: "create",
	setup(C) {
		let { t: P } = k(), F = A(), { client: I } = d("impex"), L = n(), R = T({
			name: "",
			direction: "inbound",
			...r()
		}), z = v(() => o.map((e) => ({
			label: P(`impex.directions.${e}`),
			value: e
		})));
		async function B() {
			if (!I) return;
			let e = a(R);
			if (e === null) {
				L.invalid("options", P("impex.channel.options_invalid"));
				return;
			}
			let t = i({
				name: R.name,
				direction: R.direction,
				...e
			});
			await L.run(async () => u(await I.POST("/channels", { body: t })), async (e) => {
				await F.push(s(e.data.name));
			});
		}
		return (n, r) => {
			let i = l, a = g, o = h, s = _, u = f, d = m, v = p, C = c;
			return w(), y("div", j, [S(i, { title: D(P)("impex.channel.create_title") }, null, 8, ["title"]), S(C, { "screen-key": "impex.channel-create" }, {
				default: O(() => [b("div", M, [S(t, { action: D(L) }, null, 8, ["action"]), S(v, null, {
					default: O(() => [S(d, {
						state: D(R),
						class: "flex flex-col gap-4",
						"data-testid": "impex-channel-create-form",
						onSubmit: B
					}, {
						default: O(() => [b("div", N, [
							S(o, {
								label: D(P)("impex.channels.name"),
								hint: D(P)("impex.channel.name_hint"),
								class: "w-64"
							}, {
								default: O(() => [S(a, {
									modelValue: D(R).name,
									"onUpdate:modelValue": r[0] ||= (e) => D(R).name = e,
									name: "name",
									required: ""
								}, null, 8, ["modelValue"])]),
								_: 1
							}, 8, ["label", "hint"]),
							S(o, {
								label: D(P)("impex.channels.direction"),
								class: "w-44"
							}, {
								default: O(() => [S(s, {
									modelValue: D(R).direction,
									"onUpdate:modelValue": r[1] ||= (e) => D(R).direction = e,
									items: D(z),
									name: "direction"
								}, null, 8, ["modelValue", "items"])]),
								_: 1
							}, 8, ["label"]),
							S(e, { form: D(R) }, null, 8, ["form"])
						]), b("div", null, [S(u, {
							type: "submit",
							icon: "i-lucide-plus",
							loading: D(L).busy.value,
							"data-testid": "impex-channel-create-submit"
						}, {
							default: O(() => [x(E(D(P)("impex.channel.create")), 1)]),
							_: 1
						}, 8, ["loading"])])]),
						_: 1
					}, 8, ["state"])]),
					_: 1
				})])]),
				_: 1
			})]);
		};
	}
});
export { P as default };
