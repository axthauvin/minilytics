const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const test = require("node:test");
const vm = require("node:vm");

const root = path.resolve(__dirname, "..");
const tracker = fs.readFileSync(path.join(root, "minilytics.js"), "utf8");

function storage() {
    const values = new Map();
    return {
        values,
        getItem: (key) => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, String(value)),
        removeItem: (key) => values.delete(key),
    };
}

function loadTracker(options = {}) {
    const beacons = [];
    const errors = [];
    const listeners = {};
    const documentListeners = {};
    const timers = [];
    const script = {
        src: "http://localhost:8080/minilytics.js",
        getAttribute(name) {
            return (
                {
                    "data-site-id": "demo",
                    "data-site-key": "test-write-key",
                    // Most tests are about what is sent, not when.
                    "data-require-interaction": "false",
                    ...options.attributes,
                }[name] ?? null
            );
        },
    };
    const document = {
        currentScript: script,
        readyState: "complete",
        title: "Tracker smoke test",
        referrer: "",
        hidden: false,
        ...options.document,
        addEventListener(type, listener) {
            (documentListeners[type] ||= []).push(listener);
        },
    };
    const context = {
        Blob,
        URL,
        URLSearchParams,
        crypto: { randomUUID: () => "00000000-0000-4000-8000-000000000000" },
        document,
        location: {
            href: "http://localhost:8080/demo.html",
            hostname: "localhost",
            pathname: "/demo.html",
            search: "",
        },
        navigator: {
            language: "en-US",
            sendBeacon(endpoint, body) {
                beacons.push({ endpoint, body });
                return true;
            },
            ...options.navigator,
        },
        console: { error: (...args) => errors.push(args), debug() {} },
        screen: { width: 1440, height: 900 },
        innerWidth: 1280,
        innerHeight: 720,
        matchMedia: () => ({ matches: false }),
        localStorage: storage(),
        sessionStorage: storage(),
        history: { pushState() {}, replaceState() {} },
        addEventListener(type, listener) {
            (listeners[type] ||= []).push(listener);
        },
        removeEventListener(type, listener) {
            listeners[type] = (listeners[type] || []).filter(
                (item) => item !== listener,
            );
        },
        setTimeout(callback, delay) {
            timers.push({ callback, delay, cleared: false });
            return timers.length;
        },
        clearTimeout(id) {
            if (timers[id - 1]) timers[id - 1].cleared = true;
        },
        fetch: options.fetch,
    };
    context.window = context;
    vm.runInNewContext(tracker, context, { filename: "minilytics.js" });
    const interact = (type = "pointermove", isTrusted = true) =>
        [...(listeners[type] || [])].forEach((listener) =>
            listener({ type, isTrusted }),
        );
    const dispatch = (type) =>
        [...(documentListeners[type] || []), ...(listeners[type] || [])].forEach(
            (listener) => listener({ type, isTrusted: true }),
        );
    // Runs the pending timers set for the given delay.
    const tick = (delay) =>
        timers
            .filter((timer) => !timer.cleared && timer.delay === delay)
            .forEach((timer) => {
                timer.cleared = true;
                timer.callback();
            });
    return { beacons, context, dispatch, errors, interact, tick };
}

async function payload(body) {
    return JSON.parse(await body.text());
}

test("tracker auto-records a pageview and custom events on the script origin", async () => {
    const { beacons, context } = loadTracker();

    assert.equal(beacons.length, 1);
    assert.equal(beacons[0].endpoint, "http://localhost:8080/track.php");
    assert.deepEqual(await payload(beacons[0].body), {
        site_id: "demo",
        site_key: "test-write-key",
        session_id: "00000000000040008000000000000000",
        visitor_id: "00000000000040008000000000000000",
        name: "pageview",
        data: {
            path: "/demo.html",
            title: "Tracker smoke test",
            hostname: "localhost",
            referrer: null,
            language: "en-US",
            tracking_mode: "strict",
            screen: "1440×900",
        },
    });

    context.minilytics.track("signup_click", { plan: "pro" });
    assert.equal(beacons.length, 2);
    assert.equal(beacons[1].endpoint, "http://localhost:8080/track.php");
    assert.equal((await payload(beacons[1].body)).name, "signup_click");
    assert.equal((await payload(beacons[1].body)).data.plan, "pro");
});

