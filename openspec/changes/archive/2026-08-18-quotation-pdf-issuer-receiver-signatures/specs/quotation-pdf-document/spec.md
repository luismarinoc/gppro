# Quotation PDF Document Specification

## Purpose

The quotation PDF is a commercial document issued by Gpartner Consulting to a
customer. It MUST let the customer verify who issued the offer, see the
customer's own identity as recorded on the document, and provide a visual
area for both parties to sign. This spec covers the issuer identity block,
the receiver identity block, and the signature section rendered in
`templates/quotation/pdf.html.twig`.

## Requirements

### Requirement: Issuer identity block

The quotation PDF MUST render an issuer block containing the company name,
logo, RUT, address, phone, email, and website. The RUT, address, phone,
email, and website MUST be sourced from admin-editable branding
configuration keys (`theme.branding.vat_id`, `theme.branding.address`,
`theme.branding.phone`, `theme.branding.email`, `theme.branding.website`),
which MUST default to Gpartner's real values (RUT 77.073.462-2; Avenida
Apoquindo 4700, Depto. 11, Las Condes, Santiago; +56 9 44516977;
info@gpartnerc.com; www.gpartnerc.com) so the PDF renders correctly with no
admin action required.

#### Scenario: Default installation renders full issuer identity

- GIVEN a fresh installation where no admin has edited branding configuration
- WHEN a quotation PDF is generated
- THEN the issuer block shows the Gpartner company name, logo, RUT
  77.073.462-2, the Apoquindo address, the phone number, the email address,
  and the website

#### Scenario: Admin-edited branding values are reflected

- GIVEN an admin has changed `theme.branding.vat_id`, `address`, `phone`,
  `email`, or `website` via the system configuration screen
- WHEN a quotation PDF is generated afterward
- THEN the issuer block shows the updated values, not the defaults

### Requirement: Receiver identity block

The quotation PDF MUST render a receiver block showing the customer's
company or name, RUT (`Customer::getVatId()`), formatted address
(`Customer::getFormattedAddress()`), phone (`Customer::getPhone()`), and
email (`Customer::getEmail()`). Each field is nullable on `Customer`, so
each MUST be independently guarded: a field with no value MUST be omitted
entirely from the rendered output, and MUST NOT render an empty or
placeholder label.

#### Scenario: Fully populated customer renders all receiver fields

- GIVEN a customer with company, RUT, address, phone, and email all set
- WHEN a quotation PDF is generated for that customer
- THEN the receiver block shows all five values

#### Scenario: Partially populated customer omits missing fields

- GIVEN a customer with only a name and email set (RUT, address, and phone
  are null)
- WHEN a quotation PDF is generated for that customer
- THEN the receiver block shows the name and email only, with no RUT,
  address, or phone label rendered

### Requirement: Signature block

The quotation PDF MUST render a signature section unconditionally,
regardless of the quotation's acceptance status. The section MUST present
two columns — one for the Gpartner issuer and one for the customer — each
containing a blank line for a physical signature, a date line, and a
printed-name line. The customer column's printed-name line MUST pre-fill
with `customer.contact` when it is set, and MUST remain blank when it is
not. The signature block is presentational only: it MUST NOT capture,
persist, or validate any signature data, and MUST NOT be linked to the
existing accept/reject email flow (`QuotationMailService`/`QuotationEmail`).

#### Scenario: Signature block renders regardless of quotation status

- GIVEN a quotation in any status (draft, sent, accepted, rejected, expired)
- WHEN its PDF is generated
- THEN the signature section renders with both the Gpartner and customer
  columns

#### Scenario: Customer name line pre-fills from contact when available

- GIVEN a customer whose `contact` field is set to a person's name
- WHEN the quotation PDF is generated
- THEN the customer signature column's printed-name line shows that name

#### Scenario: Customer name line stays blank without a contact

- GIVEN a customer whose `contact` field is null
- WHEN the quotation PDF is generated
- THEN the customer signature column's printed-name line renders blank

### Requirement: Spanish translation coverage for new labels

Every new label introduced by the issuer, receiver, and signature blocks
(for example RUT, address, phone, email, and signature captions) MUST have
a corresponding translation key in `translations/messages.es.xlf`. Coverage
in locale files other than `es` is out of scope for this change.

#### Scenario: New labels render in Spanish

- GIVEN the application's active locale is `es`
- WHEN a quotation PDF is generated
- THEN every new issuer, receiver, and signature label renders using its
  `translations/messages.es.xlf` translation, not a raw translation key
