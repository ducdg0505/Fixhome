# FixHome — Deploy production on Render

This project is prepared for a Render Web Service + Render Postgres deployment.

## Architecture

- Web: Python + Streamlit on Render Web Service
- Database: Render Postgres (private network)
- Uploaded images: Render persistent disk mounted at `/opt/render/project/src/uploads`
- Region: Singapore
- Domain: `fixhome.id.vn` / `www.fixhome.id.vn`

## First deploy

1. Push this folder to a GitHub repository.
2. Open Render Dashboard -> New -> Blueprint.
3. Connect the GitHub repository. Render detects `render.yaml`.
4. During Blueprint creation, provide:
   - `FIXHOME_ADMIN_PHONE`
   - `FIXHOME_ADMIN_PASSWORD` (use a strong unique password)
5. Apply the Blueprint and wait until both `fixhome-db` and `fixhome-web` are healthy.
6. Open the generated `https://fixhome-web-....onrender.com` URL.
7. Sign in with `admin@fixhome.id.vn` and the password entered in step 4.

The database is initialized automatically on the first request. Production mode creates only the admin account; demo customers/companies are not seeded.

## Domain

In Render: Web Service -> Settings -> Custom Domains -> add:

- `fixhome.id.vn`
- `www.fixhome.id.vn`

Render then shows the DNS records that must be added at P.A Vietnam. Add exactly those records in the P.A Vietnam DNS manager and return to Render to verify the domain. Render provisions and renews TLS automatically.

## Database connectivity

`DATABASE_URL` is injected from `fixhome-db` by Blueprint and uses Render's private network. External database access is blocked by `ipAllowList: []`.

For local development, do not use the internal Render URL. Either use local SQLite (default) or explicitly configure a permitted external Postgres URL.

## Important production notes

- The Blueprint uses paid minimum compute and a persistent disk because a customer-facing service should not depend on Free-instance spin-down or ephemeral upload storage.
- Do not enable the BLIP model on this small web instance. Keep `FIXHOME_ENABLE_VISION_AI=0`; deploy AI inference separately later.
- Keep `.env`, database credentials, and passwords out of Git.
- Before real customer launch, replace demo-style entity storage with normalized SQL tables/migrations as the next backend milestone.
