# Contributing

Thank you for helping improve Site Abilities MCP.

## Before opening a change

- Use an issue for behavior changes or new high-impact abilities.
- Report vulnerabilities privately according to `SECURITY.md`.
- Keep every ability narrowly scoped and capability checked.
- Do not add telemetry, hardcoded credentials, site-specific data, permanent deletion, or arbitrary code execution.

## Development workflow

1. Fork the repository and create a focused branch.
2. Make readable, WordPress-compatible changes.
3. Add or update tests for permission denial, valid execution, stale state, and recovery.
4. Run:

   ```bash
   find . -name '*.php' -print0 | xargs -0 -n1 php -l
   php tests/smoke.php
   php tests/read-only-profile.php
   ```

5. Confirm that no real URL, username, password, token, personal data, or client configuration is present.
6. Open a pull request explaining the user impact and security implications.

## Ability checklist

Every new ability must include:

- Precise input and output schemas
- `additionalProperties: false` on object inputs
- A native WordPress capability check
- Accurate `readonly`, `destructive`, `idempotent`, and `openWorldHint` annotations
- A safe default exposure decision
- State validation for mutations
- A recovery path where WordPress supports one
- Tests covering authorization and failure cases
