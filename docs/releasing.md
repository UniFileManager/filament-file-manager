# Release policy

## Versioning

UniFileManager follows Semantic Versioning:

- `MAJOR`: breaking public API, configuration, or supported-platform changes.
- `MINOR`: backwards-compatible features.
- `PATCH`: backwards-compatible fixes and documentation updates.
- Pre-release versions use suffixes such as `0.1.0-beta`.

## Before publishing

1. Run `composer test`.
2. Confirm the GitHub Actions Laravel/Filament compatibility matrix is green.
3. Run `composer audit` and review advisories.
4. Update `CHANGELOG.md` and `docs/upgrading.md` for user-facing changes or configuration updates.
5. Tag the release as `vX.Y.Z` and publish the matching GitHub release.
6. Publish to Packagist from the tagged repository.

## Support policy

Supported Laravel and Filament versions are defined in `composer.json` and
verified by CI. Deprecations are documented in a minor release and removed only
in the next appropriate major release.
