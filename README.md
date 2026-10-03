<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/connections-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=connections-for-laravel">
    <img src="art/hero.png" alt="Connections for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/connections-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/connections-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/connections-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/connections-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/connections-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/connections-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=connections-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Connections for Laravel

Many-to-many connections between any Eloquent models — a user to a team, an organization to a
project — each with its own permissions, an optional expiry and an invitation flow (pending →
accepted / blocked). Expired connections grant nothing and prune themselves.

## Installation

Requires PHP 8.4, and Laravel 12 or 13.

```bash
composer require roundly-consulting/connections-for-laravel
php artisan vendor:publish --tag="connections-migrations"
php artisan migrate
```

If your connected models use UUID or ULID keys, set `CONNECTIONS_KEY_TYPE=uuid` / `ulid`
**before** migrating.

## Usage

Make each model that takes part connectable (`User` the same way):

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Connections\Concerns\HasConnections;
use RoundlyConsulting\Connections\Contracts\Connectable;

class Team extends Model implements Connectable
{
    use HasConnections;
}
```

Connect a pair with permissions and an expiry, then check and change what it may do:

```php
use RoundlyConsulting\Connections\Facades\Connections;

Connections::between($user, $team)
    ->withPermissions('view', 'posts.*')
    ->expiresIn(now()->addMonth())
    ->connect();

Connections::between($user, $team)->exists();                     // true — active connection
Connections::between($user, $team)->permissions()->has('posts.edit'); // true — wildcard match
Connections::between($user, $team)->permissions()->grant('publish');

$user->hasPermissionThroughConnection($team, 'publish');          // true

Connections::between($user, $team)->disconnect();                 // soft delete
Connections::prune();                                             // drop expired connections
```

<!-- roundly-docs:start -->
## Documentation

The full documentation — configuration, every feature and its API, and testing — lives on our
website: **[roundly-consulting.com/open-source/docs/connections-for-laravel](https://roundly-consulting.com/open-source/docs/connections-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=connections-for-laravel)**

Release notes are in [CHANGELOG.md](CHANGELOG.md). To contribute, see the
[contributing guide](https://github.com/roundly-consulting/.github/blob/main/CONTRIBUTING.md).
<!-- roundly-docs:end -->

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=connections-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=connections-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
