# OWASP ZAP baseline scan

CLAUDE.md §14 asks for a ZAP baseline scan with no High findings before release. `baseline.yaml` runs ZAP's spider and its passive checks only. Nothing is attacked, so it is safe to run against the evaluation copy.

## Run it

Requirements: ZAP 2.17 or newer (the cross-platform zip needs only Java 17+), or the ZAP Docker image.

```bash
ZAP_TARGET=https://eval-repository.tua.edu.ph ZAP_REPORT_DIR=$PWD/storage/zap \
  /opt/zap/zap.sh -cmd -autorun deploy/zap/baseline.yaml
```

To use the Docker image instead, mount the repository and run the same plan:

```bash
docker run --rm -v $PWD:/zap/wrk ghcr.io/zaproxy/zaproxy:stable \
  zap.sh -cmd -autorun /zap/wrk/deploy/zap/baseline.yaml
```

The HTML and JSON reports are written to `ZAP_REPORT_DIR`.

## Last result (Nov 27, 2026, local test server, 150 URLs)

**No High findings.** After the two fixes below, a second scan left 3 Medium findings (2 accepted, 1 false positive), 1 Low (handled by the server config) and 2 informational notes.

| Risk | Finding | Status |
|---|---|---|
| Medium | CSP: `script-src 'unsafe-eval'` | **Accepted.** Alpine.js, bundled with Livewire 3, evaluates the `x-data` and `@click` expressions in the templates. Inline scripts are still blocked: the one inline script carries a nonce. Switching to Alpine's CSP build would mean rewriting every template expression. |
| Medium | CSP: `style-src 'unsafe-inline'` | **Accepted.** Charts (ECharts), progress bars and score bars set inline `style` widths. Styles cannot run code. |
| Medium | Absence of anti-CSRF tokens on `/register` | **False positive.** The flagged form is the Privacy Notice dialog's `<form method="dialog">` Close button, which never sends anything to the server. The registration form has its `_token`. |
| Low | Cookie without HttpOnly | **Fixed.** The script-readable `XSRF-TOKEN` cookie is no longer sent (`ValidateCsrfTokenWithoutCookie`). The session cookie was already HttpOnly. |
| Low | `X-Powered-By` header | **Fixed** in the app (`SecurityHeaders`). On the server, also set `expose_php = Off` (see `deploy/nginx-tua.conf`). |
| Low | `X-Content-Type-Options` missing on `robots.txt` | **Server config.** PHP's built-in test server serves static files itself. Nginx adds the header for static files (`deploy/nginx-tua.conf`). |

The scan was run while signed out. Before release, also run it against the evaluation copy over HTTPS, where HSTS and `upgrade-insecure-requests` apply.