function rejectingFetch() {
    return Promise.resolve({
        ok: false,
        status: 403,
        statusText: "Forbidden",
        text: () => Promise.resolve('{"error":"Invalid tracking key."}'),
    });
}

test("tracker stays silent about a rejected endpoint response outside debug mode", async () => {
    const { errors } = loadTracker({ fetch: rejectingFetch });

    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(errors.length, 0);
});

test("tracker stays silent about missing configuration outside debug mode", () => {
    const { beacons, errors } = loadTracker({
        attributes: { "data-site-key": null },
    });

    assert.equal(beacons.length, 0);
    assert.equal(errors.length, 0);
});

test("tracker reports a rejected endpoint response in the browser console in debug mode", async () => {
    const { errors } = loadTracker({
        attributes: { "data-debug": "true" },
        fetch: rejectingFetch,
    });

    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(errors.length, 1);
    assert.equal(
        errors[0][0],
        "[Minilytics] Tracking endpoint rejected an event.",
    );
    assert.deepEqual(JSON.parse(JSON.stringify(errors[0][1])), {
        status: 403,
        statusText: "Forbidden",
        response: '{"error":"Invalid tracking key."}',
        event: "pageview",
        endpoint: "http://localhost:8080/track.php",
    });
});

test("tracker explains the local opt-out and does not send an event", () => {
    const { beacons, context } = loadTracker({
        attributes: { "data-debug": "true" },
    });
    context.localStorage.setItem("minilytics_opt_out", "true");
    const logs = [];
    context.console.debug = (...args) => logs.push(args);

    context.minilytics.track("button_click");
    assert.equal(beacons.length, 1, "the custom event must not be sent");
    assert.equal(
        logs[0][0],
        "[Minilytics] Event was not sent because tracking is opted out.",
    );
    assert.deepEqual(JSON.parse(JSON.stringify(logs[0][1])), {
        event: "button_click",
        reason: "local_storage",
        resolution:
            "For this browser profile, run minilytics.optIn() and reload the page.",
    });
});

test("strict mode sends a page-scoped event despite GPC without using web storage", async () => {
    const { beacons, context } = loadTracker({
        navigator: { globalPrivacyControl: true },
    });

    assert.equal(beacons.length, 1);
    const event = await payload(beacons[0].body);
    assert.equal(event.data.tracking_mode, "strict");
    assert.equal(event.data.screen, "1440×900");
    assert.equal(event.session_id, event.visitor_id);
    assert.equal(context.sessionStorage.values.size, 0);
    assert.equal(context.localStorage.values.size, 0);
});

test("enriched mode waits for consent and remains blocked by GPC", () => {
    const pending = loadTracker({
        attributes: { "data-privacy-mode": "enriched" },
    });
    assert.equal(pending.beacons.length, 0);
    assert.equal(pending.context.minilytics.consent(), true);
    assert.equal(pending.beacons.length, 1);

    const protectedBrowser = loadTracker({
        attributes: { "data-privacy-mode": "enriched" },
        navigator: { globalPrivacyControl: true },
    });
    assert.equal(protectedBrowser.context.minilytics.consent(), false);
    assert.equal(protectedBrowser.beacons.length, 0);
});

test("automated browser with navigator.webdriver is ignored", () => {
    const { beacons, context } = loadTracker({
        attributes: { "data-debug": "true" },
        navigator: { webdriver: true },
    });
    const logs = [];
    context.console.debug = (...args) => logs.push(args);

    assert.equal(
        beacons.length,
        0,
        "auto pageview must not be sent for webdriver",
    );
    context.minilytics.track("bot_event");
    assert.equal(
        beacons.length,
        0,
        "custom events must not be sent for webdriver",
    );
    assert.equal(
        logs[0][0],
        "[Minilytics] Event was not sent because an automated browser was detected.",
    );
    assert.deepEqual(JSON.parse(JSON.stringify(logs[0][1])), {
        event: "bot_event",
        reason: "webdriver",
    });
});

test("page views and events wait for the first trusted interaction", async () => {
    const { beacons, context, interact } = loadTracker({
        attributes: { "data-require-interaction": "true" },
    });
    context.minilytics.track("signup", { plan: "pro" });
    assert.equal(beacons.length, 0);

    interact("keydown", false);
    assert.equal(beacons.length, 0, "synthetic events do not count");

    interact("pointermove");
    assert.equal(beacons.length, 2);
    assert.deepEqual(
        [(await payload(beacons[0].body)).name, (await payload(beacons[1].body)).name],
        ["pageview", "signup"],
    );

    interact("scroll");
    assert.equal(beacons.length, 2, "only the first interaction flushes");
});

