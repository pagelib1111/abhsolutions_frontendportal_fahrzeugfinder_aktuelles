# ABH Solutions Plattform

WordPress plugin for the ABH Solutions vehicle finder, manufacturer news and partner portal.

Current package version: **0.4.0**.

## Current scope

- Shared manufacturer profiles, partner assignments, central moderation and email routing.
- Public vehicle finder, featured vehicle cards, manufacturer news and vehicle inquiry form.
- Partner onboarding with manufacturer selection, email verification and central account approval. Multiple users can belong to the same manufacturer; partner users work entirely in the front end.
- Three-step wizard for vehicles and news with contextual help, preview, browser-local draft saving, editable text suggestions, image preview and drag-and-drop upload.
- Company dashboard with published entries, items awaiting review, requested corrections, review activity and quick vehicle availability changes that go through central approval.
- Receipt, approval, correction and rejection emails. Approval notices link to the published entry.
- Event cards show date and location and label past events.
- Central editorial dashboard with pending approvals, published vehicles/news, manufacturer and partner overview, manufacturer filters, submitter attribution and central correction of pending and published entries.
- Dedicated ABH editorial-manager role with access to approvals and partner-account decisions without granting general WordPress administration.
- Manufacturer profiles, inquiry routing addresses and specialties can be corrected from the editorial area.
- Public vehicle detail pages include technical facts, availability, price-on-request messaging and a direct inquiry form. News detail pages show manufacturer and event details.
- Finder supports keyword search, existing vehicle filters and sorting; news supports manufacturer, topic and event-period filters. Vehicle cards use clearer calls to action and polished German labels.
- One centrally approved vehicle can be highlighted as “Angebot des Monats”; the label expires automatically at month end, and no public price is shown.
- Central edits are logged with editor and timestamp. New listings retain their submitting partner as author after approval.
- Vehicle inquiries go to the email address stored in the relevant manufacturer profile. An optional copy can go to ABH.
- Prices are never displayed; vehicle listings use “Preis auf Anfrage”.
- Seed records for the six manufacturers are added on activation. Contact email addresses must be entered by ABH.

## Installation

1. Upload the `abh-solutions-platform` folder to `wp-content/plugins/` or upload the ZIP in WordPress.
2. Activate **ABH Solutions Plattform**.
3. In **Einstellungen → ABH Solutions**, set the central moderation email. The central review team can use the **ABH Redaktion** menu; assign the **ABH Redaktion** role to designated reviewers.
4. Edit each profile under **Aufbauhersteller** and add its inquiry email.
5. The plugin creates a published **Herstellerportal** page with login and partner registration. After email confirmation, ABH reviews each account under **ABH Redaktion → Partnerzugänge**. Only approved accounts can use the wizard. Existing installations are checked on the next admin visit too.
6. Add the public finder, featured vehicles, monthly offer and news shortcodes to the desired Flatsome UX Builder pages.

## Shortcodes

- `[abh_fahrzeugfinder]` — filterable vehicle listing.
- `[abh_fahrzeugkarten limit="6" featured="1"]` — up to six featured vehicles.
- `[abh_angebot_des_monats]` — centrally selected monthly offer; automatically expires at month end.
- `[abh_aktuelles limit="6"]` — latest manufacturer updates.
- `[abh_partnerportal]` — login, partner registration and protected submission portal.
- `[abh_fahrzeuganfrage]` — inquiry form for a vehicle detail page; also accepts `vehicle_id="123"`.

## Operational notes

- Unsubmitted drafts are stored in the partner’s current browser and device. Submitted content and its review history are stored in WordPress.
- Confirm the site’s privacy policy URL and configure reliable transactional email delivery before opening registrations.
- Manufacturer inquiry email addresses and final vehicle taxonomy values still need to be supplied.
- The public detail layouts use the active theme’s single template and append ABH details and the inquiry form to the content area.
