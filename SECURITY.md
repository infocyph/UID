# Security Policy

## Supported Versions

Security fixes are provided for the latest major release line.

| Version | Supported |
|---------| --- |
| `4.x`   | ✅ |
| `< 4.0` | ❌ |

## Reporting a Vulnerability

Please report vulnerabilities privately.

1. Use GitHub private vulnerability reporting for this repository (`Security` -> `Advisories` -> `Report a vulnerability`).
2. If private reporting is unavailable, email: `infocyph@gmail.com`.
3. Do not open a public issue for security vulnerabilities.

Please include:

- Affected package version(s)
- PHP version and runtime environment
- Reproduction steps or proof of concept
- Impact assessment (confidentiality/integrity/availability)
- Any known workaround

## Response Process

- Initial acknowledgment target: within 3 business days
- Triage target: within 7 business days
- Fix and release timeline depends on severity and exploitability

If a report is accepted, a patched release will be prepared and published. Credit will be provided unless you request otherwise.

## Disclosure Policy

This project follows coordinated disclosure:

- Keep details private until a fix is released
- Publish advisory/release notes after remediation
- Share CVE information when applicable

## Scope

In scope:

- Vulnerabilities in code under `src/`
- Supply-chain risks introduced by direct dependencies

Out of scope:

- Issues only affecting unsupported versions
- Local-only misconfiguration without a library defect
