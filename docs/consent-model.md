# Consent model

## Categories

| Category | Required | Typical use | Default cookie patterns cleaned on revoke |
|---|---|---|---|
| `necessary` | always on | session, security, load balancing, the consent cookie itself | — |
| `functional` | optional | language/region, embeds | — |
| `analytics` | optional | measurement | `_ga`, `_ga_*`, `_gid`, `_gat`, `_gat_*` |
| `marketing` | optional | advertising, remarketing | `_gcl_au`, `_gcl_aw`, `_gcl_dc`, `_gcl_gb`, `_fbp`, `_fbc` |

- Only `necessary` can be required. The validator refuses `required: true` on any
  other category, so analytics or advertising can never be smuggled in as "necessary".
- Remove a default optional category with `"categories": { "functional": false }`.
- Add your own (max 12 in total): `"categories": { "social": { "cookies": ["fr"] } }` and
  give it copy via `texts.<lang>.categories.social.{title,description}`.

## The record

Stored in a first-party cookie (default name `open_consent`, 180 days, `SameSite=Lax`,
`Secure` on HTTPS, readable by your server):

```json
{"schema":1,"policy":"1","profile":"opt-in","method":"custom",
 "categories":{"necessary":true,"functional":false,"analytics":true,"marketing":false},
 "createdAt":1760000000,"updatedAt":1760000000,"expiresAt":1775552000}
```

`method` is one of `accept-all`, `reject-all`, `custom`, `withdraw`. Nothing identifies
the visitor: no ID, IP address, user agent or URL is stored.

## When a stored record is NOT consent

| `reason` | Cause | Effect |
|---|---|---|
| `missing` | no cookie | implied defaults, banner shown |
| `corrupt` | not JSON, wrong types, unknown method, `necessary` not true | same |
| `schema` | newer schema, or older with no migration | same |
| `policy` | `policyVersion` changed | same; previous choices pre-fill nothing automatically |
| `expired` | past `expiresAt` | same |
| `categories` | a configured category is missing from the record (you added one) | same |

The engine exposes the reason as `OpenConsent.getConsent().reason`.

## Policy profiles

| Profile | Before a choice | Use when |
|---|---|---|
| `opt-in` (default) | every optional category **denied** | prior consent is required (e.g. EU/UK-style rules) |
| `opt-out` | optional categories **granted**; with Global Privacy Control (`gpc: true`, default) `marketing` is denied | an objection-based regime applies to you |

The built-in banner copy is written for `opt-in` ("with your permission we also use…").
If you use `opt-out`, override `texts.<lang>.banner.text` so it describes what actually
happens before a choice.

The engine does not geolocate visitors. If you need different profiles per region, serve
a different config per region from your server (e.g. via the PHP library or the hosted
config API). Choosing the right profile is a legal decision, not a technical one.

## Changing the policy

Bump `policyVersion` (any string, `[A-Za-z0-9._-]`, ≤32 chars) after a material change —
new vendors, new purposes. Every visitor is asked again.

## Limits of client-side records

The record lives in the visitor's browser. It can be deleted, edited or blocked by the
visitor or by browser privacy features, and it cannot prove after the fact what someone
consented to. If you need an auditable consent log, record it server-side yourself
(OpenConsent fires a `consent` event you can forward) and mind the personal data that
such a log contains.
