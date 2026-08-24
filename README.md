# CuciNow.co

Mobile-first cleaning service website and operations system for CuciNow.co by Thursina Land & Services.

## Included in the first release

- English/Bahasa Malaysia marketing copy and responsive landing page
- Preliminary RM quote estimator with customer capture
- Secure staff-only login
- Quote pipeline and printable quotation view
- Quote-to-booking and quote-to-invoice workflows
- Payment recording and outstanding-balance tracking
- Customer, staff, booking, subscriber, and newsletter campaign views
- Database-backed queues and cPanel cron scheduling
- GitHub-built `deploy` branch with production dependencies

## Source-of-truth boundary

The public content uses the supplied Thursina company profile for company history, management capability, service categories, project sectors and named historical experience. Launch packaging, calculator estimates, home/grand-hall offers, Klang Valley launch coverage and digital workflows are recommendations requiring business approval before launch.

The profile includes historical certificates that expired in 2021. The site does not claim current MOF, Bumiputera or CIDB certification until renewed documents are verified.

## Technology

- Laravel 13 / PHP 8.3+
- Livewire 4
- Tailwind CSS 4 and Vite
- MariaDB/MySQL with `utf8mb4_unicode_ci`
- Database queues suitable for shared hosting

See [the cPanel guide](docs/CPANEL_DEPLOYMENT.md) for the no-SSH deployment procedure.
