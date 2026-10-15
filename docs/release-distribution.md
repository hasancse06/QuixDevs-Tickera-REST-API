# Distribution and manual publication

## Current identity and history

Repository: [hasancse06/QuixDevs-Tickera-REST-API](https://github.com/hasancse06/QuixDevs-Tickera-REST-API). The user confirmed this identity, and unauthenticated HTTP checks reached the repository and Releases page on 2026-10-15. The v1.0.0 release page and latest installation asset returned 404; no public release is claimed. The unauthenticated REST API returned empty public tag and release lists. Maintainers should also inspect private drafts with authorized access before creating a tag.

The local Git directory is on main at cf3c6bf with origin configured to the confirmed repository and no local tags. No Git configuration or history was changed during documentation finalization. Keep the repository name separate from the lowercase installation slug quixdevs-tickera-rest-api. Recommended first commit: **Prepare QuixDevs Tickera REST API v1.0.0 distribution**.

## Reproduce the production artifact

From a clean source checkout, run:

```sh
composer install --no-interaction --prefer-dist
composer lint
composer analyse
composer audit --locked
composer test:release
# Requires the dedicated installed WordPress/Tickera fixture:
QUIXDEVS_WP_PATH=/path/to/isolated-wordpress composer test
python3 tools/build-release.py
(cd dist && shasum -a 256 -c quixdevs-tickera-rest-api.zip.sha256)
```

The fixed allowlist includes the main PHP file, 11 required classes, docs/rest-api.md, LICENSE and readme.txt. Paths are sorted, timestamps fixed and permissions 0644. ZIP CRCs and source bytes are checked. Runtime PHP additions require allowlist review. Build scripts, CI, screenshots/branding, full developer docs, vendor, credentials and local fixtures are excluded. No arbitrary size limit discards required files. Python's same zlib version produces byte-identical repeated builds; cross-zlib compression bytes are not promised. The manifest records each runtime file's SHA-256, archive digest/size and optional source commit/tag. This is a checksum manifest, not a signed provenance attestation.

For a validated existing tag:

```sh
python3 tools/build-release.py --tag v1.0.0 --require-clean
```

That mode refuses an absent/different tag, changed plugin version, dirty checkout or missing Git history. A local untagged build is useful for installation testing but cannot be uploaded by the release helper as a validated tagged release.

## GitHub Actions

ci.yml uses read-only permissions and defines PHP 8.1/8.2/8.3/8.4 × WordPress 6.8/7.1.3 integration jobs with MySQL 8.0 and genuine pinned Tickera. It checks syntax, WPCS, PHPStan, Composer advisories, packaging/release-helper unit tests, actual author isolation, fresh ZIP installation, official Plugin Check, workflow syntax and redacted source/history/extracted-package secret scans. Remote execution is separately recorded in final-release-audit.md.

release.yml accepts an existing stable tag on manual dispatch or tag push. It checks out automation at github.workflow_sha and source at the supplied tag in separate folders. Current automation builds that exact clean tagged source, verifies tag/version/commit, scans the package and runs reusable CI against the resolved commit. Only the final draft job has contents:write. There is no tag-writing or publishing step.

The Python REST helper verifies the remote existing tag, including annotated tags, against the package commit. It paginates releases, also looks up the tag, and deduplicates by release ID. A published match or multiple distinct drafts aborts without mutation. The first release creates a draft; reruns resume a unique draft. An uncertain/422 create result is looked up before any further action; POST creation is never blindly retried. Matching asset digests are reused; stale draft assets can be replaced after rechecking draft state before every mutation. All uploads use HTTPS official GitHub hosts and authorization is never forwarded across redirects. Credentials/error response bodies are not logged.

GitHub Actions installation tokens are supported without a normal collaborator permissions.push flag. The default GITHUB_TOKEN needs repository workflow permission for contents:write. An optional **RELEASE_TOKEN** secret can hold a short-lived GitHub App installation token with repository Contents read/write (and Workflows write if GitHub requires it for that target). No token is stored in source. Review GitHub's [release API and token permissions](https://docs.github.com/en/rest/releases/releases). API authorization is the authority; the helper does not infer permissions from a collaborator flag. GitHub CLI is unnecessary.

Do not manually publish a draft while automation is uploading. The helper checks before every mutation and at completion, but GitHub supplies no atomic “only while draft” asset mutation primitive: a maintainer publication in the network interval between check and mutation cannot be fully prevented. Workflow concurrency serializes runs for a tag; external clients must also avoid concurrent creation/publication.

## Commands for later repository setup

These commands are instructions only; none were run during preparation. The repository already has main/origin and commit cf3c6bf. Review the exact diff and current remote tags before committing the final notes/workflow changes.

```sh
cd /Users/hasanonline/WordPress-Projects-GitHub/Tickera-Event-Ticketing-System/quixdevs-tickera-rest-api-github
git add .
git status --short
git diff --cached --stat
git commit -m "Prepare QuixDevs Tickera REST API v1.0.0 distribution"
git ls-remote --heads --tags origin
git push -u origin main
```

If the confirmed remote already has history, clone/fetch and reconcile it first instead of blindly initializing/pushing. After the remote CI matrix passes and v1.0.0 is confirmed unused, the maintainer may create an annotated tag and push it:

```sh
git tag -a v1.0.0 -m "QuixDevs Tickera REST API v1.0.0"
git push origin v1.0.0
```

Existing tags must never be recreated or force-moved. To update automation separately, merge the corrected workflow on the default branch, then manually dispatch Prepare draft release from that branch with the already existing tag. The artifact still comes from the selected tag's source; release notes come from docs/releases/TAG.md in that same commit. Review the draft's commit, ZIP layout/digest, notes, local verification limits, complete remote matrix and install result, then publish manually in GitHub Releases. README already uses the confirmed repository and exact release asset filename. A public latest URL does not resolve for a draft-only repository.

## Local REST helper, if needed

No gh installation is required. Supply a short-lived token in a protected environment, never as a command argument. Against a clean existing tag's validated dist directory:

```sh
python3 tools/create-draft-release.py --repository hasancse06/QuixDevs-Tickera-REST-API --tag v1.0.0 --commit FULL_40_CHARACTER_TAG_COMMIT --dist dist --notes docs/github-release-notes.md
```

The helper requires GITHUB_TOKEN or RELEASE_TOKEN and refuses untagged/dirty manifests. This command creates/resumes a draft and uploads ZIP, checksum and manifest; it never publishes.
