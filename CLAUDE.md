# CLAUDE.md

@.github/copilot-instructions.md

## Releasing

Pushing a `v*` tag runs [`deploy.yml`](.github/workflows/deploy.yml), which publishes the plugin to the WordPress.org SVN repository, and from there to every site that auto-updates it. The `wporg` environment only restricts which refs can deploy; nobody has to approve the run. Creating a GitHub Release creates its tag too, so it deploys the same way, and it also runs the [release notes agent](.github/workflows/release-notes.md).

Releases happen only after the owner explicitly approves that release. Agents may prepare a version-bump pull request, but never push a tag, create a GitHub Release, or run an SVN deploy. Push branches with `git push --no-follow-tags`, so a local tag can't ride along and trigger a deploy.

A version-bump pull request changes:

- the `Version:` header in [`jekyll-exporter.php`](jekyll-exporter.php)
- `Stable tag:` in [`docs/header.md`](docs/header.md), which `script/build-readme` doesn't update
- a new entry at the top of [`docs/changelog.md`](docs/changelog.md)
- [`readme.txt`](readme.txt), regenerated with `script/build-readme` (it copies `Stable tag` from the `Version:` header)

The deploy fails unless the tag, the `Version:` header, and both `Stable tag` lines all match.
