# AI assistants (MCP)

Ask an AI assistant such as Claude or ChatGPT about your traffic, for example "Where did last week's visitors come from?", and it answers from your own Minilytics data. Assistants sign in with your Minilytics account and can only read your statistics, never change anything.

## Connect an assistant

Open **Settings → AI assistants** and pick your assistant. Minilytics shows the steps for it, with one-click buttons for Claude, Cursor and VS Code.

![Settings → AI assistants, with the steps to connect Claude](../assets/ai-assistants.png)

When you connect, the assistant opens a Minilytics page in your browser and asks you to allow access. There is no key to copy and nothing else to configure.

The assistant reaches Minilytics from the internet, so your Minilytics must be served over HTTPS. Its address for assistants is your Minilytics address followed by `/mcp.php`, for example `https://analytics.example.com/mcp.php`. The examples below use this one, so replace it with yours.

### Claude

Click **Add to Claude** in **Settings → AI assistants**. Claude opens with the connector already filled in. Click **Add**, then **Connect**, and allow access in Minilytics.

To add it by hand, open **Customize → Connectors → Add custom connector** in Claude, name it **Minilytics** and paste your address. The connector then works in every Claude app on your account, including Claude Code. On Team and Enterprise plans, an owner adds it once in **Organization settings → Connectors**, then each member connects.

If you use Claude Code with an API key rather than a Claude account, run this command, then open `/mcp`, select **minilytics** and choose **Authenticate**.

```bash
claude mcp add --transport http minilytics https://analytics.example.com/mcp.php
```

### ChatGPT

1. In **Settings → Apps → Advanced settings**, turn on **Developer mode**. It needs a paid plan, and on Business or Enterprise plans an admin may have to allow custom apps.
2. Click **Create app**, name it **Minilytics**, choose **OAuth** and paste your address.
3. Click **Create**, then allow access in Minilytics. In a chat, turn the app on from the **+** menu.

### Codex

In Codex, open **Settings → MCP**, add a server named **minilytics** with the **Streamable HTTP** type and your address, then click **Authenticate**. With the Codex command line, run these two commands instead.

```bash
codex mcp add minilytics --url https://analytics.example.com/mcp.php
codex mcp login minilytics
```

### Cursor

Click **Add to Cursor** in **Settings → AI assistants**, then click **Connect** next to minilytics in Cursor's MCP settings and allow access.

### VS Code

Click **Add to VS Code** in **Settings → AI assistants**. When VS Code starts the server, sign in to Minilytics and allow access.

### Other assistants

For Gemini CLI, Windsurf or any other assistant, **Settings → AI assistants → Other tools** gives you a short text to paste into it, and the assistant sets itself up. You can also add your address to the assistant's MCP settings yourself, and most assistants then sign in on their own.

Some versions of Antigravity do not finish signing in. Use an [access token](#access-tokens) with it.

## What assistants can see

Assistants read the same reports as the dashboard, such as visitors, pages, referrers, events, funnels, journeys, countries, devices and blocked bots, for any period. They can also read individual visits, as on the Sessions page: the pages viewed and events triggered, with the country and device. Visits stay anonymous: assistants never see an IP address, and a visitor's code changes every month.

## Manage access

**Settings → AI assistants → Authorized access** lists the connected assistants and your access tokens, with when each was last used. Revoke one to cut its access right away.

After you update Minilytics, an assistant may still use the old list of reports. Refresh it without reconnecting. In Claude, open **Customize → Connectors**, choose Minilytics and click **Update**, then start a new conversation. With other assistants, restart them or reconnect the server.

## Access tokens

Some assistants cannot sign in, for example agents running without a browser. Give them an access token instead. Create one in **Settings → AI assistants → Other tools**, under **Assistant can't sign in?**, and copy it right away, because it is shown only once. Like a sign-in, a token can only read your statistics, and you can revoke it at any time.

Add the token to the assistant's MCP settings, as in this example.

```json
{
    "mcpServers": {
        "minilytics": {
            "type": "http",
            "url": "https://analytics.example.com/mcp.php",
            "headers": { "Authorization": "Bearer mlt_your_token" }
        }
    }
}
```

## Troubleshooting

- **The assistant fails with `Unauthorized` without opening a sign-in page.** It cannot sign in on its own. Use an [access token](#access-tokens).
- **No assistant can sign in.** Check that Minilytics is served over HTTPS. On Nginx or Caddy, use the [web server configuration](../operations/README.md#web-server-configuration), which lets assistants sign in. Behind a reverse proxy, the proxy must forward the `X-Forwarded-Proto` header.
