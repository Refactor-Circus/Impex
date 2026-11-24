import { T as e, o as t, p as n, w as r, y as i } from "./chunks/shared-_UUfUMm3.js";
import { t as a } from "./chunks/ImpexStatusBadge-0Fh6yTGt.js";
import { t as o } from "./chunks/ImpexState-C2BI291k.js";
import { AtriumDetails as s, AtriumErrorCard as c, AtriumNoAccess as l, AtriumStat as u, defineAtriumModule as d, integrationData as f, mergeAtriumMessages as ee, useAtriumIntegration as p } from "@refactor-circus/atrium";
import { UButton as m, UCard as te, ULink as ne, USkeleton as h } from "@refactor-circus/atrium/ui";
import { Fragment as g, computed as _, createBlock as v, createCommentVNode as y, createElementBlock as b, createElementVNode as x, createTextVNode as re, createVNode as S, defineComponent as C, openBlock as w, renderList as T, toDisplayString as E, unref as D, withCtx as O } from "vue";
import { useI18n as k } from "vue-i18n";
// src/components/MessageVolumeWidget.vue?vue&type=script&setup=true&lang.ts
var A = {
	key: 3,
	class: "grid grid-cols-2 gap-2 text-sm",
	"data-testid": "impex-message-volume"
}, j = { "data-testid": "impex-message-volume-inbound" }, M = { "data-testid": "impex-message-volume-outbound" }, N = { class: "col-span-2 text-xs text-muted" }, P = /* @__PURE__ */ C({
	__name: "MessageVolumeWidget",
	props: {
		data: {},
		settings: {}
	},
	setup(t) {
		let { t: n } = k(), r = e(), i = _(() => r.data.value?.messages ?? null);
		return (e, t) => {
			let a = l, o = c, s = h, d = u;
			return D(r).forbidden.value ? (w(), v(a, {
				key: 0,
				compact: ""
			})) : D(r).status.value === "error" ? (w(), v(o, {
				key: 1,
				message: D(n)("atrium.widget.failed")
			}, null, 8, ["message"])) : D(r).status.value === "success" ? D(i) ? (w(), b("div", A, [
				S(d, {
					label: D(n)("impex.widgets.inbound"),
					icon: "i-lucide-arrow-down-left",
					tone: "primary"
				}, {
					value: O(() => [x("span", j, E(D(i).inbound), 1)]),
					_: 1
				}, 8, ["label"]),
				S(d, {
					label: D(n)("impex.widgets.outbound"),
					icon: "i-lucide-arrow-up-right"
				}, {
					value: O(() => [x("span", M, E(D(i).outbound), 1)]),
					_: 1
				}, 8, ["label"]),
				x("p", N, E(D(n)("impex.widgets.window", { hours: D(i).window_hours })), 1)
			])) : y("", !0) : (w(), v(s, {
				key: 2,
				class: "h-10"
			}));
		};
	}
}), F = { "data-testid": "impex-recent-failures" }, I = {
	key: 3,
	class: "text-sm text-muted"
}, L = {
	key: 4,
	class: "divide-y divide-default text-sm"
}, R = { class: "shrink-0 text-xs text-muted" }, z = /* @__PURE__ */ C({
	__name: "RecentFailuresWidget",
	props: {
		data: {},
		settings: {}
	},
	setup(t) {
		let { t: r } = k(), a = e(), o = _(() => a.data.value?.runs.recent_failures ?? null);
		return (e, t) => {
			let s = l, u = c, d = h, f = ne;
			return w(), b("div", F, [D(a).forbidden.value ? (w(), v(s, {
				key: 0,
				compact: ""
			})) : D(a).status.value === "error" ? (w(), v(u, {
				key: 1,
				message: D(r)("atrium.widget.failed")
			}, null, 8, ["message"])) : D(o) ? D(o).length === 0 ? (w(), b("p", I, E(D(r)("impex.widgets.no_failures")), 1)) : (w(), b("ul", L, [(w(!0), b(g, null, T(D(o), (e) => (w(), b("li", {
				key: e.id,
				class: "flex items-center justify-between gap-2 py-1.5"
			}, [S(f, {
				to: D(i)(e.id),
				class: "truncate font-medium",
				"data-testid": `impex-failure-${e.id}`
			}, {
				default: O(() => [re(E(e.flow), 1)]),
				_: 2
			}, 1032, ["to", "data-testid"]), x("span", R, E(D(n)(e.finished_at)), 1)]))), 128))])) : (w(), v(d, {
				key: 2,
				class: "h-16"
			}))]);
		};
	}
}), B = { "data-testid": "impex-run-status" }, V = {
	key: 3,
	class: "space-y-3"
}, H = {
	class: "text-sm text-muted",
	"data-testid": "impex-run-status-active"
}, U = { class: "grid grid-cols-2 gap-2 text-sm" }, W = ["data-testid"], G = { class: "font-medium tabular-nums" }, K = /* @__PURE__ */ C({
	__name: "RunStatusWidget",
	props: {
		data: {},
		settings: {}
	},
	setup(n) {
		let { t: r } = k(), i = e(), o = _(() => i.data.value?.runs ?? null);
		return (e, n) => {
			let s = l, u = c, d = h;
			return w(), b("div", B, [D(i).forbidden.value ? (w(), v(s, {
				key: 0,
				compact: ""
			})) : D(i).status.value === "error" ? (w(), v(u, {
				key: 1,
				message: D(r)("atrium.widget.failed")
			}, null, 8, ["message"])) : D(o) ? (w(), b("div", V, [x("p", H, E(D(r)("impex.widgets.active", { count: D(o).active })), 1), x("ul", U, [(w(!0), b(g, null, T(D(t), (e) => (w(), b("li", {
				key: e,
				class: "flex items-center justify-between gap-2",
				"data-testid": `impex-run-status-${e}`
			}, [S(a, { status: e }, null, 8, ["status"]), x("span", G, E(D(o).by_status[e] ?? 0), 1)], 8, W))), 128))])])) : (w(), v(d, {
				key: 2,
				class: "h-16"
			}))]);
		};
	}
}), q = { "data-testid": "impex-settings" }, J = { class: "grid gap-4 md:grid-cols-3" }, Y = { class: "font-medium" }, ie = /* @__PURE__ */ C({
	__name: "SettingsPanel",
	props: { data: {} },
	setup(e) {
		let { t } = k(), n = r(async (e) => f(await e.GET("/settings-summary")).data), i = (e) => t("impex.settings.days", { count: e }), a = (e) => t("impex.settings.seconds", { count: e }), c = (e) => t("impex.settings.bytes", { count: e }), l = _(() => {
			let e = n.data.value;
			return e ? [
				{
					key: "retention",
					title: t("impex.settings.retention"),
					rows: [
						{
							key: "completed_runs_days",
							label: t("impex.settings.completed_runs"),
							value: i(e.retention.completed_runs_days)
						},
						{
							key: "failed_runs_days",
							label: t("impex.settings.failed_runs"),
							value: i(e.retention.failed_runs_days)
						},
						{
							key: "messages_days",
							label: t("impex.settings.messages"),
							value: i(e.retention.messages_days)
						},
						{
							key: "artifacts_days",
							label: t("impex.settings.artifacts"),
							value: i(e.retention.artifacts_days)
						},
						{
							key: "events_days",
							label: t("impex.settings.events"),
							value: i(e.retention.events_days)
						},
						{
							key: "deliveries_days",
							label: t("impex.settings.deliveries"),
							value: i(e.retention.deliveries_days)
						}
					]
				},
				{
					key: "limits",
					title: t("impex.settings.limits"),
					rows: [
						{
							key: "max_step_seconds",
							label: t("impex.settings.max_step"),
							value: a(e.limits.max_step_seconds)
						},
						{
							key: "resume_margin_seconds",
							label: t("impex.settings.resume_margin"),
							value: a(e.limits.resume_margin_seconds)
						},
						{
							key: "lease_seconds",
							label: t("impex.settings.lease"),
							value: a(e.limits.lease_seconds)
						},
						{
							key: "lock_seconds",
							label: t("impex.settings.lock"),
							value: a(e.limits.lock_seconds)
						},
						{
							key: "max_resumptions",
							label: t("impex.settings.max_resumptions"),
							value: String(e.limits.max_resumptions)
						},
						{
							key: "fan_out_max",
							label: t("impex.settings.fan_out_max"),
							value: String(e.limits.fan_out_max)
						},
						{
							key: "sync_seconds",
							label: t("impex.settings.sync"),
							value: a(e.limits.sync_seconds)
						}
					]
				},
				{
					key: "artifacts",
					title: t("impex.settings.artifact_storage"),
					rows: [
						{
							key: "disk",
							label: t("impex.settings.disk"),
							value: e.artifacts.disk ?? t("impex.settings.default_disk")
						},
						{
							key: "path",
							label: t("impex.settings.path"),
							value: e.artifacts.path
						},
						{
							key: "inline_threshold",
							label: t("impex.settings.inline_threshold"),
							value: c(e.artifacts.inline_threshold)
						},
						{
							key: "memo_bytes",
							label: t("impex.settings.memo"),
							value: c(e.artifacts.memo_bytes)
						},
						{
							key: "preview_bytes",
							label: t("impex.settings.preview_bytes"),
							value: c(e.preview_bytes)
						}
					]
				}
			] : [];
		});
		return (e, t) => {
			let r = s, i = te;
			return w(), b("div", q, [S(o, {
				error: D(n).error.value,
				ready: D(n).data.value !== null
			}, {
				default: O(() => [x("div", J, [(w(!0), b(g, null, T(D(l), (e) => (w(), v(i, {
					key: e.key,
					"data-testid": `impex-settings-${e.key}`
				}, {
					header: O(() => [x("h3", Y, E(e.title), 1)]),
					default: O(() => [S(r, { items: e.rows }, null, 8, ["items"])]),
					_: 2
				}, 1032, ["data-testid"]))), 128))])]),
				_: 1
			}, 8, ["error", "ready"])]);
		};
	}
});
// src/components/hooks/hooks.ts
function ae(e) {
	return typeof e == "number" && Number.isFinite(e) ? String(e) : typeof e == "string" && e !== "" ? e : null;
}
function oe(e) {
	let t = ae(e);
	return t === null ? null : `/impex/runs/${encodeURIComponent(t)}`;
}
// src/components/hooks/ImpexRunLink.vue
var se = /* @__PURE__ */ C({
	__name: "ImpexRunLink",
	props: {
		data: {},
		record: {}
	},
	setup(e) {
		let t = e, { t: n } = k(), r = _(() => oe(t.record));
		return (e, t) => {
			let i = m;
			return D(r) ? (w(), v(i, {
				key: 0,
				to: D(r),
				size: "xs",
				color: "neutral",
				variant: "ghost",
				icon: "i-lucide-workflow",
				label: D(n)("hooks.run_link.label"),
				"data-testid": "hooks-impex-run-link"
			}, null, 8, ["to", "label"])) : y("", !0);
		};
	}
}), ce = { hooks: { run_link: { label: "Run" } } }, le = { impex: {
	common: {
		not_found_title: "Not found",
		not_found_description: "It may have been deleted, or the link is wrong.",
		load_failed: "This failed to load.",
		action_forbidden: "You may not do that.",
		filter: "Filter",
		clear: "Clear",
		none: "None",
		load_more: "Load more",
		cancel: "Cancel",
		save: "Save"
	},
	statuses: {
		pending: "Pending",
		running: "Running",
		waiting: "Waiting",
		rolling_back: "Rolling back",
		completed: "Completed",
		failed: "Failed",
		cancelled: "Cancelled",
		undone: "Undone",
		skipped: "Skipped",
		active: "Active",
		disabled: "Disabled",
		paused: "Paused",
		succeeded: "Succeeded"
	},
	directions: {
		inbound: "Inbound",
		outbound: "Outbound"
	},
	body_policies: {
		all: "Keep every body",
		failures: "Keep failed bodies only",
		none: "Keep no bodies"
	},
	widgets: {
		active: "{count} active",
		no_failures: "No failed runs.",
		inbound: "Inbound",
		outbound: "Outbound",
		window: "Over the last {hours} hours"
	},
	settings: {
		days: "{count} days",
		seconds: "{count} seconds",
		bytes: "{count} bytes",
		retention: "Retention",
		completed_runs: "Completed runs",
		failed_runs: "Failed runs",
		messages: "Messages",
		artifacts: "Artifacts",
		events: "Stream events",
		deliveries: "Deliveries",
		limits: "Limits",
		max_step: "Longest step",
		resume_margin: "Resume margin",
		lease: "Lease",
		lock: "Lock",
		max_resumptions: "Most resumptions",
		fan_out_max: "Largest fan-out",
		sync: "Synchronous wait",
		artifact_storage: "Artifact storage",
		disk: "Disk",
		default_disk: "The default disk",
		path: "Path",
		inline_threshold: "Stored inline below",
		memo: "Memo size",
		preview_bytes: "Body preview"
	},
	runs: {
		title: "Runs",
		all_statuses: "Any status",
		status: "Status",
		all_triggers: "Any trigger",
		trigger: "Trigger",
		flow: "Flow",
		empty: "No runs.",
		started: "Started",
		finished: "Finished"
	},
	run: {
		id: "Run",
		cancel: "Cancel",
		cancelled: "The run was cancelled.",
		cancelled_from_dashboard: "Cancelled from the dashboard.",
		retry: "Retry",
		retried: "The run is being retried.",
		idempotency_key: "Idempotency key",
		parent: "Parent run",
		tags: "Tags",
		error: "Error",
		signal_title: "Send a signal",
		signal_name: "Signal name",
		signal_payload: "Payload (JSON, optional)",
		send: "Send",
		signal_sent: "The signal was sent.",
		signal_ignored: "The run has finished; nothing was delivered.",
		payload_invalid: "The payload is not valid JSON.",
		steps: "Steps",
		no_steps: "No steps yet.",
		rollback: "Rollback",
		undone: "Undone",
		attempts: "{count} attempts",
		resumed: "Resumed {count} times",
		result_on_disk: "Result on disk",
		messages: "Messages",
		no_messages: "This run sent and received no messages."
	},
	flows: {
		title: "Flows",
		empty: "No flows are registered.",
		slug: "Flow",
		schedule: "Schedule",
		state: "State",
		enabled: "Enabled",
		disabled: "Disabled",
		run: "Run",
		arguments: "Arguments (JSON, optional)",
		arguments_invalid: "The arguments must be a JSON array or object."
	},
	messages: {
		title: "Messages",
		all_directions: "Any direction",
		direction: "Direction",
		channel: "Channel",
		empty: "No messages.",
		endpoint: "Endpoint",
		status_code: "Status",
		when: "When",
		transport: "Transport"
	},
	message: {
		verified: "Verified",
		bad_signature: "Bad signature",
		unsigned: "Unsigned",
		duration: "Duration",
		milliseconds: "{count} ms",
		size: "Size",
		bytes: "{count} bytes",
		signature: "Signature",
		run: "Run",
		headers: "Headers",
		body: "Body",
		empty_body: "No body was kept.",
		body_on_disk: "The full body is on the artifact disk ({id})."
	},
	channels: {
		title: "Channels",
		new: "New channel",
		empty: "No channels.",
		name: "Name",
		direction: "Direction",
		transport: "Transport",
		status: "Status",
		source: "Source",
		stored: "Stored",
		from_config: "From config"
	},
	channel: {
		create_title: "New channel",
		create: "Create channel",
		name_hint: "Lowercase, dotted or dashed. It cannot be renamed later.",
		edit: "Edit channel",
		updated: "The channel was updated.",
		body_policy: "Body policy",
		signing_secret: "Signing secret",
		signing_secret_hint: "Leave blank to keep the current secret.",
		options: "Options",
		options_hint: "The transport's settings, as a JSON object.",
		options_invalid: "The options must be a JSON object.",
		rotate_secret: "Rotate secret",
		secret_rotated: "The secret was rotated. Copy it now, it is not shown again: {secret}",
		delete: "Delete",
		delete_confirm: "Delete this channel",
		flow: "Flow",
		configured_hint: "This channel comes from config. Change it there."
	},
	subscriptions: {
		title: "Subscriptions",
		stream: "Stream",
		all_statuses: "Any status",
		status: "Status",
		empty: "No subscriptions.",
		subscriber: "Subscriber",
		endpoint: "Endpoint",
		feed_only: "Feed only",
		failures: "Failures",
		last_delivered: "Last delivered"
	},
	subscription: {
		pause: "Pause",
		paused: "The subscription was paused.",
		resume: "Resume",
		resumed: "The subscription was resumed.",
		ping: "Ping",
		ping_succeeded: "The endpoint answered.",
		ping_failed: "The ping failed: {error}",
		replay_from: "Replay from event",
		replay: "Replay",
		replaying: "The subscription will replay from that event.",
		replay_invalid: "Enter an event number.",
		topics: "Topics",
		selection: "Selection",
		format: "Format",
		cursor: "Cursor",
		paused_until: "Paused until",
		last_error: "Last error",
		deliveries: "Deliveries",
		no_deliveries: "No deliveries yet.",
		events: "Events"
	}
} }, X = [
	{
		path: "/impex/channels/:name",
		name: "impex-channels-name",
		component: () => import("./chunks/_name_-BvDsVPDY.js")
	},
	{
		path: "/impex/channels/create",
		name: "impex-channels-create",
		component: () => import("./chunks/create-CBdknnaf.js")
	},
	{
		path: "/impex/channels",
		name: "impex-channels",
		component: () => import("./chunks/channels-DWlf0oGz.js")
	},
	{
		path: "/impex/flows",
		name: "impex-flows",
		component: () => import("./chunks/flows-CwsmnM7k.js")
	},
	{
		path: "/impex/messages/:message",
		name: "impex-messages-message",
		component: () => import("./chunks/_message_-CdjSe0s0.js")
	},
	{
		path: "/impex/messages",
		name: "impex-messages",
		component: () => import("./chunks/messages-CzOmL9q3.js")
	},
	{
		path: "/impex/runs/:run",
		name: "impex-runs-run",
		component: () => import("./chunks/_run_-C0UUt8Yf.js")
	},
	{
		path: "/impex/runs",
		name: "impex-runs",
		component: () => import("./chunks/runs-DPTA73YY.js")
	},
	{
		path: "/impex/subscriptions/:subscription",
		name: "impex-subscriptions-subscription",
		component: () => import("./chunks/_subscription_-D_nh-iq1.js")
	},
	{
		path: "/impex/subscriptions",
		name: "impex-subscriptions",
		component: () => import("./chunks/subscriptions-BkHAmGsw.js")
	}
], Z = {
	"impex.runs": "impex-runs",
	"impex.run": "impex-runs-run",
	"impex.flows": "impex-flows",
	"impex.messages": "impex-messages",
	"impex.message": "impex-messages-message",
	"impex.channels": "impex-channels",
	"impex.channel-create": "impex-channels-create",
	"impex.channel": "impex-channels-name",
	"impex.subscriptions": "impex-subscriptions",
	"impex.subscription": "impex-subscriptions-subscription"
};
async function Q(e, t) {
	let { client: n } = p("impex");
	return n ? f(await n.GET("/search", { params: { query: {
		q: e,
		limit: t.limit
	} } })).data.slice(0, t.limit).map((e) => ({
		title: e.flow,
		subtitle: e.status,
		icon: null,
		group: null,
		link: {
			path: i(e.id),
			external: !1
		}
	})) : [];
}
async function $() {
	let { client: e } = p("impex");
	if (!e) return { badge: null };
	try {
		let t = f(await e.GET("/stats")).data.runs.active;
		return { badge: t > 0 ? t : null };
	} catch {
		return { badge: null };
	}
}
var ue = d({
	key: "impex",
	routes: X,
	screens: Z,
	widgets: {
		"impex.run-status": K,
		"impex.recent-failures": z,
		"impex.message-volume": P
	},
	extensions: { "impex.run-link": se },
	settingsPanels: { impex: ie },
	liveResolvers: { "impex.active-runs": $ },
	searchSources: { impex: Q },
	messages: { en: ee(ce, le) }
});
export { $ as activeRuns, ue as default, Z as screens, Q as searchRuns };
