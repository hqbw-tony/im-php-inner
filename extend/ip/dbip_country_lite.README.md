# DB-IP Country Lite

This project uses the free DB-IP Country Lite database for local English IP geolocation.

- Source: https://db-ip.com/db/lite.php
- Download used: `dbip-country-lite-2026-05.csv.gz`
- License: CC BY 4.0
- Attribution: IP geolocation by DB-IP, https://db-ip.com

Generated local index files:

- `dbip_country_lite_v4.dat`
- `dbip_country_lite_v6.dat`
- `country_names_en.php`

To update the database, download a newer `dbip-country-lite-YYYY-MM.csv.gz` from DB-IP and run:

```bash
php extend/ip/build_dbip_country.php extend/ip/dbip-country-lite-YYYY-MM.csv.gz
```

The query is local only. User IP addresses are not sent to any external service.