test("a page visible for 3 seconds is counted without any interaction", async () => {
    const { beacons, tick } = loadTracker({
        attributes: { "data-require-interaction": "true" },
        document: { visibilityState: "visible" },
    });
    assert.equal(beacons.length, 0);

    tick(3000);
    assert.equal(beacons.length, 1);
    assert.equal((await payload(beacons[0].body)).name, "pageview");
});

test("a prerendered page is only counted once it is shown", async () => {
    const { beacons, context, dispatch, tick } = loadTracker({
        attributes: { "data-require-interaction": "true" },
        document: { visibilityState: "hidden", prerendering: true },
    });
    tick(3000);
    assert.equal(beacons.length, 0, "the timer does not run while prerendering");

    Object.assign(context.document, { visibilityState: "visible", prerendering: false });
    dispatch("prerenderingchange");
    tick(3000);
    assert.equal(beacons.length, 1);
});

test("a page left before interacting is reported, not counted", () => {
    const requests = [];
    const { beacons, dispatch } = loadTracker({
        attributes: { "data-require-interaction": "true" },
        document: { visibilityState: "visible" },
        fetch: (endpoint, init) => {
            requests.push(JSON.parse(init.body));
            return Promise.resolve({ ok: true });
        },
    });

    dispatch("pagehide");
    assert.equal(beacons.length, 0);
    assert.deepEqual(
        requests.map((request) => [request.name, request.data.reason]),
        [["_ml_bot", "no_interaction"]],
    );
});

test("a page never shown is not reported when it is left", () => {
    const requests = [];
    const { dispatch } = loadTracker({
        attributes: { "data-require-interaction": "true" },
        document: { visibilityState: "hidden" },
        fetch: (endpoint, init) => {
            requests.push(JSON.parse(init.body));
            return Promise.resolve({ ok: true });
        },
    });

    dispatch("pagehide");
    assert.deepEqual(requests, []);
});

test("a detected automated browser is reported to the server", async () => {
    const requests = [];
    const { beacons } = loadTracker({
        navigator: { webdriver: true },
        fetch: (endpoint, init) => {
            requests.push({ endpoint, init });
            return Promise.resolve({ ok: true });
        },
    });

    assert.equal(beacons.length, 0);
    assert.equal(requests.length, 1);
    assert.equal(requests[0].endpoint, "http://localhost:8080/track.php");
    assert.deepEqual(JSON.parse(requests[0].init.body), {
        site_id: "demo",
        site_key: "test-write-key",
        name: "_ml_bot",
        data: { reason: "webdriver" },
    });
});

for (const [name, reason, navigator] of [
    ["no languages", "no_languages", { languages: [] }],
    [
        "a desktop Chrome without window.chrome",
        "no_chrome_object",
        { userAgent: "Mozilla/5.0 (X11; Linux x86_64) Chrome/152.0.0.0 Safari/537.36" },
    ],
    [
        "a mobile user agent without touch",
        "mobile_without_touch",
        { userAgent: "Mozilla/5.0 (iPhone; CPU iPhone OS 17_4)", maxTouchPoints: 0 },
    ],
    [
        "a Windows user agent on another platform",
        "platform_mismatch",
        { userAgent: "Mozilla/5.0 (Windows NT 10.0; Win64; x64)", platform: "Linux x86_64" },
    ],
]) {
    test(`a browser with ${name} is reported as inconsistent`, () => {
        const requests = [];
        const { beacons } = loadTracker({
            navigator,
            fetch: (endpoint, init) => {
                requests.push(JSON.parse(init.body));
                return Promise.resolve({ ok: true });
            },
        });

        assert.equal(beacons.length, 0);
        assert.equal(requests[0].data.reason, reason);
    });
}

test("a regular browser is not reported", () => {
    const names = [];
    loadTracker({
        navigator: {
            languages: ["en-US"],
            platform: "Linux x86_64",
            userAgent: "Mozilla/5.0 (X11; Linux x86_64) Gecko/20100101 Firefox/130.0",
            maxTouchPoints: 0,
        },
        fetch: (endpoint, init) => {
            names.push(JSON.parse(init.body).name);
            return Promise.resolve({ ok: true });
        },
    });

    assert.deepEqual(names, ["pageview"]);
});
