# SOCA for Moodle

Installable CIMD OpenID Connect sign-in for Moodle, with ES256 client assertions, PKCE S256, deny-by-default admission and explicit account links. **Version 0.1.0 is an evaluation preview**, with [remaining requirements](docs/requirements.md) documented per ID.

Moodle browser login through the native identity-provider button. Moodle web services and mobile delegated grants are outside this profile. Site administrators, guests, deleted, suspended and unconfirmed users are excluded.

Run `composer install --no-dev`, then install the directory as `auth/soca`. Complete Moodle Notifications to install its XMLDB tables. Enable **SOCA sign-in** under **Site administration, Plugins, Authentication, Manage authentication**. Its native settings page accepts profile JSON and links to maintenance. Enable the auth plugin while keeping the candidate profile `enabled: false` for operator testing.

Mount a persistent P-256 signing key using `SOCA_PRIVATE_KEY_FILE` and set the canonical HTTPS origin plus admission rules. Keep the candidate disabled until public document checks and an operator test login pass for that exact revision. See [configuration](docs/configuration.md), [operations and recovery](docs/operations.md), and [verification](docs/verification.md).

```sh
composer install
composer test
CONTAINER_ENGINE=podman bash demo/run.sh test
CONTAINER_ENGINE=podman bash demo/run.sh serve
```

The disposable local demo installs the actual host in PHP containers. Local HTTP never bypasses production HTTPS or public-network checks; live SSO activation requires publicly reachable HTTPS metadata or a checked proxy profile. Tests include signed synthetic-provider flows and native host account/session checks. No live-provider login has been completed.

Runtime: Moodle 4.5/5.0 on PHP 8.3+; native smoke tested with Moodle 4.5.15 and MariaDB 11.4. New users have `auth=soca`, no cached local password and only Moodle's ordinary authenticated user baseline. No enrolments or additional role assignments are added.

CI tests protocol/bridge cases on PHP 8.3/8.4, installs the native host, audits Composer dependencies and builds packages/SBOMs. Dependabot tracks Composer, actions and the demo image. CodeQL checks Python packaging and workflow code; [CodeQL does not analyse PHP](https://codeql.github.com/docs/codeql-overview/supported-languages-and-frameworks/). See [security reporting](SECURITY.md).
