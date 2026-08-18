# Design: Quotation PDF — Issuer/Receiver Identity Blocks + Signature Section

## Technical Approach

Presentation-only change on the existing quotation PDF pipeline (proposal approaches 1 + 3 + 4a). Issuer identity comes from five new `theme.branding.*` system-configuration keys with real Gpartner values as DI node defaults; receiver identity is read straight off the existing `Customer` getters; the signature area is a static, mPDF-safe `<table>` inside the already-present `.doc-bottom` container. No entity, schema, migration, service, or renderer change. `src/Service/QuotationPdfRenderer.php` stays untouched — the template reads config directly.

## Architecture Decisions

### Decision: Issuer data from DI-defaulted config keys (risk #2 resolved)

**Choice**: Add `vat_id`, `address`, `phone`, `email`, `website` scalar nodes with real Gpartner defaults to the `branding` node (`src/DependencyInjection/Configuration.php:507-523`) plus matching `Configuration` entries in the `branding` `SystemConfigurationModel` (`SystemConfigurationController.php:610-629`). No template-literal fallback.

**Alternatives considered**: Twig `is not empty ? … : 'literal'` fallback (the existing `issuerName`/`logo` pattern); hardcoded Twig literals; `Customer`-as-`sending_company`.

**Rationale**: The default chain is verified end-to-end, so a second fallback would be dead code that duplicates legal data in two places and drifts. Traced chain: `Configuration.php` node defaults → `AppExtension::__construct`/`prepare` flattens the processed tree into dot-notation and sets `gppro.config` (`AppExtension.php:96-105`) → injected as `SystemConfiguration::$settings` (`config/services.yaml:75-77`) → `find()` returns `$settings[$key]` when no DB row exists (`SystemConfiguration.php:54-63`); `prepare()` only *overwrites* with persisted rows. `ConfigurationRepository::saveSystemConfiguration()` **deletes** the row on a null submitted value (`:58-61`), so clearing the admin field resurrects the DI default rather than blanking the PDF. The existing `issuerName`/`logo` literals exist only because upstream Kimai defaults those nodes to `null`; ours default to real values, so the condition can never fire.

**Consequence**: `Configuration.php` is a compiled container parameter — a cache clear / container rebuild is required after deploy for new defaults to appear.

### Decision: Twig whitelist untouched (confirmed non-risk)

**Choice**: Do not extend the `switch` in `src/Twig/Configuration.php:50-53`.

**Rationale**: `QuotationPdfRenderer` injects the main non-sandboxed `Environment`. Non-`saml.`/`ldap.` keys fall through to the generic `return $this->configuration->find($name)` (`:75`). The whitelist only matters for sandboxed invoice environments, which never render `quotation/pdf.html.twig`.

### Decision: Signature lives inside the existing `.doc-bottom` absolute container

**Choice**: One absolutely positioned container only. Order inside `.doc-bottom`: `.summary` → `.response-hint` → `.doc-signatures`.

**Alternatives considered**: A second `position: absolute` block at a different `bottom` offset; normal-flow placement right after the items table.

**Rationale**: mPDF does no collision detection between absolutely positioned siblings — two bottom-anchored blocks silently overlap when either grows. Normal-flow placement floats mid-page on short quotations and can still collide with `.doc-bottom`. A single container grows upward from the `bottom: 14mm` anchor and stays internally ordered.

**Height budget** (A4 297mm, margins 12/12 → 273mm flow): signatures add ≈35mm, bringing `.doc-bottom` to ≈85mm; its top edge lands ≈198mm from the page top, leaving ≈186mm of normal flow. Current header + accent bar + parties (≈45mm) + table head (≈10mm) + 10 line rows (≈9mm each) ≈ 145mm — fits with ≈40mm headroom.

### Decision: Quotation-scoped label keys, not the generic ones

**Choice**: New `quotation.*` label keys in `translations/messages.es.xlf`.

**Rationale**: The generic `vat_id` key renders **"CIF / NIF"** (`messages.es.xlf:889-892`) — the EU tax ID, wrong for a Chilean RUT — and it is shared with customer admin screens, so it cannot be retargeted. `address`/`phone`/`email` are reusable but mixing sources would be inconsistent; keep one namespace. `quotation.signature_*` mirrors the `invoice.signature_user|customer` convention (`:661-668`).

## Data Flow

    Configuration.php (node defaults)
            │  AppExtension flatten → %gppro.config%
            ▼
    SystemConfiguration::$settings ──┐
    configuration DB rows ───────────┴─→ find() ─→ Twig config() ─┐
                                                                   ├─→ pdf.html.twig ─→ mPDF
    Quotation → Customer (vatId/formattedAddress/phone/email/contact)┘

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `src/DependencyInjection/Configuration.php:507-523` | Modify | 5 scalar nodes under `branding` with Gpartner defaults: `vat_id` `77.073.462-2`, `address` `Avenida Apoquindo 4700, Depto. 11, Las Condes, Santiago`, `phone` `+56 9 44516977`, `email` `info@gpartnerc.com`, `website` `www.gpartnerc.com` |
| `src/Controller/SystemConfigurationController.php:610-629` | Modify | 5 `Configuration` entries, `TextType`, `setRequired(false)`, `setTranslationDomain('system-configuration')` |
| `templates/quotation/pdf.html.twig` | Modify | Issuer meta lines in `.doc-issuer-cell`; receiver rows in `.doc-parties`; `.doc-signatures` table + CSS in `.doc-bottom` |
| `translations/messages.es.xlf` | Modify | New `quotation.*` label + signature keys |
| `translations/system-configuration.{es,en}.xlf` | Modify | Admin labels for the 5 new keys (raw key would render otherwise) |
| `tests/DependencyInjection/ConfigurationTest.php:490-497` | Modify | Default-tree assertion asserts the exact `branding` array — must gain the 4 keys or it fails |

