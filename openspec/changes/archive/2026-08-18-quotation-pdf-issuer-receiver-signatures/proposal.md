# Proposal: Quotation PDF — Issuer/Receiver Identity Blocks + Signature Section

## Intent

The quotation PDF is a commercial document but does not read like one. It shows only issuer name+logo and receiver name/address/email (`templates/quotation/pdf.html.twig:41-50,61-69`). A Chilean commercial document shows full issuer AND receiver identity (RUT, address, phone) and a signature area for both parties. Today the customer cannot validate who issued the offer, and neither party can sign it.

## Scope

### In Scope

- Issuer block: add RUT, address, phone, email, website next to existing name+logo.
- Receiver block: company/name, RUT, formatted address, phone, email from the existing `Customer`, each null-guarded.
- Signature block: two-column visual signing area (date / name / line) for Gpartner and customer.
- Config: `theme.branding.vat_id|address|phone|email|website`, defaulting to real Gpartner data (RUT 77.073.462-2; Avenida Apoquindo 4700, Depto. 11, Las Condes, Santiago; +56 9 44516977; info@gpartnerc.com; www.gpartnerc.com), admin-editable.
- Translations: `quotation.*` keys in `messages.es.xlf`; config labels in `system-configuration.{es,en}.xlf`.

### Out of Scope

- E-signature capture, digital signing, signature persistence.
- Linking signatures to the accept/reject email flow (`QuotationMailService`) — deferred.
- Entity/schema/migration changes; `Customer` already has every receiver field.
- The other ~38 locale files (`es`/`en` only).
- Invoice templates and `InvoiceTemplate` (precedent only).

## Capabilities

### New Capabilities

- `quotation-pdf-document`: issuer identity, receiver identity, and signature area on the quotation PDF.

### Modified Capabilities

- None.

## Approach

Exploration approaches **1** (issuer) + **3** (receiver) + **4a** (signature).

**Issuer via config, not Twig literals.** Extend the branding node (`DependencyInjection/Configuration.php:507-523`) and admin model (`SystemConfigurationController.php:610-629`). Rationale: matches the existing `theme.branding.logo|company` pattern, no migration, keeps legal data editable when the address or phone changes, and avoids embedding tenant business data in a shared template. Real values ship as node defaults, so the PDF is correct with zero admin action. Approach 2 (`Customer`-as-`sending_company`) is rejected: a new association and admin workflow is over-engineered for one fixed issuer.

**Receiver: template only.** Read `customer.company|name`, `vatId`, `getFormattedAddress()`, `phone`, `email` behind `{% if %}` so partial records never render an empty "RUT:" label.

**Signature: static block.** Reuse the CSS top-border blank-line pattern from `templates/invoice/renderer/timesheet.html.twig:139-142` with quotation-scoped keys. No plumbing, no new data.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `templates/quotation/pdf.html.twig` | Modified | Issuer fields, expanded receiver block, signature section |
| `src/DependencyInjection/Configuration.php:507-523` | Modified | Five `branding` scalar nodes with Gpartner defaults |
| `src/Controller/SystemConfigurationController.php:610-629` | Modified | Five `Configuration` entries in the `branding` model |
| `src/Twig/Configuration.php:50-53` | Verify | Branding whitelist for restricted Twig environments |
| `translations/messages.es.xlf` | Modified | New `quotation.*` keys |
| `translations/system-configuration.{es,en}.xlf` | Modified | Labels for the new config keys |
| `src/Service/QuotationPdfRenderer.php` | Unchanged | Template reads config directly |
| `src/Entity/Customer.php` | Unchanged | Receiver fields already exist |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| New keys return null in the PDF context via the `Twig/Configuration.php` whitelist | Med | `sdd-design` confirms the renderer's Twig environment; whitelist the keys if needed |
| Node defaults do not reach already-installed instances if branding is DB-persisted | Med | `sdd-design` traces `Configuration::find()`; template-side default only if unreliable |
| Nullable customer fields render empty labels | Med | Per-field `{% if %}`, verified with a minimal customer |
| PDF layout overflow from denser blocks | Low | Visual check of the rendered PDF |
| Untranslated keys in other locales | Low | Declared non-goal; Symfony falls back |

## Rollback Plan

Single revert of the change commit. No schema, migration, or persisted data is added. Reverting restores the previous letterhead; the config keys disappear with the code. Admin-edited branding rows would remain orphaned in configuration storage and are harmless.

## Dependencies

- None.

## Success Criteria

- [ ] PDF issuer block shows name, logo, RUT, address, phone, email, website.
- [ ] PDF receiver block shows name/company, RUT, address, phone, email, omitting absent rows.
- [ ] PDF shows a two-column signature area with date, name, and line for both parties.
- [ ] The five issuer values are admin-editable and default to the real Gpartner data with no admin action.
- [ ] No migration, entity, or schema change.

## Open Questions

None. All three are resolved by the user:

1. Signature block unconditional, or hidden once accepted via email? **Resolved: unconditional — always renders.**
2. Customer signature column labeled with `customer.contact` when present, or always blank? **Resolved: pre-fills with `customer.contact` when present, blank otherwise.**
3. Issuer email/website also shown? **Resolved: yes — both email (`info@gpartnerc.com`) and website (`www.gpartnerc.com`) are shown, in addition to RUT/address/phone.**
