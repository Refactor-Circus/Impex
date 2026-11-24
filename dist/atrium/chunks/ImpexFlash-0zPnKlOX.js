import { AtriumErrorCard as e, AtriumNoAccess as t } from "@refactor-circus/atrium";
import { UAlert as n } from "@refactor-circus/atrium/ui";
import { Fragment as r, computed as i, createBlock as a, createCommentVNode as o, createElementBlock as s, createElementVNode as c, defineComponent as l, openBlock as u, renderList as d, toDisplayString as f, unref as p, withCtx as m } from "vue";
import { useI18n as h } from "vue-i18n";
// src/components/ImpexFlash.vue?vue&type=script&setup=true&lang.ts
var g = {
	key: 0,
	class: "space-y-2"
}, _ = { class: "space-y-1" }, v = ["data-field"], y = /* @__PURE__ */ l({
	__name: "ImpexFlash",
	props: { action: {} },
	setup(l) {
		let y = l, { t: b } = h(), x = i(() => y.action.notice.value), S = i(() => y.action.failure.value), C = i(() => y.action.forbidden.value), w = i(() => Object.entries(y.action.errors.value));
		return (i, l) => {
			let h = n, y = t, T = e;
			return p(x) || p(S) || p(C) || p(w).length ? (u(), s("div", g, [
				p(x) ? (u(), a(h, {
					key: 0,
					color: "success",
					variant: "subtle",
					title: p(x),
					"data-testid": "impex-notice"
				}, null, 8, ["title"])) : o("", !0),
				p(C) ? (u(), a(y, {
					key: 1,
					compact: "",
					description: p(b)("impex.common.action_forbidden")
				}, null, 8, ["description"])) : o("", !0),
				p(S) ? (u(), a(T, {
					key: 2,
					message: p(S)
				}, null, 8, ["message"])) : o("", !0),
				p(w).length ? (u(), a(h, {
					key: 3,
					color: "error",
					variant: "subtle",
					icon: "i-lucide-circle-alert",
					role: "alert",
					"data-testid": "impex-field-errors"
				}, {
					description: m(() => [c("ul", _, [(u(!0), s(r, null, d(p(w), ([e, t]) => (u(), s("li", {
						key: e,
						"data-field": e
					}, f(t.join(" ")), 9, v))), 128))])]),
					_: 1
				})) : o("", !0)
			])) : o("", !0);
		};
	}
});
export { y as t };
