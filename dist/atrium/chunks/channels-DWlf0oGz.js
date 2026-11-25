import { t as e } from "./ImpexState-C2BI291k.js";
import { t } from "./ImpexStatusBadge-0Fh6yTGt.js";
import { u as n, w as r } from "./shared-_UUfUMm3.js";
import { AtriumIntegrationScreen as i, AtriumPageHeader as a, AtriumState as o, integrationData as s } from "@refactor-circus/atrium";
import { UButton as c, ULink as l, UTable as u } from "@refactor-circus/atrium/ui";
import { computed as d, createElementBlock as f, createTextVNode as p, createVNode as m, defineComponent as h, openBlock as g, toDisplayString as _, unref as v, withCtx as y } from "vue";
import { useI18n as b } from "vue-i18n";
// src/pages/channels/index.vue?vue&type=script&setup=true&lang.ts
var x = {
	class: "space-y-4",
	"data-testid": "impex-channels"
}, S = /* @__PURE__ */ h({
	__name: "index",
	setup(h) {
		let { t: S } = b(), C = r(async (e) => s(await e.GET("/channels")).data), w = d(() => [
			{
				accessorKey: "name",
				header: S("impex.channels.name")
			},
			{
				accessorKey: "direction",
				header: S("impex.channels.direction")
			},
			{
				accessorKey: "transport",
				header: S("impex.channels.transport")
			},
			{
				accessorKey: "status",
				header: S("impex.channels.status")
			},
			{
				accessorKey: "stored",
				header: S("impex.channels.source")
			}
		]);
		return (r, s) => {
			let d = c, h = a, b = l, T = o, E = u, D = i;
			return g(), f("div", x, [m(h, { title: v(S)("impex.channels.title") }, {
				default: y(() => [m(d, {
					to: "/impex/channels/create",
					icon: "i-lucide-plus",
					"data-testid": "impex-new-channel"
				}, {
					default: y(() => [p(_(v(S)("impex.channels.new")), 1)]),
					_: 1
				})]),
				_: 1
			}, 8, ["title"]), m(D, { "screen-key": "impex.channels" }, {
				default: y(() => [m(e, {
					error: v(C).error.value,
					ready: v(C).data.value !== null
				}, {
					default: y(() => [m(E, {
						data: v(C).data.value ?? [],
						columns: v(w),
						"data-testid": "impex-channels-table"
					}, {
						"name-cell": y(({ row: e }) => [m(b, {
							to: v(n)(e.original.name),
							class: "font-medium",
							"data-testid": `impex-channel-row-${e.original.name}`
						}, {
							default: y(() => [p(_(e.original.name), 1)]),
							_: 2
						}, 1032, ["to", "data-testid"])]),
						"direction-cell": y(({ row: e }) => [p(_(v(S)(`impex.directions.${e.original.direction}`)), 1)]),
						"status-cell": y(({ row: e }) => [m(t, { status: e.original.status }, null, 8, ["status"])]),
						"stored-cell": y(({ row: e }) => [p(_(e.original.stored ? v(S)("impex.channels.stored") : v(S)("impex.channels.from_config")), 1)]),
						empty: y(() => [m(T, {
							compact: "",
							title: v(S)("impex.channels.empty"),
							"data-testid": "impex-channels-empty"
						}, null, 8, ["title"])]),
						_: 1
					}, 8, ["data", "columns"])]),
					_: 1
				}, 8, ["error", "ready"])]),
				_: 1
			})]);
		};
	}
});
export { S as default };
