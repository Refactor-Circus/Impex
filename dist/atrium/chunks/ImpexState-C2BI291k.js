import { AtriumErrorCard as e, AtriumNoAccess as t, AtriumState as n, atriumStatus as r } from "@refactor-circus/atrium";
import { USkeleton as i } from "@refactor-circus/atrium/ui";
import { computed as a, createBlock as o, defineComponent as s, openBlock as c, renderSlot as l, unref as u } from "vue";
import { useI18n as d } from "vue-i18n";
// src/components/ImpexState.vue
var f = /* @__PURE__ */ s({
	__name: "ImpexState",
	props: {
		error: {},
		ready: { type: Boolean }
	},
	setup(s) {
		let f = s, { t: p } = d(), m = a(() => r(f.error));
		return (r, a) => {
			let d = t, f = n, h = e, g = i;
			return u(m) === 403 ? (c(), o(d, { key: 0 })) : u(m) === 404 ? (c(), o(f, {
				key: 1,
				kind: "info",
				icon: "i-lucide-search-x",
				title: u(p)("impex.common.not_found_title"),
				description: u(p)("impex.common.not_found_description"),
				"data-testid": "impex-not-found"
			}, null, 8, ["title", "description"])) : s.error ? (c(), o(h, {
				key: 2,
				message: u(p)("impex.common.load_failed")
			}, null, 8, ["message"])) : s.ready ? l(r.$slots, "default", {}, void 0, void 0, 4) : (c(), o(g, {
				key: 3,
				class: "h-24"
			}));
		};
	}
});
export { f as t };
