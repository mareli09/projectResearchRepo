# Deploy files

Placeholders for the ICT Office server (Ubuntu, Intel, 4 GB RAM, 70 GB). Fill in during Sprint 9.

- `deploy.sh`: deploy steps (CLAUDE.md §19)
- `supervisor-tua.conf`: queue workers (2) and the analytics service (1 Uvicorn worker on 127.0.0.1:8001)
- `crontab`: Laravel scheduler

Still to add: the Nginx site with HSTS and CSP headers, the UFW rules, the backup scripts and the systemd `MemoryMax` for the analytics service.
