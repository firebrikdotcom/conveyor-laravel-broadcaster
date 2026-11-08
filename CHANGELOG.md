# Changelog

## 0.3.0 — Unreleased

Breaking pre-1.0 release (next minor after 0.2.0).

- Require PHP 8.3+, Laravel/Illuminate 13+, Firebase JWT 7+ and Guzzle 8+; declare production dependencies correctly.
- Upgrade development tools to current stable releases, including PHPUnit 13, Testbench 11, PHPStan 2.3, Larastan 3.14 and PHPCS 4.
- Remove the prerelease server development dependency and synthetic OpenSwoole platform declaration. The optional server-association contract is unchanged.
- Support Laravel string channel names, surface transport/JSON errors, reject missing-channel/unauthenticated requests cleanly and honor request authentication guards.
- Persist token use limits, consume them atomically and reject expired stored tokens. Revoke or backfill previously issued limited tokens with null remaining uses before upgrading.
- Add regression tests, coverage reporting, PHP 8.3–8.5 CI, canonical Conveyor branding and distribution exclusions.

## 0.2.0

Previous stable release. Historical tags remain available in Git.
