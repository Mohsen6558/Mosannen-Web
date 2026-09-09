# Mosannen — working notes

Web rewrite of a VB.NET WinForms dental clinic application. Read `README.md`
for setup; this file records the conventions and the traps.

## Non-negotiables

- **Money is `bigint` Rial.** Never a float, never a decimal. Toman is a
  display concern only (`formatMoney(x, { display: 'toman' })`).
- **Dates are Gregorian in the database and the API.** Jalali exists only at
  the presentation edge: `resources/js/Support/jalali.js` on the client and
  `App\Support\JalaliDate` on the server. Nothing else converts.
- **Radiography files are private.** They live under random paths on a
  swappable disk and are streamed by `RadiographController`. Never expose a
  public URL and never name a file after a patient.
- **Legacy passwords are never imported.** Migrated accounts get a random
  hash and `must_change_password`.

## Conventions

- Tooth codes are FDI two-digit strings (`'13'`, `'65'`). `App\Support\Teeth`
  and `resources/js/Support/teeth.js` are mirrors — change both together.
- Permissions are named abilities from `App\Support\Permissions`. Adding one
  means adding it there, then re-running `PermissionSeeder`.
- Controllers return Inertia responses shaped for the page; models are not
  serialised wholesale.
- Deleting clinical or financial records is always a soft delete.

## Traps already hit

- **Do not name an Eloquent scope `on`.** `Model::on($connection)` is a
  built-in static, so `scopeOn` is unreachable and the argument is read as a
  database connection name. `Appointment::scopeOnDate` exists for that reason.
- **RTL logical properties.** The sidebar is on the inline-start edge, so
  content is padded with `ps-*`, not `pe-*`. Getting this backwards puts the
  content underneath the sidebar.
- **Pages cannot fill layout slots.** The layout is applied by the Inertia
  resolver in `app.js`, so a page's root template has no component to attach
  a named slot to — the Vue compiler crashes with a confusing
  `Cannot read properties of undefined (reading 'type')`. Use the
  `PageHeader` component inside the page instead.
- **Tests run against PostgreSQL, not sqlite.** Patient search uses `ILIKE`
  and a trigram index.
- **The container's config cache leaks onto the host.** `bootstrap/cache` is
  inside the bind mount and the app container caches config on boot, baking
  in `DB_HOST=postgres`. Host-side `php artisan` then fails to resolve it.
  Run artisan through `docker compose exec app` while the stack is up, or
  delete `bootstrap/cache/config.php`.
- **The app image runs as www-data (33) and ignores PUID/PGID.** Do not set
  `user:` on the service either — the entrypoint templates
  `/etc/nginx/nginx.conf` as root before dropping privileges. Chown
  `storage` and `bootstrap/cache` to 33 instead.

## Legacy schema map

| Legacy | Now |
|---|---|
| `tblCustomer` | `patients` |
| `tblOpration` | `treatments` + `treatment_teeth` |
| `tblPay` | `payments` |
| `tblImages` | `radiographs` + `radiograph_teeth` |
| `tblTitle` / `tblSubtitles` | `treatment_categories` / `treatment_services` |
| `tblDrag` / `tblSubDrag` | `drugs` / `drug_variants` |
| `tblStore` / `tblStoreUsage` | `stock_items` / `stock_movements` |
| `tblBimeh` / `tblPayType` | `insurances` / `payment_types` |
| `tblUser` | `users` + spatie roles/permissions |
| `tblSMS` | `sms_messages` + `sms_templates` |
| `tblVisit` | `visits` |
| `dbo.getReminderMoney` | `Patient::balance` |

Legacy column quirks worth remembering when reading `LegacyImporter`:
`Birhdate` (sic), `Permession` (sic), `Opration` (sic), `Drag` means دارو
(drug, not drag), `Takhfif` is a discount, `Khowlage` is a medical summary,
`CodeZonkan` is the paper binder number.
