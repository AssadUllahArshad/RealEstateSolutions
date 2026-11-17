import { execFileSync } from 'node:child_process';

export default function globalSetup() {
    execFileSync('php', ['artisan', 'cache:clear', '--env=e2e'], {
        stdio: 'inherit',
    });
    execFileSync('php', ['artisan', 'migrate:fresh', '--env=e2e', '--force'], {
        stdio: 'inherit',
    });
    execFileSync(
        'php',
        [
            'artisan',
            'db:seed',
            '--env=e2e',
            '--class=BrowserBookingSeeder',
            '--force',
        ],
        { stdio: 'inherit' },
    );
}
