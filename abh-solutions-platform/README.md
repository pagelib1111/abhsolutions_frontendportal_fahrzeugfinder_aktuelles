# ABH Solutions Plattform

WordPress plugin for the ABH Solutions vehicle finder, manufacturer news and partner portal.

Current package version: **0.2.1**.

## Current scope

- Shared manufacturer profiles, partner assignments, central moderation and email routing.
- Public vehicle finder, featured vehicle cards, manufacturer news and vehicle inquiry form.
- Partner onboarding with manufacturer selection, email verification and central account approval. Multiple users can belong to the same manufacturer; partner users work entirely in the front end.
- Three-step wizard for vehicles and news with contextual help, preview, browser-local draft saving, editable text suggestions, image preview and drag-and-drop upload.
- Company dashboard with published entries, items awaiting review, requested corrections, review activity and quick vehicle availability changes that go through central approval.
- Receipt, approval, correction and rejection emails. Approval notices link to the published entry.
- Event cards show date and location and label past events.
- Vehicle inquiries go to the email address stored in the relevant manufacturer profile. An optional copy can go to ABH.
- Prices are never displayed; vehicle listings use “Preis auf Anfrage”.
- Seed records for the six manufacturers are added on activation. Contact email addresses must be entered by ABH.

## Installation

1. Upload the `abh-solutions-platform` folder to `wp-content/plugins/` or upload the ZIP in WordPress.
2. Activate **ABH Solutions Plattform**.
3. In **Einstellungen → ABH Solutions**, set the central moderation email.
4. Edit each profile under **Aufbauhersteller** and add its inquiry email.
5. The plugin creates a published **Herstellerportal** page with login and partner registration. After email confirmation, ABH reviews each account under **Einstellungen → ABH Partnerzugänge**. Only approved accounts can use the wizard. Existing installations are checked on the next admin visit too.
6. Add the public finder, featured vehicles and news shortcodes to the desired Flatsome UX Builder pages.

## Shortcodes

- `[abh_fahrzeugfinder]` — filterable vehicle listing.
- `[abh_fahrzeugkarten limit="6" featured="1"]` — up to six featured vehicles.
- `[abh_aktuelles limit="6"]` — latest manufacturer updates.
- `[abh_partnerportal]` — login, partner registration and protected submission portal.
- `[abh_fahrzeuganfrage]` — inquiry form for a vehicle detail page; also accepts `vehicle_id="123"`.

## Operational notes

- Unsubmitted drafts are stored in the partner’s current browser and device. Submitted content and its review history are stored in WordPress.
- Confirm the site’s privacy policy URL and configure reliable transactional email delivery before opening registrations.
- Manufacturer inquiry email addresses and final vehicle taxonomy values still need to be supplied.
