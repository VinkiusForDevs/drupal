# Vinkius Connector for Drupal

> The **Vinkius AI Governance** panel inside Drupal — the site owner's
> observation-and-response surface for the Vinkius runtime. Open source,
> **GPLv2 or later**.

![License](https://img.shields.io/badge/License-GPLv2%20or%20later-blue.svg)
![Drupal](https://img.shields.io/badge/Drupal-10%20%7C%2011-0678be?logo=drupal&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-4F5B93?logo=php&logoColor=white)
![Version](https://img.shields.io/badge/version-1.0.0-orange.svg)

**Vinkius Connector** turns a Drupal site into a first-class node in the
[Vinkius](https://vinkius.com) AI platform. It gives the site owner a live
governance panel right inside Drupal: watch governed agent
operations, inspect incidents and execution evidence, search the Vinkius
capabilities catalog, and trigger emergency response — without leaving
Drupal and without storing any Vinkius data on the site.

## The core principle

This module is an **observation-and-response** surface. It follows one
non-negotiable rule:

> **It never manages connectors, credentials, policies, webhooks, or
> publishing inside Drupal.** Those live in the Vinkius cloud. Every value
> in the panel is read **live** from the Vinkius API — nothing is persisted in
> Drupal except the site's own visitor-consent records and local settings.

That boundary is what makes the module safe to ship: no backend secret
ever reaches the database, and any management decision is a deep link into
[cloud.vinkius.com](https://cloud.vinkius.com), not an action taken locally.

## What you get

### The governance panel

A top-level **Vinkius** menu with these screens:

| Screen | What it shows |
| --- | --- |
| **AI Connections** | The landing/connect page — device-flow login and the connectors catalog |
| **Mission Control** | Top-line KPIs and the live request activity feed |
| **Agent Activity** | The agent/client rollup — who is driving requests |
| **Connector Traffic** | Requests by connector |
| **Access Tokens** | The token fleet, with per-token activity |
| **Agent Passport** | Per-connector identity, scopes and lifecycle |
| **AI Spend** | Cost by server, plus estimated savings |
| **Capability Reliability** | Slowest and most-failing tools |
| **Security Posture** | DLP/FinOps enforcement state and per-connector security |
| **Request Failures** | The error breakdown by origin (agent / upstream / Vinkius) |
| **Compliance & Evidence** | Readiness scorecard, framework coverage, evidence packs |
| **Incidents** | Errors, DLP blocks, quarantined connectors and the control trail |
| **Consent** | The site's visitor-consent records — the only data stored locally |
| **Settings** | Connect to Vinkius, UI language, trust badge, daily digest |

### The AI Governance workbench

Every analytics screen is a faithful port of the Vinkius cloud counterpart —
same design tokens, same cards, same charts. Each section is independently
readable and drillable: click any row and the panel opens the per-entity
request log in place, no page hop to the cloud needed. On Free accounts the
plan bar is shown with a **Subscribe to Vinkius** link, and the capabilities
catalog replicates the `cloud.vinkius.com/capabilities` search with a
debounced query, category chips and infinite scroll.

### Emergency response

Halt a single connector or the whole site, approve resumption, and reset a
tripped circuit breaker directly from the panel. Enforcement happens in the
Vinkius runtime; the module only triggers it and reflects the result.

### Site surfaces (public)

- Visitor consent form — the explicit-consent primitive and the **only** data
  the module stores on the site.
- `GET /vinkius/ajax?route=agent-manifest` — a machine-readable manifest
  agents use to discover the site, its consent scopes and the consent endpoint.

## How it works

The panel is a thin, live view over the Vinkius API — not a copy of it:

```
  Drupal admin UI          module AJAX             Vinkius API
 +----------------+      +-------------+      +-----------------+
 | Governance     | -->  | ajax        | -->  | api.vinkius.com |
 | Incidents      |      | (live read) |      | (per call)      |
 +----------------+      +-------------+      +-----------------+
                                    |
                                    | deep link (manage, don't act locally)
                                    v
                           cloud.vinkius.com / Vinkius runtime
```

- **Live reads** — each panel request hits the Vinkius API under the owner's
  session; nothing is cached or written to Drupal.
- **Triggers, not management** — halt / resume / circuit-reset POST a control
  command to the runtime; the runtime enforces it.
- **Deep links** — every "manage this" affordance points to the Vinkius cloud,
  where connectors, credentials, policies, webhooks and publishing actually
  live.

## How login works

Vinkius Connector uses the **OAuth 2.0 device flow** (RFC 8628). There are no
passwords and no API keys in the database:

1. In **Settings**, click **Connect with Vinkius** — the module requests a
   device code.
2. The panel shows the code and an **Open approval page** button.
3. On your [Vinkius account](https://cloud.vinkius.com) you approve that code.
4. The panel polls in the background and connects automatically.

Revoke or reset the session anytime from **Settings → Connect**.

## Installation

1. Place the `modules/vinkius_connector` folder in your Drupal site and
   enable the module.
2. Open **Configuration → Vinkius** (or the Vinkius admin page).
3. Click **Connect with Vinkius**, then approve the device code on your
   Vinkius account. The platform endpoints are fixed — there is nothing to
   configure.

## Requirements

- Drupal **10.x or 11.x**.
- PHP **8.1+** with cURL enabled.
- A Vinkius account — the Free plan is enough to connect; paid plans unlock
  longer analytics windows.

## Security & privacy

- **No secret material in the database** — no API keys, no client secrets, no
  business data. The only credentials stored are the owner's own OAuth session
  tokens, used to renew the connection and never sent to the browser.
- **No Vinkius data is persisted** — agents, incidents, spend and evidence are
  always read live.
- **One local store** — visitor-consent records (keyed IP hash, never raw
  PII).
- **Escaped everywhere** — output escaping and a token guard on every admin
  route.
- **User-authorized only** — external calls go to the fixed Vinkius endpoints
  and happen only after the owner logs in.

## Internationalization

The UI ships in six locales: `en` (base), `pt_BR`, `fr_FR`, `es_ES`,
`de_DE` and `ja_JP`. The language is selectable in **Settings** (default:
follow the site). Catalogs are parsed at runtime.

## Repository layout

```
.
|-- modules/vinkius_connector/          # the module
|   |-- vinkius_connector.info.yml      # module metadata
|   |-- vinkius_connector.routing.yml   # routes
|   |-- vinkius_connector.libraries.yml # assets
|   |-- vinkius_connector.install       # schema
|   |-- src/                            # controllers + services
|   |-- includes/                       # ported PHP classes
|   |-- templates/                      # workbench shell + screens
|   |-- admin/css  admin/js             # vanilla, no build step
|-- README.md
|-- LICENSE
```

No Composer dependencies, no build step, no bundler.

## Development

There is nothing to build — the admin UI is plain PHP, CSS and vanilla JS.

## License

**GPLv2 or later** (see [LICENSE](LICENSE)) — compatible with Drupal core.
"Or later" is the key part: it includes GPLv3. The one architectural rule
above is non-negotiable — keep it intact in every change.