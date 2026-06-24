# Contributing

## Git Workflow

### Branch Structure

| Branch | Purpose |
|--------|---------|
| `stable` | Release-only. The release script manages all merges here. |
| `development` | Integration branch. All feature work is merged here before release. |
| `feat/vX.X.X/*` | New features targeting a specific version. |
| `fix/vX.X.X/*` | Bug fixes targeting a specific version. |
| `developer/vX.X.X/*` | Developer tooling, tests, or non-user-facing changes. |

### Starting a New Feature

Always branch from `development`:

```bash
git checkout development
git pull
git checkout -b feat/v1.3.4/your-feature-name
```

### Finishing a Feature

Merge back into `development` when complete:

```bash
git checkout development
git merge feat/v1.3.4/your-feature-name
```

### Release Flow

Once all features for a version are merged into `development`:

```bash
git checkout stable
git merge development
npm run release patch   # or minor / major
```

The release script handles version bumping, tagging, and publishing.

### What Not to Do

- Do not branch feature work from `stable`.
- Do not create `release/vX.X.X` branches.
- Do not push directly to `stable` — let the release script manage it.

## Commit Messages

This project uses [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <description>
```

**Types:** `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`

**Scopes:** `Member`, `Gateway`, `Admin`, `Settings`, `UX`

**Examples:**
```
feat(Member): add email-based member lookup
fix(Gateway): handle PayPal IPN timeout gracefully
test(Member): add status system validation tests
```
