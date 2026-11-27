# Deploy files

Files for the ICT Office server (Ubuntu, Intel, 4 GB RAM, 70 GB).

- `deploy.sh`: deploy steps (CLAUDE.md §19)
- `supervisor-tua.conf`: queue workers (2), the long-job worker (1: backups, restores, clustering) and the analytics service (1 Uvicorn worker on 127.0.0.1:8001)
- `crontab`: Laravel scheduler
- `nginx-tua.conf`: HTTPS site, HTTP to HTTPS redirect, HSTS and headers for static files, upload size, PHP-FPM, hidden files denied; PHP settings in its header
- `zap/`: OWASP ZAP baseline plan and the last result

Still to add on the server itself: the UFW rules (80, 443, and SSH from the ICT network only) and a memory cap for the analytics service (`MemoryMax` if it runs under systemd instead of Supervisor). Backups are built into the app: see Backups in the main README.

In the server's `.env`, set `APP_ENV=production`, `APP_DEBUG=false` and `SESSION_SECURE_COOKIE=true`.
