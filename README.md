<p align="center">
    <a href="https://github.com/luismarinoc/gppro/actions/workflows/testing.yaml"><img alt="CI Status" src="https://github.com/luismarinoc/gppro/actions/workflows/testing.yaml/badge.svg"></a>
</p>

<h1 align="center">gppro</h1>

gppro is a professional grade time-tracking and invoicing application.
It handles use-cases of freelancers as well as companies with dozens or hundreds of users.
gppro tracks project times and ships with many advanced features, including but not limited to:

JSON API, invoicing, data exports, multi-timer and punch-in punch-out mode, tagging, multi-user - multi-timezones - multi-language,
authentication via SAML/LDAP/Database, two-factor authentication (2FA) with TOTP, customizable role and team permissions, responsive design,
user/customer/project specific rates, advanced search & filtering, money and time budgets, advanced reporting.

### Requirements

- PHP 8.2 minimum with support for 8.3, 8.4, 8.5
- MariaDB / MySQL: oldest maintained LTS release (MariaDB >= [10.6](https://endoflife.date/mariadb) or MySQL >= [8.4](https://endoflife.date/mysql)) or newer
- A webserver and subdomain (subdirectory is not supported)
- PHP extensions: `gd`, `intl`, `json`, `mbstring`, `pdo`, `tokenizer`, `xml`, `xsl`, `zip`

## Installation

- Caddy with Docker-Compose (see `.docker/`)
- SSH setup with Git and Composer
- Docker images with FPM only or incl. Apache

### Updating gppro

- [UPGRADING guide](UPGRADING.md) — version specific steps

## Roadmap and releases

Every code change, whether it's a new feature or a bugfix, will be done on the `main` branch.

## Contributing

You want to contribute to this repository? This is so great!
The best way to start is to open a new issue for bugs or feature requests, or a discussion for questions, support and such.

There is one simple rule in our "Code of conduct": Don't be an ass!

### Credits

gppro is a fork of [Kimai](https://github.com/kimai/kimai), based on modern technologies and frameworks such as [PHP](https://www.php.net/),
[Symfony](https://github.com/symfony/symfony) and [Doctrine](https://github.com/doctrine/),
[Bootstrap](https://github.com/twbs/bootstrap) and [Tabler](https://tabler.io/),
and [countless](composer.json) [others](package.json).
