# Security Policy

## Supported versions

Only the latest published alpha or stable release receives security fixes.

## Reporting a vulnerability

Do not open a public issue for a suspected vulnerability.

Use GitHub's private vulnerability reporting feature on the repository **Security** tab. Include:

- Affected version and WordPress/PHP versions
- Required WordPress role or capabilities
- Reproduction steps or a minimal proof of concept
- Expected and actual behavior
- Potential impact
- Suggested remediation, if known

Do not test against a site you do not own or have explicit permission to assess. Do not include production credentials, personal data, or private site content in a report.

The maintainer will acknowledge a complete report as soon as practical, investigate it privately, and coordinate a release before public disclosure when the issue is confirmed.

## Security design

- The default MCP exposure profile is read-only.
- WordPress capabilities are checked server-side for every ability.
- MCP annotations are advisory and are never treated as authorization.
- Mutations use explicit confirmation and/or expected-state guards.
- Permanent deletion and arbitrary code or option editing are outside project scope.
- Credentials belong in the MCP client or authorization provider, never in this repository or plugin storage.
