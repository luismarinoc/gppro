# Exploration: Quotation PDF — Issuer/Receiver Identity Blocks + Signature Section

## Current State

The quotation PDF (`templates/quotation/pdf.html.twig`, rendered by `src/Service/QuotationPdfRenderer.php`) shows a minimal letterhead: issuer name+logo from `config('theme.branding.company')`/`config('theme.branding.logo')` (`pdf.html.twig:41-50`; fallback logo built in `QuotationPdfRenderer::defaultLogoDataUri()`, lines 65-74), and a receiver block (`pdf.html.twig:61-69`) with only `customer.name`/`address`/`email`. No signature block exists anywhere.

## Findings

**1. `src/Entity/Customer.php` — receiver fields already exist.** All nullable strings: `getVatId()`/`setVatId()` (`:320-328`, column `vat_id`), `getPhone()`/`setPhone()` (`:399-407`), `getHomepage()` (`:439-447`), `getCompany()` (`:310-318`), `getContact()` (`:330-338`), `getAddress()` (`:340-348`), `getAddressLine1/2/3()` (`:559-587`), `getPostCode()`/`getCity()` (`:589-607`), `getFormattedAddress()` (`:350-377`, structured address w/ fallback to raw), `getFax()` (`:409-417`), `getMobile()` (`:419-427`), `getEmail()` (`:429-437`), `getCountry()` (ISO-2, `:379-387`). Conclusion: `Customer` already models everything the receiver block needs — zero entity changes required.

**2. `src/Entity/InvoiceTemplate.php` — reference pattern for real invoices' issuer data.** Own scalar fields `company`/`vatId`/`address`/`contact` (`:39-48`) are deprecated since 2.41/2.45 in favor of delegating to a linked `Customer`: `getAddress()` (`:146-149`) = `customer?.getFormattedAddress() ?? own`; `getCompany()` (`:189-192`) = `customer?.getCompany() ?? customer?.getName() ?? own`; `getVatId()` (`:232-235`) = `customer?.getVatId() ?? own`; `getContact()` (`:245-248`) does NOT delegate; `logo` (`:79-80,265-273`) is its own field. The `customer` association is required (`:90-93`), set via `InvoiceTemplateForm` field labeled `sending_company` (help text: *"Esta empresa se muestra como el emisor en la factura..."*, `src/Form/InvoiceTemplateForm.php:67-71`; `translations/messages.es.xlf:2129-2131`). Pattern: real invoices reuse `Customer` itself for the issuing company, no separate config schema. Issuer data flows into `templates/invoice/renderer/*.twig` via `src/Invoice/Hydrator/InvoiceModelDefaultHydrator.php:69-79` (`template.company`, `template.address`, `template.title`, `template.vat_id`, `template.contact`, `template.logo`); rendered under "invoice.from"/"invoice.to" in `invoice.html.twig:30-72`.

**3. `SystemConfigurationController.php:610-629` — branding config keys.** Confirmed only 4 keys in the `'branding'` model: `theme.branding.logo` (`:612-615`), `theme.branding.company` (`:616-619`), `defaults.customer.currency` (`:620-623`, unrelated), `company.financial_year` (`:624-628`, unrelated). No RUT/address/phone config key exists for the issuing company today.

**4. Signature investigation.** No signature-capture/display feature exists anywhere in `src/`/`templates/` for quotations/invoices/contracts as a dynamic PDF element. One static precedent: `templates/invoice/renderer/timesheet.html.twig:139-142` renders two static caption lines with a CSS top-border simulating blank signing lines — `invoice.signature_user`/`invoice.signature_customer` (`translations/messages.es.xlf:661-668`) — only on the `timesheet` invoice renderer, not `invoice.html.twig` or quotations, with no dynamic data. The only other `signature` hits in `src/` are `User.php:267,743,753` (`signatureDate`/`resetSecuritySignature()` — security-token invalidation, unrelated). The quotation accept/reject email flow (`src/Service/QuotationMailService.php`) is not an e-signature: 7-day hashed token (`:24,51-69`), `respond()` (`:88+`) calls `Quotation::accept()/reject()` and persists via `QuotationEmail::recordResponse()` (`src/Entity/QuotationEmail.php:44-53,88-100`) which stores response/respondedAt/IP/user-agent but no signer name or cryptographic signature — a click-through confirmation (`quotation.email.confirmation_hint`).

**5. `Quotation`/`QuotationLine` structure.** `Quotation`: `customer` (required, receiver), `project`, `createdBy`, `invoice`, `status` enum with guarded transitions, `currency` (CLP/USD/CLF), `validUntil`, `paymentTermDays` (30/60/90), `tax`/`discount`/`surcharge`, `lines` (1-10). No RUT/address/phone/signature fields on `Quotation` itself. `QuotationLine`: `quotation`, `catalogItem`, `description`, `unitPrice`, `quantity` — no relevance to this change.

**6. `translations/messages.es.xlf` `quotation.*` coverage.** All 41 `quotation.*` keys (`:2657-2721`) checked — none for RUT/dirección/teléfono/firma. Reusable generic keys exist: `address` (`:397-399`), `phone` (`:405-407`), `vat_id` (`:889-890`), `contact` (`:393-395`). `invoice.signature_user`/`invoice.signature_customer` exist but are invoice-namespaced (reusable naming convention only).

## Affected Areas

- `templates/quotation/pdf.html.twig` — new issuer block, expanded receiver block, new signature section
- `src/Service/QuotationPdfRenderer.php` — may need to pass additional issuer data to the template
- `src/Controller/SystemConfigurationController.php:610-629` — new `Configuration` entries for issuer RUT/address/phone
- `translations/messages.es.xlf` (at minimum) — new `quotation.*` keys for RUT/dirección/teléfono/firma
- `src/Entity/InvoiceTemplate.php`/`InvoiceTemplateForm.php`/`InvoiceModelDefaultHydrator.php` — precedent only, not directly touched
- `src/Entity/Customer.php` — no changes needed

## Approaches Considered

1. **New `theme.branding.*` config keys** (vat_id/address/phone) — matches existing pattern, no migration, appropriate for a single fixed issuer. Effort: Low.
2. **Reuse `Customer`-as-`sending_company` pattern from `InvoiceTemplate`** — reuses validated fields but over-engineered for a single, fixed issuer. Effort: Medium.
3. **Receiver: expand `.doc-parties` template block** with `customer.vatId`/`phone`/`getFormattedAddress()` — zero backend changes, unambiguous. Effort: Low.
4. **Signature: static blank-line** (reuse `timesheet.html.twig` pattern) vs **dynamic "confirmed via email"** sourced from `QuotationEmail` — static is low effort/no plumbing; dynamic needs the untraced `Quotation`↔`QuotationEmail` relationship.

## Recommendation

Receiver: approach 3. Issuer: approach 1 (extend `theme.branding.*`). Signature: start with the static blank-line pattern (approach 4a), optionally layering dynamic e-acceptance audit text later.

## Risks

- Storage/retrieval mechanism behind `config('theme.branding.company')` not traced in detail — `sdd-design` should confirm new keys need no migration.
- `Quotation` ↔ `QuotationEmail` association path not traced — needed only if the dynamic signature option is chosen.
- New translation keys need at minimum `messages.es.xlf`; other locale files may go untranslated unless scoped.
- All `Customer` receiver fields are nullable — template must guard with `{% if %}` to avoid empty "RUT:" labels.

## Ready for Proposal

Yes.
