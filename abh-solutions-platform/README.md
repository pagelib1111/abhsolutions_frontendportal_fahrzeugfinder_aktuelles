# ABH Solutions Plattform

WordPress plugin foundation for the ABH Solutions vehicle finder, manufacturer news and partner portal.

## Current development scope

- One plugin with shared manufacturer records, partner assignments, central moderation, and email routing.
- Public modules: vehicle finder, featured vehicle cards, manufacturer news and vehicle inquiry.
- Three-step partner front-end wizard for vehicles and news with contextual fields, field help, preview and central approval before publication.
- Manufacturer contact email is stored on the manufacturer profile. Vehicle inquiries are routed to that address; an optional ABH copy can be configured.
- Prices are never stored as public listing output; the front end says “Preis auf Anfrage”.
- Vehicle availability and editorial approval are separate states.
- Seed records for the six currently named manufacturers are added on activation. Contact emails are intentionally left blank until supplied.

## Installation

1. Upload the `abh-solutions-platform` folder to `wp-content/plugins/` or zip it and install in WordPress.
2. Activate **ABH Solutions Plattform**.
3. In **Einstellungen → ABH Solutions**, set the central moderation email.
4. Edit each profile under **Aufbauhersteller** and add its inquiry email.
5. Create partner users in WordPress, assign the **ABH Aufbauhersteller** role and select the linked manufacturer in the user profile.
6. Add pages and insert shortcodes in Flatsome UX Builder.

## Shortcodes

- `[abh_fahrzeugfinder]` — filterable vehicle listing.
- `[abh_fahrzeugkarten limit="6" featured="1"]` — up to six featured vehicles.
- `[abh_aktuelles limit="6"]` — latest manufacturer updates.
- `[abh_partnerportal]` — protected front-end submission portal.
- `[abh_fahrzeuganfrage]` — inquiry form for a vehicle detail page; also accepts `vehicle_id="123"`.

## Notes for the next implementation milestone

This is the initial development foundation. The next iteration should add partner-side editing of existing vehicles and news, configurable vehicle taxonomies, availability reminders and a polished review queue. The exact vehicle/body-type taxonomy and partner email addresses are not yet supplied.
