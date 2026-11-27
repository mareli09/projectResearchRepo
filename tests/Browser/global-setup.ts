import { execSync } from 'node:child_process';
import { rmSync } from 'node:fs';

/** Fresh test database, demo data and unique upload files before every run. */
export default function globalSetup(): void {
    rmSync('storage/logs/e2e.log', { force: true });
    execSync('php artisan e2e:prepare', { stdio: 'inherit' });
}
