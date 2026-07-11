# Publishing `zindua/sdk` on Packagist

Packagist account: **zindua** — [packagist.org/profile](https://packagist.org/profile/)

## Prerequisites

1. `packages/zindua-php/composer.json` committed on `main`
2. Git tag matching version (e.g. `v1.0.0`)
3. Packagist user **zindua** linked to GitHub with access to `bbasabana/zindua`
4. If the repo is **private**, enable Packagist access to private repos in [Packagist profile → GitHub](https://packagist.org/profile/)

## One-time submit (API)

```bash
export PACKAGIST_USER=zindua
export PACKAGIST_TOKEN=your_main_api_token   # never commit

curl -XPOST -H'content-type:application/json' \
  "https://packagist.org/api/create-package?username=${PACKAGIST_USER}&apiToken=${PACKAGIST_TOKEN}" \
  -d'{"repository":{"url":"https://github.com/bbasabana/zindua"}}'
```

Packagist indexes monorepos: it detects `packages/zindua-php/composer.json` as **zindua/sdk**.

## Tag and push

```bash
git tag -a v1.0.0 -m "zindua/sdk PHP SDK v1.0.0"
git push origin v1.0.0
```

Bump `"version"` in `composer.json` before each release.

## Trigger re-index (safe token)

```bash
curl -XPOST -H'content-type:application/json' \
  "https://packagist.org/api/update-package?username=zindua&apiToken=${PACKAGIST_SAFE_TOKEN}" \
  -d'{"repository":{"url":"https://github.com/bbasabana/zindua"}}'
```

## Verify

```bash
curl -sS https://packagist.org/packages/zindua/sdk.json | jq .package.versions
composer show zindua/sdk --all  # after publish propagates
```

## Security

- Store API tokens only in Packagist profile or CI secrets
- Rotate tokens if they were ever pasted in chat or logs
- Never commit tokens to git
