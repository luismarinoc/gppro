# Tasks: Quotation PDF — Issuer/Receiver Identity Blocks + Signature Section

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~260–320 |
| 400-line budget risk | Medium |
| Chained PRs recommended | No |
| Suggested split | Single PR |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Medium

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Branding config defaults + admin labels | PR 1 | `vendor/bin/phpunit tests/DependencyInjection/ConfigurationTest.php tests/Configuration/SystemConfigurationTest.php` | N/A — config-only, no runtime scenario | Revert `Configuration.php`, `SystemConfigurationController.php`, config translations |
| 2 | Template issuer/receiver/signature + es labels | PR 1 (same) | `vendor/bin/phpunit tests/Twig/QuotationPdfTest.php tests/Service/QuotationPdfRendererTest.php` | Render a 10-line quotation PDF and inspect page 1 | Revert `pdf.html.twig` + `messages.es.xlf` additions |

## Phase 1: Branding Configuration (Foundation)

- [x] 1.1 [RED] Update `tests/DependencyInjection/ConfigurationTest.php:490-497` branding array to expect `vat_id`, `address`, `phone`, `email`, `website` Gpartner defaults; confirm it fails
- [x] 1.2 [RED] Add a `SystemConfiguration::find('theme.branding.vat_id'|address|phone|email|website)` unit test with a stub `ConfigLoaderInterface` returning `[]`, asserting the DI default; confirm it fails
- [x] 1.3 [GREEN] Add 5 scalar nodes (`vat_id`, `address`, `phone`, `email`, `website`) with Gpartner defaults to `branding` in `src/DependencyInjection/Configuration.php:507-523`; re-run 1.1+1.2, confirm pass
- [x] 1.4 Add 5 matching `Configuration` entries (`TextType`, `setRequired(false)`, `setTranslationDomain('system-configuration')`) to `SystemConfigurationController.php:610-629`

## Phase 2: Admin Labels

- [x] 2.1 Add `theme.branding.vat_id|address|phone|email|website` labels to `translations/system-configuration.es.xlf` and `.en.xlf`

## Phase 3: Template — Issuer + Receiver Blocks

- [x] 3.1 [RED] Write a `KernelTestCase` test rendering `quotation/pdf.html.twig` with a fully-populated `Customer`, asserting issuer vat_id/address/phone/email/website and all 5 receiver fields render; confirm it fails
- [x] 3.2 [RED] Extend the test with a bare `Customer` (only name+email), asserting no orphan `RUT:`/address/phone label renders; confirm it fails
- [x] 3.3 [GREEN] Add guarded issuer meta lines (`config('theme.branding.*')`) to `.doc-issuer-cell` in `pdf.html.twig`
- [x] 3.4 [GREEN] Expand `.doc-parties` receiver block: `company|default(name)`, `vatId`, `getFormattedAddress()|nl2br`, `phone`, `email`, each independently `{% if %}`-guarded; re-run 3.1+3.2, confirm pass

## Phase 4: Signature Section

- [x] 4.1 [RED] Extend the integration test: signature section renders unconditionally across quotation statuses, with Gpartner + customer columns and date lines; confirm it fails
- [x] 4.2 [RED] Extend the test: customer with `contact` set shows that name; `contact` null renders blank; confirm it fails
- [x] 4.3 [GREEN] Add the `.doc-signatures` table markup + CSS (per design.md Interfaces/Contracts) inside `.doc-bottom` in `pdf.html.twig`; re-run 4.1+4.2, confirm pass

## Phase 5: Spanish Translations

- [x] 5.1 Add `quotation.issuer_vat_id|address|phone|email|website` and `quotation.signature_issuer|customer|date` keys to `translations/messages.es.xlf`
- [x] 5.2 Re-run Phase 3+4 tests under `es` locale; confirm no raw translation key renders

## Phase 6: Verification

- [x] 6.1 [Manual, non-automated] Render a 10-line quotation PDF and visually inspect page 1 for `.doc-bottom`/items-table overlap
- [x] 6.2 Run full suite: `ConfigurationTest`, `SystemConfiguration` test, new pdf integration test, `QuotationPdfRendererTest` (expect unchanged/green)