## Interfaces / Contracts

Issuer block (uses `.doc-issuer-meta`, already styled at `:13-14`):

```twig
{% set issuerVatId = config('theme.branding.vat_id') %}
<div class="doc-issuer-meta">
  {% if issuerVatId %}<p>{{ 'quotation.issuer_vat_id'|trans }}: {{ issuerVatId }}</p>{% endif %}
  {# address / phone / email / website identical #}
</div>
```

Receiver block — `{% if %}` per field, all `Customer` getters nullable:
`company|default(name)`, `vatId`, `getFormattedAddress()|nl2br`, `phone`, `email`.

Signature table — 46% / 8% spacer / 46%, **always rendered**:

```twig
<table class="doc-signatures" cellpadding="0" cellspacing="0">
  <tr><td class="sign-space">&nbsp;</td><td class="sign-gap">&nbsp;</td><td class="sign-space">&nbsp;</td></tr>
  <tr><td class="sign-line">{{ 'quotation.signature_issuer'|trans }}</td><td>&nbsp;</td>
      <td class="sign-line">{{ 'quotation.signature_customer'|trans }}</td></tr>
  <tr><td class="sign-name">{{ issuerName }}</td><td>&nbsp;</td>
      <td class="sign-name">{% if quotation.customer.contact %}{{ quotation.customer.contact }}{% else %}&nbsp;{% endif %}</td></tr>
  <tr><td class="sign-date">{{ 'quotation.signature_date'|trans }}: ______________</td><td>&nbsp;</td>
      <td class="sign-date">{{ 'quotation.signature_date'|trans }}: ______________</td></tr>
</table>
```

CSS (mPDF-safe, no flexbox — the template was deliberately migrated off flex):

```css
.doc-signatures { width: 100%; border-collapse: collapse; margin-top: 12pt; }
.doc-signatures td { border: 0; padding: 0; vertical-align: bottom; }  /* MUST override global `th, td { border-bottom }` at :29 */
.doc-signatures .sign-space { padding-top: 14mm; }
.doc-signatures .sign-gap { width: 8%; }
.doc-signatures .sign-line { width: 46%; border-top: 1px solid #555; padding-top: 4pt; font-size: 9pt; color: #777; text-transform: uppercase; }
.doc-signatures .sign-name { font-size: 10pt; padding-top: 2pt; }
.doc-signatures .sign-date { font-size: 9pt; color: #555; padding-top: 6pt; }
```

The `border: 0` reset is mandatory: the global `th, td` rule (`:29`) would otherwise draw grey lines through every signature cell — the same reason `.summary td` and `.doc-parties td` already reset it.

## Non-Goals (do not scope-creep)

The signature block is **purely presentational**. No persistence, no e-signature capture, no new entity/field, and **no linkage to `QuotationMailService` / `QuotationEmail`**. The token-based accept/reject email flow stays completely untouched and separate. Other locales beyond `es` (and `en` for config labels) are out of scope.

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | New branding defaults present in the processed tree | Extend the `branding` array in `tests/DependencyInjection/ConfigurationTest.php:490-497` (this test fails otherwise) |
| Unit | `find('theme.branding.vat_id')` returns the DI default with an empty repository | `SystemConfiguration` with a stub `ConfigLoaderInterface` returning `[]` |
| Integration | Template renders issuer/receiver/signature and omits absent customer fields | `KernelTestCase` rendering `quotation/pdf.html.twig` with a bare `Customer` and a fully populated one; assert no orphan `RUT:` label |
| Manual | Layout: no `.doc-bottom` / items-table overlap | Render a 10-line quotation PDF and inspect page 1 |

Existing `tests/Service/QuotationPdfRendererTest.php` mocks `Environment` and needs no change.

## Threat Matrix

N/A — no routing, shell, subprocess, VCS/PR automation, executable-file classification, or process-integration boundary. Config values are admin-authored text rendered through Twig's default HTML autoescaping into mPDF; `nl2br` is applied only to the already-escaped customer address, matching the existing line `:66`.

## Migration / Rollout

No migration. Deploy requires a container/cache rebuild so `%gppro.config%` picks up the new defaults. Rollback is a single revert; any admin-edited `theme.branding.*` rows remain orphaned and harmless.

## Review Budget

Estimated ≈150–200 changed lines against the 400-line budget — Low risk, single PR.

## Open Questions

- None. All prior open questions are resolved: signature always renders; customer column pre-fills from `customer.contact`; issuer shows 7 fields (name, logo, RUT, address, phone, email, website).
