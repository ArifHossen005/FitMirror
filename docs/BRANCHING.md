# Git Branching Strategy

FitMirror uses a two-tier trunk model: a stable branch and a working branch, with short-lived feature branches feeding into the working branch.

## Branches

| Branch | Purpose | Protected | Deploys to |
|---|---|---|---|
| `main` | Always releasable. Every commit on `main` is a candidate for production. | Yes — no direct pushes, PR + passing CI required | Production (manual trigger) |
| `develop` | Integration branch for the current work-in-progress phase. Feature branches merge here first. | Yes — PR + passing CI required | Staging (auto-deploy on merge) |
| `feature/<phase>-<short-description>` | One feature or task group, e.g. `feature/p3-sslcommerz-payments` | No | — |
| `fix/<short-description>` | Bug fix not tied to a specific phase | No | — |
| `hotfix/<short-description>` | Urgent production fix, branched from `main` | No | Production, then back-merged into `develop` |

## Workflow

1. Branch from `develop`: `git checkout -b feature/p3-sslcommerz-payments develop`
2. Commit in small, reviewable increments. Prefix commit subjects with the phase or module when useful (`[3.C] Add SSLCommerz session initiate service`).
3. Open a PR into `develop`. CI (Pint, Larastan, PHPUnit, npm build) must pass.
4. Squash-merge into `develop` once approved.
5. At the end of a phase (or when `develop` is release-ready), open a PR from `develop` into `main`.
6. Tag releases on `main` using semantic versioning: `v0.1.0`, `v0.2.0`, ... `v1.0.0` at public launch.

## Hotfixes

Branch `hotfix/<description>` directly from `main`, fix, PR into `main`, deploy, then immediately PR the same commit into `develop` so the fix isn't lost on the next release.

## Commit Message Convention

```
[Phase.Section] Short imperative summary

Optional body explaining why, not what.
```

Example: `[2.B] Add TOTP 2FA challenge step to login flow`

## Rules

- Never force-push `main` or `develop`.
- Never commit `.env`, `vendor/`, `node_modules/`, or `storage/` — see root `.gitignore`.
- A feature branch lives as long as its task group is open; delete it after merge.
